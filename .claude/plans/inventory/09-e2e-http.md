# Inventory (Stock View) — HTTP Feature Tests

```
Framework:   PHPUnit / Pest  (no browser, no JavaScript)
Test file:   tests/E2E/InventoryE2ETest.php
Section:     // MODULE I — Stock View
Run command: php artisan test --testsuite=E2E
```

## Seed / beforeEach (already in file — do NOT duplicate)

```php
$this->seed(RoleSeeder::class);
$this->seed(InventoryPermissionSeeder::class);
$this->seed(InventoryLocationPermissionSeeder::class);
$this->seed(InventorySerialPermissionSeeder::class);
$this->seed(InventoryMovementPermissionSeeder::class);

$this->admin   = User::factory()->create()->assignRole('admin');
$this->manager = User::factory()->create()->assignRole('manager');
$this->sales   = User::factory()->create()->assignRole('sales');

$this->product1  = Product::factory()->create(['sku' => 'WIDGET-001']);
$this->product2  = Product::factory()->create(['sku' => 'WIDGET-002']);
$this->locationL1  = InventoryLocation::factory()->create(['code' => 'L1']);
$this->locationL2  = InventoryLocation::factory()->create(['code' => 'L2']);
$this->locationL45 = InventoryLocation::factory()->create(['code' => 'L45']);
```

Use HTTP tests for: auth/403/404, count logic, orphan notice, empty state, view data assertions.
Navigation drill-downs and user journeys live in `09-e2e-playwright.md`.

---

## Auth & Access

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| I-01 | Unauthenticated | GET `/admin/inventory` | Redirect to login |
| I-02 | `admin` | GET `/admin/inventory` | 200 OK |
| I-03 | `manager` | GET `/admin/inventory` | 200 OK |
| I-04 | `sales` | GET `/admin/inventory` | 200 OK — all 3 roles have read access |

---

## Stock Dashboard — Count Logic

| # | Setup | Expected |
|---|-------|----------|
| I-05 | WIDGET-001: 3 in_stock (L1=2, L45=1), 1 sold | `viewData('stockOverview')` for WIDGET-001 has count = 3 (sold excluded) |
| I-06 | WIDGET-002: only sold/damaged serials | WIDGET-002 key does NOT appear in `stockOverview` |
| I-07 | No serials at all | Empty state message rendered in response |
| I-08 | Product is soft-deleted, has 1 in_stock serial | Product excluded from `stockOverview`; `$orphanedSerialCount = 1`; yellow warning notice rendered: "1 serial not shown — their product has been archived." |

> **I-08 implementation:** `InventoryService::overview()` uses `->whereHas('product')` to
> exclude serials whose product is soft-deleted (prevents null-access on `$serial->product->sku`).
> Controller calls `orphanedSerialCount()` and passes it to the view.

---

## Drill-Down: Stock by SKU — 404s

| # | Setup | Action | Expected |
|---|-------|--------|----------|
| I-11 | Unknown product ID | GET `/admin/inventory/99999` | 404 |

---

## Drill-Down: Serials at SKU + Location — 404s

| # | Setup | Action | Expected |
|---|-------|--------|----------|
| I-16 | Unknown location ID | GET `/admin/inventory/{product_id}/99999` | 404 |
| I-17 | Soft-deleted location | GET `inventory.by-sku-at-location` for deleted location | 404 — route model binding excludes soft-deleted |

---

## Notes

- All data mutations happen via `inventory-serial` or `inventory-movement` modules.
  This module is read-only — tests assert display logic only.
- Factory states needed:
  - `InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)`
  - `InventorySerial::factory()->sold()->forProduct($product)`
  - `InventorySerial::factory()->damaged()->forProduct($product)`
- The `tests/E2E` directory is registered as the `E2E` testsuite in `phpunit.xml`.
