# ProductSEO Module — Service

## Method: `setSeoForListing()`

Added to `app/Services/ProductListingService.php`.

```php
use Artesaos\SEOTools\Facades\JsonLd;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\SEOMeta;
use Illuminate\Support\Str;

public function setSeoForListing(ProductListing $listing): void
{
    // Listing must have product eager-loaded: with('product:id,sku,name,regular_price,sale_price')
    $title       = $listing->meta_title ?? $listing->title;
    $description = $listing->meta_description
        ?? Str::limit($listing->title . ' — ' . $listing->product->sku, 160);
    $url         = route('portal.shop.listing', $listing->slug);

    // Meta tags
    SEOMeta::setTitle($title);
    SEOMeta::setDescription($description);
    SEOMeta::setCanonical($url);

    // Open Graph
    OpenGraph::setTitle($title)
        ->setDescription($description)
        ->setUrl($url)
        ->setType('product');

    // JSON-LD Product schema
    JsonLd::setType('Product')
        ->setTitle($title)
        ->addValue('description', $description)
        ->addValue('sku', $listing->product->sku)
        ->addValue('url', $url)
        ->addValue('offers', [
            '@type'         => 'Offer',
            'price'         => $listing->product->sale_price ?? $listing->product->regular_price,
            'priceCurrency' => 'USD',
            'availability'  => $listing->is_active
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
        ]);
}
```

---

## Portal Controller: `PortalListingController`

> The admin `ProductListingController` serves the admin side only.
> The portal `/shop/{slug}` route needs its own controller.

**New file:** `app/Http/Controllers/PortalListingController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProductListing;
use App\Services\ProductListingService;
use Illuminate\View\View;

class PortalListingController extends Controller
{
    public function __construct(
        private readonly ProductListingService $listingService,
    ) {}

    public function show(string $slug): View
    {
        $listing = ProductListing::where('slug', $slug)
            ->where('visibility', 'public')
            ->where('is_active', true)
            ->with('product:id,sku,name,regular_price,sale_price')
            ->firstOrFail();

        $this->listingService->setSeoForListing($listing);

        return view('portal.shop.show', compact('listing'));
    }
}
```

**Update `product-slug/04-routes.md` portal route closure** — replace the Step 1 redirect with a call to `PortalListingController::show()`:

```php
// Before (temporary redirect — defined in product-slug/04-routes.md):
if ($listing) {
    return redirect()->route('product-listings.show', $listing);
}

// After (product-seo replaces this):
if ($listing) {
    return app(PortalListingController::class)->show($listing->slug);
}
```

> **Note:** The route closure in product-slug/04-routes.md handles slug resolution and 301 redirects. The portal controller handles SEO binding and view rendering. The closure hands off to the controller only after confirming the slug is current and valid.

---

## Rules

- `ProductListing` must have `product` eager-loaded before calling `setSeoForListing()` — the method does not query the DB itself
- Currency hardcoded as `USD` — make configurable when multi-currency is added
- `sale_price` takes priority over `regular_price` in the offer price
- Method is void — side-effectful by design (sets facade state for the view)
- `PortalListingController` is portal-only — no auth middleware, no policy check (listing is already confirmed public+active by the route closure)

---

## Checklist
- [ ] `setSeoForListing()` added to `ProductListingService`
- [ ] `use` statements added for all three facades + `Str`
- [ ] `PortalListingController` created at `app/Http/Controllers/PortalListingController.php`
- [ ] Route closure in `routes/web.php` updated: Step 1 calls `PortalListingController::show()` instead of redirecting to admin
- [ ] `$listing->product` is always eager-loaded before `setSeoForListing()` is called
