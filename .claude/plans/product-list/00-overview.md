# ProductList Module — Overview

## Purpose
Storefront listings. Each ProductListing is a named entry for a Product on the shop.
One Product (SKU) can have many ProductListings — e.g., "Blue / XL", "Red / M", "Standard".
Orders reference ProductListings, not Products directly.
Prices always come from the parent Product — no price fields on the listing.

## Core Concept
```
Product (SKU: TSHIRT-001)
  regular_price: $19.99 | sale_price: $14.99
  ├── ProductListing: "Blue / XL"   → slug: tshirt-001-blue-xl  → visibility: public
  ├── ProductListing: "Blue / M"    → slug: tshirt-001-blue-m   → visibility: public
  └── ProductListing: "Red / M"     → slug: tshirt-001-red-m    → visibility: draft
```

- Each listing has a title and slug — prices are read from the parent product
- Listing slug used for storefront URLs: `/shop/tshirt-001-blue-xl`
- Slug is **mutable** — regenerates when title changes; old slugs stored in `product_listing_slug_redirects` and served as 301s
- Uses `spatie/laravel-sluggable` for slug generation + uniqueness
- Orders attach to listing ID

## Relationship to Other Modules
```
Product ──→ ProductListing (many)
                  ↓
            Order Line Items  (future Orders module)
```

| Downstream module | What it adds |
|-------------------|-------------|
| `product-slug` | Slug redirect table + portal route for `/shop/{slug}` |
| `product-seo` | `meta_title`/`meta_description` columns, `setSeoForListing()`, portal view, sitemap |

## Features
| # | Feature |
|---|---------|
| 1 | List all listings — paginated, search by title, filter by product/status/visibility |
| 2 | View listing — detail with product prices (read-only), visibility, slug |
| 3 | Create listing — pick parent product, set title + visibility |
| 4 | Edit listing — update title + visibility; slug auto-regenerated on title change |
| 5 | Delete listing — soft delete; block if attached to active orders |
| 6 | Restore listing — restore soft-deleted listing |
| 7 | Toggle visibility — flip public/draft without full edit |

## File Map
| File | Path |
|------|------|
| Migration: listings | `database/migrations/xxxx_create_product_listings_table.php` |
| Migration: redirects | `database/migrations/xxxx_create_product_listing_slug_redirects_table.php` |
| Model | `app/Models/ProductListing.php` |
| Redirect Model | `app/Models/ProductListingSlugRedirect.php` |
| Factory | `database/factories/ProductListingFactory.php` |
| Service | `app/Services/ProductListingService.php` |
| Controller | `app/Http/Controllers/ProductListingController.php` |
| Store Request | `app/Http/Requests/ProductListing/StoreProductListingRequest.php` |
| Update Request | `app/Http/Requests/ProductListing/UpdateProductListingRequest.php` |
| Policy | `app/Policies/ProductListingPolicy.php` |
| View: index | `resources/views/product_listings/index.blade.php` |
| View: show | `resources/views/product_listings/show.blade.php` |
| View: create | `resources/views/product_listings/create.blade.php` |
| View: edit | `resources/views/product_listings/edit.blade.php` |
| View: _form | `resources/views/product_listings/_form.blade.php` |
| Permission Seeder | `database/seeders/ProductListingPermissionSeeder.php` |
| Data Seeder | `database/seeders/ProductListingSeeder.php` |
| Feature Test | `tests/Feature/ProductListingControllerTest.php` |
| Unit Test | `tests/Unit/Services/ProductListingServiceTest.php` |

## Files to Modify
| File | Change |
|------|--------|
| `app/Enums/Permission.php` | Add 6 PRODUCT_LISTINGS_* constants |
| `app/Providers/AppServiceProvider.php` | Register ProductListingPolicy |
| `routes/web.php` | Add Route::resource + toggleVisibility + restore inside admin group; add slug redirect route on portal side |
| `database/seeders/DatabaseSeeder.php` | Call ProductListingPermissionSeeder + ProductListingSeeder |

## Dependencies
```bash
composer require spatie/laravel-sluggable
```

## Implementation Order
1. Schema → both migrations → `php artisan migrate`
2. Enum: `ListingVisibility` (public, private, draft)
3. Model + Factory
4. Service
5. FormRequests (Store + Update)
6. Policy + Permission constants
7. Controller
8. Routes
9. Views
10. Seeders
11. Tests

## Role Access Matrix
| Permission | Super Admin | Admin | Staff |
|------------|-------------|-------|-------|
| List listings | ✅ | ✅ | ✅ |
| View listing | ✅ | ✅ | ✅ |
| Create listing | ✅ | ✅ | ❌ |
| Edit listing | ✅ | ✅ | ❌ |
| Delete listing | ✅ | ✅ | ❌ |
| Restore listing | ✅ | ✅ | ❌ |

## Key Rules
- `strict_types=1` on every PHP file
- Always `$request->validated()` — never `$request->all()`
- Eager load product with prices (`with('product:id,sku,name,regular_price,sale_price')`) — never lazy load
- Soft delete only — block delete if listing has active orders
- `$this->authorize()` on every controller action
- Slug auto-generated via `spatie/laravel-sluggable` from `product.sku + title`; regenerated on title change
- Old slugs saved to `product_listing_slug_redirects` table; 301 redirect route serves them
- No price, stock, or attribute fields on the listing — all prices read from parent Product
- Every controller action has a Pest feature test
- Every service method has a Pest unit test
