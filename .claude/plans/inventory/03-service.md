# Inventory Module — Service

## File
`app/Services/InventoryService.php`

---

## Responsibilities

- All query logic for stock data lives here
- Returns plain Collection objects — no HTTP concerns
- HTTP-agnostic: can be called from controller, job, or test identically
- No `DB::transaction()` needed — this is a read-only service

---

## Full Code

```php
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
     * The count of each group = qty on hand for that product.
     *
     * @return Collection<int, Collection<int, InventorySerial>>
     */
    public function overview(): Collection
    {
        // NOTE: Loads all in_stock serials in a single query then groups in PHP.
        // Acceptable for V1 small-to-medium warehouses (under ~5,000 total serials).
        // V2: Replace with a DB-level GROUP BY aggregation query + pagination
        // when the warehouse grows beyond that threshold.
        //
        // whereHas('product') excludes serials whose product has been soft-deleted.
        // Those serials are surfaced separately via orphanedSerialCount() and shown
        // as a warning notice in the view — they are not silently hidden.
        return InventorySerial::with('product')
            ->whereHas('product')
            ->where('status', SerialStatus::InStock)
            ->orderBy('product_id')
            ->get()
            ->groupBy('product_id');
    }

    /**
     * Count of in_stock serials whose product has been soft-deleted.
     * Used to render the orphaned-serials notice on the stock overview dashboard.
     * Returns 0 when all products are active.
     */
    public function orphanedSerialCount(): int
    {
        return InventorySerial::whereDoesntHave('product')
            ->where('status', SerialStatus::InStock)
            ->count();
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
```

---

## Method Contracts

| Method | Accepts | Returns | Notes |
|--------|---------|---------|-------|
| `overview()` | — | `Collection<int, Collection<int, InventorySerial>>` | Keyed by product_id. Excludes serials with soft-deleted products. |
| `orphanedSerialCount()` | — | `int` | Count of in_stock serials whose product is soft-deleted. 0 = no orphans. |
| `stockBySku(Product $product)` | Eloquent model | `Collection<int, Collection<int, InventorySerial>>` | Keyed by location_id |
| `stockBySkuAtLocation(Product $product, InventoryLocation $location)` | Two Eloquent models | `Collection<int, InventorySerial>` | Flat, ordered by serial_number |

---

## Eager Loading — No N+1

| Method | Eager loads |
|--------|-------------|
| `overview()` | `product` via `whereHas('product')` — serials with soft-deleted products are excluded and counted separately by `orphanedSerialCount()` |
| `stockBySku()` | `location` (to get code/name for each group header) |
| `stockBySkuAtLocation()` | `product`, `location` (both needed for the detail view header) |

Never access `$serial->product` or `$serial->location` without eager loading.

---

## What the Service Does NOT Do

- Does not filter by `is_active` on Product or InventoryLocation — the stock dashboard
  shows stock reality, not product visibility. A product may be inactive but still
  have physical serials on shelves. However, **soft-deleted products** are excluded from
  `overview()` to prevent a null-access crash in the view (product relation returns null
  for deleted records). These orphaned serials are surfaced via `orphanedSerialCount()`
  and shown as a yellow warning notice in the view so admins are aware of them.
- Does not paginate — the collections are grouped in PHP after a single DB query.
  For very large warehouses, pagination can be added in a V2 iteration.
- Does not write anything — no `DB::transaction()`, no model mutations.
- Does not accept `$request` — HTTP-agnostic by design.

---

## Empty Results

All three methods return an empty `Collection` when no in_stock serials exist.
The views must handle this gracefully with an empty state message.

```php
// Overview with no stock
$stockOverview->isEmpty() // true → show "No stock on hand" message

// Stock by SKU with no stock for this product
$stockByLocation->isEmpty() // true → show "No stock at any location" message

// SKU at Location with no serials
$serials->isEmpty() // true → show "No in_stock serials for this SKU at this location" message
```
