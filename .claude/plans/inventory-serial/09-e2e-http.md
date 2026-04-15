# InventorySerial — HTTP Feature Tests

```
Framework:   PHPUnit / Pest  (no browser, no JavaScript)
Test file:   tests/E2E/InventoryE2ETest.php
Section:     // MODULE S — InventorySerial
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
$this->locationL1  = InventoryLocation::factory()->create(['code' => 'L1', 'name' => 'Shelf L1']);
$this->locationL2  = InventoryLocation::factory()->create(['code' => 'L2', 'name' => 'Shelf L2']);
$this->locationL45 = InventoryLocation::factory()->create(['code' => 'L45', 'name' => 'Shelf L45']);
```

> No Playwright tests for this module. Serial receive/edit is pure form and validation logic.
> HTTP feature tests cover all cases including `purchase_price` visibility (rendered vs not
> rendered in response HTML) and all validation errors.

---

## Auth & Access

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| S-01 | Unauthenticated | GET `/admin/inventory-serials` | Redirect to login |
| S-02 | `sales` | GET `/admin/inventory-serials` | 200 OK |
| S-03 | `sales` | GET `/admin/inventory-serials/create` | 200 OK — sales can receive stock |
| S-04 | `sales` | POST valid serial | Redirect to show |
| S-05 | `sales` | GET `/admin/inventory-serials/{id}/edit` | 403 Forbidden — `sales` role lacks `inventory-serials.edit` permission |

---

## Happy Path — Receive (Create)

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| S-06 | `admin` | POST `{serial_number: 'SN-001', product_id: WIDGET-001, inventory_location_id: L1, purchase_price: 49.99, received_at: today}` | Redirect to show; `status = in_stock`; one `receive` movement row created automatically |
| S-07 | `admin` | POST `serial_number: 'SN-001'` again (same product) | Validation error: "This serial number already exists in the system" |
| S-08 | `admin` | POST `serial_number: 'SN-001'` for a **different** product | Validation error — serial numbers are **globally unique** (physically stamped on hardware; never reused across items) |
| S-09 | `admin` | POST with inactive location (`is_active = false`) | Validation error: "location does not exist or is no longer active" |
| S-09b | `admin` | POST with soft-deleted location (was active before deletion) | Validation error — `whereNull('deleted_at')` check catches it independently of `is_active` |
| S-10 | `admin` | POST with `purchase_price = 0` | Succeeds — zero price is valid |
| S-11 | `admin` | POST with `purchase_price = -1` | Validation error: must be ≥ 0 |

---

## Serial Show Page

| # | Actor | Setup | Expected |
|---|-------|-------|----------|
| S-12 | `admin` | Serial has 5 movements | Show page response contains all 5 in movement table |
| S-13 | `admin` | Serial has 25 movements | Response contains paginated list; `assertSee` pagination text |
| S-14 | `admin` | Serial `status = in_stock` | Response contains "Record Adjustment" link to `inventory-movements.create?serial_id={id}&type=adjustment` |
| S-15 | `admin` | Serial `status = sold` | Response does NOT contain "Record Adjustment" link |
| S-16 | `admin` | Serial `status = damaged` | Response does NOT contain "Record Adjustment" link |
| S-17 | `admin` | View any serial | `purchase_price` value is visible in response HTML |
| S-18 | `manager` | View any serial | `purchase_price` value is visible in response HTML |
| S-19 | `sales` | View any serial | `purchase_price` section is NOT rendered — `assertDontSee` the value |

---

## Edit Notes

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| S-20 | `admin` | PUT `{notes: 'Damaged corner box'}` | Redirect to show; notes updated in DB |
| S-21 | `admin` | PUT body includes `serial_number` | Ignored — not in validated fields; serial_number unchanged in DB |
| S-22 | `admin` | PUT body includes `purchase_price` | Ignored — not in validated fields; purchase_price unchanged in DB |
| S-23 | `admin` | PUT `{notes: str_repeat('x', 5001)}` | Validation error: max 5000 chars |
| S-23b | `admin` | PUT `{notes: str_repeat('x', 5000)}` | Succeeds — boundary value accepted |

---

## List Behaviour

| # | Actor | Setup | Expected |
|---|-------|-------|----------|
| S-24 | `admin` | 25 serials exist | Paginated list in response |
| S-25 | `admin` | Mix of in_stock, sold, damaged serials | All statuses visible — list is not filtered by status |
| S-26 | `sales` | View list | `purchase_price` column NOT rendered |

---

## Notes

- Factory states needed:
  - `InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)`
  - `InventorySerial::factory()->sold()->forProduct($product)`
  - `InventorySerial::factory()->damaged()->forProduct($product)`
- The `receive` movement is created automatically inside `InventorySerialService::receive()`.
  S-06 must assert the movement row exists in DB after a successful store.
- **Serial number uniqueness:** Globally unique — `Rule::unique('inventory_serials', 'serial_number')->withoutTrashed()` with NO `where('product_id', ...)` scope. Real-world hardware serial numbers are stamped on the device and never repeated.
- **Location validation:** Uses `Rule::exists('inventory_locations', 'id')->where('is_active', true)->whereNull('deleted_at')`. A bare `exists:inventory_locations,id` silently accepts soft-deleted locations — do NOT use it.
- **Notes max length:** 5000 characters — enforced in both `StoreInventorySerialRequest` and `UpdateInventorySerialRequest`.
- Build dependency: `inventory-location` migrations must run before these tests.
