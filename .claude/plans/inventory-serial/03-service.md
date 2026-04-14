# InventorySerial — Service

## InventorySerialService.php

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SerialStatus;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InventorySerialService
{
    /**
     * Paginated list of serials with optional filters.
     *
     * @param  array{
     *     search?: string,
     *     status?: string,
     *     product_id?: int|string,
     *     location_id?: int|string,
     * }  $filters
     */
    public function list(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return InventorySerial::with([
            'product:id,sku,name',
            'location:id,code,name',
        ])
            ->when(
                isset($filters['search']) && $filters['search'] !== '',
                fn ($q) => $q->search($filters['search'])
            )
            ->when(
                isset($filters['status']) && $filters['status'] !== '',
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when(
                isset($filters['product_id']) && $filters['product_id'] !== '',
                fn ($q) => $q->where('product_id', (int) $filters['product_id'])
            )
            ->when(
                isset($filters['location_id']) && $filters['location_id'] !== '',
                fn ($q) => $q->where('inventory_location_id', (int) $filters['location_id'])
            )
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Receive a new physical unit into the warehouse.
     *
     * Creates the InventorySerial row and an InventoryMovement of type 'receive'
     * inside a single DB transaction.
     *
     * @param  array{
     *     product_id: int,
     *     inventory_location_id: int,
     *     serial_number: string,
     *     purchase_price: numeric-string|float,
     *     received_at: string,
     *     supplier_name?: string|null,
     *     notes?: string|null,
     * }  $data
     * @param  User  $receivedBy  The authenticated user logging the receipt.
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

            // Create the initial receive movement so the ledger stays consistent.
            // Note: product_id is intentionally omitted — the movement table has no product_id column.
            // Product is accessible via inventory_serial_id → inventory_serials.product_id.
            InventoryMovement::create([
                'inventory_serial_id'    => $serial->id,
                'from_location_id'       => null,                          // external (supplier)
                'to_location_id'         => $serial->inventory_location_id,
                'type'                   => 'receive',
                'quantity'               => 1,
                'reference'              => $serial->supplier_name,
                'notes'                  => "Received serial {$serial->serial_number}.",
                'user_id'                => $receivedBy->id,
            ]);

            return $serial->load([
                'product:id,sku,name',
                'location:id,code,name',
                'receivedBy:id,name,email',
            ]);
        });
    }

    /**
     * Update mutable fields: notes and supplier_name only.
     * serial_number and purchase_price are intentionally excluded.
     *
     * @param  array{notes?: string|null, supplier_name?: string|null}  $data
     */
    public function updateNotes(InventorySerial $serial, array $data): InventorySerial
    {
        $serial->update([
            'notes'         => $data['notes'] ?? null,
            'supplier_name' => $data['supplier_name'] ?? $serial->supplier_name,
        ]);
        return $serial->fresh(['product', 'location']);
    }

    // NOTE: Marking a serial as damaged or missing is handled by InventoryMovementService::adjustment()
    // The serial show page links to the movement create form with type=adjustment pre-selected.
    // Do NOT add markDamaged/markMissing here — all status changes must create a movement row.

    /**
     * Find a single serial by its serial_number string (case-insensitive).
     * Returns null if not found.
     */
    public function findBySerial(string $serialNumber): ?InventorySerial
    {
        return InventorySerial::with([
            'product:id,sku,name',
            'location:id,code,name',
            'receivedBy:id,name,email',
            'movements' => fn ($q) => $q->orderByDesc('created_at')->limit(20),
        ])
            ->where('serial_number', $serialNumber)
            ->first();
    }
}
```

**File path:** `app/Services/InventorySerialService.php`

---

## Design Notes

### updateNotes — Simple Update
`updateNotes` uses a direct `update()` call restricted to `notes` and `supplier_name`.
Immutability of `serial_number` and `purchase_price` is enforced by `UpdateInventorySerialRequest`
which excludes those fields from `validated()` — they never reach the service.

### receive — Transaction Guarantee
`receive()` creates both the `InventorySerial` and the `InventoryMovement` atomically.
If the movement insert fails (e.g. FK constraint), the serial row is also rolled back.
This keeps the ledger consistent with serial counts.

### markDamaged / markMissing — Delegated to Movement Module
Status changes (damaged, missing) are NOT handled here. They must go through
`InventoryMovementService::adjustment()` so that every status change creates a movement row.
The serial show page links to the inventory-movement create form with `type=adjustment` pre-selected.

### findBySerial — Eager Movements
Movements are limited to 20 most recent to avoid loading unbounded history on lookup.
The show controller may extend this further if full history is needed.
