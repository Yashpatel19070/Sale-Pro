# Product Module — Controller

## File
`app/Http/Controllers/ProductController.php`

## Named Routes

| Action | Method | URL | Route Name |
|--------|--------|-----|------------|
| index | GET | `/admin/products` | `products.index` |
| create | GET | `/admin/products/create` | `products.create` |
| store | POST | `/admin/products` | `products.store` |
| show | GET | `/admin/products/{product}` | `products.show` |
| edit | GET | `/admin/products/{product}/edit` | `products.edit` |
| update | PUT/PATCH | `/admin/products/{product}` | `products.update` |
| destroy | DELETE | `/admin/products/{product}` | `products.destroy` |
| toggleActive | POST | `/admin/products/{product}/toggle-active` | `products.toggle-active` |
| restore | POST | `/admin/products/{trashedProduct}/restore` | `products.restore` |

## Full Implementation

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\ProductCategoryService;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService         $service,
        private readonly ProductCategoryService $categoryService,
        private readonly InventoryService       $inventoryService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $products   = $this->service->list($request->only(['search', 'category_id', 'active']));
        $categories = $this->categoryService->dropdown();

        return view('products.index', compact('products', 'categories'));
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        $categories = $this->categoryService->dropdown();

        return view('products.create', compact('categories'));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->service->create($request->validated());

        return redirect()
            ->route('products.show', $product)
            ->with('success', "Product \"{$product->name}\" created.");
    }

    public function show(Product $product): View
    {
        $this->authorize('view', $product);

        $product->load('category');

        // Paginated listings — DB-level, 15 per page
        $listings = $product->listings()->paginate(15);

        // In-stock serials grouped by location — reuses InventoryService
        // Returns Collection<location_id, Collection<InventorySerial>> with 'location' eager-loaded
        $stockByLocation = $this->inventoryService->stockBySku($product);

        return view('products.show', compact('product', 'listings', 'stockByLocation'));
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        $categories = $this->categoryService->dropdown();

        return view('products.edit', compact('product', 'categories'));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->service->update($product, $request->validated());

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        try {
            $this->service->delete($product);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('products.index')
            ->with('success', 'Product deleted.');
    }

    public function toggleActive(Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $product = $this->service->toggleActive($product);
        $state   = $product->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Product {$state}.");
    }

    public function restore(Product $product): RedirectResponse
    {
        $this->authorize('restore', Product::class);

        $product = $this->service->restore($product->id);

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Product restored.');
    }
}
```

## Notes
- `ProductCategoryService` injected for dropdown data on index/create/edit
- `InventoryService` injected — `show()` calls `stockBySku($product)` to display per-location stock
- `show()` does NOT load `listings` relation on `$product` — listings fetched separately via `$product->listings()->paginate(15)` to avoid loading all rows into memory
- `restore` uses `Product::class` (not instance) for policy — trashed models loaded separately
- `toggleActive` redirects back (used from list or detail page)
- `destroy` catches `RuntimeException` from service and shows error flash
