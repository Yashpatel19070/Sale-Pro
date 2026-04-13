<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductService
{
    /**
     * Paginated list with filters.
     *
     * @param  array{search?: string, category_id?: int, active?: bool}  $filters
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
     * redirect records — atomic, inside the same transaction.
     *
     * @param  array<string, mixed>  $data
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
                app(ProductListingService::class)
                    ->regenerateSlugsForProduct($product->fresh());
            }

            return $product->fresh('category');
        });
    }

    /**
     * Soft-delete a product. Throws if active listings exist.
     * Guard is inside the transaction to prevent a TOCTOU race.
     */
    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product): void {
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
