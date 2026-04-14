<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InventoryLocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class InventoryLocationService
{
    /**
     * Return a paginated list of locations.
     * Supports optional search (code / name) and active status filter.
     *
     * @param  array{search?: string, status?: string}  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return InventoryLocation::withoutTrashed()
            ->when(
                isset($filters['search']) && $filters['search'] !== '',
                fn ($q) => $q->search($filters['search'])
            )
            ->when(
                isset($filters['status']) && $filters['status'] !== '',
                fn ($q) => $q->byStatus($filters['status'])
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Create a new inventory location.
     *
     * @param  array<string, mixed>  $data  — from StoreInventoryLocationRequest::validated()
     */
    public function store(array $data): InventoryLocation
    {
        return InventoryLocation::create([
            'code' => strtoupper(trim($data['code'])),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * Update an existing inventory location.
     * Code is NOT updatable after creation — only name and description change.
     *
     * @param  array<string, mixed>  $data  — from UpdateInventoryLocationRequest::validated()
     */
    public function update(InventoryLocation $location, array $data): InventoryLocation
    {
        $location->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return $location->fresh();
    }

    /**
     * Deactivate (soft delete) a location.
     *
     * Blocks if the location has active serials on it.
     * Throws ValidationException so the controller can redirect back with an error.
     *
     * @throws ValidationException
     */
    public function deactivate(InventoryLocation $location): void
    {
        // Guard and writes are inside one transaction — prevents TOCTOU where a serial
        // could be assigned between the count check and the soft delete.
        DB::transaction(function () use ($location): void {
            $activeSerialCount = $this->countActiveSerials($location);

            if ($activeSerialCount > 0) {
                throw ValidationException::withMessages([
                    'location' => "Cannot deactivate \"{$location->code}\" — it has {$activeSerialCount} active serial(s) on it. Move or reassign them first.",
                ]);
            }

            $location->update(['is_active' => false]);
            $location->delete(); // sets deleted_at
        });
    }

    /**
     * Restore a soft-deleted location.
     * Re-activates is_active so it appears in dropdowns again.
     */
    public function restore(InventoryLocation $location): InventoryLocation
    {
        DB::transaction(function () use ($location): void {
            $location->restore();           // clears deleted_at
            $location->update(['is_active' => true]);
        });

        return $location->fresh();
    }

    /**
     * Return active locations for use in dropdowns in other modules.
     * Orders by code for consistent display.
     */
    public function activeForDropdown(): Collection
    {
        return InventoryLocation::active()
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }

    /**
     * Return the count of active serials currently on this location.
     * Used by the show view. Returns 0 when inventory_serials table does not exist yet.
     */
    public function activeSerialCount(InventoryLocation $location): int
    {
        return $this->countActiveSerials($location);
    }

    /**
     * Count in-stock serials on this location.
     * Uses Schema::hasTable() guard so it is safe before inventory_serials is migrated.
     */
    private function countActiveSerials(InventoryLocation $location): int
    {
        if (! Schema::hasTable('inventory_serials')) {
            return 0;
        }

        return (int) DB::table('inventory_serials')
            ->where('inventory_location_id', $location->id)
            ->where('status', 'in_stock')
            ->count();
    }
}
