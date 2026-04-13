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
                isset($filters['active']),
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
        if ($product->hasActiveListings()) {
            throw new RuntimeException(
                "Cannot delete \"{$product->name}\" — it has active listings. Deactivate or delete the listings first."
            );
        }

        DB::transaction(function () use ($product): void {
            // Soft-delete inactive listings too (no orphans)
            $product->listings()->delete();
            $product->delete();
        });
    }

    /**
     * Restore a soft-deleted product.
     */
    public function restore(int $id): Product
    {
        $product = Product::onlyTrashed()->findOrFail($id);
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
| `restore(id)` | Restore soft-deleted product by ID |
| `toggleActive(product)` | Flip is_active; returns fresh model |
| `dropdown()` | Active products for select inputs (id, sku, name) |

## Key Rules
- SKU uppercased on both create and update
- SKU change triggers `ProductListingService::regenerateSlugsForProduct()` — bulk slug regen + redirects, inside the same transaction
- Delete blocked by `hasActiveListings()` — throw `RuntimeException` with friendly message
- All writes wrapped in `DB::transaction()`
- `fresh('category')` after update to return eager-loaded data
- Soft-deleting a product cascades to soft-delete its inactive listings
