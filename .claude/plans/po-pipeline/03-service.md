# PO Pipeline Module — Service

## PipelineService

```php
<?php
// app/Services/PipelineService.php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PipelineStage;
use App\Enums\UnitEventAction;
use App\Enums\UnitJobStatus;
use App\Models\PoLine;
use App\Models\PoUnitJob;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\InventoryMovementService;
use App\Services\PoReturnService;
use App\Services\PurchaseOrderService;
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
     * Create a unit job for a PO line. Called when procurement receives a unit.
     * Writes a receive event and increments the PO line's qty_received.
     *
     * @throws \DomainException
     * @throws \Throwable
     */
    public function createJob(PoLine $line, User $user, ?string $notes = null): PoUnitJob
    {
        $po = $line->purchaseOrder;

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
                'purchase_order_id'  => $po->id,
                'po_line_id'         => $line->id,
                'inventory_serial_id' => null,
                'current_stage'      => PipelineStage::Receive,
                'status'             => UnitJobStatus::Passed, // receive = confirmed arrival
                'assigned_to_user_id' => $user->id,
            ]);

            $this->writeEvent($job, PipelineStage::Receive, UnitEventAction::Pass, $user, $notes);

            $this->poService->incrementReceived($line);

            // Advance job to next stage (visual)
            $this->advance($job, $po, $user);

            return $job->fresh(['purchaseOrder', 'poLine.product', 'assignedTo', 'events.user']);
        });
    }

    /**
     * Claim a pending unit job. Sets status to in_progress and assigns the user.
     * Must be called before pass() or fail(). Redirects to detail page after claim.
     *
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
            $job->refresh(); // TOCTOU guard — re-check inside transaction

            throw_if(
                $job->status !== UnitJobStatus::Pending,
                \DomainException::class,
                'This job was just claimed by another worker.'
            );

            $job->update([
                'status'              => UnitJobStatus::InProgress,
                'assigned_to_user_id' => $user->id,
            ]);

            $this->writeEvent($job, $job->current_stage, UnitEventAction::Start, $user);

            return $job->fresh(['purchaseOrder', 'poLine.product', 'assignedTo', 'events.user']);
        });
    }

    /**
     * Pass a unit at its current stage. Advances to next stage (or closes job at shelf).
     * Job must be in_progress and claimed by this user before calling pass().
     *
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

            // Stage-specific actions before advancing
            if ($stage === PipelineStage::SerialAssign) {
                $this->assignSerial($job, $data);
            }

            $this->writeEvent($job, $stage, UnitEventAction::Pass, $user, $data['notes'] ?? null);

            if ($stage->isFinal()) {
                // shelf stage passed — create inventory serial and close job
                $this->completeAtShelf($job, $user, $data);
            } else {
                $this->advance($job, $job->purchaseOrder, $user);
            }

            return $job->fresh(['purchaseOrder', 'poLine.product', 'inventorySerial', 'events.user']);
        });
    }

    /**
     * Fail a unit at its current stage. Marks job as failed, triggers Return PO creation.
     * Job must be in_progress and claimed by this user before calling fail().
     *
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

            // Auto-create return PO for the failed unit
            $this->returnService->createForFailedUnit($job, $user);

            // Check if PO should auto-close after this terminal state
            $this->poService->checkAndClose($job->purchaseOrder);

            return $job->fresh(['purchaseOrder', 'poLine.product', 'assignedTo', 'events.user']);
        });
    }

    /**
     * Queue of unit jobs visible to a user based on their pipeline permissions.
     * Shows jobs at stages the user is permitted to work on.
     *
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

    // ── Private Helpers ───────────────────────────────────────────────────────────

    /**
     * Advance the job to the next stage. Handles skip logic for tech and qa.
     */
    private function advance(PoUnitJob $job, PurchaseOrder $po, User $user): void
    {
        $next = $job->current_stage->next();

        if ($next === null) {
            return; // already at shelf — completeAtShelf handled separately
        }

        // Skip tech if PO flag set
        if ($next === PipelineStage::Tech && $po->skip_tech) {
            $this->writeEvent($job, PipelineStage::Tech, UnitEventAction::Skip, $user, 'Skipped per PO skip_tech flag.');
            $next = PipelineStage::Qa;
        }

        // Skip qa if PO flag set
        if ($next === PipelineStage::Qa && $po->skip_qa) {
            $this->writeEvent($job, PipelineStage::Qa, UnitEventAction::Skip, $user, 'Skipped per PO skip_qa flag.');
            $next = PipelineStage::Shelf;
        }

        $job->update([
            'current_stage'      => $next,
            'status'             => UnitJobStatus::Pending,
            'assigned_to_user_id' => null,
        ]);
    }

    /**
     * Assign a serial number to the job at the serial_assign stage.
     * Stored in pending_serial_number — travels with the job to shelf.
     * InventorySerial is NOT created here — only at shelf stage.
     *
     * @param  array{serial_number: string}  $data
     */
    private function assignSerial(PoUnitJob $job, array $data): void
    {
        $job->update(['pending_serial_number' => strtoupper(trim($data['serial_number']))]);
    }

    /**
     * When unit passes the shelf stage: create InventorySerial via InventoryMovementService::receive().
     * Serial number read from job->pending_serial_number (set at serial_assign stage).
     * purchase_price comes from the PO line — no re-entry needed.
     *
     * @param  array{inventory_location_id: int, notes?: string|null}  $data
     */
    private function completeAtShelf(PoUnitJob $job, User $user, array $data): void
    {
        $poLine = $job->poLine;

        $serial = $this->movementService->receive([
            'product_id'            => $poLine->product_id,
            'inventory_location_id' => $data['inventory_location_id'],
            'serial_number'         => $job->pending_serial_number, // set at serial_assign stage
            'purchase_price'        => $poLine->unit_price,         // locked at PO creation
            'received_at'           => now()->format('Y-m-d'),
            'supplier_name'         => null,                        // not needed — PO number traceable via job
            'notes'                 => $data['notes'] ?? null,
        ], $user);

        $job->update([
            'inventory_serial_id' => $serial->id,
            'status'              => UnitJobStatus::Passed,
        ]);

        $this->poService->checkAndClose($job->purchaseOrder);
    }

    /**
     * Write an immutable event row to po_unit_events.
     */
    private function writeEvent(
        PoUnitJob $job,
        PipelineStage $stage,
        UnitEventAction $action,
        User $user,
        ?string $notes = null,
    ): void {
        $job->events()->create([
            'stage'      => $stage,
            'action'     => $action,
            'user_id'    => $user->id,
            'notes'      => $notes,
            'created_at' => now(),
        ]);
    }
}
```

---

## Method Summary

| Method | Description |
|--------|-------------|
| `createJob(line, user, notes)` | Creates a pending unit job for a PO line. Writes receive event, increments qty_received, advances to visual. |
| `start(job, user)` | Claims a pending job. Sets status → in_progress, assigns user. Writes start event. TOCTOU-safe via double-check inside transaction. |
| `pass(job, user, data)` | Passes the job at its current stage. Guards: must be in_progress + assigned to user. Handles serial assignment at serial_assign stage. Creates InventorySerial at shelf stage. |
| `fail(job, user, notes)` | Marks job failed. Guards: must be in_progress + assigned to user. Writes fail event. Auto-creates return PO. Calls checkAndClose. |
| `queue(filters)` | Paginated list of pending jobs filtered by stage(s). Shows only pending — in_progress jobs are on the worker's detail page. |
| `advance(job, po, user)` | Private. Moves job to next stage (status → pending, assigned_to → null). Auto-skips tech/qa per PO flags, writing skip events. |
| `assignSerial(job, data)` | Private. Stores serial number on job at serial_assign stage. |
| `completeAtShelf(job, user, data)` | Private. Calls `InventoryMovementService::receive()`. Sets serial ID on job. Calls checkAndClose. |
| `writeEvent(job, stage, action, user, notes)` | Private. Inserts immutable event row. |

---

## Notes

- Worker flow: `start()` → redirect to detail page → `pass()` or `fail()` there. Never pass/fail a job that hasn't been claimed.
- `start()` has a TOCTOU guard: first check before transaction, second `$job->refresh()` check inside transaction. Prevents race condition where two workers claim the same unit simultaneously.
- `pass()` and `fail()` require `status === InProgress` AND `assigned_to_user_id === $user->id`. This prevents: (a) acting on unclaimed jobs, (b) one worker completing another worker's job.
- `advance()` resets `status → pending` and `assigned_to → null` when moving to the next stage — the next department's worker claims it fresh.
- `createJob()` immediately marks the receive stage as passed and advances — "received" is confirmed by the act of creating the job.
- `pass()` at `serial_assign` does not create `InventorySerial` yet — serial number stored on `pending_serial_number`. Creation happens only at `shelf` stage so it counts only when fully processed.
- `fail()` uses constructor-injected `$this->returnService` — no circular dependency exists.
- `advance()` handles both skips in sequence — if both tech AND qa are skipped, the job goes directly from serial_assign to shelf.
- All multi-step operations wrapped in `DB::transaction` to ensure atomicity.
- `queue()` returns oldest-first so jobs are processed FIFO within each stage.
