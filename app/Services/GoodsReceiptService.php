<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GoodsReceiptService
{
    public function store(PurchaseOrder $po, array $data, User $receivedBy): GoodsReceipt
    {
        $allowedStatuses = [
            PurchaseOrderStatus::Approved,
            PurchaseOrderStatus::OnTheWay,
            PurchaseOrderStatus::PartiallyReceived,
        ];

        throw_if(
            ! in_array($po->status, $allowedStatuses, true),
            \DomainException::class,
            'Goods receipts can only be created for approved, on-the-way, or partially received purchase orders.'
        );

        $this->validateLineQtys($po, $data['lines']);

        return DB::transaction(function () use ($po, $data, $receivedBy): GoodsReceipt {
            $grn = GoodsReceipt::create([
                'purchase_order_id' => $po->id,
                'grn_number' => $this->generateGrnNumber(),
                'received_by' => $receivedBy->id,
                'received_date' => $data['received_date'],
                'notes' => $data['notes'] ?? null,
                'status' => GoodsReceiptStatus::Draft,
            ]);

            $now = now();
            GoodsReceiptLine::insert(array_map(fn ($line) => [
                'goods_receipt_id' => $grn->id,
                'purchase_order_line_id' => $line['purchase_order_line_id'],
                'qty_received' => $line['qty_received'],
                'notes' => $line['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $data['lines']));

            return $grn->fresh(['lines.purchaseOrderLine.product', 'receivedBy']);
        });
    }

    public function update(GoodsReceipt $grn, array $data, ?PurchaseOrder $po = null): GoodsReceipt
    {
        throw_if(
            $grn->status === GoodsReceiptStatus::Complete,
            \DomainException::class,
            'Completed goods receipts cannot be edited.'
        );

        $po ??= $grn->purchaseOrder;
        $this->validateLineQtys($po, $data['lines'], $grn->id);

        return DB::transaction(function () use ($grn, $data): GoodsReceipt {
            $grn->update([
                'received_date' => $data['received_date'],
                'notes' => $data['notes'] ?? null,
            ]);

            $grn->lines()->delete();

            $now = now();
            GoodsReceiptLine::insert(array_map(fn ($line) => [
                'goods_receipt_id' => $grn->id,
                'purchase_order_line_id' => $line['purchase_order_line_id'],
                'qty_received' => $line['qty_received'],
                'notes' => $line['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $data['lines']));

            return $grn->fresh(['lines.purchaseOrderLine.product']);
        });
    }

    public function complete(GoodsReceipt $grn, ?PurchaseOrder $po = null): GoodsReceipt
    {
        throw_if(
            $grn->status === GoodsReceiptStatus::Complete,
            \DomainException::class,
            'This goods receipt is already complete.'
        );

        return DB::transaction(function () use ($grn, $po): GoodsReceipt {
            $grn->update(['status' => GoodsReceiptStatus::Complete]);

            $po ??= $grn->purchaseOrder()->with('lines')->first();
            $this->updatePoQtyReceived($po);
            $this->updatePoStatus($po);

            return $grn->loadMissing(['lines.purchaseOrderLine.product', 'purchaseOrder.supplier', 'receivedBy']);
        });
    }

    public function delete(GoodsReceipt $grn): void
    {
        throw_if(
            $grn->status === GoodsReceiptStatus::Complete,
            \DomainException::class,
            'Completed goods receipts cannot be deleted.'
        );

        $grn->delete();
    }

    public function updatePoQtyReceived(PurchaseOrder $po): void
    {
        $po->load('lines');

        $receivedQtys = DB::table('goods_receipt_lines as grl')
            ->join('goods_receipts as gr', 'gr.id', '=', 'grl.goods_receipt_id')
            ->where('gr.purchase_order_id', $po->id)
            ->where('gr.status', GoodsReceiptStatus::Complete->value)
            ->whereNull('gr.deleted_at')
            ->groupBy('grl.purchase_order_line_id')
            ->selectRaw('grl.purchase_order_line_id, SUM(grl.qty_received) as total_received')
            ->pluck('total_received', 'purchase_order_line_id');

        foreach ($po->lines as $line) {
            $received = (float) ($receivedQtys[$line->id] ?? 0);
            $line->update(['qty_received' => min($received, (float) $line->qty_ordered)]);
        }
    }

    public function updatePoStatus(PurchaseOrder $po): void
    {
        $po->load('lines');
        $lines = $po->lines;

        if ($lines->isEmpty()) {
            return;
        }

        $allFull = $lines->every(fn ($l) => (float) $l->qty_received >= (float) $l->qty_ordered);
        $anyReceived = $lines->some(fn ($l) => (float) $l->qty_received > 0);

        if ($allFull) {
            $po->update(['status' => PurchaseOrderStatus::Received]);
        } elseif ($anyReceived) {
            $po->update(['status' => PurchaseOrderStatus::PartiallyReceived]);
        }
    }

    public function generateGrnNumber(): string
    {
        $year = now()->year;
        $prefix = "GRN-{$year}-";

        $max = GoodsReceipt::withTrashed()
            ->where('grn_number', 'like', "{$prefix}%")
            ->max('grn_number');

        $next = $max ? ((int) substr($max, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function validateLineQtys(PurchaseOrder $po, array $lines, ?int $excludeGrnId = null): void
    {
        $po->load('lines');

        $lineIds = array_column($lines, 'purchase_order_line_id');

        $alreadyReceivedByLine = DB::table('goods_receipt_lines as grl')
            ->join('goods_receipts as gr', 'gr.id', '=', 'grl.goods_receipt_id')
            ->whereIn('grl.purchase_order_line_id', $lineIds)
            ->where('gr.status', GoodsReceiptStatus::Complete->value)
            ->whereNull('gr.deleted_at')
            ->when($excludeGrnId, fn ($q) => $q->where('gr.id', '!=', $excludeGrnId))
            ->groupBy('grl.purchase_order_line_id')
            ->selectRaw('grl.purchase_order_line_id, SUM(grl.qty_received) as total')
            ->pluck('total', 'purchase_order_line_id');

        foreach ($lines as $lineData) {
            $poLine = $po->lines->firstWhere('id', $lineData['purchase_order_line_id']);

            if (! $poLine) {
                continue;
            }

            $alreadyReceived = (float) ($alreadyReceivedByLine[$poLine->id] ?? 0);
            $remaining = (float) $poLine->qty_ordered - $alreadyReceived;

            throw_if(
                (float) $lineData['qty_received'] > $remaining,
                \DomainException::class,
                "Quantity received for '{$poLine->description}' exceeds remaining ({$remaining})."
            );
        }
    }
}
