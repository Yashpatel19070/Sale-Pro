# Inventory Module — Controller

## File
`app/Http/Controllers/InventoryController.php`

---

## Responsibilities

- Receive HTTP request (route model binding for product / location)
- Authorize via `InventoryPolicy`
- Delegate to `InventoryService`
- Return Blade view

No FormRequests (read-only module). No exception catching needed (no writes, no domain failures).

---

## Full Code

```php
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
        $this->authorize('viewAny', InventorySerial::class);

        $stockOverview = $this->inventory->overview();

        return view('inventory.index', compact('stockOverview'));
    }

    /**
     * Stock by SKU — breakdown of all locations holding this product, with serials.
     */
    public function showBySku(Product $product): View
    {
        $this->authorize('viewBySku', InventorySerial::class);

        $stockByLocation = $this->inventory->stockBySku($product);

        return view('inventory.show-by-sku', compact('product', 'stockByLocation'));
    }

    /**
     * Serials for one SKU at one specific location.
     */
    public function showBySkuAtLocation(Product $product, InventoryLocation $location): View
    {
        $this->authorize('viewBySkuAtLocation', InventorySerial::class);

        $serials = $this->inventory->stockBySkuAtLocation($product, $location);

        return view('inventory.show-by-sku-at-location', compact('product', 'location', 'serials'));
    }
}
```

---

## Route Model Binding

Laravel resolves `Product $product` and `InventoryLocation $location` via route model binding.
If the model is not found, Laravel automatically returns a 404 — no manual `findOrFail()` needed.

---

## Authorization

`$this->authorize()` checks permissions via `InventoryPolicy`.
The `InventorySerial::class` is used as the model class because there is no `Inventory` model.
The policy gates are: `viewAny`, `viewBySku`, `viewBySkuAtLocation`.

All three roles (`admin`, `manager`, `sales`) pass all three checks.
The `super-admin` role bypasses all gate checks via the `Gate::before()` superadmin hook.

---

## Method Summary

| Method | Route | View | Service call |
|--------|-------|------|--------------|
| `index()` | `GET /admin/inventory` | `inventory.index` | `overview()` |
| `showBySku(Product $product)` | `GET /admin/inventory/{product}` | `inventory.show-by-sku` | `stockBySku($product)` |
| `showBySkuAtLocation(Product $product, InventoryLocation $location)` | `GET /admin/inventory/{product}/{location}` | `inventory.show-by-sku-at-location` | `stockBySkuAtLocation($product, $location)` |

Each location row in `showBySku` links to `route('inventory.by-sku-at-location', [$product, $location])`.

---

## View Data

| Action | Variable passed to view | Type |
|--------|------------------------|------|
| `index` | `$stockOverview` | `Collection<int, Collection<int, InventorySerial>>` |
| `showBySku` | `$product`, `$stockByLocation` | `Product`, `Collection<int, Collection<int, InventorySerial>>` |
| `showBySkuAtLocation` | `$product`, `$location`, `$serials` | `Product`, `InventoryLocation`, `Collection<int, InventorySerial>` |

---

## Missing Import

Add the `InventorySerial` import to the controller:

```php
use App\Models\InventorySerial;
```

The full import list for the controller:

```php
use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\View\View;
```
