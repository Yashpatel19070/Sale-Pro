<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    /**
     * Stock overview dashboard — total in_stock count per SKU across all locations.
     */
    public function index(): View
    {
        $this->authorize('inventoryViewAny', InventorySerial::class);

        $stockOverview = $this->inventory->overview();

        return view('inventory.index', compact('stockOverview'));
    }

    /**
     * Stock by SKU — breakdown of all locations holding this product, with serial counts.
     */
    public function showBySku(Product $product): View
    {
        $this->authorize('inventoryViewBySku', InventorySerial::class);

        $stockByLocation = $this->inventory->stockBySku($product);

        return view('inventory.show-by-sku', compact('product', 'stockByLocation'));
    }

    /**
     * Serials for one SKU at one specific location.
     */
    public function showBySkuAtLocation(Product $product, InventoryLocation $location): View
    {
        $this->authorize('inventoryViewBySkuAtLocation', InventorySerial::class);

        $serials = $this->inventory->stockBySkuAtLocation($product, $location);

        return view('inventory.show-by-sku-at-location', compact('product', 'location', 'serials'));
    }
}
