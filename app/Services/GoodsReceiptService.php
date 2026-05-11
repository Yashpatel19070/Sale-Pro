<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GoodsReceiptService
{
    public function __construct(private readonly PurchaseOrderService $poService) {}

    public function store(PurchaseOrder $po, array $data, User $receivedBy): GoodsReceipt
    {
        return DB::transaction(function () use ($po, $data, $receivedBy): GoodsReceipt {
            $lockedPo = PurchaseOrder::lockForUpdate()->findOrFail($po->id);

            $allowedStatuses = [
                PurchaseOrderStatus::Approved,
                PurchaseOrderStatus::OnTheWay,
                PurchaseOrderStatus::PartiallyReceived,
            ];

            throw_if(
                ! in_array($lockedPo->status, $allowedStatuses, true),
                \DomainException::class,
                'Goods receipts can only be created for approved, on-the-way, or partially received purchase orders.'
            );

            $this->validateLineQtys($lockedPo, $data['lines']);

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
        $po ??= $grn->purchaseOrder;

        return DB::transaction(function () use ($grn, $po, $data): GoodsReceipt {
            $locked = GoodsReceipt::lockForUpdate()->findOrFail($grn->id);
            throw_if(
                $locked->status === GoodsReceiptStatus::Complete,
                \DomainException::class,
                'Completed goods receipts cannot be edited.'
            );

            $this->validateLineQtys($po, $data['lines'], $grn->id);

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
        $po ??= $grn->purchaseOrder;

        return DB::transaction(function () use ($grn, $po): GoodsReceipt {
            $locked = GoodsReceipt::lockForUpdate()->findOrFail($grn->id);
            throw_if(
                $locked->status === GoodsReceiptStatus::Complete,
                \DomainException::class,
                'This goods receipt is already complete.'
            );

            $lockedPo = PurchaseOrder::lockForUpdate()->findOrFail($po->id);
            throw_if(
                in_array($lockedPo->status, [PurchaseOrderStatus::Cancelled, PurchaseOrderStatus::Rejected], true),
                \DomainException::class,
                'Cannot complete a goods receipt for a cancelled or rejected purchase order.'
            );

            $grn->update(['status' => GoodsReceiptStatus::Complete]);

            $lockedPo->load('lines');
            $this->updatePoQtyReceived($lockedPo);
            $this->updatePoStatus($lockedPo);

            return $grn->loadMissing(['lines.purchaseOrderLine.product', 'purchaseOrder.supplier', 'receivedBy']);
        });
    }

    public function submitQc(GoodsReceipt $grn, array $data, User $inspector): GoodsReceipt
    {
        $result = DB::transaction(function () use ($grn, $data, $inspector): GoodsReceipt {
            $grn->refresh();
            $po = $grn->purchaseOrder()->with('lines')->first();

            throw_if(
                $grn->status !== GoodsReceiptStatus::Complete,
                \DomainException::class,
                'QC can only be submitted for completed goods receipts.'
            );

            throw_if(
                $po->status !== PurchaseOrderStatus::QualityCheck,
                \DomainException::class,
                'Purchase order is not in quality check status.'
            );

            $alreadyDone = $grn->lines()->lockForUpdate()->whereNotNull('qty_passed')->exists();
            throw_if(
                $alreadyDone,
                \DomainException::class,
                'QC has already been submitted for this goods receipt.'
            );

            foreach ($data['lines'] as $lineData) {
                $grnLine = GoodsReceiptLine::find($lineData['goods_receipt_line_id']);

                throw_if(
                    ! $grnLine || $grnLine->goods_receipt_id !== $grn->id,
                    \DomainException::class,
                    'Invalid goods receipt line.'
                );

                $sum = (int) $lineData['qty_passed'] + (int) $lineData['qty_failed'];
                throw_if(
                    $sum !== (int) $grnLine->qty_received,
                    \DomainException::class,
                    "Pass + fail ({$sum}) must equal received qty ({$grnLine->qty_received})."
                );

                $grnLine->update([
                    'qty_passed' => (int) $lineData['qty_passed'],
                    'qty_failed' => (int) $lineData['qty_failed'],
                    'qc_inspected_at' => now(),
                    'qc_inspected_by' => $inspector->id,
                ]);
            }

            $this->poService->passQualityCheck($po, null);

            return $grn->fresh(['lines.purchaseOrderLine.product', 'purchaseOrder']);
        });

        activity()
            ->causedBy($inspector)
            ->performedOn($grn)
            ->withProperties([
                'grn_number' => $grn->grn_number,
                'lines' => collect($data['lines'])->map(fn ($l) => [
                    'goods_receipt_line_id' => $l['goods_receipt_line_id'],
                    'qty_passed' => $l['qty_passed'],
                    'qty_failed' => $l['qty_failed'],
                ])->all(),
            ])
            ->log('qc_submitted');

        return $result;
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
        $skipStatuses = [
            PurchaseOrderStatus::QualityCheck,
            PurchaseOrderStatus::PartiallyReceived,
            PurchaseOrderStatus::Received,
            PurchaseOrderStatus::Cancelled,
            PurchaseOrderStatus::Rejected,
        ];
        if (in_array($po->status, $skipStatuses, true)) {
            return;
        }

        $po->load('lines');
        $lines = $po->lines;

        if ($lines->isEmpty()) {
            return;
        }

        $anyReceived = $lines->some(fn ($l) => (float) $l->qty_received > 0);

        if ($anyReceived) {
            $po->update(['status' => PurchaseOrderStatus::QualityCheck]);
        }
    }

    public function generateGrnNumber(): string
    {
        return DB::transaction(function (): string {
            $year = now()->year;
            $prefix = "GRN-{$year}-";

            $max = GoodsReceipt::withTrashed()
                ->where('grn_number', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->max('grn_number');

            $next = $max ? ((int) substr($max, strlen($prefix))) + 1 : 1;

            return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
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

            throw_if(
                ! $poLine,
                \DomainException::class,
                "Purchase order line {$lineData['purchase_order_line_id']} does not belong to this purchase order."
            );

            $alreadyReceived = (float) ($alreadyReceivedByLine[$poLine->id] ?? 0);
            $remaining = (float) $poLine->qty_ordered - $alreadyReceived;

            throw_if(
                (float) $lineData['qty_received'] > $remaining,
                \DomainException::class,
                "Quantity received for '{$poLine->description}' exceeds remaining ({$remaining})."
            );
        }
    }

    public function assertCanAssignSerials(GoodsReceipt $grn, PurchaseOrder $po): void
    {
        throw_if(
            $grn->status !== GoodsReceiptStatus::Complete,
            \DomainException::class,
            'Goods receipt is not complete.'
        );

        throw_if(
            ! in_array($po->status, [PurchaseOrderStatus::PartiallyReceived, PurchaseOrderStatus::Received], true),
            \DomainException::class,
            'Purchase order is not in the correct status for serial assignment.'
        );

        throw_if(
            InventoryMovement::where('goods_receipt_id', $grn->id)->exists(),
            \DomainException::class,
            'Serials have already been assigned for this goods receipt.'
        );
    }
}
