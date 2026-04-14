# InventorySerial — Controller

## InventorySerialController.php

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SerialStatus;
use App\Http\Requests\InventorySerial\StoreInventorySerialRequest;
use App\Http\Requests\InventorySerial\UpdateInventorySerialRequest;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Services\InventoryLocationService;
use App\Services\InventorySerialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventorySerialController extends Controller
{
    public function __construct(
        private readonly InventorySerialService $service,
        private readonly InventoryLocationService $locationService,
    ) {}

    // ── List ───────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $this->authorize('viewAny', InventorySerial::class);

        $serials   = $this->service->list($request->only(['search', 'status', 'product_id', 'location_id']));
        $statuses  = SerialStatus::options();
        $products  = Product::active()->orderBy('name')->select(['id', 'sku', 'name'])->get();
        $locations = $this->locationService->activeForDropdown();

        return view('inventory.serials.index', compact('serials', 'statuses', 'products', 'locations'));
    }

    // ── Show ───────────────────────────────────────────────────────────────────

    public function show(InventorySerial $inventorySerial): View
    {
        $this->authorize('view', $inventorySerial);

        $inventorySerial->load([
            'product:id,sku,name,regular_price',
            'location:id,code,name',
            'receivedBy:id,name,email',
        ]);

        // Movements are loaded as a separate paginated query — NOT eager-loaded on the model.
        // This prevents unbounded collection sizes on serials with long movement history.
        $movements = $inventorySerial->movements()
            ->select(['id', 'inventory_serial_id', 'from_location_id', 'to_location_id', 'type', 'notes', 'user_id', 'created_at'])
            ->with(['fromLocation:id,code,name', 'toLocation:id,code,name', 'user:id,name'])
            ->latest()
            ->paginate(15);

        return view('inventory.serials.show', [
            'serial'    => $inventorySerial,
            'movements' => $movements,
        ]);
    }

    // ── Create / Store ─────────────────────────────────────────────────────────

    public function create(): View
    {
        $this->authorize('create', InventorySerial::class);

        $products  = Product::active()->orderBy('name')->select(['id', 'sku', 'name'])->get();
        $locations = $this->locationService->activeForDropdown();

        return view('inventory.serials.create', compact('products', 'locations'));
    }

    public function store(StoreInventorySerialRequest $request): RedirectResponse
    {
        $this->authorize('create', InventorySerial::class);

        $serial = $this->service->receive($request->validated(), $request->user());

        return redirect()
            ->route('inventory-serials.show', $serial)
            ->with('success', "Serial \"{$serial->serial_number}\" received successfully.");
    }

    // ── Edit / Update ──────────────────────────────────────────────────────────

    public function edit(InventorySerial $inventorySerial): View
    {
        $this->authorize('update', $inventorySerial);

        $inventorySerial->load(['product:id,sku,name', 'location:id,code,name']);

        return view('inventory.serials.edit', ['serial' => $inventorySerial]);
    }

    public function update(UpdateInventorySerialRequest $request, InventorySerial $inventorySerial): RedirectResponse
    {
        $this->authorize('update', $inventorySerial);

        $this->service->updateNotes($inventorySerial, $request->validated());

        return redirect()
            ->route('inventory-serials.show', $inventorySerial)
            ->with('success', 'Serial updated.');
    }

    // NOTE: markDamaged and markMissing actions are NOT on this controller.
    // Status changes are handled by InventoryMovementService::adjustment().
    // On the show page, link to the movement create form with type pre-selected:
    //   route('inventory-movements.create', ['serial' => $serial->id, 'type' => 'adjustment'])
}
```

**File path:** `app/Http/Controllers/InventorySerialController.php`

---

## Design Notes

### Route Model Binding
Laravel will bind `{inventorySerial}` in route parameters to the `InventorySerial` model
automatically. The camelCase form matches the route parameter name — ensure routes use
`{inventorySerial}` (not `{serial}` or `{inventory_serial}`).

### markDamaged / markMissing — Removed
These actions were removed because they update serial status WITHOUT creating a movement row,
which violates the "every change = one movement row" rule. Status changes are handled by
InventoryMovementService::adjustment(). The serial show page links to the movement create form
with type=adjustment pre-selected.

### authorize() called twice on store/update
The policy check appears in both the form action (`create()`/`edit()`) and the write action
(`store()`/`update()`). This is intentional — the write action must re-authorize in case
the user's permissions changed between loading the form and submitting it.

### No destroy() action
Serials are not deleted via the UI in V1. If a soft-delete flow is needed in the future,
add it with an explicit policy gate (`delete`).

### Dropdown Query Pattern
`Product::active()->orderBy('name')->select(['id', 'sku', 'name'])->get()` follows the
`scopeForDropdown` pattern from the Product model. A dedicated `scopeForDropdown` could
be added to keep controllers thin, but the explicit chain is clear enough here.

### InventoryLocationService — activeForDropdown()
`InventoryLocationService` is injected in the constructor alongside `InventorySerialService`.
Both `index()` and `create()` call `$this->locationService->activeForDropdown()` to get
active locations for filter dropdowns and the receive form. This keeps the query logic
in the service layer rather than the controller.
