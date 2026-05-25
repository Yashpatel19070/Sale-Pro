<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SerialStatus;
use App\Http\Requests\InventorySerial\StoreInventorySerialRequest;
use App\Http\Requests\InventorySerial\UpdateInventorySerialRequest;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Services\InventoryLocationService;
use App\Services\InventoryMovementService;
use App\Services\InventorySerialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventorySerialController extends Controller
{
    public function __construct(
        private readonly InventorySerialService $service,
        private readonly InventoryLocationService $locationService,
        private readonly InventoryMovementService $movementService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', InventorySerial::class);

        $serials = $this->service->list($request->only(['search', 'status', 'product_id', 'location_id']));
        $statuses = SerialStatus::options();
        $products = Product::active()->orderBy('name')->select(['id', 'sku', 'name'])->get();
        $locations = $this->locationService->activeForDropdown();

        return view('inventory.serials.index', compact('serials', 'statuses', 'products', 'locations'));
    }

    public function show(InventorySerial $inventorySerial): View
    {
        $this->authorize('view', $inventorySerial);

        $inventorySerial->load([
            'product:id,sku,name,regular_price',
            'location:id,code,name',
            'receivedBy:id,name,email',
        ]);

        $movements = $inventorySerial->movements()
            ->select(['id', 'inventory_serial_id', 'from_location_id', 'to_location_id', 'type', 'notes', 'user_id', 'goods_receipt_id', 'created_at'])
            ->with([
                'fromLocation:id,code,name',
                'toLocation:id,code,name',
                'user:id,name',
                'goodsReceipt:id,grn_number,purchase_order_id',
                'goodsReceipt.purchaseOrder:id,po_number,supplier_id',
                'goodsReceipt.purchaseOrder.supplier:id,name',
            ])
            ->latest()
            ->paginate(15);

        return view('inventory.serials.show', [
            'serial' => $inventorySerial,
            'movements' => $movements,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', InventorySerial::class);

        $products = Product::active()->orderBy('name')->select(['id', 'sku', 'name'])->get();
        $locations = $this->locationService->activeForDropdown();

        return view('inventory.serials.create', compact('products', 'locations'));
    }

    public function store(StoreInventorySerialRequest $request): RedirectResponse
    {
        $this->authorize('create', InventorySerial::class);

        $serial = $this->movementService->receive($request->validated(), $request->user());

        return redirect()
            ->route('inventory-serials.show', $serial)
            ->with('success', "Serial \"{$serial->serial_number}\" received successfully.");
    }

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

    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InventorySerial::class);

        $productId = $request->integer('product_id');
        $locationId = $request->integer('location_id');

        if (! $productId || ! $locationId) {
            return response()->json([]);
        }

        $serials = InventorySerial::inStock()
            ->where('product_id', $productId)
            ->where('inventory_location_id', $locationId)
            ->orderBy('serial_number')
            ->get(['id', 'serial_number']);

        return response()->json($serials);
    }
}
