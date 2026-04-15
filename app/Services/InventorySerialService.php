<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventorySerial;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InventorySerialService
{
    /**
     * Paginated list of serials with optional filters.
     *
     * @param  array{search?: string, status?: string, product_id?: int|string, location_id?: int|string}  $filters
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
     * Update mutable fields: notes and supplier_name only.
     * serial_number and purchase_price are intentionally excluded.
     *
     * @param  array{notes?: string|null, supplier_name?: string|null}  $data
     */
    public function updateNotes(InventorySerial $serial, array $data): InventorySerial
    {
        $serial->update([
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $serial->notes,
            'supplier_name' => array_key_exists('supplier_name', $data) ? $data['supplier_name'] : $serial->supplier_name,
        ]);

        return $serial->fresh(['product', 'location']);
    }

    /**
     * Find a single serial by its serial_number string (case-sensitive, stored uppercase).
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
            ->where('serial_number', strtoupper($serialNumber))
            ->first();
    }
}
