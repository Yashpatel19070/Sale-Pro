<?php

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
     * Receive a new physical unit into the warehouse.
     *
     * Creates the InventorySerial row and an InventoryMovement of type 'receive'
     * atomically. Serial status defaults to in_stock.
     *
     * @param  array{product_id: int, inventory_location_id: int, serial_number: string, purchase_price: numeric-string|float, received_at: string, supplier_name?: string|null, notes?: string|null}  $data
     *
     * @throws \Throwable
     */
    public function receive(array $data, User $receivedBy): InventorySerial
    {
        return DB::transaction(function () use ($data, $receivedBy): InventorySerial {
            $serial = InventorySerial::create([
                'product_id' => $data['product_id'],
                'inventory_location_id' => $data['inventory_location_id'],
                'serial_number' => strtoupper(trim($data['serial_number'])),
                'purchase_price' => $data['purchase_price'],
                'received_at' => $data['received_at'],
                'supplier_name' => $data['supplier_name'] ?? null,
                'received_by_user_id' => $receivedBy->id,
                'status' => SerialStatus::InStock->value,
                'notes' => $data['notes'] ?? null,
            ]);

            InventoryMovement::create([
                'inventory_serial_id' => $serial->id,
                'from_location_id' => null,
                'to_location_id' => $serial->inventory_location_id,
                'type' => MovementType::Receive,
                'purchase_price' => $data['purchase_price'],
                'reference' => $serial->supplier_name,
                'notes' => "Received serial {$serial->serial_number}.",
                'user_id' => $receivedBy->id,
            ]);

            return $serial->load([
                'product:id,sku,name',
                'location:id,code,name',
                'receivedBy:id,name,email',
            ]);
        });
    }

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
                'type' => MovementType::Transfer,
                'from_location_id' => $fromLocation->id,
                'to_location_id' => $toLocation->id,
                'reference' => $reference,
                'notes' => $notes,
                'user_id' => $user->id,
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
                'type' => MovementType::Sale,
                'from_location_id' => $fromLocation->id,
                'to_location_id' => null,
                'reference' => $reference,
                'notes' => $notes,
                'user_id' => $user->id,
            ]);

            $serial->update([
                'inventory_location_id' => null,
                'status' => SerialStatus::Sold,
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

            // No assertSerialInStockAt() here — adjustment deliberately skips the location check.
            // A damaged or missing serial may have an unknown or stale shelf location.
            // We only require in_stock status; location is cleared to null as part of the adjustment.

            $movement = InventoryMovement::create([
                'inventory_serial_id' => $serial->id,
                'type' => MovementType::Adjustment,
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'reference' => $reference,
                'notes' => $notes,
                'user_id' => $user->id,
            ]);

            $serial->update([
                'status' => SerialStatus::from($newStatus),
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
                    fn ($sq) => $sq->where('serial_number', 'like', '%'.$filters['serial_number'].'%')
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
                fn ($q) => $q->where('created_at', '>=', $filters['date_from'].' 00:00:00')
            )
            ->when(
                ! empty($filters['date_to']),
                fn ($q) => $q->where('created_at', '<=', $filters['date_to'].' 23:59:59')
            )
            ->latest()
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * Auto-generate N serial numbers for one SKU and batch-insert them.
     *
     * Serial range reserved atomically via lockForUpdate() on the sequences table.
     * Runs in its own DB::transaction() — never nest inside GRN complete().
     *
     * @param  array{product_id: int, qty: int, inventory_location_id: int, purchase_price: numeric-string|float, source_ref?: string|null}  $data
     * @return Collection<int, InventorySerial>
     *
     * @throws \DomainException
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
            $to = $current + $data['qty'];

            DB::table('sequences')
                ->where('name', 'serial_number')
                ->update(['value' => $to]);

            // 2. Build all rows in memory — zero extra queries in loop
            $year = now()->year;
            $now = now()->toDateTimeString();
            $serialNumbers = [];
            $serialRows = [];

            for ($seq = $from; $seq <= $to; $seq++) {
                $serialNumber = sprintf('SN-%d-%06d', $year, $seq);
                $serialNumbers[] = $serialNumber;
                $serialRows[] = [
                    'product_id' => $data['product_id'],
                    'inventory_location_id' => $data['inventory_location_id'],
                    'serial_number' => $serialNumber,
                    'purchase_price' => $data['purchase_price'],
                    'received_at' => now()->toDateString(),
                    'received_by_user_id' => $receivedBy->id,
                    'status' => SerialStatus::InStock->value,
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // 3. Batch INSERT serials — 1 query regardless of N
            InventorySerial::insert($serialRows);

            // 4. Load created serials to get their IDs — 1 query
            $serials = InventorySerial::with(['product:id,sku,name', 'location:id,code,name'])
                ->whereIn('serial_number', $serialNumbers)
                ->get();

            // 5. Batch INSERT movements — 1 query regardless of N
            $movementRows = $serials->map(fn (InventorySerial $serial) => [
                'inventory_serial_id' => $serial->id,
                'type' => MovementType::Receive->value,
                'from_location_id' => null,
                'to_location_id' => $data['inventory_location_id'],
                'purchase_price' => $data['purchase_price'],
                'reference' => $data['source_ref'] ?? null,
                'notes' => null,
                'user_id' => $receivedBy->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray();

            InventoryMovement::insert($movementRows);

            return $serials;
        });
    }
}
