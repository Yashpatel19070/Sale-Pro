<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return PurchaseOrder::withTrashed()
            ->with(['supplier', 'createdBy', 'approvedBy'])
            ->when(
                ! empty($filters['search']),
                fn ($q) => $q->where('po_number', 'like', '%'.$filters['search'].'%')
            )
            ->when(
                ! empty($filters['status']),
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when(
                ! empty($filters['supplier_id']),
                fn ($q) => $q->where('supplier_id', $filters['supplier_id'])
            )
            ->when(
                ! empty($filters['date_from']),
                fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from'])
            )
            ->when(
                ! empty($filters['date_to']),
                fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to'])
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    public function store(array $data, User $createdBy): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $createdBy): PurchaseOrder {
            $po = PurchaseOrder::create([
                'po_number' => $this->generatePoNumber(),
                'supplier_id' => $data['supplier_id'],
                'status' => PurchaseOrderStatus::Draft,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $createdBy->id,
                'subtotal' => 0,
                'tax_total' => 0,
                'grand_total' => 0,
            ]);

            foreach ($data['lines'] as $line) {
                $qtyOnHand = InventorySerial::forProduct($line['product_id'])->inStock()->count();

                PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $line['product_id'],
                    'description' => $line['description'],
                    'qty_ordered' => $line['qty_ordered'],
                    'qty_received' => 0,
                    'qty_on_hand_snapshot' => $qtyOnHand,
                    'unit_cost' => $line['unit_cost'],
                    'tax_rate' => $line['tax_rate'],
                    'line_total' => $this->calculateLineTotal($line),
                ]);
            }

            $this->recalculateTotals($po);

            return $po->fresh(['lines.product', 'supplier']);
        });
    }

    public function update(PurchaseOrder $po, array $data): PurchaseOrder
    {
        throw_if(
            ! $po->status->isEditable(),
            \DomainException::class,
            "Purchase order cannot be edited in '{$po->status->label()}' status."
        );

        return DB::transaction(function () use ($po, $data): PurchaseOrder {
            $po->update([
                'supplier_id' => $data['supplier_id'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $po->lines()->delete();

            foreach ($data['lines'] as $line) {
                PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $line['product_id'],
                    'description' => $line['description'],
                    'qty_ordered' => $line['qty_ordered'],
                    'qty_received' => 0,
                    'qty_on_hand_snapshot' => $line['qty_on_hand_snapshot'],
                    'unit_cost' => $line['unit_cost'],
                    'tax_rate' => $line['tax_rate'],
                    'line_total' => $this->calculateLineTotal($line),
                ]);
            }

            $this->recalculateTotals($po);

            return $po->fresh(['lines.product', 'supplier']);
        });
    }

    public function submit(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po): PurchaseOrder {
            $locked = PurchaseOrder::lockForUpdate()->findOrFail($po->id);

            throw_if(
                ! $locked->status->isEditable(),
                \DomainException::class,
                'Only draft or rejected purchase orders can be submitted.'
            );

            $locked->update([
                'status' => PurchaseOrderStatus::PendingApproval,
                'rejection_reason' => null,
            ]);

            return $locked->fresh();
        });
    }

    public function approve(PurchaseOrder $po, User $approver): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $approver): PurchaseOrder {
            $locked = PurchaseOrder::lockForUpdate()->findOrFail($po->id);

            throw_if(
                $locked->status !== PurchaseOrderStatus::PendingApproval,
                \DomainException::class,
                'Only pending approval purchase orders can be approved.'
            );

            $locked->update([
                'status' => PurchaseOrderStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function reject(PurchaseOrder $po, string $reason): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $reason): PurchaseOrder {
            $locked = PurchaseOrder::lockForUpdate()->findOrFail($po->id);

            throw_if(
                $locked->status !== PurchaseOrderStatus::PendingApproval,
                \DomainException::class,
                'Only pending approval purchase orders can be rejected.'
            );

            $locked->update([
                'status' => PurchaseOrderStatus::Rejected,
                'rejection_reason' => $reason,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            return $locked->fresh();
        });
    }

    public function markOnTheWay(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po): PurchaseOrder {
            $locked = PurchaseOrder::lockForUpdate()->findOrFail($po->id);

            throw_if(
                $locked->status !== PurchaseOrderStatus::Approved,
                \DomainException::class,
                'Only approved purchase orders can be marked as on the way.'
            );

            $locked->update(['status' => PurchaseOrderStatus::OnTheWay]);

            return $locked->fresh();
        });
    }

    public function cancel(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po): PurchaseOrder {
            $locked = PurchaseOrder::lockForUpdate()->findOrFail($po->id);

            throw_if(
                in_array($locked->status, [
                    PurchaseOrderStatus::Closed,
                    PurchaseOrderStatus::Cancelled,
                    PurchaseOrderStatus::Returned,
                ], true),
                \DomainException::class,
                'This purchase order cannot be cancelled.'
            );

            $locked->update(['status' => PurchaseOrderStatus::Cancelled]);

            return $locked->fresh();
        });
    }

    public function passQualityCheck(PurchaseOrder $po, ?string $notes): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $notes): PurchaseOrder {
            $locked = PurchaseOrder::lockForUpdate()->findOrFail($po->id);

            throw_if(
                $locked->status !== PurchaseOrderStatus::QualityCheck,
                \DomainException::class,
                'Quality check can only be performed on orders awaiting quality check.'
            );

            $locked->load('lines');
            $allDone = $locked->lines->every(fn ($l) => (float) $l->qty_received >= (float) $l->qty_ordered);

            $locked->update([
                'status' => $allDone ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived,
                'qc_notes' => $notes,
            ]);

            return $locked->fresh();
        });
    }

    public function delete(PurchaseOrder $po): void
    {
        $po->delete();
    }

    public function restore(PurchaseOrder $po): void
    {
        $po->restore();
    }

    public function getAssignedGrnIds(array $grnIds): array
    {
        if (empty($grnIds)) {
            return [];
        }

        return InventoryMovement::whereIn('goods_receipt_id', $grnIds)
            ->select('goods_receipt_id')
            ->distinct()
            ->pluck('goods_receipt_id')
            ->flip()
            ->all();
    }

    public function generatePoNumber(): string
    {
        return DB::transaction(function (): string {
            $year = now()->year;
            $prefix = "PO-{$year}-";

            $max = PurchaseOrder::withTrashed()
                ->where('po_number', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->max('po_number');

            $next = $max ? ((int) substr($max, strlen($prefix))) + 1 : 1;

            return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }

    public function recalculateTotals(PurchaseOrder $po): void
    {
        $po->load('lines');

        ['subtotal' => $subtotal, 'taxTotal' => $taxTotal] = $po->lines->reduce(
            function (array $carry, mixed $l): array {
                $base = (float) $l->qty_ordered * (float) $l->unit_cost;

                return [
                    'subtotal' => $carry['subtotal'] + $base,
                    'taxTotal' => $carry['taxTotal'] + $base * (float) $l->tax_rate / 100,
                ];
            },
            ['subtotal' => 0.0, 'taxTotal' => 0.0]
        );

        $po->update([
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'grand_total' => $subtotal + $taxTotal,
        ]);
    }

    private function calculateLineTotal(array $line): float
    {
        return (float) $line['qty_ordered'] * (float) $line['unit_cost']
            * (1 + (float) $line['tax_rate'] / 100);
    }
}
