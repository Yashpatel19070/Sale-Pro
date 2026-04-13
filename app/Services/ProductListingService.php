<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;

// Stub service — full implementation in the product-list module.
class ProductListingService
{
    /**
     * Regenerate slugs for all non-trashed listings of the given product.
     * Called automatically when a product's SKU changes.
     * Full implementation provided by the product-list module.
     */
    public function regenerateSlugsForProduct(Product $product): void
    {
        // no-op until product-list module is implemented
    }
}
