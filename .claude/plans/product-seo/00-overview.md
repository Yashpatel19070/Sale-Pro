# ProductSEO Module — Overview

## Purpose
Provide SEO metadata and sitemap support for public-facing product listing pages on the portal (`/shop/{slug}`). Admins set `meta_title` and `meta_description` per listing via the admin form. The portal renders full SEO tags (meta, Open Graph, JSON-LD) on each listing detail page. `/sitemap.xml` lists all publicly visible, active listings.

---

## Packages

```bash
composer require artesaos/seotools spatie/laravel-sitemap
php artisan vendor:publish --provider="Artesaos\SEOTools\Providers\SEOToolsServiceProvider"
```

| Package | Version | Purpose |
|---------|---------|---------|
| `artesaos/seotools` | ^1.3 | Meta tags, Open Graph, Twitter Card, JSON-LD |
| `spatie/laravel-sitemap` | ^7.x | `/sitemap.xml` generation |

Config published to: `config/seotools.php`

---

## Scope

| In scope | Out of scope |
|----------|-------------|
| SEO on `product_listings` table | SEO on `products` table |
| Portal `/shop/{slug}` page | Admin listing detail page |
| Admin create/edit form meta fields | Customer-editable SEO |
| `/sitemap.xml` (on-demand) | Scheduled sitemap generation |
| `public` + `is_active=true` listings only | Draft, private, soft-deleted listings |

---

## File Map

| File | Path |
|------|------|
| SEO config | `config/seotools.php` (new — published) |
| Migration: SEO columns | `database/migrations/xxxx_add_seo_columns_to_product_listings_table.php` (new) |
| Model: ProductListing $fillable | `app/Models/ProductListing.php` (update — add meta columns) |
| Service method | `app/Services/ProductListingService.php` (update — add `setSeoForListing()`) |
| Portal listing controller | `app/Http/Controllers/PortalListingController.php` (new) |
| Portal layout head | `resources/views/layouts/portal.blade.php` (update — add seotools directives) |
| Portal listing view | `resources/views/portal/shop/show.blade.php` (new) |
| Admin form partial | `resources/views/product_listings/_form.blade.php` (update — add SEO fields) |
| StoreProductListingRequest | `app/Http/Requests/ProductListing/StoreProductListingRequest.php` (update — add meta rules) |
| UpdateProductListingRequest | `app/Http/Requests/ProductListing/UpdateProductListingRequest.php` (update — add meta rules) |
| Sitemap controller | `app/Http/Controllers/SitemapController.php` (new) |
| Feature test | `tests/Feature/ProductListingSeoTest.php` (new) |
| Routes | `routes/web.php` (update — portal shop closure + `/sitemap.xml` route) |

---

## Prerequisites

> **product-seo requires both `product-list` and `product-slug` to be fully implemented, tested, and migrated before starting.**

## Relationship to Other Modules

| Module | Relationship |
|--------|-------------|
| `product-list` | Adds `meta_title`/`meta_description` columns to `product_listings`; extends `ProductListingService` with `setSeoForListing()`; adds meta fields to admin form; updates `ProductListing::$fillable` |
| `product-slug` | Slug is the canonical URL for SEO tags and sitemap; updates the portal route closure (`portal.shop.listing`) to call `PortalListingController` instead of the temporary admin redirect |

---

## Key Design Decisions

- `meta_title` varchar(160) nullable — Google truncates at ~60 chars, allow up to 160 for flexibility; fallback: `$listing->title`
- `meta_description` varchar(320) nullable — generous limit; fallback: first 160 chars of title + product SKU
- `JsonLd` type = `Product` with `name`, `url`, `sku` (from parent product) and `offers.price`
- Sitemap generated on-demand via route — no scheduler at this stage
- Sitemap excludes: `visibility != 'public'`, `is_active = false`, `deleted_at IS NOT NULL`
- `<link rel="canonical">` explicitly set to current listing URL (belt-and-suspenders alongside 301 redirects)

---

## Implementation Order

> Start only after product-list and product-slug are fully complete.

1. `composer require artesaos/seotools spatie/laravel-sitemap`
2. Publish seotools config: `php artisan vendor:publish --provider="Artesaos\SEOTools\Providers\SEOToolsServiceProvider"`
3. Schema — add SEO columns migration → `php artisan migrate`
4. Update `ProductListing::$fillable` — add `meta_title`, `meta_description`
5. Service — add `setSeoForListing()` to `ProductListingService`
6. Portal controller — create `PortalListingController`
7. Routes — update portal shop closure to call `PortalListingController`; add `/sitemap.xml` route
8. Portal layout — add Blade directives to `<head>`
9. Admin form — add meta fields to `_form.blade.php`; add validation to FormRequests
10. Sitemap — `SitemapController`
11. Tests

---

## Checklist
- [ ] `composer require artesaos/seotools spatie/laravel-sitemap`
- [ ] Config published: `config/seotools.php`
- [ ] Migration adds `meta_title` + `meta_description` to `product_listings` (000004)
- [ ] `ProductListing::$fillable` updated with `meta_title`, `meta_description`
- [ ] `setSeoForListing()` method on `ProductListingService`
- [ ] `PortalListingController` created; portal shop route closure updated to call it
- [ ] Portal layout `<head>` renders all four seotools directives
- [ ] Admin `_form.blade.php` has meta title + description fields
- [ ] `StoreProductListingRequest` + `UpdateProductListingRequest` validate meta fields
- [ ] `SitemapController` filters to public+active listings only
- [ ] `/sitemap.xml` route added to portal unauthenticated routes
- [ ] Feature tests pass: `php artisan test --filter=Seo`
