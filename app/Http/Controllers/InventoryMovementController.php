<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Enums\SerialStatus;
use App\Http\Requests\Inventory\StoreInventoryMovementRequest;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
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
     * Paginated movement history with optional filters.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', InventoryMovement::class);

        $filters = $request->validate([
            'serial_number' => ['nullable', 'string', 'max:100'],
            'location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
            'type' => ['nullable', 'string', Rule::in(array_column(MovementType::cases(), 'value'))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $movements = $this->movements->listMovements($filters);
        $locations = InventoryLocation::where('is_active', true)->orderBy('code')->get();
        $types = MovementType::cases();

        return view('inventory.movements.index', compact('movements', 'locations', 'types', 'filters'));
    }

    /**
     * Show the create form for transfer, sale, or adjustment.
     * Pre-fills serial if ?serial_id= is provided in the query string.
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
        $selectedType = $request->query('type', 'transfer');

        // Resolve from already-loaded collection — avoids a second DB query
        $selectedSerial = $request->filled('serial_id')
            ? $serials->find((int) $request->query('serial_id'))
            : null;

        return view('inventory.movements.create', compact(
            'serials', 'locations', 'selectedSerial', 'selectedType', 'types'
        ));
    }

    /**
     * Persist the new movement. Delegates to service based on validated type.
     */
    public function store(StoreInventoryMovementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $serial = InventorySerial::findOrFail($data['inventory_serial_id']);
        $user = $request->user();
        $type = MovementType::from($data['type']);

        try {
            $movement = match ($type) {
                MovementType::Transfer => $this->movements->transfer(
                    serial: $serial,
                    fromLocation: InventoryLocation::findOrFail($data['from_location_id']),
                    toLocation: InventoryLocation::findOrFail($data['to_location_id']),
                    user: $user,
                    reference: $data['reference'] ?? null,
                    notes: $data['notes'] ?? null,
                ),

                MovementType::Sale => $this->movements->sale(
                    serial: $serial,
                    fromLocation: InventoryLocation::findOrFail($data['from_location_id']),
                    user: $user,
                    reference: $data['reference'] ?? null,
                    notes: $data['notes'] ?? null,
                ),

                MovementType::Adjustment => $this->movements->adjustment(
                    serial: $serial,
                    newStatus: $data['adjustment_status'],
                    user: $user,
                    fromLocationId: $data['from_location_id'] ?? null,
                    toLocationId: $data['to_location_id'] ?? null,
                    reference: $data['reference'] ?? null,
                    notes: $data['notes'] ?? null,
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
     * All movements for a specific serial — serial timeline page.
     */
    public function forSerial(InventorySerial $inventorySerial): View
    {
        $this->authorize('viewAny', InventoryMovement::class);

        $inventorySerial->load('product');
        $movements = $this->movements->historyForSerial($inventorySerial);

        return view('inventory.movements.serial-timeline', compact('inventorySerial', 'movements'));
    }
}
