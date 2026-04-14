# Inventory Module — Schema

## No Migration Required

This module has **no database table of its own**. It derives all data from the
`inventory_serials` table which is managed by the inventory serials module.

**Do not create a migration for this module.**

---

## Existing Tables Used (read only)

### `products`
```
id              bigint PK
sku             varchar — e.g. "WIDGET-001"
name            varchar
regular_price   decimal(10,2)
sale_price      decimal(10,2) nullable
is_active       boolean
timestamps
softDeletes
```

### `inventory_locations`
```
id              bigint PK
code            varchar — e.g. "L1", "L99", "ZONE-A-BIN-3"
name            varchar — human label: "Shelf L1 – Row A"
is_active       boolean default true
timestamps
```

### `inventory_serials`
```
id                      bigint PK
product_id              FK → products.id
inventory_location_id   FK → inventory_locations.id nullable
serial_number           varchar — unique serial / barcode
purchase_price          decimal(10,2)
status                  enum(in_stock, sold, damaged, missing)
received_at             datetime
timestamps
```

> `inventory_location_id` can be NULL when a serial has been sold, lost, or is not yet
> assigned to a shelf. The stock views only show serials where `status = in_stock`,
> so unlocated serials are automatically excluded.

---

## Query Patterns

### 1. Stock Overview — total in_stock count per product

```php
// Returns a Collection keyed by product_id
// Each value is a Collection of InventorySerial models with their 'product' relation loaded.
$rows = InventorySerial::with('product')
    ->where('status', SerialStatus::InStock)
    ->orderBy('product_id')
    ->get()
    ->groupBy('product_id');
```

Produces a structure like:
```
[
  1 => [InventorySerial{product_id:1, ...}, InventorySerial{product_id:1, ...}],
  2 => [InventorySerial{product_id:2, ...}],
  ...
]
```

The count of each group = `qty_on_hand` for that product across all locations.

### 2. Stock by SKU — serials per location for one product

```php
$serials = InventorySerial::with('location')
    ->where('product_id', $product->id)
    ->where('status', SerialStatus::InStock)
    ->orderBy('inventory_location_id')
    ->get()
    ->groupBy('inventory_location_id');
```

Result:
```
[
  5  => [InventorySerial{location_id:5, serial_number:'SN-001'}, ...],
  12 => [InventorySerial{location_id:12, serial_number:'SN-009'}, ...],
]
```

Each group key is a location ID. `$group->first()->location` gives the `InventoryLocation` model.

### 3. Stock by Location — serials per product for one shelf

```php
$serials = InventorySerial::with('product')
    ->where('inventory_location_id', $location->id)
    ->where('status', SerialStatus::InStock)
    ->orderBy('product_id')
    ->get()
    ->groupBy('product_id');
```

Result:
```
[
  3 => [InventorySerial{product_id:3, serial_number:'SN-042'}, ...],
  7 => [InventorySerial{product_id:7, serial_number:'SN-055'}, ...],
]
```

---

## Indexes (already expected on inventory_serials)

The queries above are efficient only with the following indexes. These should be created
in the `inventory_serials` migration (not here):

```sql
-- Filter by status — used in every query
INDEX idx_inventory_serials_status (status)

-- Stock by SKU
INDEX idx_inventory_serials_product_status (product_id, status)

-- Stock by Location
INDEX idx_inventory_serials_location_status (inventory_location_id, status)
```

If these indexes are missing, add them in the `inventory_serials` migration or a
new standalone migration in the serials module — not here.

---

## SerialStatus Enum (expected location)

The `status` column maps to a PHP-backed enum. Expected at:
`app/Enums/SerialStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum SerialStatus: string
{
    case InStock  = 'in_stock';
    case Sold     = 'sold';
    case Damaged  = 'damaged';
    case Missing  = 'missing';
}
```

If this enum does not exist yet, it must be created by the inventory serials module
before this module is implemented. This module only reads it — it does not own it.
