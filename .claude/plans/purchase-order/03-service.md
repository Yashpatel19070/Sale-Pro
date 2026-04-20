# Purchase Order Module — Service

## PurchaseOrderService

```php
<?php
// app/Services/PurchaseOrderService.php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PipelineStage;
use App\Enums\PoStatus;
use App\Enums\PoType;
use App\Enums\UnitJobStatus;
use App\Models\InventorySerial;
use App\Models\PoLine;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    /**
     * Paginated PO list with optional filters.
     *
     * @param  array{search?: string, status?: string, supplier_id?: int, type?: string}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return PurchaseOrder::with(['supplier', 'createdBy'])
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
            ->when(
                isset($filters['supplier_id']) && $filters['supplier_id'] !== '',
                fn ($q) => $q->where('supplier_id', $filters['supplier_id'])
            )
            ->when(
                isset($filters['type']) && $filters['type'] !== '',
                fn ($q) => $q->where('type', $filters['type'])
            )
            ->latest()
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * Create a new draft PO with lines.
     *
     * @param  array{supplier_id: int, notes?: string|null, skip_tech?: bool, skip_qa?: bool, lines: array<array{product_id: int, qty_ordered: int, unit_price: numeric-string|float}>}  $data
     *
     * @throws \Throwable
     */
    public function create(array $data, User $createdBy): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $createdBy): PurchaseOrder {
            $po = PurchaseOrder::create([
                'po_number'          => $this->generatePoNumber(),
                'type'               => PoType::Purchase,
                'supplier_id'        => $data['supplier_id'],
                'status'             => PoStatus::Draft,
                'skip_tech'          => $data['skip_tech'] ?? false,
                'skip_qa'            => $data['skip_qa'] ?? false,
                'notes'              => $data['notes'] ?? null,
                'created_by_user_id' => $createdBy->id,
            ]);

            foreach ($data['lines'] as $line) {
                $po->lines()->create(
                    $this->lineDataWithSnapshot($line)
                );
            }

            return $po->load(['supplier', 'lines.product', 'createdBy']);
        });
    }

    /**
     * Update a draft PO's header and lines.
     * Only allowed when status = draft.
     *
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
                'notes'       => $data['notes'] ?? null,
                'skip_tech'   => $data['skip_tech'] ?? $po->skip_tech,
                'skip_qa'     => $data['skip_qa'] ?? $po->skip_qa,
            ]);

            if (isset($data['lines'])) {
                $po->lines()->delete();

                foreach ($data['lines'] as $line) {
                    $po->lines()->create(
                        $this->lineDataWithSnapshot($line)
                    );
                }
            }

            return $po->fresh(['supplier', 'lines.product', 'createdBy']);
        });
    }

    /**
     * Confirm a draft PO → moves to open. Locks prices.
     *
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

        $po->update([
            'status'       => PoStatus::Open,
            'confirmed_at' => now(),
        ]);

        return $po->fresh(['supplier', 'lines.product']);
    }

    /**
     * Cancel a PO. Only allowed when draft or open with no units received.
     * Reason is required — stored in cancel_notes for permanent audit record.
     *
     * @throws \DomainException
     */
    public function cancel(PurchaseOrder $po, string $cancelNotes): PurchaseOrder
    {
        $cancellableStatuses = [PoStatus::Draft, PoStatus::Open];

        throw_if(
            ! in_array($po->status, $cancellableStatuses, true),
            \DomainException::class,
            'Only draft or open POs can be cancelled.'
        );

        throw_if(
            $po->lines()->where('qty_received', '>', 0)->exists(),
            \DomainException::class,
            'Cannot cancel a PO that has received units.'
        );

        $po->update([
            'status'       => PoStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_notes' => $cancelNotes,
        ]);

        return $po->fresh();
    }

    /**
     * Reopen a closed PO.
     * Manager can reopen up to 2 times. 3rd+ requires super-admin.
     * Blocked if any unit from this PO is currently on the shelf.
     *
     * @throws \DomainException
     */
    public function reopen(PurchaseOrder $po, User $user): PurchaseOrder
    {
        throw_if(
            $po->status !== PoStatus::Closed,
            \DomainException::class,
            'Only closed POs can be reopened.'
        );

        // Guard: blocked if any unit is currently on the shelf
        throw_if(
            $po->unitJobs()
                ->where('current_stage', PipelineStage::Shelf->value)
                ->where('status', UnitJobStatus::Passed->value)
                ->exists(),
            \DomainException::class,
            'Cannot reopen: one or more units from this PO are currently on the shelf.'
        );

        // Guard: 3rd+ reopen requires super-admin
        if ($po->reopen_count >= 2) {
            throw_if(
                ! $user->hasRole('super-admin'),
                \DomainException::class,
                'Third or subsequent reopens require Super Admin approval.'
            );
        }

        $po->update([
            'status'       => PoStatus::Open,
            'reopen_count' => $po->reopen_count + 1,
            'reopened_at'  => now(),
            // closed_at is intentionally NOT nulled — it remains as audit of when PO was last closed
        ]);

        return $po->fresh();
    }

    /**
     * Increment qty_received on a PO line. Called by the pipeline when a unit
     * passes the receive stage.
     *
     * @throws \DomainException
     */
    public function incrementReceived(PoLine $line): void
    {
        throw_if(
            $line->isFulfilled(),
            \DomainException::class,
            "PO line {$line->id} is already fully received."
        );

        $line->increment('qty_received');

        // Update PO status to partial if it was open
        $po = $line->purchaseOrder;
        if ($po->status === PoStatus::Open) {
            $po->update(['status' => PoStatus::Partial]);
        }
    }

    /**
     * Check if PO should auto-close. Call after every unit job closes.
     * Closes if all lines fulfilled AND all unit jobs are in a terminal state.
     */
    public function checkAndClose(PurchaseOrder $po): void
    {
        $po->loadMissing(['lines', 'unitJobs']);

        $allLinesFulfilled = $po->lines->every(fn (PoLine $line) => $line->isFulfilled());

        $terminalStatuses = ['passed', 'failed', 'skipped'];
        $allJobsClosed = $po->unitJobs->every(
            fn ($job) => in_array($job->status, $terminalStatuses, true)
        );

        if ($allLinesFulfilled && $allJobsClosed) {
            $po->update([
                'status'    => PoStatus::Closed,
                'closed_at' => now(),
            ]);
        }
    }

    // ── Private Helpers ───────────────────────────────────────────────────────────

    /**
     * Build line data array with stock/inbound snapshots captured at this moment.
     * Called inside DB::transaction — reads are consistent within the transaction.
     * snapshot_inbound counts other open POs only, not the current PO being created.
     *
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
            'product_id'       => $productId,
            'qty_ordered'      => $line['qty_ordered'],
            'qty_received'     => 0,
            'unit_price'       => $line['unit_price'],
            'snapshot_stock'   => (int) $snapshotStock,
            'snapshot_inbound' => (int) $snapshotInbound,
        ];
    }

    /**
     * Auto-generate PO number. Format: PO-YYYY-XXXX.
     * Counter resets each calendar year.
     */
    public function generatePoNumber(): string
    {
        $year = now()->year;

        $count = PurchaseOrder::whereYear('created_at', $year)->count();

        return sprintf('PO-%d-%04d', $year, $count + 1);
    }
}
```

---

## Method Summary

| Method | Description |
|--------|-------------|
| `list(filters)` | Paginated POs. Filters: search (po_number/supplier), status, supplier_id, type. |
| `create(data, user)` | Creates draft PO + lines in one transaction. Auto-generates PO number. Captures stock/inbound snapshot per line. |
| `update(po, data)` | Draft-only. Replaces lines entirely if `lines` key present. Re-captures snapshots on new lines. |
| `confirm(po)` | Draft → Open. Validates at least one line exists. Sets confirmed_at. |
| `cancel(po, cancelNotes)` | Draft/Open → Cancelled. Blocked if any unit received. Sets cancelled_at + cancel_notes. Reason required. |
| `reopen(po, user)` | Closed → Open. Manager for attempts 1-2, super-admin for 3+. Blocked if unit on shelf. |
| `incrementReceived(line)` | +1 to qty_received on a line. Updates PO status to partial if was open. |
| `checkAndClose(po)` | Auto-closes when all lines fulfilled + all jobs terminal. Sets closed_at. |

---

## Implementation Deviations (actual code differs from plan above)

### Added import: `UniqueConstraintViolationException`
```php
use Illuminate\Database\UniqueConstraintViolationException;
```

### `list()` — added `withCount('lines')`
Index view displays `$po->lines_count`; must be eager-loaded:
```php
PurchaseOrder::with(['supplier', 'createdBy'])->withCount('lines')
```

### `syncLines()` — private helper extracted
`create()` and `update()` both loop over lines. Extracted to avoid duplication:
```php
private function syncLines(PurchaseOrder $po, array $lines): void
{
    foreach ($lines as $line) {
        $po->lines()->create($this->lineDataWithSnapshot($line));
    }
}
```

### `confirm()` / `cancel()` / `checkAndClose()` — direct attribute assignment
System lifecycle fields removed from `$fillable` (see 02-model.md deviations).
`$po->update([...])` silently ignores non-fillable fields, so service uses direct assignment:
```php
// confirm():
$po->status = PoStatus::Open;
$po->confirmed_at = now();
$po->save();

// cancel():
$po->status = PoStatus::Cancelled;
$po->cancelled_at = now();
$po->cancel_notes = $cancelNotes;
$po->save();

// checkAndClose():
$po->status = PoStatus::Closed;
$po->closed_at = now();
$po->save();
```

### `reopen()` — wrapped in `DB::transaction` + `lockForUpdate` (race condition fix)
Concurrent reopens could both pass the `reopen_count >= 2` super-admin gate before either commits. Fix:
```php
public function reopen(PurchaseOrder $po, User $user): PurchaseOrder
{
    return DB::transaction(function () use ($po, $user): PurchaseOrder {
        $po = PurchaseOrder::lockForUpdate()->findOrFail($po->id);
        // ... guards ...
        $po->status = PoStatus::Open;
        $po->reopen_count = $po->reopen_count + 1;
        $po->reopened_at = now();
        $po->save();
        return $po->fresh();
    });
}
```

### `incrementReceived()` — wrapped in `DB::transaction` (data integrity fix)
Two writes (line increment + PO status update) must be atomic:
```php
public function incrementReceived(PoLine $line): void
{
    DB::transaction(function () use ($line): void {
        // ...
    });
}
```

### `generatePoNumber()` — retry on `UniqueConstraintViolationException`
Unique index already exists on `po_number` (from original migration). Added retry:
```php
try {
    return sprintf('PO-%d-%04d', $year, $count + 1);
} catch (UniqueConstraintViolationException) {
    $count = PurchaseOrder::whereYear('created_at', $year)->count();
    return sprintf('PO-%d-%04d', $year, $count + 1);
}
```
| `generatePoNumber()` | PO-YYYY-XXXX. Year-scoped sequential. |

---

## Notes

- `update()` replaces all lines when `lines` key is present — no partial line updates. Snapshots re-captured on the new lines (reflects stock at edit time, not original creation time).
- `lineDataWithSnapshot()` runs inside the parent `DB::transaction` — no separate lock needed. MySQL REPEATABLE READ gives consistent reads within the transaction. No `lockForUpdate()` here — snapshots are informational display data, not business logic gates.
- `snapshot_inbound` counts `qty_ordered - qty_received` across open/partial PURCHASE POs for the same product. Excludes the current PO being created (it doesn't exist yet, or lines were just deleted in update).
- `reopen()` shelf-check uses `current_stage = shelf AND status = passed` to detect shelved units.
- `checkAndClose()` is called by `PipelineService` after every unit job transitions to terminal state.
- `generatePoNumber()` uses `COUNT` not `MAX(id)` so year resets correctly.
- No `DB::transaction` in `confirm/cancel/reopen` — single table write each.

---

## Additional Implementation Deviations (Group B/C fixes)

### `update()` — `notes` preserves existing value when key absent
`$data['notes'] ?? null` would null out notes on partial updates. Fixed to:
```php
'notes' => array_key_exists('notes', $data) ? $data['notes'] : $po->notes,
```
`array_key_exists` distinguishes "key missing" (preserve) from "key present as null" (intentional clear).

### Controller `edit()` — `$existingLines` mapping moved out of Blade
`_form.blade.php` had PHP business logic (model→array mapping). Moved to `PurchaseOrderController::edit()`:
```php
$existingLines = $purchaseOrder->lines->map(fn ($l) => [
    'product_id'  => $l->product_id,
    'qty_ordered' => $l->qty_ordered,
    'unit_price'  => $l->unit_price,
])->toArray();
```
Blade now: `$existingLines = old('lines', $existingLines ?? [['product_id'=>'','qty_ordered'=>1,'unit_price'=>'']])`

## Critical Bug Fixes (post-review)

### `create()` — UniqueConstraintViolationException retry moved to call site
The original plan wrapped `sprintf()` in a try/catch — dead code because `UniqueConstraintViolationException`
is thrown at `PurchaseOrder::create()` (the INSERT), not at `sprintf()`. Fixed by extracting attrs and
wrapping only the create call:
```php
try {
    $po = PurchaseOrder::create(['po_number' => $this->generatePoNumber()] + $attrs);
} catch (UniqueConstraintViolationException) {
    $po = PurchaseOrder::create(['po_number' => $this->generatePoNumber()] + $attrs);
}
```
Import added: `use Illuminate\Database\UniqueConstraintViolationException;`

### `generatePoNumber()` — dead try/catch removed
Original plan had try/catch inside `generatePoNumber()` around `sprintf()`. Removed — sprintf never throws.
Method now returns directly.

### `incrementReceived()` — `loadMissing()` to prevent lazy-load violation
`$line->purchaseOrder` inside a transaction could trigger `LazyLoadingViolationException` if relation
not already loaded. Fixed to `$line->loadMissing('purchaseOrder')->purchaseOrder`.

### `checkAndClose()` — empty unitJobs guard + isNotEmpty on lines
`Collection::every()` vacuously returns `true` on empty collection. Without a guard, a PO with zero
unit jobs and all lines fulfilled would prematurely close. Fixed:
```php
if ($po->unitJobs->isEmpty()) {
    return;
}
$allLinesFulfilled = $po->lines->isNotEmpty()
    && $po->lines->every(fn (PoLine $line) => $line->isFulfilled());
```

### `reopen()` — `'super-admin'` replaced with `Role::SuperAdmin->value`
Plan had hardcoded string `'super-admin'`. Replaced with new `Role` enum:
```php
! $user->hasRole(Role::SuperAdmin->value)
```
New enum: `app/Enums/Role.php` — cases: `SuperAdmin`, `Admin`, `Manager`, `Procurement`, `Sales`.
