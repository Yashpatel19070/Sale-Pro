# Product Slug Feature — Portal Redirect Route

## Dependencies

- **Requires:** `product_listings` table + `product_listing_slug_redirects` table
- **Requires:** `ProductListing` and `ProductListingSlugRedirect` models
- **Modifies:** `routes/web.php` — add inside the **portal authenticated middleware group**

> Admin routes for product listings are in `product-list/08-seeders-routes.md`.
> This file only covers the portal slug redirect route.

---

## Route

```php
// Listing slug redirect
// Catches current slugs and old slugs, serves or 301-redirects accordingly
Route::get('shop/{slug}', function (string $slug) {
    // 1. Current slug — listing is live and public
    $listing = \App\Models\ProductListing::where('slug', $slug)
        ->where('visibility', 'public')
        ->where('is_active', true)
        ->first();

    if ($listing) {
        // For now redirect to admin show page.
        // Replace with portal listing view when storefront is built.
        return redirect()->route('product-listings.show', $listing);
    }

    // 2. Old slug — 301 to the listing's current slug
    $redirect = \App\Models\ProductListingSlugRedirect::with('listing')
        ->where('old_slug', $slug)
        ->first();

    if ($redirect && $redirect->listing) {
        return redirect()->route('portal.shop.listing', $redirect->listing->slug, 301);
    }

    // 3. Unknown slug
    abort(404);
})->name('portal.shop.listing');
```

---

## Request Flow

```
GET /shop/tshirt-001-blue-m

Step 1: Check product_listings
          WHERE slug = 'tshirt-001-blue-m'
          AND visibility = 'public'
          AND is_active = true
        → Found? Serve listing
        → Not found? Continue

Step 2: Check product_listing_slug_redirects
          WHERE old_slug = 'tshirt-001-blue-m'
        → Found with non-null listing? 301 → /shop/tshirt-001-blue-medium
        → listing null (soft-deleted)? Continue
        → Not found? Continue

Step 3: abort(404)
```

---

## Notes

- Step 1 requires both `visibility = 'public'` AND `is_active = true` — either alone is not enough
- Step 2 uses `with('listing')` to avoid N+1
- Step 2 redirect uses status `301` (permanent) — third argument to `redirect()->route()`
- Step 2 checks `$redirect->listing` is not null — handles soft-deleted listings correctly (falls through to 404 instead of erroring)
- The portal listing page is a future module. For now the route redirects to the admin show page. The route name `portal.shop.listing` is already set so the real view can be wired later without touching other code.

---

## Checklist

- [ ] Route placed inside portal middleware group (not admin group)
- [ ] Route name is `portal.shop.listing`
- [ ] Step 1 checks both `visibility = 'public'` AND `is_active = true`
- [ ] Step 2 uses `with('listing')` to avoid N+1
- [ ] Step 2 redirect uses status code `301` (not default 302)
- [ ] Step 2 checks `$redirect->listing` is not null
- [ ] Step 3 is `abort(404)`
