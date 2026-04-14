# InventoryLocation Module — Service

**File:** `app/Services/InventoryLocationService.php`

The service contains all business logic. The controller calls the service — it never touches the model directly.

---

## Full Service Code

```php
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
     * @param array{search?: string, status?: string} $filters
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
     * @param array<string, mixed> $data — from StoreInventoryLocationRequest::validated()
     */
    public function store(array $data): InventoryLocation
    {
        return InventoryLocation::create([
            'code'        => strtoupper(trim($data['code'])),
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active'   => true,
        ]);
    }

    /**
     * Update an existing inventory location.
     * Code is NOT updatable after creation — only name and description change.
     *
     * @param array<string, mixed> $data — from UpdateInventoryLocationRequest::validated()
     */
    public function update(InventoryLocation $location, array $data): InventoryLocation
    {
        $location->update([
            'name'        => $data['name'],
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
     * @throws \Illuminate\Validation\ValidationException
     */
    public function deactivate(InventoryLocation $location): void
    {
        // Guard: cannot deactivate if active serials are assigned to this location.
        // Use Schema::hasTable() so this is safe before inventory_serials is migrated.
        $activeSerialCount = Schema::hasTable('inventory_serials')
            ? DB::table('inventory_serials')
                ->where('inventory_location_id', $location->id)
                ->where('status', 'in_stock')
                ->count()
            : 0;

        if ($activeSerialCount > 0) {
            throw ValidationException::withMessages([
                'location' => "Cannot deactivate \"{$location->code}\" — it has {$activeSerialCount} active serial(s) on it. Move or reassign them first.",
            ]);
        }

        $location->update(['is_active' => false]);
        $location->delete(); // sets deleted_at
    }

    /**
     * Restore a soft-deleted location.
     * Re-activates is_active so it appears in dropdowns again.
     */
    public function restore(InventoryLocation $location): InventoryLocation
    {
        $location->restore();           // clears deleted_at
        $location->update(['is_active' => true]);

        return $location->fresh();
    }

    // Used by InventorySerialController and InventoryMovementController create() actions
    // to populate the location dropdown on their create forms.
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
        if (! Schema::hasTable('inventory_serials')) {
            return 0;
        }

        return (int) DB::table('inventory_serials')
            ->where('inventory_location_id', $location->id)
            ->where('status', 'in_stock')
            ->count();
    }
}
```

---

## Method Summary

| Method | Input | Output | Notes |
|--------|-------|--------|-------|
| `list(array $filters)` | `search`, `status` keys | `LengthAwarePaginator` | 20/page, withQueryString |
| `store(array $data)` | validated array | `InventoryLocation` | Code uppercased + trimmed |
| `update(InventoryLocation, array $data)` | model + validated array | `InventoryLocation` (fresh) | Code immutable — only name/description |
| `deactivate(InventoryLocation)` | model | void | Throws `ValidationException` if active serials exist |
| `restore(InventoryLocation)` | soft-deleted model | `InventoryLocation` (fresh) | Restores + re-activates |
| `activeForDropdown()` | — | `Collection` | Active locations ordered by code — for use in other modules |
| `activeSerialCount(InventoryLocation)` | model | int | Safe — returns 0 if table not yet migrated |

---

## Rules

- `store()` always uppercases the `code` — `L1` and `l1` are the same location.
- `update()` intentionally does NOT accept `code` — the code is immutable after creation.
- `deactivate()` checks for active serials using `DB::table()` (not `InventorySerial::`)
  to avoid a hard class dependency before that module is built. When `InventorySerial`
  exists you can optionally refactor to use the model.
- `activeSerialCount()` guards with `Schema::hasTable()` so the show view works even during
  the period before `inventory_serials` is migrated.
- No `DB::transaction()` needed — this module only writes to a single table.
- Data must come pre-validated from controller — never call `$request->all()` inside service.
