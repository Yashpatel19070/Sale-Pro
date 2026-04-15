# InventoryMovement — HTTP Feature Tests

```
Framework:   PHPUnit / Pest  (no browser, no JavaScript)
Test file:   tests/E2E/InventoryE2ETest.php
Section:     // MODULE M — InventoryMovement
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
$this->locationL1  = InventoryLocation::factory()->create(['code' => 'L1']);
$this->locationL2  = InventoryLocation::factory()->create(['code' => 'L2']);
$this->locationL45 = InventoryLocation::factory()->create(['code' => 'L45']);
```

Create serials per-test using factory states:
- `InventorySerial::factory()->inStock()->forProduct($this->product1)->atLocation($this->locationL1)->create()`
- `InventorySerial::factory()->sold()->forProduct($this->product1)->create()`
- `InventorySerial::factory()->damaged()->forProduct($this->product1)->create()`

---

## Auth & Access

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| M-01 | Unauthenticated | GET `/admin/inventory-movements` | Redirect to login |
| M-02 | `sales` | GET `/admin/inventory-movements` | 200 OK |
| M-03 | `sales` | GET `/admin/inventory-movements/create` | 200 OK |
| M-04 | `sales` | Response: index page | "Record Movement" button visible |
| M-05 | `sales` | Response: create form | Adjustment radio NOT rendered — sales lacks adjust permission |
| M-06 | `sales` | POST `{type: adjustment, inventory_serial_id: X, adjustment_status: damaged}` | 403 — `authorize()` blocks it |

---

## Transfer — Happy Path

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| M-07 | `admin` | SN-A at L1 (in_stock) | POST `{type: transfer, inventory_serial_id: SN-A, from_location_id: L1, to_location_id: L2}` | Redirect to index; serial `inventory_location_id = L2` in DB; movement row `type = transfer` created |
| M-08 | `manager` | SN-B at L45 | Transfer SN-B to L1 | Succeeds — manager has transfer permission |
| M-09 | `sales` | SN-C at L1 | Transfer SN-C to L2 | Succeeds — sales has transfer permission |

---

## Transfer — Validation

| # | Actor | Input | Expected |
|---|-------|-------|----------|
| M-10 | `admin` | `from_location_id = L2` but serial is at L1 | Validation error: "Serial is not at that location" |
| M-11 | `admin` | `from_location_id = to_location_id` (L1 → L1) | Validation error: from and to must differ |
| M-12 | `admin` | Serial `status = sold` | Validation error: "Serial is not in stock" |
| M-13 | `admin` | `to_location_id` missing | Validation error: destination location required |
| M-14 | `admin` | `inventory_serial_id` = nonexistent ID | Validation error: serial does not exist |

---

## Sale — Happy Path

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| M-15 | `admin` | SN-D at L1 (in_stock) | POST `{type: sale, inventory_serial_id: SN-D, from_location_id: L1, reference: 'ORD-001'}` | Redirect; serial `status = sold` in DB; movement `type = sale` created |
| M-16 | `sales` | SN-E at L2 | POST sale | Succeeds — sales has sell permission |

---

## Sale — Validation

| # | Actor | Input | Expected |
|---|-------|-------|----------|
| M-17 | `admin` | `from_location_id` doesn't match serial's actual location | Validation error: location mismatch |
| M-18 | `admin` | Serial `status = sold` | Validation error: not in stock |
| M-19 | `admin` | `to_location_id` included with `type = sale` | Validation error: `to_location_id` prohibited for sale |

---

## Adjustment — Happy Path

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| M-20 | `admin` | SN-F at L1 (in_stock) | POST `{type: adjustment, inventory_serial_id: SN-F, adjustment_status: damaged}` | Redirect; serial `status = damaged` in DB; movement row created |
| M-21 | `admin` | SN-G at L45 (in_stock) | POST `{type: adjustment, adjustment_status: missing}` | Serial `status = missing`; removed from stock count |
| M-22 | `manager` | SN-H in_stock | POST adjustment | Succeeds — manager has adjust permission |

---

## Adjustment — Guards

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| M-23 | `admin` | SN-F is now `damaged` | POST adjustment again on SN-F | Error: "Serial is not in stock (current status: damaged)" |
| M-24 | `admin` | SN-D is now `sold` | POST adjustment on SN-D | Error: "Serial is not in stock" |
| M-25 | `sales` | Any in_stock serial | POST `{type: adjustment, ...}` | 403 Forbidden |
| M-26 | `admin` | Any serial | POST `{type: receive, ...}` | Validation error: "Receive movements cannot be recorded manually" |

---

## Adjustment — Validation

| # | Actor | Input | Expected |
|---|-------|-------|----------|
| M-27 | `admin` | `adjustment_status = 'sold'` | Validation error: must be `damaged` or `missing` |
| M-28 | `admin` | `adjustment_status` missing when `type = adjustment` | Validation error: adjustment_status required |
| M-29 | `admin` | `from_location_id` included with `type = adjustment` | Validation error: `from_location_id` prohibited for adjustment |

---

## Movement Index — Filters

| # | Filter | Setup | Expected |
|---|--------|-------|----------|
| M-30 | `?serial_number=SN-001` | Multiple serials | Only movements for SN-001 |
| M-31 | `?location_id=L1` | Movements at various locations | Only movements involving L1 |
| M-32 | `?type=transfer` | Mix of types | Only transfer rows |
| M-33 | `?date_from=today` | Old and new movements | Only today's movements |
| M-34 | `?date_from=today&date_to=today` | Full date range | Only movements on today |
| M-35 | Reset filter (no params) | Active filters applied | All movements visible |
| M-36 | No filter, 25+ movements | — | Paginated; first page shown |

---

## Movement Immutability

| # | Action | Expected |
|---|--------|----------|
| M-37 | GET `/admin/inventory-movements/{id}/edit` | 404 — route does not exist |
| M-38 | PUT `/admin/inventory-movements/{id}` | 404 — route does not exist |
| M-39 | DELETE `/admin/inventory-movements/{id}` | 404 — route does not exist |

---

## Serial Timeline

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| M-40 | `admin` | Serial has 3 movements | GET `/admin/inventory-serials/{id}/movements` | All 3 events in response |
| M-41 | `admin` | Serial has 25 movements | GET timeline | All 25 returned — **unpaginated by design**; `historyForSerial()` returns a flat `Collection` |
| M-42 | `admin` | Serial has 0 movements | GET timeline | "No movements recorded yet" message |
| M-43 | `sales` | Any serial | GET timeline | 200 OK — all roles can view |
| M-44 | `admin` | Timeline page | — | "← Back to serial" link → `inventory-serials.show` |

---

## Notes

- Factory states: `InventoryMovement::factory()->transfer()`, `->sale()`, `->adjustment()`, `->receive()`
- Build dependency: `inventory-location` and `inventory-serial` must be migrated first.
- The `DB::transaction()` + `$serial->refresh()` TOCTOU guard is tested at unit level
  (`InventoryMovementServiceTest`) — E2E tests cover observable outcomes only.
- `sales` cannot adjust: verify both that the radio is absent in the form AND that a direct POST
  returns 403 (defence in depth — two separate assertions, M-05 and M-06).
