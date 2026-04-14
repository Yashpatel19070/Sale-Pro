# InventoryLocation Module — Controller

**File:** `app/Http/Controllers/InventoryLocationController.php`

---

## Full Controller Code

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\StoreInventoryLocationRequest;
use App\Http\Requests\Inventory\UpdateInventoryLocationRequest;
use App\Models\InventoryLocation;
use App\Services\InventoryLocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryLocationController extends Controller
{
    public function __construct(private readonly InventoryLocationService $service) {}

    /**
     * GET /admin/inventory-locations
     * Paginated list with search + active/inactive filter.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', InventoryLocation::class);

        $locations = $this->service->list(
            $request->only(['search', 'status'])
        );

        return view('inventory.locations.index', [
            'locations' => $locations,
            'filters'   => $request->only(['search', 'status']),
        ]);
    }

    /**
     * GET /admin/inventory-locations/{inventoryLocation}
     * Show a single location with active serial count.
     */
    public function show(InventoryLocation $inventoryLocation): View
    {
        $this->authorize('view', $inventoryLocation);

        $activeSerialCount = $this->service->activeSerialCount($inventoryLocation);

        return view('inventory.locations.show', [
            'location'          => $inventoryLocation,
            'activeSerialCount' => $activeSerialCount,
        ]);
    }

    /**
     * GET /admin/inventory-locations/create
     * Show the create form.
     */
    public function create(): View
    {
        $this->authorize('create', InventoryLocation::class);

        return view('inventory.locations.create');
    }

    /**
     * POST /admin/inventory-locations
     * Store a new location.
     */
    public function store(StoreInventoryLocationRequest $request): RedirectResponse
    {
        $this->authorize('create', InventoryLocation::class);

        $location = $this->service->store($request->validated());

        return redirect()
            ->route('inventory-locations.show', $location)
            ->with('success', "Location \"{$location->code}\" created successfully.");
    }

    /**
     * GET /admin/inventory-locations/{inventoryLocation}/edit
     * Show the edit form.
     */
    public function edit(InventoryLocation $inventoryLocation): View
    {
        $this->authorize('update', $inventoryLocation);

        return view('inventory.locations.edit', [
            'location' => $inventoryLocation,
        ]);
    }

    /**
     * PUT /admin/inventory-locations/{inventoryLocation}
     * Update name and description.
     */
    public function update(
        UpdateInventoryLocationRequest $request,
        InventoryLocation $inventoryLocation,
    ): RedirectResponse {
        $this->authorize('update', $inventoryLocation);

        $this->service->update($inventoryLocation, $request->validated());

        return redirect()
            ->route('inventory-locations.show', $inventoryLocation)
            ->with('success', "Location \"{$inventoryLocation->code}\" updated successfully.");
    }

    /**
     * DELETE /admin/inventory-locations/{inventoryLocation}
     * Deactivate (soft delete) a location.
     * Blocked if the location has active serials on it.
     */
    public function destroy(InventoryLocation $inventoryLocation): RedirectResponse
    {
        $this->authorize('delete', $inventoryLocation);

        try {
            $this->service->deactivate($inventoryLocation);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('inventory-locations.index')
            ->with('success', "Location \"{$inventoryLocation->code}\" deactivated.");
    }

    /**
     * POST /admin/inventory-locations/{id}/restore
     * Restore a soft-deleted location.
     * Route model binding resolves trashed models via withTrashed() in route definition.
     */
    public function restore(int $id): RedirectResponse
    {
        $location = InventoryLocation::withTrashed()->findOrFail($id);

        $this->authorize('restore', $location);

        $this->service->restore($location);

        return redirect()
            ->route('inventory-locations.show', $location)
            ->with('success', "Location \"{$location->code}\" restored.");
    }
}
```

---

## Action Summary

| Method | HTTP | URL | Policy Check | Service Call |
|--------|------|-----|-------------|--------------|
| `index` | GET | `/admin/inventory-locations` | `viewAny` | `list()` |
| `show` | GET | `/admin/inventory-locations/{inventoryLocation}` | `view` | `activeSerialCount()` |
| `create` | GET | `/admin/inventory-locations/create` | `create` | — |
| `store` | POST | `/admin/inventory-locations` | `create` | `store()` |
| `edit` | GET | `/admin/inventory-locations/{inventoryLocation}/edit` | `update` | — |
| `update` | PUT | `/admin/inventory-locations/{inventoryLocation}` | `update` | `update()` |
| `destroy` | DELETE | `/admin/inventory-locations/{inventoryLocation}` | `delete` | `deactivate()` |
| `restore` | POST | `/admin/inventory-locations/{id}/restore` | `restore` | `restore()` |

---

## Rules

- Every action calls `$this->authorize()` — no exceptions.
- `store()` and `update()` accept typed FormRequests — validated data only.
- Route model binding handles `{inventoryLocation}` — no manual `InventoryLocation::find()` in controller.
- `destroy()` catches `ValidationException` from service and redirects back with errors — no unhandled exceptions.
- `restore()` receives a plain `int $id` and resolves the trashed model manually via `withTrashed()->findOrFail()`.
  This is required because standard route model binding ignores soft-deleted records.
- Flash messages: `with('success', '...')` for all successful operations.
- Redirect after write: `store` → show, `update` → show, `destroy` → index, `restore` → show.
