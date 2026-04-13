# Product Slug Feature — Overview

## Purpose

Every `ProductListing` gets a unique URL slug generated automatically from the parent product's SKU + the listing title. When the title changes, the old slug is preserved in a redirect table so existing URLs keep working via 301 redirects. Same automatic behaviour when a product's SKU changes — all its listing slugs regenerate and old URLs redirect.

```
Product: sku = "TSHIRT-001"
Listing: title = "Blue / M"
  → slug = "tshirt-001-blue-m"
  → URL: /shop/tshirt-001-blue-m

Title changed to "Blue / Medium"
  → new slug = "tshirt-001-blue-medium"
  → old slug "tshirt-001-blue-m" → saved to product_listing_slug_redirects
  → GET /shop/tshirt-001-blue-m → 301 → /shop/tshirt-001-blue-medium
```

---

## Automatic Redirect Flow — User Perspective (Zero Overhead)

```
TITLE CHANGE
  User: edits listing title "Blue / M" → "Blue / Medium", clicks Save
  System (automatic, no user action needed):
    1. Service detects title changed
    2. Captures old slug: "tshirt-001-blue-m"
    3. generateSlug() → new slug: "tshirt-001-blue-medium"
    4. Saves new slug to DB
    5. firstOrCreate redirect: "tshirt-001-blue-m" → listing_id
  Result: /shop/tshirt-001-blue-m → 301 → /shop/tshirt-001-blue-medium  ✅ forever

SKU CHANGE
  User: edits product SKU "TSHIRT-001" → "TSHIRT-XL-001", clicks Save
  System (automatic, no user action needed):
    1. ProductService detects SKU changed
    2. Calls regenerateSlugsForProduct($product->fresh())
    3. Loops all non-trashed listings — each gets new slug + redirect record
    4. All inside same DB::transaction() as the SKU update — atomic
  Result: /shop/tshirt-001-* → 301 → /shop/tshirt-xl-001-*  ✅ forever
```

---

## Cross-Module Dependency — SKU + Slug

Slug is built from `product.sku + title`. This means:
- `ProductService::update()` must detect SKU changes and call `ProductListingService::regenerateSlugsForProduct()`
- `UpdateProductRequest` must allow SKU edits (with `Rule::unique()->ignore()`)
- See `product/03-service.md` and `product/05-requests-policy.md` for those changes

### Why trashed listings are skipped during SKU regen

1. **Not served on storefront** — stale slug on a trashed listing causes zero live breakage
2. **Redirect records for null listings are noise** — portal finds redirect, listing is null, 404 anyway
3. **Restore after SKU change** — slug carries old SKU prefix; admin should update title after restore to trigger fresh regen

### Cascade chain

```
Product hard-deleted  → listings cascade-deleted  → redirect records cascade-deleted  (DB FK)
Product soft-deleted  → listings soft-deleted     → redirect records STAY (old URLs → 404 gracefully)
Listing soft-deleted  → redirect records STAY     (correct — old URLs fall through to 404)
Listing hard-deleted  → redirect records cascade-deleted (DB FK)
```

---

## Relationship to Other Modules

```
Product ──→ ProductListing (many) ──→ Order Line Items (future)
                  ↓
        ProductListingSlugRedirect (many)
```

- `ProductListing` belongs to `Product` — slug is prefixed with `product.sku`
- `ProductListingSlugRedirect` belongs to `ProductListing` — stores retired slugs
- `ProductService` calls into `ProductListingService::regenerateSlugsForProduct()` on SKU change
- Orders (future) reference `listing_id` — slug changes never break order references

| Downstream module | What it adds |
|-------------------|-------------|
| `product-seo` | Updates the portal route closure to call `PortalListingController` (replaces temporary admin redirect); uses `portal.shop.listing` route name for canonical URLs and sitemap |

---

## Package Required

```bash
composer require spatie/laravel-sluggable
```

Must be installed **before** running migrations or writing the model.

---

## Files in This Plan

| File | What it covers |
|------|---------------|
| `01-schema.md` | `product_listing_slug_redirects` migration |
| `02-model.md` | HasSlug config on `ProductListing` + `ProductListingSlugRedirect` model |
| `03-service.md` | `create()`, `update()`, `regenerateSlugsForProduct()` — slug methods only |
| `04-routes.md` | Portal `GET /shop/{slug}` redirect route |
| `05-tests.md` | Slug unit tests + portal route feature tests |

---

## Implementation Order

1. `composer require spatie/laravel-sluggable`
2. Run `product-list/01-schema.md` first — `product_listings` table (with `slug` column) must exist before the redirect table
3. `01-schema.md` — redirect table migration → `php artisan migrate`
4. `02-model.md` — add HasSlug to `ProductListing` + create `ProductListingSlugRedirect`
5. `03-service.md` — slug methods in `ProductListingService`
6. `04-routes.md` — portal slug redirect route in `routes/web.php`
7. `05-tests.md` — slug unit + portal feature tests
8. Update `ProductService::update()` — see `product/03-service.md`
9. Update `UpdateProductRequest` — see `product/05-requests-policy.md`

---

## Key Design Decisions

| Decision | Why |
|----------|-----|
| `doNotGenerateSlugsOnUpdate()` | Service controls regen manually — captures old slug before it's overwritten |
| `setRelation()` before `save()` | New listing has no `id` yet — `load()` would fail; `setRelation()` injects fetched product without extra DB query |
| `firstOrCreate()` for redirects | Idempotent — same slug saved twice won't create duplicate redirect records |
| `slug` NOT in `$fillable` | Sluggable writes `slug` directly; if fillable a user could bypass uniqueness logic |
| `cascadeOnDelete` on `listing_id` | Hard-delete of a listing cleans up its redirect history |
| Redirects point to `listing_id` not target slug | Always resolves to current slug — no redirect chains no matter how many times title changes |

---

## Checklist

- [ ] `composer require spatie/laravel-sluggable` done
- [ ] Redirect table migration runs cleanly
- [ ] `slug` NOT in `ProductListing::$fillable`
- [ ] `doNotGenerateSlugsOnUpdate()` set in `getSlugOptions()`
- [ ] `setRelation()` called before first `save()` in `create()`
- [ ] Old slug captured before `generateSlug()` in `update()`
- [ ] `firstOrCreate()` used for all redirect records
- [ ] `regenerateSlugsForProduct()` skips trashed listings
- [ ] `ProductService::update()` calls `regenerateSlugsForProduct()` on SKU change
- [ ] Portal route returns 301 for old slugs
- [ ] Portal route returns 404 for unknown slugs
- [ ] Portal route returns 404 for draft or inactive listings
