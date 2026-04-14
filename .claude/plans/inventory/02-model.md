# Inventory Module — Model

## No Eloquent Model Required

This module does **not** create its own Eloquent model. There is no `Inventory` model.

All queries use the existing `InventorySerial` model as the data source, with eager-loaded
`product` and `location` relations to avoid N+1 queries.

---

## Models Used (defined elsewhere — do not redefine here)

### `App\Models\InventorySerial`

Expected relationships on this model (must exist before implementing this module):

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SerialStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySerial extends Model
{
    protected $fillable = [
        'product_id',
        'inventory_location_id',
        'serial_number',
        'purchase_price',
        'status',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'status'      => SerialStatus::class,
            'received_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }
}
```

> If `InventorySerial` does not have `product()` and `location()` relationships defined,
> add them before implementing this module.

### `App\Models\InventoryLocation`

Expected columns and minimal relationship needed by this module:

```php
// Only these are accessed by inventory views:
$location->id
$location->code   // e.g. "L1"
$location->name   // e.g. "Shelf L1 – Row A"
$location->is_active
```

### `App\Models\Product`

Only these columns are accessed by inventory views:

```php
$product->id
$product->sku
$product->name
$product->is_active
```

---

## Why No Inventory Model?

The "inventory" in this module is not an entity — it is a derived view of serial data.
Creating an Eloquent model for a concept without its own table would:

1. Require a `getTable()` override pointing to `inventory_serials` — confusing and redundant
2. Duplicate the `InventorySerial` model's responsibility
3. Add indirection with no benefit for a read-only module

The `InventoryService` wraps all query logic. The controller and views consume the
service return values directly. No model abstraction is needed.

---

## Data Shapes Returned by Service

### `overview()` return shape

```php
// Collection<int, Collection<int, InventorySerial>>
// Outer key = product_id, inner = serials for that product
[
    1 => Collection[
        InventorySerial { product: Product{sku:'SKU-001', name:'Widget'}, ... },
        InventorySerial { product: Product{...}, ... },
    ],
    2 => Collection[ ... ],
]
```

In the view, iterate the outer collection:
```blade
@foreach ($stockOverview as $productId => $serials)
    {{ $serials->first()->product->sku }}   {{-- product name / SKU --}}
    {{ $serials->count() }}                  {{-- total on-hand --}}
@endforeach
```

### `stockBySku(Product $product)` return shape

```php
// Collection<int, Collection<int, InventorySerial>>
// Outer key = inventory_location_id, inner = serials at that location
[
    5  => Collection[
        InventorySerial { location: InventoryLocation{code:'L1', name:'Shelf L1'}, serial_number:'SN-001' },
    ],
    12 => Collection[ ... ],
]
```

### `stockBySkuAtLocation(Product $product, InventoryLocation $location)` return shape

```php
// Collection<int, InventorySerial>
// Flat list of serials for this product at this location, ordered by serial_number
[
    InventorySerial { product: Product{sku:'WIDGET-001'}, location: InventoryLocation{code:'L1'}, serial_number:'SN-001' },
    InventorySerial { product: Product{sku:'WIDGET-001'}, location: InventoryLocation{code:'L1'}, serial_number:'SN-002' },
]
```

---

## Scope Note

Do not add scopes to `InventorySerial` for this module. The filtering logic
(`where('status', SerialStatus::InStock)`) lives in `InventoryService` where it
is visible and testable. Adding a named scope would hide the filter and make the
service harder to review at a glance.
