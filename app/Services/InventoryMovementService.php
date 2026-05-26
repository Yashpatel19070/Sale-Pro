<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SerialStatus;
use App\Models\GoodsReceipt;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\Order;
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
     * Record a sale movement initiated by an order — serial leaves the warehouse.
     * Locks the serial row before status check to prevent race conditions.
     *
     * @throws \DomainException
     */
    public function recordSale(int $serialId, Order $order, User $by, ?string $notes = null): InventoryMovement
    {
        return DB::transaction(function () use ($serialId, $order, $by, $notes): InventoryMovement {
            $serial = InventorySerial::lockForUpdate()->findOrFail($serialId);

            if ($serial->status !== SerialStatus::InStock) {
                throw new \DomainException(
                    "Serial '{$serial->serial_number}' is not in stock (current status: {$serial->status->value})."
                );
            }

            $movement = InventoryMovement::create([
                'inventory_serial_id' => $serial->id,
                'type' => MovementType::Sale,
                'from_location_id' => $serial->inventory_location_id,
                'to_location_id' => null,
                'reference' => $order->number,
                'notes' => $notes,
                'user_id' => $by->id,
            ]);

            $serial->update([
                'inventory_location_id' => null,
                'status' => SerialStatus::Sold,
            ]);

            return $movement;
        });
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
            // lockForUpdate — atomic reservation, no concurrent duplicate range
            $current = DB::table('sequences')
                ->where('name', 'serial_number')
                ->lockForUpdate()
                ->value('value');

            $from = $current + 1;
            $to = $current + $data['qty'];

            DB::table('sequences')
                ->where('name', 'serial_number')
                ->update(['value' => $to]);

            $now = now();
            $nowString = $now->toDateTimeString();
            $today = $now->toDateString();
            $serialNumbers = [];
            $serialRows = [];

            for ($seq = $from; $seq <= $to; $seq++) {
                $serialNumber = sprintf('SN-%d-%06d', $now->year, $seq);
                $serialNumbers[] = $serialNumber;
                $serialRows[] = [
                    'product_id' => $data['product_id'],
                    'inventory_location_id' => $data['inventory_location_id'],
                    'serial_number' => $serialNumber,
                    'purchase_price' => $data['purchase_price'],
                    'supplier_name' => $data['supplier_name'] ?? null,
                    'received_at' => $today,
                    'received_by_user_id' => $receivedBy->id,
                    'status' => SerialStatus::InStock->value,
                    'notes' => null,
                    'created_at' => $nowString,
                    'updated_at' => $nowString,
                ];
            }

            InventorySerial::insert($serialRows);

            $serials = InventorySerial::whereIn('serial_number', $serialNumbers)
                ->get(['id', 'serial_number']);

            $movementRows = $serials->map(fn (InventorySerial $serial) => [
                'inventory_serial_id' => $serial->id,
                'type' => MovementType::Receive->value,
                'from_location_id' => null,
                'to_location_id' => $data['inventory_location_id'],
                'purchase_price' => $data['purchase_price'],
                'reference' => $data['source_ref'] ?? null,
                'notes' => null,
                'user_id' => $receivedBy->id,
                'goods_receipt_id' => $data['goods_receipt_id'] ?? null,
                'created_at' => $nowString,
                'updated_at' => $nowString,
            ])->toArray();

            InventoryMovement::insert($movementRows);

            return $serials;
        });
    }

    /**
     * Generate serials for every QC-passed unit in a completed GRN.
     * goods_receipt_id is always $grn->id — never null, never from HTTP input.
     *
     * @param  array{lines: array<int, array{goods_receipt_line_id: int, inventory_location_id: int, purchase_price: numeric-string|float}>}  $data
     * @return Collection<int, InventorySerial>
     *
     * @throws \DomainException
     */
    public function bulkReceiveFromGrn(GoodsReceipt $grn, array $data, User $user): Collection
    {
        return DB::transaction(function () use ($grn, $data, $user): Collection {
            $grn->refresh();
            $grn->load(['lines.purchaseOrderLine.product', 'purchaseOrder']);

            throw_if(
                InventoryMovement::where('goods_receipt_id', $grn->id)->exists(),
                \DomainException::class,
                'Serials have already been assigned for this goods receipt.'
            );

            $anyNotInspected = $grn->lines->some(fn ($l) => $l->qty_passed === null);
            throw_if(
                $anyNotInspected,
                \DomainException::class,
                'QC has not been submitted for all lines on this goods receipt.'
            );

            $allowedPoStatuses = [
                PurchaseOrderStatus::PartiallyReceived,
                PurchaseOrderStatus::Received,
            ];
            throw_if(
                ! in_array($grn->purchaseOrder->status, $allowedPoStatuses, true),
                \DomainException::class,
                'Serial assignment requires the purchase order to be in received or partially received status.'
            );

            $allSerials = new Collection;

            foreach ($data['lines'] as $lineData) {
                $grnLine = $grn->lines->firstWhere('id', $lineData['goods_receipt_line_id']);

                throw_if(
                    ! $grnLine,
                    \DomainException::class,
                    "Goods receipt line {$lineData['goods_receipt_line_id']} not found on this GRN."
                );

                $qty = $grnLine->qcPassed();

                if ($qty === 0) {
                    continue;
                }

                $serials = $this->bulkReceive([
                    'product_id' => $grnLine->purchaseOrderLine->product_id,
                    'qty' => $qty,
                    'inventory_location_id' => $lineData['inventory_location_id'],
                    'purchase_price' => $lineData['purchase_price'],
                    'source_ref' => $grn->grn_number,
                    'goods_receipt_id' => $grn->id,
                    'supplier_name' => $grn->purchaseOrder->supplier->name,
                ], $user);

                $allSerials = $allSerials->concat($serials);
            }

            if ($allSerials->isNotEmpty()) {
                activity()
                    ->causedBy($user)
                    ->performedOn($grn)
                    ->withProperties([
                        'grn_number' => $grn->grn_number,
                        'serials_generated' => $allSerials->count(),
                        'serial_numbers' => $allSerials->pluck('serial_number')->all(),
                    ])
                    ->log('serials_generated');
            }

            return $allSerials;
        });
    }
}
