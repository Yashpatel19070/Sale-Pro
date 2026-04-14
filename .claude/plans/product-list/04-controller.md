# ProductList Module — Controller

## File
`app/Http/Controllers/ProductListingController.php`

## Named Routes

| Action | Method | URL | Route Name |
|--------|--------|-----|------------|
| index | GET | `/admin/product-listings` | `product-listings.index` |
| create | GET | `/admin/product-listings/create` | `product-listings.create` |
| store | POST | `/admin/product-listings` | `product-listings.store` |
| show | GET | `/admin/product-listings/{productListing}` | `product-listings.show` |
| edit | GET | `/admin/product-listings/{productListing}/edit` | `product-listings.edit` |
| update | PUT/PATCH | `/admin/product-listings/{productListing}` | `product-listings.update` |
| destroy | DELETE | `/admin/product-listings/{productListing}` | `product-listings.destroy` |
| toggleVisibility | POST | `/admin/product-listings/{productListing}/toggle-visibility` | `product-listings.toggle-visibility` |
| restore | POST | `/admin/product-listings/{trashedProductListing}/restore` | `product-listings.restore` |

> Note: can also create from a product's show page — `?product_id={id}` pre-selects the parent.

## Full Implementation

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ListingVisibility;
use App\Http\Requests\ProductListing\StoreProductListingRequest;
use App\Http\Requests\ProductListing\UpdateProductListingRequest;
use App\Models\ProductListing;
use App\Services\ProductListingService;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductListingController extends Controller
{
    public function __construct(
        private readonly ProductListingService $service,
        private readonly ProductService        $productService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProductListing::class);

        $listings     = $this->service->list($request->only(['search', 'product_id', 'visibility', 'active']));
        $products     = $this->productService->dropdown();
        $visibilities = ListingVisibility::options();

        return view('product_listings.index', compact('listings', 'products', 'visibilities'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ProductListing::class);

        $products          = $this->productService->dropdown();
        $visibilities      = ListingVisibility::options();
        $selectedProductId = $request->integer('product_id') ?: null;

        return view('product_listings.create', compact('products', 'visibilities', 'selectedProductId'));
    }

    public function store(StoreProductListingRequest $request): RedirectResponse
    {
        $this->authorize('create', ProductListing::class);

        $listing = $this->service->create($request->validated());

        return redirect()
            ->route('product-listings.show', $listing)
            ->with('success', "Listing \"{$listing->title}\" created.");
    }

    public function show(ProductListing $productListing): View
    {
        $this->authorize('view', $productListing);

        $productListing->load(['product', 'product.category']);

        return view('product_listings.show', ['listing' => $productListing]);
    }

    public function edit(ProductListing $productListing): View
    {
        $this->authorize('update', $productListing);

        $productListing->loadMissing(['product', 'product.category']);
        $visibilities = ListingVisibility::options();

        return view('product_listings.edit', [
            'listing'      => $productListing,
            'visibilities' => $visibilities,
        ]);
    }

    public function update(UpdateProductListingRequest $request, ProductListing $productListing): RedirectResponse
    {
        $this->authorize('update', $productListing);

        $this->service->update($productListing, $request->validated());

        return redirect()
            ->route('product-listings.show', $productListing)
            ->with('success', 'Listing updated.');
    }

    public function destroy(ProductListing $productListing): RedirectResponse
    {
        $this->authorize('delete', $productListing);

        try {
            $this->service->delete($productListing);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('product-listings.index')
            ->with('success', 'Listing deleted.');
    }

    public function toggleVisibility(ProductListing $productListing): RedirectResponse
    {
        $this->authorize('update', $productListing);

        $listing = $this->service->toggleVisibility($productListing);
        $state   = $listing->visibility->label();

        return back()->with('success', "Listing set to {$state}.");
    }

    public function restore(ProductListing $productListing): RedirectResponse
    {
        abort_if(! $productListing->trashed(), 404);

        $this->authorize('restore', ProductListing::class);

        $listing = $this->service->restore($productListing);

        return redirect()
            ->route('product-listings.show', $listing)
            ->with('success', 'Listing restored.');
    }
}
```

## Notes
- `create` accepts `?product_id` query param to pre-select product in create form
- `show` + `edit`: eager-load `product.category` so SKU, category name, prices are all available in views
- `restore`: guard with `abort_if(!trashed(), 404)` before authorize — prevents non-deleted model enumeration
- `update`: explicit `$this->authorize('update', $productListing)` required even though `UpdateProductListingRequest::authorize()` also checks it — belt-and-suspenders
- Edit form shows product name as read-only; only title + visibility are editable
- `visibilities` passed from `ListingVisibility::options()` for select inputs
- No adjustStock action — stock management is not part of this module
