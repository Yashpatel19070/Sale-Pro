<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PipelineStage;
use App\Enums\UnitEventAction;
use App\Enums\UnitJobStatus;
use App\Models\PoLine;
use App\Models\PoUnitJob;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PipelineService
{
    public function __construct(
        private readonly InventoryMovementService $movementService,
        private readonly PurchaseOrderService $poService,
        private readonly PoReturnService $returnService,
    ) {}

    /**
     * @throws \DomainException
     * @throws \Throwable
     */
    public function createJob(PoLine $line, User $user, ?string $notes = null): PoUnitJob
    {
        $po = $line->loadMissing('purchaseOrder')->purchaseOrder;

        throw_if(
            ! $po->canReceive(),
            \DomainException::class,
            "PO {$po->po_number} is not open for receiving (status: {$po->status->value})."
        );

        throw_if(
            $line->isFulfilled(),
            \DomainException::class,
            "PO line {$line->id} is already fully received."
        );

        return DB::transaction(function () use ($line, $po, $user, $notes): PoUnitJob {
            $job = PoUnitJob::create([
                'purchase_order_id' => $po->id,
                'po_line_id' => $line->id,
                'inventory_serial_id' => null,
                'current_stage' => PipelineStage::Receive,
                'status' => UnitJobStatus::Passed,
                'assigned_to_user_id' => $user->id,
            ]);

            $this->writeEvent($job, PipelineStage::Receive, UnitEventAction::Pass, $user, $notes);

            $this->poService->incrementReceived($line);

            $this->advance($job, $po, $user);

            return $job->fresh(['purchaseOrder', 'poLine.product', 'assignedTo', 'events.user']);
        });
    }

    /**
     * @throws \DomainException
     * @throws \Throwable
     */
    public function start(PoUnitJob $job, User $user): PoUnitJob
    {
        throw_if(
            $job->status !== UnitJobStatus::Pending,
            \DomainException::class,
            'This job is not available to claim.'
        );

        return DB::transaction(function () use ($job, $user): PoUnitJob {
            $job->refresh();

            throw_if(
                $job->status !== UnitJobStatus::Pending,
                \DomainException::class,
                'This job was just claimed by another worker.'
            );

            $job->update([
                'status' => UnitJobStatus::InProgress,
                'assigned_to_user_id' => $user->id,
            ]);

            $this->writeEvent($job, $job->current_stage, UnitEventAction::Start, $user);

            return $job->fresh(['purchaseOrder', 'poLine.product', 'assignedTo', 'events.user']);
        });
    }

    /**
     * @param  array{serial_number?: string, inventory_location_id?: int, notes?: string|null}  $data
     *
     * @throws \DomainException
     * @throws \Throwable
     */
    public function pass(PoUnitJob $job, User $user, array $data = []): PoUnitJob
    {
        throw_if(
            $job->status !== UnitJobStatus::InProgress,
            \DomainException::class,
            'Job must be claimed before it can be passed.'
        );

        throw_if(
            $job->assigned_to_user_id !== $user->id,
            \DomainException::class,
            'Only the assigned worker can pass this job.'
        );

        return DB::transaction(function () use ($job, $user, $data): PoUnitJob {
            $job->refresh();

            $stage = $job->current_stage;

            if ($stage === PipelineStage::SerialAssign) {
                $this->assignSerial($job, $data);
            }

            $this->writeEvent($job, $stage, UnitEventAction::Pass, $user, $data['notes'] ?? null);

            if ($stage->isFinal()) {
                $this->completeAtShelf($job, $user, $data);
            } else {
                $this->advance($job, $job->loadMissing('purchaseOrder')->purchaseOrder, $user);
            }

            return $job->fresh(['purchaseOrder', 'poLine.product', 'inventorySerial', 'events.user']);
        });
    }

    /**
     * @throws \DomainException
     * @throws \Throwable
     */
    public function fail(PoUnitJob $job, User $user, ?string $notes = null): PoUnitJob
    {
        throw_if(
            $job->status !== UnitJobStatus::InProgress,
            \DomainException::class,
            'Job must be claimed before it can be failed.'
        );

        throw_if(
            $job->assigned_to_user_id !== $user->id,
            \DomainException::class,
            'Only the assigned worker can fail this job.'
        );

        return DB::transaction(function () use ($job, $user, $notes): PoUnitJob {
            $job->refresh();

            $this->writeEvent($job, $job->current_stage, UnitEventAction::Fail, $user, $notes);

            $job->update(['status' => UnitJobStatus::Failed]);

            $this->returnService->createForFailedUnit($job, $user);

            $this->poService->checkAndClose($job->loadMissing('purchaseOrder')->purchaseOrder);

            return $job->fresh(['purchaseOrder', 'poLine.product', 'assignedTo', 'events.user']);
        });
    }

    /**
     * @param  array{stages: array<PipelineStage>, purchase_order_id?: int}  $filters
     */
    public function queue(array $filters = []): LengthAwarePaginator
    {
        return PoUnitJob::with(['purchaseOrder', 'poLine.product', 'inventorySerial', 'assignedTo'])
            ->when(
                ! empty($filters['stages']),
                fn ($q) => $q->whereIn('current_stage', array_map(
                    fn (PipelineStage $s) => $s->value,
                    $filters['stages']
                ))
            )
            ->when(
                isset($filters['purchase_order_id']) && $filters['purchase_order_id'] !== '',
                fn ($q) => $q->where('purchase_order_id', $filters['purchase_order_id'])
            )
            ->where('status', UnitJobStatus::Pending->value)
            ->oldest()
            ->paginate(25)
            ->withQueryString();
    }

    private function advance(PoUnitJob $job, PurchaseOrder $po, User $user): void
    {
        $next = $job->current_stage->next();

        if ($next === null) {
            return;
        }

        if ($next === PipelineStage::Tech && $po->skip_tech) {
            $this->writeEvent($job, PipelineStage::Tech, UnitEventAction::Skip, $user, 'Skipped per PO skip_tech flag.');
            $next = PipelineStage::Qa;
        }

        if ($next === PipelineStage::Qa && $po->skip_qa) {
            $this->writeEvent($job, PipelineStage::Qa, UnitEventAction::Skip, $user, 'Skipped per PO skip_qa flag.');
            $next = PipelineStage::Shelf;
        }

        $job->update([
            'current_stage' => $next,
            'status' => UnitJobStatus::Pending,
            'assigned_to_user_id' => null,
        ]);
    }

    /** @param array{serial_number: string} $data */
    private function assignSerial(PoUnitJob $job, array $data): void
    {
        $job->update(['pending_serial_number' => strtoupper(trim($data['serial_number']))]);
    }

    /** @param array{inventory_location_id: int, notes?: string|null} $data */
    private function completeAtShelf(PoUnitJob $job, User $user, array $data): void
    {
        $poLine = $job->poLine;

        $serial = $this->movementService->receive([
            'product_id' => $poLine->product_id,
            'inventory_location_id' => $data['inventory_location_id'],
            'serial_number' => $job->pending_serial_number,
            'purchase_price' => $poLine->unit_price,
            'received_at' => now()->format('Y-m-d'),
            'supplier_name' => null,
            'notes' => $data['notes'] ?? null,
        ], $user);

        $job->update([
            'inventory_serial_id' => $serial->id,
            'status' => UnitJobStatus::Passed,
        ]);

        $this->poService->checkAndClose($job->loadMissing('purchaseOrder')->purchaseOrder);
    }

    private function writeEvent(
        PoUnitJob $job,
        PipelineStage $stage,
        UnitEventAction $action,
        User $user,
        ?string $notes = null,
    ): void {
        $job->events()->create([
            'stage' => $stage,
            'action' => $action,
            'user_id' => $user->id,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }
}
