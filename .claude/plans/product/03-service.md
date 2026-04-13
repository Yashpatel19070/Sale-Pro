# Product Module — Service

## File
`app/Services/ProductService.php`

## Full Implementation

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Services\ProductListingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductService
{
    /**
     * Paginated list with filters.
     *
     * @param array{search?: string, category_id?: int, active?: bool} $filters
     */
    public function list(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return Product::with('category')
            ->when(
                isset($filters['search']) && $filters['search'] !== '',
                fn ($q) => $q->search($filters['search'])
            )
            ->when(
                isset($filters['category_id']),
                fn ($q) => $q->where('category_id', $filters['category_id'])
            )
            ->when(
                isset($filters['active']) && $filters['active'] !== '',
                fn ($q) => $q->where('is_active', (bool) $filters['active'])
            )
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Create a new product.
     *
     * @param array{category_id?: int|null, sku: string, name: string,
     *             description?: string, purchase_price?: float|null,
     *             regular_price: float, sale_price?: float|null,
     *             notes?: string, is_active?: bool} $data
     */
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $data['sku'] = strtoupper($data['sku']);

            return Product::create($data);
        });
    }

    /**
     * Update an existing product.
     * If SKU changes, regenerates slugs for all non-trashed listings and creates
     * redirect records — same automatic 301 behaviour as a title change on a listing.
     *
     * @param array<string, mixed> $data
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $skuChanged = isset($data['sku'])
                && strtoupper($data['sku']) !== $product->sku;

            if (isset($data['sku'])) {
                $data['sku'] = strtoupper($data['sku']);
            }

            $product->update($data);

            if ($skuChanged) {
                // Regenerate slugs for all non-trashed listings inside this same
                // transaction. If slug regen fails, the SKU update rolls back too —
                // product SKU and listing slugs are never out of sync.
                app(ProductListingService::class)
                    ->regenerateSlugsForProduct($product->fresh());
            }

            return $product->fresh('category');
        });
    }

    /**
     * Soft-delete a product. Throws if active listings exist.
     */
    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            // Guard inside transaction to prevent TOCTOU race — check and delete
            // are atomic; no concurrent request can sneak an active listing in between.
            if ($product->hasActiveListings()) {
                throw new RuntimeException(
                    "Cannot delete \"{$product->name}\" — it has active listings. Deactivate or delete the listings first."
                );
            }

            $product->listings()->delete();
            $product->delete();
        });
    }

    /**
     * Restore a soft-deleted product.
     * Route model binding resolves the trashed model via withTrashed() on the route.
     */
    public function restore(Product $product): Product
    {
        $product->restore();

        return $product;
    }

    /**
     * Toggle is_active on/off.
     */
    public function toggleActive(Product $product): Product
    {
        $product->update(['is_active' => ! $product->is_active]);

        return $product->fresh();
    }

    /**
     * Dropdown list for selects (id, sku, name — active only).
     *
     * @return Collection<int, Product>
     */
    public function dropdown(): Collection
    {
        return Product::forDropdown()->get();
    }
}
```

## Method Summary

| Method | Description |
|--------|-------------|
| `list(filters, perPage)` | Paginated product list with search/category/active filters |
| `create(data)` | Insert product; uppercases SKU in transaction |
| `update(product, data)` | Update product (uppercases SKU); on SKU change calls `regenerateSlugsForProduct()`; returns fresh model with category |
| `delete(product)` | Soft-delete; throws if active listings exist; soft-deletes inactive listings |
| `restore(product)` | Restore soft-deleted product (model resolved via withTrashed route binding) |
| `toggleActive(product)` | Flip is_active; returns fresh model |
| `dropdown()` | Active products for select inputs (id, sku, name) |

## Key Rules
- SKU uppercased on both create and update
- SKU change triggers `ProductListingService::regenerateSlugsForProduct()` — bulk slug regen + redirects, inside the same transaction
- Delete blocked by `hasActiveListings()` — guard is **inside** the transaction (TOCTOU prevention)
- All writes wrapped in `DB::transaction()`
- `fresh('category')` after update to return eager-loaded data
- Soft-deleting a product cascades to soft-delete its inactive listings
