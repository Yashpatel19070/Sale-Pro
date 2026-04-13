# ProductSEO Module — Sitemap

## Controller: `SitemapController`

`app/Http/Controllers/SitemapController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProductListing;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SitemapController extends Controller
{
    public function index(): \Symfony\Component\HttpFoundation\Response
    {
        $sitemap = Sitemap::create();

        ProductListing::query()
            ->where('visibility', 'public')
            ->where('is_active', true)
            ->withoutTrashed()
            ->select(['id', 'slug', 'updated_at'])
            ->each(function (ProductListing $listing) use ($sitemap): void {
                $sitemap->add(
                    Url::create(route('portal.shop.listing', $listing->slug))
                        ->setLastModificationDate($listing->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.8)
                );
            });

        return $sitemap->toResponse(request());
    }
}
```

---

## Route

In `routes/web.php`, add **outside all middleware groups** — before the portal authenticated group. Search engine crawlers have no session or auth token.

```php
// ── Public routes (no auth) ───────────────────────────────────────────────
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('portal.sitemap');

// ── Portal authenticated routes ───────────────────────────────────────────
Route::middleware(['auth', 'verified:portal.verification.notice', 'role:customer', 'active'])
    ->group(function () {
        // ... existing portal routes (dashboard, profile, etc.)
    });
```

> The `/shop/{slug}` route from product-slug is also outside auth middleware — sitemap follows the same placement.

---

## Filtering Rules

| Filter | Condition | Reason |
|--------|-----------|--------|
| Visibility | `visibility = 'public'` | private/draft listings must not be indexed |
| Active | `is_active = true` | inactive listings are off-shelf |
| Soft deleted | `->withoutTrashed()` | uses SoftDeletes scope — not raw `whereNull` |

---

## Key Design Decisions

### On-demand generation (no caching at this stage)
Sitemap is generated fresh on each request. Acceptable for small-to-medium catalog sizes. Add response caching (`Cache::remember`) when listing count grows beyond ~10,000.

### `each()` not `get()`
Uses `each()` (chunked cursor) to avoid loading all listings into memory at once.

### `select()` only needed columns
Fetches only `id`, `slug`, `updated_at` — no full model hydration needed for sitemap generation.

### Priority 0.8
Product listing pages are high-priority content (below homepage at 1.0, above generic pages at 0.5).

---

## Checklist
- [ ] `SitemapController` created at `app/Http/Controllers/SitemapController.php`
- [ ] Filters: `visibility=public`, `is_active=true`, `deleted_at IS NULL`
- [ ] `/sitemap.xml` route added to portal unauthenticated routes
- [ ] Route named `portal.sitemap`
- [ ] Returns `Content-Type: application/xml`
- [ ] Draft/private listings confirmed absent from output
