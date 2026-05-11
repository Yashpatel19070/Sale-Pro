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
     * @throws \DomainException
     */
    public function transfer(
        InventorySerial $serial,
        InventoryLocation $fromLocation,
        InventoryLocation $toLocation,
        User $user,
        ?string $reference = null,
        ?string $notes = null,
    ): InventoryMovement {
        $movement = DB::transaction(function () use (
            $serial, $fromLocation, $toLocation, $user, $reference, $notes
        ): InventoryMovement {
            // TOCTOU: refresh inside transaction so status+location checks are atomic with the write
            $serial->refresh();

            $this->assertSerialInStockAt($serial, $fromLocation);

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
                'reference'           => $reference,
                'notes'               => $notes,
                'user_id'             => $user->id,
            ]);

            $serial->update(['inventory_location_id' => $toLocation->id]);

            return $movement;
        });

        return $movement->load(['serial.product', 'fromLocation', 'toLocation', 'user']);
    }

    /**
     * Record a sale movement — serial leaves the warehouse.
     *
     * @throws \DomainException
     */
    public function sale(
        InventorySerial $serial,
        InventoryLocation $fromLocation,
        User $user,
        ?string $reference = null,
        ?string $notes = null,
    ): InventoryMovement {
        $movement = DB::transaction(function () use (
            $serial, $fromLocation, $user, $reference, $notes
        ): InventoryMovement {
            $serial->refresh();

            $this->assertSerialInStockAt($serial, $fromLocation);

            $movement = InventoryMovement::create([
                'inventory_serial_id' => $serial->id,
                'type'                => MovementType::Sale,
                'from_location_id'    => $fromLocation->id,
                'to_location_id'      => null,
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
     * Adjustment has no from_location: the serial is removed from its shelf regardless
     * of which shelf it was on. Location consistency is not checked here by design.
     *
     * @throws \DomainException
     */
    public function adjustment(
        InventorySerial $serial,
        string $newStatus,
        User $user,
        ?int $fromLocationId = null,
        ?int $toLocationId = null,
        ?string $reference = null,
        ?string $notes = null,
    ): InventoryMovement {
        $allowedStatuses = [SerialStatus::Damaged->value, SerialStatus::Missing->value];

        throw_if(
            ! in_array($newStatus, $allowedStatuses, true),
            \DomainException::class,
            'Adjustment status must be one of: '.implode(', ', $allowedStatuses).'.'
        );

        $movement = DB::transaction(function () use (
            $serial, $newStatus, $user, $fromLocationId, $toLocationId, $reference, $notes
        ): InventoryMovement {
            // TOCTOU: refresh inside transaction so status check is atomic with the write
            $serial->refresh();

            throw_if(
                $serial->status !== SerialStatus::InStock,
                \DomainException::class,
                "Only in_stock serials can be adjusted. Current status: {$serial->status->value}."
            );

            $movement = InventoryMovement::create([
                'inventory_serial_id' => $serial->id,
                'type'                => MovementType::Adjustment,
                'from_location_id'    => $fromLocationId,
                'to_location_id'      => $toLocationId,
                'reference'           => $reference,
                'notes'               => $notes,
                'user_id'             => $user->id,
            ]);

            $serial->update([
                'status'                => SerialStatus::from($newStatus),
                'inventory_location_id' => null,
            ]);

            return $movement;
        });

        return $movement->load(['serial.product', 'fromLocation', 'toLocation', 'user']);
    }

    /**
     * Assert a serial is in_stock and physically at the given location.
     * Called inside a DB::transaction after $serial->refresh() for TOCTOU safety.
     *
     * @throws \DomainException
     */
    private function assertSerialInStockAt(InventorySerial $serial, InventoryLocation $fromLocation): void
    {
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
     * Raw when() chains are used intentionally instead of model scopes.
     * scopeAtLocation() requires an InventoryLocation model; scopeOfType() requires
     * a MovementType enum. Loading those models from raw filter IDs/strings would
     * add extra queries per request. historyForSerial() can use scopeForSerial()
     * because it already holds the model object.
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

## receive() — Moved from InventorySerialService

```php
/**
 * Receive a new physical unit into the warehouse.
 *
 * Creates the InventorySerial row and an InventoryMovement of type 'receive'
 * inside a single DB transaction. Serial status defaults to in_stock.
 *
 * @param  array{product_id: int, inventory_location_id: int, serial_number: string,
 *               purchase_price: numeric-string|float, received_at: string,
 *               supplier_name?: string|null, notes?: string|null}  $data
 * @param  User  $receivedBy
 */
public function receive(array $data, User $receivedBy): InventorySerial
{
    return DB::transaction(function () use ($data, $receivedBy): InventorySerial {
        $serial = InventorySerial::create([
            'product_id'            => $data['product_id'],
            'inventory_location_id' => $data['inventory_location_id'],
            'serial_number'         => $data['serial_number'],
            'purchase_price'        => $data['purchase_price'],
            'received_at'           => $data['received_at'],
            'supplier_name'         => $data['supplier_name'] ?? null,
            'received_by_user_id'   => $receivedBy->id,
            'status'                => SerialStatus::InStock->value,
            'notes'                 => $data['notes'] ?? null,
        ]);

        InventoryMovement::create([
            'inventory_serial_id' => $serial->id,
            'from_location_id'    => null,
            'to_location_id'      => $serial->inventory_location_id,
            'type'                => MovementType::Receive,
            'purchase_price'      => $data['purchase_price'],
            'reference'           => $serial->supplier_name,
            'notes'               => "Received serial {$serial->serial_number}.",
            'user_id'             => $receivedBy->id,
        ]);

        return $serial->load([
            'product:id,sku,name',
            'location:id,code,name',
            'receivedBy:id,name,email',
        ]);
    });
}
```

---

## bulkReceiveFromGrn() — QC-Triggered Serial Generation

**Single source of truth:** all serial generation lives in `InventoryMovementService`.
`bulkReceiveFromGrn()` is the QC-flow entry point; it delegates to `bulkReceive()` internally.

```php
/**
 * Generate serials for a completed, QC-inspected GRN.
 * goods_receipt_id is always $grn->id — never null, never from request.
 *
 * @param  GoodsReceipt  $grn   route-bound model, already loaded with lines.purchaseOrderLine.product
 * @param  array{
 *     lines: array<int, array{
 *         goods_receipt_line_id: int,
 *         inventory_location_id: int,
 *         purchase_price: numeric-string|float,
 *     }>
 * }  $data   from StoreGrnSerialRequest — no qty, no product_id, no grn_id
 * @param  User  $user
 * @return \Illuminate\Database\Eloquent\Collection<int, InventorySerial>
 * @throws \DomainException  if QC not submitted, PO status invalid, or line not found
 */
public function bulkReceiveFromGrn(GoodsReceipt $grn, array $data, User $user): Collection
```

#### Rules
- Wraps entirely in `DB::transaction()` — guards inside (TOCTOU rule)
- Inside transaction (order matters):
  1. Guard: throw `DomainException` if `InventoryMovement::where('goods_receipt_id', $grn->id)->exists()` — prevents double assignment. Uses existing FK column, no schema change needed.
  2. Guard: throw `DomainException` if any GRN line has `qty_passed IS NULL` (QC not submitted)
  3. Guard: throw `DomainException` if PO status not in `[partially_received, received]`
  - For each `$data['lines']`:
    - Loads `GoodsReceiptLine` by `goods_receipt_line_id` from `$grn->lines`
    - Reads `qty = $grnLine->qty_passed` — NOT from request (immutable at QC time)
    - Skips lines where `qty_passed === 0`
    - Calls `$this->bulkReceive([..., 'goods_receipt_id' => $grn->id], $user)`
    - `goods_receipt_id` is always `$grn->id` — set here, never from HTTP input
- Returns flat `Collection` of all generated `InventorySerial` records across all lines
- **Edge case:** if ALL lines have `qty_passed = 0`, no movements are created — double-assignment guard will not block a retry (correct behaviour: nothing was assigned)

---

## bulkReceive() — Batch Serial Auto-Generation

```php
/**
 * Auto-generate N serial numbers for one SKU and batch-insert them.
 *
 * Runs in its own DB::transaction() — never nest inside GRN complete().
 * Serial range reserved atomically via lockForUpdate() on sequences table.
 *
 * @param  array{
 *     product_id: int,
 *     qty: int,
 *     inventory_location_id: int,
 *     purchase_price: numeric-string|float,
 *     source_ref?: string|null,
 *     goods_receipt_id?: int|null,  // set by GoodsReceiptService::bulkReceiveFromGrn() only — never from HTTP request
 * }  $data
 * @param  User  $receivedBy
 * @return \Illuminate\Database\Eloquent\Collection<int, InventorySerial>
 * @throws \DomainException  if qty < 1 or qty > 500
 */
public function bulkReceive(array $data, User $receivedBy): Collection
{
    throw_if(
        $data['qty'] < 1 || $data['qty'] > 500,
        \DomainException::class,
        'Quantity must be between 1 and 500.'
    );

    return DB::transaction(function () use ($data, $receivedBy): Collection {
        // 1. Reserve N sequence numbers atomically — no race condition
        $current = DB::table('sequences')
            ->where('name', 'serial_number')
            ->lockForUpdate()
            ->value('value');

        $from = $current + 1;
        $to   = $current + $data['qty'];

        DB::table('sequences')
            ->where('name', 'serial_number')
            ->update(['value' => $to]);

        // 2. Build all rows in memory — zero extra queries in loop
        $year          = now()->year;
        $now           = now()->toDateTimeString();
        $serialNumbers = [];
        $serialRows    = [];

        for ($seq = $from; $seq <= $to; $seq++) {
            $serialNumber    = sprintf('SN-%d-%06d', $year, $seq);
            $serialNumbers[] = $serialNumber;
            $serialRows[]    = [
                'product_id'            => $data['product_id'],
                'inventory_location_id' => $data['inventory_location_id'],
                'serial_number'         => $serialNumber,
                'purchase_price'        => $data['purchase_price'],
                'received_at'           => now()->toDateString(),
                'received_by_user_id'   => $receivedBy->id,
                'status'                => SerialStatus::InStock->value,
                'notes'                 => null,
                'created_at'            => $now,
                'updated_at'            => $now,
            ];
        }

        // 3. Batch INSERT serials — 1 query regardless of N
        InventorySerial::insert($serialRows);

        // 4. Load created serials to get their IDs — 1 query
        $serials = InventorySerial::with(['product:id,sku,name', 'location:id,code,name'])
            ->whereIn('serial_number', $serialNumbers)
            ->get();

        // 5. Batch INSERT movements — 1 query regardless of N
        // goods_receipt_id: null for standalone receives; set by InventoryMovementService::bulkReceiveFromGrn()
        $movementRows = $serials->map(fn (InventorySerial $serial) => [
            'inventory_serial_id' => $serial->id,
            'type'                => MovementType::Receive->value,
            'from_location_id'    => null,
            'to_location_id'      => $data['inventory_location_id'],
            'purchase_price'      => $data['purchase_price'],
            'reference'           => $data['source_ref'] ?? null,
            'notes'               => null,
            'user_id'             => $receivedBy->id,
            'goods_receipt_id'    => $data['goods_receipt_id'] ?? null,
            'created_at'          => $now,
            'updated_at'          => $now,
        ])->toArray();

        InventoryMovement::insert($movementRows);

        return $serials;
    });
}
```

**Total DB queries: 5 regardless of N units**
```
1 × lockForUpdate + read  (sequences)
1 × UPDATE sequences
1 × INSERT inventory_serials  (N rows, 1 query)
1 × SELECT inventory_serials WHERE IN (load IDs)
1 × INSERT inventory_movements  (N rows, 1 query)
```

**Serial number format:** `SN-{YEAR}-{6-digit-zero-padded-seq}` e.g. `SN-2026-000042`

**Sequence never resets per year** — global counter prevents cross-year collisions.

**source_ref** links batch back to GRN number for traceability (optional).

---

**Why here and not in InventorySerialService:**
- `receive()` always creates an `InventoryMovement` row — it IS a movement operation
- All stock mutations (`receive`, `transfer`, `sale`, `adjustment`) owned by one service
- Future `OrderService` / `POSService` only needs to inject `InventoryMovementService`
- Removes the `InventoryMovement::TYPE_RECEIVE` string constant (replaced by `MovementType::Receive` enum)

**Controller impact:** `InventorySerialController::store()` injects `InventoryMovementService`
(in addition to keeping `InventorySerialService` for `list()`, `findBySerial()`, `updateNotes()`).

---

## Critical Rules

### 1. TOCTOU — ALL guards are inside the transaction

`$serial->refresh()` re-reads the row after the transaction lock is acquired, ensuring
the status check and write are atomic. Safe for single-warehouse use. This applies to
**all three write methods** — `transfer()`, `sale()`, and `adjustment()`.

```
// ❌ Wrong — check outside transaction, another request can change status in the gap
throw_if($serial->status !== SerialStatus::InStock, ...);
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
because it is no longer available on any shelf. Adjustment deliberately skips location
consistency checking — the serial is removed from its shelf regardless of which shelf it
was on, so no `from_location` is required or verified.

### 6. Shared guard extracted into `assertSerialInStockAt()`

`transfer()` and `sale()` share identical InStock + location checks. These are extracted
into a private `assertSerialInStockAt(InventorySerial, InventoryLocation): void` method
called inside the transaction after `$serial->refresh()`.

### 7. `$allowedStatuses` uses enum values, not raw strings

```php
$allowedStatuses = [SerialStatus::Damaged->value, SerialStatus::Missing->value];
```

This keeps the guard in sync with the `SerialStatus` enum automatically.

### 10. No double assignment — `bulkReceiveFromGrn()` is idempotent-blocked

`bulkReceiveFromGrn()` checks `InventoryMovement::where('goods_receipt_id', $grn->id)->exists()` inside the transaction
before creating any serials. If movements already exist for this GRN, it throws `DomainException`.

- No schema change — uses the existing `goods_receipt_id` FK on `inventory_movements`
- Guard is INSIDE the transaction for TOCTOU safety (concurrent duplicate requests blocked)
- Exception: if ALL lines have `qty_passed = 0`, no movements are written — retry is allowed

The controller (`GoodsReceiptController::assignSerials`) mirrors this check as a UI gate:
redirects to GRN show with an error if movements already exist, preventing the form from rendering.

---

### 8. `historyForSerial()` is intentionally unpaginated

`historyForSerial()` returns a flat `Collection`, not a `LengthAwarePaginator`. This is by design:
- The serial timeline is a secondary detail view, not a primary list
- A single serial rarely has more than a few dozen movements in its lifetime
- Pagination adds complexity (view links, controller query changes) for minimal gain at this scale

If a serial ever accumulates hundreds of movements, add `->paginate(25)` at that point.

### 9. Boolean / empty string filter guard

`listMovements()` uses `isset() && !== ''` (not just `isset()`) for select-based filters
(`location_id`, `type`). This prevents the "All" select option (empty string) from
being misread as a filter value.
