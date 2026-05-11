# InventoryMovement Module — Controller

## InventoryMovementController

```php
<?php
// app/Http/Controllers/InventoryMovementController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Enums\SerialStatus;
use App\Http\Requests\Inventory\StoreBulkReceiveRequest;
use App\Http\Requests\Inventory\StoreInventoryMovementRequest;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Services\InventoryLocationService;
use App\Services\InventoryMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        $filters = $request->validate([
            'serial_number' => ['nullable', 'string', 'max:100'],
            'location_id'   => ['nullable', 'integer', 'exists:inventory_locations,id'],
            'type'          => ['nullable', 'string', Rule::in(array_column(MovementType::cases(), 'value'))],
            'date_from'     => ['nullable', 'date_format:Y-m-d'],
            'date_to'       => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

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

        $serials = InventorySerial::with(['product', 'location'])
            ->where('status', SerialStatus::InStock)
            ->orderBy('serial_number')
            ->get();

        $locations = $this->locationService->activeForDropdown();
        $types = MovementType::cases();

        // Resolve from already-loaded collection — avoids a second DB query
        $selectedSerial = $request->filled('serial_id')
            ? $serials->find((int) $request->query('serial_id'))
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
     * Show the bulk receive form — auto-generate serials for one SKU.
     * Standalone receive only — NOT used for QC serial assignment.
     * QC serial assignment uses GoodsReceiptController::assignSerials() instead,
     * which guarantees goods_receipt_id is always set via route parameter.
     * Accepts ?product_id=X&qty=Y to pre-fill from external links.
     */
    public function bulkReceive(Request $request): View
    {
        $this->authorize('bulkReceive', InventoryMovement::class);

        $products  = Product::orderBy('name')->get(['id', 'sku', 'name']);
        $locations = $this->locationService->activeForDropdown();

        // Pre-fill values from query params (0 = not provided → no pre-selection)
        $prefilledProductId = $request->integer('product_id', 0);
        $prefilledQty       = $request->integer('qty', 0);

        return view('inventory.movements.bulk-receive', compact(
            'products', 'locations', 'prefilledProductId', 'prefilledQty'
        ));
    }

    /**
     * Generate N serial numbers, batch-insert, redirect to print view.
     */
    public function storeBulkReceive(StoreBulkReceiveRequest $request): RedirectResponse
    {
        try {
            $serials = $this->movements->bulkReceive(
                $request->validated(),
                $request->user(),
            );
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        session(['bulk_receive_ids' => $serials->pluck('id')->toArray()]);

        return redirect()
            ->route('inventory-movements.bulk-receive-print')
            ->with('success', "Generated {$serials->count()} serial numbers. Ready to print.");
    }

    /**
     * Render the printable label sheet for the just-generated batch.
     * Clears session after rendering — one-time use.
     */
    public function printBulkReceive(): View|RedirectResponse
    {
        $this->authorize('bulkReceive', InventoryMovement::class);

        $ids = session('bulk_receive_ids', []);

        if (empty($ids)) {
            return redirect()
                ->route('inventory-movements.bulk-receive')
                ->withErrors(['error' => 'No serials to print. Generate a batch first.']);
        }

        $serials = InventorySerial::with(['product:id,sku,name', 'location:id,code,name'])
            ->whereIn('id', $ids)
            ->get();

        session()->forget('bulk_receive_ids');

        return view('inventory.movements.bulk-receive-print', compact('serials'));
    }

    /**
     * All movements for a specific serial — used on the serial show page.
     * Accessible by: admin, manager, sales.
     */
    public function forSerial(InventorySerial $inventorySerial): View
    {
        $this->authorize('viewAny', InventoryMovement::class);

        $inventorySerial->load('product');
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

    // Bulk receive — admin/manager only
    Route::get('/bulk-receive',       [InventoryMovementController::class, 'bulkReceive'])
         ->name('bulk-receive');
    Route::post('/bulk-receive',      [InventoryMovementController::class, 'storeBulkReceive'])
         ->name('bulk-receive.store');
    Route::get('/bulk-receive/print', [InventoryMovementController::class, 'printBulkReceive'])
         ->name('bulk-receive-print');
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

### bulkReceive() — pre-fill via query params

When navigating from PO show page "Assign Serials" link, `?product_id=X&qty=Y` is appended.
The view must pre-select the product in the dropdown and pre-fill the qty input.
If params are absent (direct navigation), form renders empty — no defaults.
`prefilledProductId=0` means not provided; view treats 0 as no selection.

### bulkReceive() — session-based print handoff

`storeBulkReceive()` stores generated serial IDs in session (`bulk_receive_ids`).
`printBulkReceive()` loads them by ID, renders the print view, then clears the session key.
One-time use — refreshing the print page redirects back to the form.

### bulkReceive() — admin/manager only

`sales` role cannot create stock. Policy `bulkReceive()` checks `INVENTORY_MOVEMENTS_BULK_RECEIVE` permission.
`StoreBulkReceiveRequest::authorize()` enforces the same — double-gated.

### DomainException handling

The controller wraps the service call in `try/catch (\DomainException)` and returns
`back()->withErrors()` so the form re-displays with the error message above the submit button.
