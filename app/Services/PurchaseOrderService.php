<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PipelineStage;
use App\Enums\PoStatus;
use App\Enums\PoType;
use App\Enums\Role;
use App\Enums\UnitJobStatus;
use App\Models\InventorySerial;
use App\Models\PoLine;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    /**
     * @param  array{search?: string, status?: string, supplier_id?: int, type?: string}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return PurchaseOrder::with(['supplier', 'createdBy'])
            ->withCount('lines')
            ->when(
                ! empty($filters['search']),
                fn ($q) => $q->where(function ($inner) use ($filters): void {
                    $inner->where('po_number', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', '%'.$filters['search'].'%'));
                })
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
                ! empty($filters['type']),
                fn ($q) => $q->where('type', $filters['type'])
            )
            ->latest()
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * @param  array{supplier_id: int, notes?: string|null, skip_tech?: bool, skip_qa?: bool, lines: array<array{product_id: int, qty_ordered: int, unit_price: numeric-string|float}>}  $data
     *
     * @throws \Throwable
     */
    public function create(array $data, User $createdBy): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $createdBy): PurchaseOrder {
            $attrs = [
                'type' => PoType::Purchase,
                'supplier_id' => $data['supplier_id'],
                'status' => PoStatus::Draft,
                'skip_tech' => $data['skip_tech'] ?? false,
                'skip_qa' => $data['skip_qa'] ?? false,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $createdBy->id,
            ];

            try {
                $po = PurchaseOrder::create(['po_number' => $this->generatePoNumber()] + $attrs);
            } catch (UniqueConstraintViolationException) {
                $po = PurchaseOrder::create(['po_number' => $this->generatePoNumber()] + $attrs);
            }

            $this->syncLines($po, $data['lines']);

            return $po->load(['supplier', 'lines.product', 'createdBy']);
        });
    }

    /**
     * @param  array{supplier_id?: int, notes?: string|null, skip_tech?: bool, skip_qa?: bool, lines?: array<array{product_id: int, qty_ordered: int, unit_price: numeric-string|float}>}  $data
     *
     * @throws \DomainException
     * @throws \Throwable
     */
    public function update(PurchaseOrder $po, array $data): PurchaseOrder
    {
        throw_if(
            ! $po->isEditable(),
            \DomainException::class,
            'Only draft POs can be edited.'
        );

        return DB::transaction(function () use ($po, $data): PurchaseOrder {
            $po->update([
                'supplier_id' => $data['supplier_id'] ?? $po->supplier_id,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $po->notes,
                'skip_tech' => $data['skip_tech'] ?? $po->skip_tech,
                'skip_qa' => $data['skip_qa'] ?? $po->skip_qa,
            ]);

            if (isset($data['lines'])) {
                $po->lines()->delete();
                $this->syncLines($po, $data['lines']);
            }

            return $po->fresh(['supplier', 'lines.product', 'createdBy']);
        });
    }

    /**
     * @param  array<array{product_id: int, qty_ordered: int, unit_price: numeric-string|float}>  $lines
     */
    private function syncLines(PurchaseOrder $po, array $lines): void
    {
        foreach ($lines as $line) {
            $po->lines()->create($this->lineDataWithSnapshot($line));
        }
    }

    /**
     * @throws \DomainException
     */
    public function confirm(PurchaseOrder $po): PurchaseOrder
    {
        throw_if(
            $po->status !== PoStatus::Draft,
            \DomainException::class,
            'Only draft POs can be confirmed.'
        );

        throw_if(
            $po->lines()->count() === 0,
            \DomainException::class,
            'Cannot confirm a PO with no lines.'
        );

        $po->status = PoStatus::Open;
        $po->confirmed_at = now();
        $po->save();

        return $po->fresh(['supplier', 'lines.product']);
    }

    /**
     * @throws \DomainException
     */
    public function cancel(PurchaseOrder $po, string $cancelNotes): PurchaseOrder
    {
        throw_if(
            ! in_array($po->status, [PoStatus::Draft, PoStatus::Open], true),
            \DomainException::class,
            'Only draft or open POs can be cancelled.'
        );

        throw_if(
            $po->lines()->where('qty_received', '>', 0)->exists(),
            \DomainException::class,
            'Cannot cancel a PO that has received units.'
        );

        $po->status = PoStatus::Cancelled;
        $po->cancelled_at = now();
        $po->cancel_notes = $cancelNotes;
        $po->save();

        return $po->fresh();
    }

    /**
     * @throws \DomainException
     * @throws \Throwable
     */
    public function reopen(PurchaseOrder $po, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $user): PurchaseOrder {
            // Lock the row to prevent concurrent reopens bypassing the count gate.
            $po = PurchaseOrder::lockForUpdate()->findOrFail($po->id);

            throw_if(
                $po->status !== PoStatus::Closed,
                \DomainException::class,
                'Only closed POs can be reopened.'
            );

            throw_if(
                $po->unitJobs()
                    ->where('current_stage', PipelineStage::Shelf->value)
                    ->where('status', UnitJobStatus::Passed->value)
                    ->exists(),
                \DomainException::class,
                'Cannot reopen: one or more units from this PO are currently on the shelf.'
            );

            if ($po->reopen_count >= 2) {
                throw_if(
                    ! $user->hasRole(Role::SuperAdmin->value),
                    \DomainException::class,
                    'Third or subsequent reopens require Super Admin approval.'
                );
            }

            $po->status = PoStatus::Open;
            $po->reopen_count = $po->reopen_count + 1;
            $po->reopened_at = now();
            $po->save();

            return $po->fresh();
        });
    }

    /**
     * @throws \DomainException
     * @throws \Throwable
     */
    public function incrementReceived(PoLine $line): void
    {
        DB::transaction(function () use ($line): void {
            throw_if(
                $line->isFulfilled(),
                \DomainException::class,
                "PO line {$line->id} is already fully received."
            );

            $line->increment('qty_received');

            $po = $line->loadMissing('purchaseOrder')->purchaseOrder;
            if ($po->status === PoStatus::Open) {
                $po->status = PoStatus::Partial;
                $po->save();
            }
        });
    }

    public function checkAndClose(PurchaseOrder $po): void
    {
        $po->loadMissing(['lines', 'unitJobs']);

        if ($po->unitJobs->isEmpty()) {
            return;
        }

        $allLinesFulfilled = $po->lines->isNotEmpty()
            && $po->lines->every(fn (PoLine $line) => $line->isFulfilled());

        $allJobsClosed = $po->unitJobs->every(
            fn ($job) => $job->status->isTerminal()
        );

        if ($allLinesFulfilled && $allJobsClosed) {
            $po->status = PoStatus::Closed;
            $po->closed_at = now();
            $po->save();
        }
    }

    public function generatePoNumber(): string
    {
        $year = now()->year;
        $count = PurchaseOrder::whereYear('created_at', $year)->count();

        return sprintf('PO-%d-%04d', $year, $count + 1);
    }

    /**
     * @param  array{product_id: int, qty_ordered: int, unit_price: numeric-string|float}  $line
     */
    private function lineDataWithSnapshot(array $line): array
    {
        $productId = $line['product_id'];

        $snapshotStock = InventorySerial::where('product_id', $productId)
            ->where('status', 'in_stock')
            ->count();

        $snapshotInbound = PoLine::whereHas('purchaseOrder', fn ($q) => $q
            ->whereIn('status', [PoStatus::Open->value, PoStatus::Partial->value])
            ->where('type', PoType::Purchase->value))
            ->where('product_id', $productId)
            ->sum(DB::raw('qty_ordered - qty_received'));

        return [
            'product_id' => $productId,
            'qty_ordered' => $line['qty_ordered'],
            'qty_received' => 0,
            'unit_price' => $line['unit_price'],
            'snapshot_stock' => (int) $snapshotStock,
            'snapshot_inbound' => (int) $snapshotInbound,
        ];
    }
}
