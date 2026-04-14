<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\StoreInventoryLocationRequest;
use App\Http\Requests\Inventory\UpdateInventoryLocationRequest;
use App\Models\InventoryLocation;
use App\Services\InventoryLocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InventoryLocationController extends Controller
{
    public function __construct(private readonly InventoryLocationService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', InventoryLocation::class);

        $filters = $request->only(['search', 'status']);
        $locations = $this->service->list($filters);

        return view('inventory.locations.index', compact('locations', 'filters'));
    }

    public function show(InventoryLocation $inventoryLocation): View
    {
        $this->authorize('view', $inventoryLocation);

        $activeSerialCount = $this->service->activeSerialCount($inventoryLocation);

        return view('inventory.locations.show', [
            'location' => $inventoryLocation,
            'activeSerialCount' => $activeSerialCount,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', InventoryLocation::class);

        return view('inventory.locations.create');
    }

    public function store(StoreInventoryLocationRequest $request): RedirectResponse
    {
        $this->authorize('create', InventoryLocation::class);

        $location = $this->service->store($request->validated());

        return redirect()
            ->route('inventory-locations.show', $location)
            ->with('success', "Location \"{$location->code}\" created successfully.");
    }

    public function edit(InventoryLocation $inventoryLocation): View
    {
        $this->authorize('update', $inventoryLocation);

        return view('inventory.locations.edit', ['location' => $inventoryLocation]);
    }

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

    public function destroy(InventoryLocation $inventoryLocation): RedirectResponse
    {
        $this->authorize('delete', $inventoryLocation);

        try {
            $this->service->deactivate($inventoryLocation);
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('inventory-locations.index')
            ->with('success', "Location \"{$inventoryLocation->code}\" deactivated.");
    }

    /**
     * Route uses ->withTrashed() so soft-deleted models resolve via model binding.
     * Authorization runs immediately after binding — before any data is exposed.
     */
    public function restore(InventoryLocation $inventoryLocation): RedirectResponse
    {
        $this->authorize('restore', $inventoryLocation);

        $this->service->restore($inventoryLocation);

        return redirect()
            ->route('inventory-locations.show', $inventoryLocation)
            ->with('success', "Location \"{$inventoryLocation->code}\" restored.");
    }
}
