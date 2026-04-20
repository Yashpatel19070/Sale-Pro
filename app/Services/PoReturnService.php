<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PoStatus;
use App\Enums\PoType;
use App\Models\PoUnitJob;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;

class PoReturnService
{
    /**
     * Auto-create a return PO for a failed unit job.
     * Called by PipelineService::fail() inside a DB::transaction — no nested transaction here.
     * MySQL InnoDB rolls back only the failing statement on unique violation, not the outer
     * transaction, so the retry is safe inside the caller's transaction.
     */
    public function createForFailedUnit(PoUnitJob $job, User $user): PurchaseOrder
    {
        $job->load(['purchaseOrder.supplier', 'poLine']);

        $originalPo = $job->purchaseOrder;
        $originalLine = $job->poLine;

        $attrs = [
            'type' => PoType::Return,
            'parent_po_id' => $originalPo->id,
            'supplier_id' => $originalPo->supplier_id,
            'status' => PoStatus::Open,
            'skip_tech' => false,
            'skip_qa' => false,
            'notes' => "Return for failed unit in job #{$job->id} at stage {$job->current_stage->value}.",
            'created_by_user_id' => $user->id,
        ];

        try {
            $returnPo = PurchaseOrder::create(['po_number' => $this->generateReturnPoNumber()] + $attrs);
        } catch (UniqueConstraintViolationException) {
            $returnPo = PurchaseOrder::create(['po_number' => $this->generateReturnPoNumber()] + $attrs);
        }

        $returnPo->confirmed_at = now();
        $returnPo->save();

        $returnPo->lines()->create([
            'product_id' => $originalLine->product_id,
            'qty_ordered' => 1,
            'qty_received' => 0,
            'unit_price' => $originalLine->unit_price,
            'snapshot_stock' => 0,
            'snapshot_inbound' => 0,
        ]);

        return $returnPo->load(['supplier', 'lines.product', 'parentPo']);
    }

    /**
     * @param  array{search?: string, status?: string}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return PurchaseOrder::ofType(PoType::Return)
            ->with(['supplier', 'lines.product', 'parentPo', 'createdBy'])
            ->when(
                ! empty($filters['search']),
                fn ($q) => $q->where(function ($inner) use ($filters): void {
                    $inner->where('po_number', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', '%'.$filters['search'].'%'));
                })
            )
            ->when(
                isset($filters['status']) && $filters['status'] !== '',
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->latest()
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * @throws \DomainException
     */
    public function close(PurchaseOrder $returnPo): PurchaseOrder
    {
        throw_if(
            $returnPo->type !== PoType::Return,
            \DomainException::class,
            'Only return POs can be closed via this method.'
        );

        throw_if(
            $returnPo->status !== PoStatus::Open,
            \DomainException::class,
            "Return PO is already {$returnPo->status->value}."
        );

        $returnPo->status = PoStatus::Closed;
        $returnPo->closed_at = now();
        $returnPo->save();

        return $returnPo->fresh();
    }

    public function generateReturnPoNumber(): string
    {
        $year = now()->year;
        $count = PurchaseOrder::whereYear('created_at', $year)->count();

        return sprintf('PO-%d-%04d', $year, $count + 1);
    }
}
