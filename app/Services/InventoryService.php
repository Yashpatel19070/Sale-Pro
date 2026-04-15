<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SerialStatus;
use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Product;
use Illuminate\Support\Collection;

class InventoryService
{
    /**
     * Stock overview: total in_stock serial count per product, across all locations.
     *
     * Returns a Collection keyed by product_id.
     * Each value is a Collection of InventorySerial models (with 'product' loaded).
     *
     * NOTE: Loads all in_stock serials in a single query then groups in PHP.
     * Acceptable for V1 small-to-medium warehouses (under ~5,000 total serials).
     *
     * @return Collection<int, Collection<int, InventorySerial>>
     */
    public function overview(): Collection
    {
        return InventorySerial::with('product')
            ->where('status', SerialStatus::InStock)
            ->orderBy('product_id')
            ->get()
            ->groupBy('product_id');
    }

    /**
     * Stock by SKU: all in_stock serials for a product, grouped by location.
     *
     * Returns a Collection keyed by inventory_location_id.
     * Each value is a Collection of InventorySerial models (with 'location' loaded).
     *
     * @return Collection<int, Collection<int, InventorySerial>>
     */
    public function stockBySku(Product $product): Collection
    {
        return InventorySerial::with('location')
            ->where('product_id', $product->id)
            ->where('status', SerialStatus::InStock)
            ->orderBy('inventory_location_id')
            ->get()
            ->groupBy('inventory_location_id');
    }

    /**
     * SKU at Location: all in_stock serials for one product at one specific location.
     *
     * Returns a flat Collection of InventorySerial models ordered by serial_number.
     * Both 'product' and 'location' relations are eager-loaded.
     *
     * @return Collection<int, InventorySerial>
     */
    public function stockBySkuAtLocation(Product $product, InventoryLocation $location): Collection
    {
        return InventorySerial::with(['product', 'location'])
            ->where('product_id', $product->id)
            ->where('inventory_location_id', $location->id)
            ->where('status', SerialStatus::InStock)
            ->orderBy('serial_number')
            ->get();
    }
}
