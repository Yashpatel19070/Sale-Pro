# Inventory Module — Overview (Stock Visibility Layer)

## Business Context

This module is the **stock visibility layer** — it has NO separate database table of its own.
It is a read-only module that provides two focused views into stock data that already exists
in `inventory_serials`.

```
Product: WIDGET-001
  ├── Shelf L1   → serial SN-001, SN-002   (2 in_stock)
  ├── Shelf L99  → serial SN-003, SN-004   (2 in_stock)
  └── Shelf L45  → serial SN-005           (1 in_stock)
                   ──────────────────────
  Total on-hand: 5 serials with status = in_stock
```

Think of this as a **stock dashboard** — no writes, only reads and aggregations.

---

## Prerequisites
- `inventory-location` module fully built and migrated
- `inventory-serial` module fully built and migrated
- `inventory-movement` module fully built and migrated

---

## Module Boundary

- **Admin-only** — no customer portal exposure
- **Read-only** — no create / edit / delete routes at all
- **No own table** — all data lives in `inventory_serials`
- **Three views**: stock dashboard → SKU detail (locations + counts) → serials at one SKU+location

---

## Existing Data Models (already built — this module does NOT create them)

| Model | Table | Key columns |
|-------|-------|-------------|
| `Product` | `products` | `id`, `sku`, `name`, `is_active` |
| `InventoryLocation` | `inventory_locations` | `id`, `code`, `name`, `is_active` |
| `InventorySerial` | `inventory_serials` | `id`, `product_id`, `inventory_location_id`, `serial_number`, `status`, `received_at` |

`InventorySerial.status` is an enum: `in_stock | sold | damaged | missing`.
Only serials with `status = in_stock` count toward available stock.

---

## Dependency Diagram

```
Product (existing)
  └── InventorySerial (existing)
        └── InventoryLocation (existing)

InventoryController (new)
  └── InventoryService (new)
        ├── queries InventorySerial::with(['product'])          — overview
        ├── queries InventorySerial::with(['location'])         — stockBySku
        └── queries InventorySerial::with(['product','location']) — stockBySkuAtLocation

InventoryPolicy (new)
  └── policy methods: viewAny / viewBySku / viewBySkuAtLocation

Navigation drill-down:
  Stock Dashboard → click SKU → SKU Detail (locations + counts) → click location → Serials (SKU + location)
```

---

## Features (V1 — read only)

| # | Feature | Description |
|---|---------|-------------|
| 1 | Stock dashboard | All SKUs with total in_stock serial count |
| 2 | Stock by SKU | Locations holding that SKU + count at each — click location to drill in |
| 3 | SKU at Location | All serial numbers for one SKU at one specific location |

---

## Query Patterns

```php
// Stock overview — total in_stock per product
InventorySerial::with('product')
    ->where('status', SerialStatus::InStock)
    ->get()
    ->groupBy('product_id');

// Stock by SKU (product_id given)
InventorySerial::with('location')
    ->where('product_id', $product->id)
    ->where('status', SerialStatus::InStock)
    ->get()
    ->groupBy('inventory_location_id');

// SKU at Location (product_id + inventory_location_id given)
InventorySerial::with(['product', 'location'])
    ->where('product_id', $product->id)
    ->where('inventory_location_id', $location->id)
    ->where('status', SerialStatus::InStock)
    ->orderBy('serial_number')
    ->get();
```

> **Why no SQL view or stored count?** Serials have status changes (sold, damaged) that are
> already tracked in `inventory_serials`. Deriving counts from live rows prevents drift.

---

## File Map

| File | Path |
|------|------|
| Service | `app/Services/InventoryService.php` |
| Controller | `app/Http/Controllers/InventoryController.php` |
| Policy | `app/Policies/InventoryPolicy.php` |
| View: dashboard | `resources/views/inventory/index.blade.php` |
| View: by SKU | `resources/views/inventory/show-by-sku.blade.php` |
| View: SKU at location | `resources/views/inventory/show-by-sku-at-location.blade.php` |
| Feature test | `tests/Feature/InventoryControllerTest.php` |
| Unit test | `tests/Unit/Services/InventoryServiceTest.php` |

---

## Files to Modify

| File | Change |
|------|--------|
| `app/Enums/Permission.php` | Add `INVENTORY_VIEW_ANY`, `INVENTORY_VIEW_BY_SKU`, `INVENTORY_VIEW_BY_SKU_AT_LOCATION` constants |
| `app/Providers/AppServiceProvider.php` | Register `InventoryPolicy` (gate for `inventory` resource) |
| `routes/web.php` | Add 3 read-only inventory routes inside admin middleware group |

---

## Role Access Matrix

| Permission | admin | manager | sales |
|------------|:-----:|:-------:|:-----:|
| View stock dashboard | ✅ | ✅ | ✅ |
| Stock by SKU | ✅ | ✅ | ✅ |
| SKU at Location | ✅ | ✅ | ✅ |

All three roles have full read access. There are no write permissions.
`super-admin` bypasses all gate checks via `Gate::before()` — not listed explicitly.

---

## Implementation Order

1. Add permission constants to `app/Enums/Permission.php`
2. Register `InventoryPolicy` in `AppServiceProvider`
3. Write `InventoryService` (queries only — no writes)
4. Write `InventoryController` (3 read actions)
5. Add routes to `routes/web.php`
6. Write 3 Blade views
7. Write Pest feature + unit tests

---

## Key Rules

- **Read-only** — absolutely no create / edit / delete routes or service methods
- **Only `in_stock` serials count** — always filter `where('status', SerialStatus::InStock)`
- **Eager load everything** — no N+1 queries; always use `with(['relation'])`
- **No FormRequests** — read-only module, no user input to validate beyond route model binding
- **No seeder** — permissions are added to the existing `RolesAndPermissionsSeeder` (or a dedicated `InventoryPermissionSeeder`)
- **Roles**: `admin`, `manager`, `sales` — NOT 'staff'; `super-admin` bypasses gate via `Gate::before()`
- `strict_types=1` on every PHP file
- `$this->authorize()` on every controller action via the Policy
