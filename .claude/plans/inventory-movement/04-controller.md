# InventoryMovement Module — Controller

## InventoryMovementController

```php
<?php
// app/Http/Controllers/InventoryMovementController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Http\Requests\Inventory\StoreInventoryMovementRequest;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Services\InventoryLocationService;
use App\Services\InventoryMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryMovementController extends Controller
{
    public function __construct(
        private readonly InventoryMovementService $movements,
        private readonly InventoryLocationService $locationService,
    ) {}

    /**
     * Paginated movement history, with optional filters.
     * Accessible by: admin, manager, sales.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', InventoryMovement::class);

        $filters = $request->only(['serial_number', 'location_id', 'type', 'date_from', 'date_to']);

        $movements  = $this->movements->listMovements($filters);
        $locations  = InventoryLocation::where('is_active', true)->orderBy('code')->get();
        $types      = MovementType::cases();

        return view('inventory.movements.index', compact('movements', 'locations', 'types', 'filters'));
    }

    /**
     * Show the create form for transfer, sale, or adjustment.
     * Pre-fills serial if ?serial_id= is provided in the query string.
     * Accessible by: admin, manager, sales (adjustment restricted to admin/manager by policy).
     */
    public function create(Request $request): View
    {
        $this->authorize('create', InventoryMovement::class);

        $serials   = InventorySerial::with('product')
            ->where('status', 'in_stock')
            ->orderBy('serial_number')
            ->get();

        $locations = $this->locationService->activeForDropdown();
        $types     = MovementType::cases();

        // Optionally pre-select a serial from the query string
        $selectedSerial = $request->filled('serial_id')
            ? InventorySerial::with('product', 'location')->find($request->query('serial_id'))
            : null;

        $selectedType = $request->query('type', 'transfer'); // default to transfer

        return view('inventory.movements.create', compact(
            'serials', 'locations', 'selectedSerial', 'selectedType', 'types'
        ));
    }

    /**
     * Persist the new movement. Delegates to service based on validated type.
     */
    public function store(StoreInventoryMovementRequest $request): RedirectResponse
    {
        $data   = $request->validated();
        $serial = InventorySerial::findOrFail($data['inventory_serial_id']);
        $user   = $request->user();
        $type   = MovementType::from($data['type']);

        try {
            $movement = match ($type) {
                MovementType::Transfer => $this->movements->transfer(
                    serial:       $serial,
                    fromLocation: InventoryLocation::findOrFail($data['from_location_id']),
                    toLocation:   InventoryLocation::findOrFail($data['to_location_id']),
                    user:         $user,
                    reference:    $data['reference'] ?? null,
                    notes:        $data['notes']     ?? null,
                ),

                MovementType::Sale => $this->movements->sale(
                    serial:       $serial,
                    fromLocation: InventoryLocation::findOrFail($data['from_location_id']),
                    user:         $user,
                    reference:    $data['reference'] ?? null,
                    notes:        $data['notes']     ?? null,
                ),

                MovementType::Adjustment => $this->movements->adjustment(
                    serial:         $serial,
                    newStatus:      $data['adjustment_status'],
                    user:           $user,
                    fromLocationId: $data['from_location_id'] ?? null,
                    toLocationId:   $data['to_location_id']   ?? null,
                    reference:      $data['reference']        ?? null,
                    notes:          $data['notes']            ?? null,
                ),

                MovementType::Receive => throw new \DomainException(
                    'Receive movements are created automatically by the serial registration flow.'
                ),
            };
        } catch (\DomainException $e) {
            return back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('inventory-movements.index')
            ->with('success', "Movement recorded for serial '{$movement->serial->serial_number}'.");
    }

    /**
     * All movements for a specific serial — used on the serial show page.
     * Accessible by: admin, manager, sales.
     */
    public function forSerial(InventorySerial $inventorySerial): View
    {
        $this->authorize('viewAny', InventoryMovement::class);

        $movements = $this->movements->historyForSerial($inventorySerial);

        return view('inventory.movements.serial-timeline', compact('inventorySerial', 'movements'));
    }
}
```

---

## Route Definitions (preview — full definition in 08-seeders-routes.md)

```php
// Inside admin route group in routes/web.php:

Route::prefix('inventory-movements')->name('inventory-movements.')->group(function () {

    Route::get('/',        [InventoryMovementController::class, 'index'])
         ->name('index');

    Route::get('/create',  [InventoryMovementController::class, 'create'])
         ->name('create');

    Route::post('/',       [InventoryMovementController::class, 'store'])
         ->name('store');
});

// Serial timeline — nested under serials
Route::get(
    'inventory-serials/{inventorySerial}/movements',
    [InventoryMovementController::class, 'forSerial']
)->name('inventory-serials.movements');
```

---

## Design Notes

### No edit or destroy actions

`InventoryMovement` is immutable. There is no `edit()`, `update()`, or `destroy()` method.
Any attempt to add them must be blocked during code review.

### `match` instead of if/else

The `store()` method uses PHP 8 `match` on the `MovementType` enum to dispatch to the correct
service method. This is exhaustive — adding a new enum case without updating `match` causes a
`\UnhandledMatchError` at runtime, which is the desired behavior (forces the developer to handle
all cases).

### `create()` pre-fills serial

When navigating from the serial show page, passing `?serial_id=X` pre-selects the serial in the
form so the operator doesn't have to search for it again.

### Authorization model

- `viewAny` — all roles (admin, manager, sales)
- `create` — all roles, but the Policy additionally restricts `adjustment` type to admin and manager
  (handled in `StoreInventoryMovementRequest::authorize()` per-type, not controller-level)

### DomainException handling

The controller wraps the service call in `try/catch (\DomainException)` and returns
`back()->withErrors()` so the form re-displays with the error message above the submit button.
