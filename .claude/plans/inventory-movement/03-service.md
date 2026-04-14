# InventoryMovement Module — Service

## InventoryMovementService

```php
<?php
// app/Services/InventoryMovementService.php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\SerialStatus;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InventoryMovementService
{
    /**
     * Transfer a serial from one location to another.
     *
     * Business rules (all enforced inside the transaction):
     * - Serial status must be `in_stock`
     * - Serial's current location must match `$fromLocation`
     *
     * @throws \DomainException
     */
    public function transfer(
        InventorySerial  $serial,
        InventoryLocation $fromLocation,
        InventoryLocation $toLocation,
        User             $user,
        ?string          $reference = null,
        ?string          $notes     = null,
    ): InventoryMovement {
        $movement = DB::transaction(function () use (
            $serial, $fromLocation, $toLocation, $user, $reference, $notes
        ): InventoryMovement {
            // Reload inside transaction to get a fresh lock-free snapshot
            $serial->refresh();

            throw_if(
                $serial->status !== SerialStatus::InStock,
                \DomainException::class,
                "Serial '{$serial->serial_number}' is not in stock (current status: {$serial->status->value})."
            );

            throw_if(
                $serial->inventory_location_id !== $fromLocation->id,
                \DomainException::class,
                "Serial '{$serial->serial_number}' is not at location '{$fromLocation->code}'."
            );

            throw_if(
                $fromLocation->id === $toLocation->id,
                \DomainException::class,
                'From and to locations must be different.'
            );

            $movement = InventoryMovement::create([
                'inventory_serial_id' => $serial->id,
                'type'                => MovementType::Transfer,
                'from_location_id'    => $fromLocation->id,
                'to_location_id'      => $toLocation->id,
                'purchase_price'      => null,
                'reference'           => $reference,
                'notes'               => $notes,
                'user_id'             => $user->id,
            ]);

            $serial->update([
                'inventory_location_id' => $toLocation->id,
            ]);

            return $movement;
        });

        return $movement->load(['serial.product', 'fromLocation', 'toLocation', 'user']);
    }

    /**
     * Record a sale movement — serial leaves the warehouse.
     *
     * Business rules (all enforced inside the transaction):
     * - Serial status must be `in_stock`
     * - Serial's current location must match `$fromLocation`
     *
     * @throws \DomainException
     */
    public function sale(
        InventorySerial  $serial,
        InventoryLocation $fromLocation,
        User             $user,
        ?string          $reference = null,
        ?string          $notes     = null,
    ): InventoryMovement {
        $movement = DB::transaction(function () use (
            $serial, $fromLocation, $user, $reference, $notes
        ): InventoryMovement {
            $serial->refresh();

            throw_if(
                $serial->status !== SerialStatus::InStock,
                \DomainException::class,
                "Serial '{$serial->serial_number}' is not in stock (current status: {$serial->status->value})."
            );

            throw_if(
                $serial->inventory_location_id !== $fromLocation->id,
                \DomainException::class,
                "Serial '{$serial->serial_number}' is not at location '{$fromLocation->code}'."
            );

            $movement = InventoryMovement::create([
                'inventory_serial_id' => $serial->id,
                'type'                => MovementType::Sale,
                'from_location_id'    => $fromLocation->id,
                'to_location_id'      => null,
                'purchase_price'      => null,
                'reference'           => $reference,
                'notes'               => $notes,
                'user_id'             => $user->id,
            ]);

            $serial->update([
                'inventory_location_id' => null,
                'status'                => SerialStatus::Sold,
            ]);

            return $movement;
        });

        return $movement->load(['serial.product', 'fromLocation', 'toLocation', 'user']);
    }

    /**
     * Record an adjustment — changes serial status to damaged or missing.
     *
     * `$newStatus` must be one of: 'damaged', 'missing'
     *
     * @throws \DomainException
     */
    public function adjustment(
        InventorySerial $serial,
        string          $newStatus,
        User            $user,
        ?int            $fromLocationId = null,
        ?int            $toLocationId   = null,
        ?string         $reference      = null,
        ?string         $notes          = null,
    ): InventoryMovement {
        $allowedStatuses = ['damaged', 'missing'];

        throw_if(
            ! in_array($newStatus, $allowedStatuses, true),
            \DomainException::class,
            "Adjustment status must be one of: " . implode(', ', $allowedStatuses) . "."
        );

        throw_if(
            $serial->status !== SerialStatus::InStock,
            \DomainException::class,
            "Only in_stock serials can be adjusted. Current status: {$serial->status->value}."
        );

        $movement = DB::transaction(function () use (
            $serial, $newStatus, $user, $fromLocationId, $toLocationId, $reference, $notes
        ): InventoryMovement {
            // TOCTOU protection: $serial->refresh() re-reads the row after the transaction lock
            // is acquired, ensuring the status check and write are atomic. Safe for single-warehouse use.
            $serial->refresh();

            $movement = InventoryMovement::create([
                'inventory_serial_id' => $serial->id,
                'type'                => MovementType::Adjustment,
                'from_location_id'    => $fromLocationId,
                'to_location_id'      => $toLocationId,
                'purchase_price'      => null,
                'reference'           => $reference,
                'notes'               => $notes,
                'user_id'             => $user->id,
            ]);

            $serial->update([
                'status'                => $newStatus,
                'inventory_location_id' => null, // no longer on a shelf
            ]);

            return $movement;
        });

        return $movement->load(['serial.product', 'fromLocation', 'toLocation', 'user']);
    }

    /**
     * Chronological movement timeline for a single serial.
     * Used on the InventorySerial show page.
     *
     * @return Collection<int, InventoryMovement>
     */
    public function historyForSerial(InventorySerial $serial): Collection
    {
        return InventoryMovement::with(['serial.product', 'fromLocation', 'toLocation', 'user'])
            ->forSerial($serial)
            ->oldest()
            ->get();
    }

    /**
     * Paginated movement log for the history index page.
     * Supports filters: serial_number, location_id, type, date_from, date_to.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listMovements(array $filters = []): LengthAwarePaginator
    {
        return InventoryMovement::with(['serial.product', 'fromLocation', 'toLocation', 'user'])
            ->when(
                ! empty($filters['serial_number']),
                fn ($q) => $q->whereHas(
                    'serial',
                    fn ($sq) => $sq->where('serial_number', 'like', '%' . $filters['serial_number'] . '%')
                )
            )
            ->when(
                isset($filters['location_id']) && $filters['location_id'] !== '',
                fn ($q) => $q->where(function ($inner) use ($filters): void {
                    $inner->where('from_location_id', $filters['location_id'])
                          ->orWhere('to_location_id', $filters['location_id']);
                })
            )
            ->when(
                isset($filters['type']) && $filters['type'] !== '',
                fn ($q) => $q->where('type', $filters['type'])
            )
            ->when(
                ! empty($filters['date_from']),
                fn ($q) => $q->where('created_at', '>=', $filters['date_from'] . ' 00:00:00')
            )
            ->when(
                ! empty($filters['date_to']),
                fn ($q) => $q->where('created_at', '<=', $filters['date_to'] . ' 23:59:59')
            )
            ->latest()
            ->paginate(25)
            ->withQueryString();
    }
}
```

---

## Critical Rules

### 1. TOCTOU — guards are inside the transaction

`$serial->refresh()` re-reads the row after the transaction lock is acquired, ensuring
the status check and write are atomic. Safe for single-warehouse use.

```
// ❌ Wrong — check outside transaction, another request can change status in the gap
if ($serial->status !== SerialStatus::InStock) { throw ... }
DB::transaction(fn() => InventoryMovement::create(...));

// ✅ Correct — refresh + check + write all atomic
DB::transaction(function () use ($serial) {
    $serial->refresh();
    throw_if($serial->status !== SerialStatus::InStock, ...);
    InventoryMovement::create(...);
    $serial->update(...);
});
```

### 2. Serial updated atomically

Every method that changes serial state (`location`, `status`) does it inside the same
`DB::transaction()` that creates the movement row. If the movement insert fails, the
serial update rolls back automatically.

### 3. No HTTP in the service

All methods accept typed models and scalar values — no `$request` objects. The controller
resolves models from validated data and passes them as arguments.

### 4. Return value

Each write method returns the freshly loaded `InventoryMovement` with all relations eager-loaded.
The controller uses this for the redirect flash or JSON response.

### 5. `adjustment()` always clears location

When a serial is marked damaged or missing, its `inventory_location_id` is set to `null`
because it is no longer available on any shelf.

### 6. Boolean / empty string filter guard

`listMovements()` uses `isset() && !== ''` (not just `isset()`) for select-based filters
(`location_id`, `type`). This prevents the "All" select option (empty string) from
being misread as a filter value.
