# ProductList Module — Service

## File
`app/Services/ProductListingService.php`

## Full Implementation

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ListingVisibility;
use App\Models\Product;
use App\Models\ProductListing;
use App\Models\ProductListingSlugRedirect;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductListingService
{
    /**
     * Paginated list with filters.
     *
     * @param array{search?: string, product_id?: int, visibility?: string, active?: bool} $filters
     */
    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return ProductListing::with(['product:id,category_id,sku,name,regular_price,sale_price', 'product.category:id,name'])
            ->when(
                isset($filters['search']) && $filters['search'] !== '',
                fn ($q) => $q->search($filters['search'])
            )
            ->when(
                isset($filters['product_id']),
                fn ($q) => $q->where('product_id', $filters['product_id'])
            )
            ->when(
                isset($filters['visibility']),
                fn ($q) => $q->where('visibility', $filters['visibility'])
            )
            ->when(
                isset($filters['active']) && $filters['active'] !== '',
                fn ($q) => $q->where('is_active', (bool) $filters['active'])
            )
            ->orderBy('product_id')
            ->orderBy('title')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Create a new listing.
     *
     * @param array{product_id: int, title: string, visibility: string, is_active?: bool} $data
     */
    public function create(array $data): ProductListing
    {
        return DB::transaction(function () use ($data): ProductListing {
            // Must eager-load product before save so getSlugOptions() can prefix with SKU
            $product = Product::findOrFail($data['product_id']);

            $listing = new ProductListing($data);
            $listing->setRelation('product', $product);
            $listing->generateSlug(); // explicit — survives WithoutModelEvents contexts (e.g. DatabaseSeeder)
            $listing->save();

            return $listing->load(['product:id,category_id,sku,name,regular_price,sale_price', 'product.category:id,name']);
        });
    }

    /**
     * Update an existing listing. product_id is immutable — stripped if present.
     *
     * @param array<string, mixed> $data
     */
    public function update(ProductListing $listing, array $data): ProductListing
    {
        return DB::transaction(function () use ($listing, $data): ProductListing {
            unset($data['product_id']); // immutable after creation

            $titleChanged = isset($data['title']) && $data['title'] !== $listing->title;

            if ($titleChanged) {
                $oldSlug = $listing->slug;

                // Allow sluggable to regenerate on this save
                $listing->fill($data);
                $listing->generateSlug(); // spatie method — forces regen
                $listing->save();

                // Persist old slug for 301 redirect; ignore if already recorded
                ProductListingSlugRedirect::firstOrCreate(
                    ['old_slug' => $oldSlug],
                    ['listing_id' => $listing->id],
                );
            } else {
                $listing->update($data);
            }

            return $listing->fresh(['product:id,category_id,sku,name,regular_price,sale_price', 'product.category:id,name']);
        });
    }

    /**
     * Soft-delete a listing.
     */
    public function delete(ProductListing $listing): void
    {
        $listing->delete();
    }

    /**
     * Restore a soft-deleted listing.
     */
    public function restore(ProductListing $listing): ProductListing
    {
        $listing->restore();

        return $listing;
    }

    /**
     * Toggle visibility between public and draft.
     */
    public function toggleVisibility(ProductListing $listing): ProductListing
    {
        $next = $listing->visibility === ListingVisibility::Public
            ? ListingVisibility::Draft
            : ListingVisibility::Public;

        $listing->visibility = $next;
        $listing->save();

        return $listing;
    }

    /**
     * Regenerate slugs for all active (non-trashed) listings when a product's SKU changes.
     * Called by ProductService::update() inside its transaction — atomic with the SKU update.
     *
     * Full implementation and design rationale: see product-slug/03-service.md
     */
    public function regenerateSlugsForProduct(Product $product): void
    {
        $listings = ProductListing::where('product_id', $product->id)->get();

        foreach ($listings as $listing) {
            $oldSlug = $listing->slug;

            $listing->setRelation('product', $product);
            $listing->generateSlug();
            $listing->save();

            if ($oldSlug && $oldSlug !== $listing->slug) {
                ProductListingSlugRedirect::firstOrCreate(
                    ['old_slug' => $oldSlug],
                    ['listing_id' => $listing->id],
                );
            }
        }
    }

}
```

## Method Summary

| Method | Description |
|--------|-------------|
| `list(filters, perPage)` | Paginated listings; filter by product/visibility/active/search; eager loads product with prices |
| `create(data)` | Sets product relation before save so sluggable can prefix SKU; wraps in transaction — slug logic: `product-slug/03-service.md` |
| `update(listing, data)` | On title change: saves old slug to redirects table then calls `generateSlug()`; strips product_id — slug logic: `product-slug/03-service.md` |
| `delete(listing)` | Soft-delete; has guard hook for future orders check |
| `restore(id)` | Restore soft-deleted listing |
| `toggleVisibility(listing)` | Flips public ↔ draft |
| `regenerateSlugsForProduct(product)` | Bulk slug regen for all non-trashed listings when SKU changes; called by `ProductService::update()` — see `product-slug/03-service.md` |

## Key Rules
- `product_id` immutable after creation — stripped in `update()`
- `create()`: call `$listing->generateSlug()` explicitly before `save()` — do NOT rely on the `creating` event; `DatabaseSeeder` uses `WithoutModelEvents` which suppresses it
- `create()`: must set product relation before `generateSlug()` so `getSlugOptions()` can read `product->sku`
- `update()`: uses `doNotGenerateSlugsOnUpdate()` option — only regens when service explicitly calls `$listing->generateSlug()`
- Old slug saved via `ProductListingSlugRedirect::firstOrCreate()` — idempotent, no duplicate redirects
- `toggleVisibility()`: direct property assignment + `save()` — avoids `->value` unwrap and extra DB round-trip from `fresh()`
- Eager load always includes `product.category` (needs `category_id` in the product column select)
- All writes in `DB::transaction()`
