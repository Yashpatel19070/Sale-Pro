# InventoryLocation — HTTP Feature Tests

```
Framework:   PHPUnit / Pest  (no browser, no JavaScript)
Test file:   tests/E2E/InventoryE2ETest.php
Section:     // MODULE L — InventoryLocation
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

$this->locationL1  = InventoryLocation::factory()->create(['code' => 'L1',  'name' => 'Shelf L1']);
$this->locationL2  = InventoryLocation::factory()->create(['code' => 'L2',  'name' => 'Shelf L2']);
$this->locationL45 = InventoryLocation::factory()->create(['code' => 'L45', 'name' => 'Shelf L45']);
```

> No Playwright tests for this module. Location CRUD is pure form/redirect logic with no
> JavaScript behavior — HTTP feature tests cover 100% of the cases.

---

## Auth & Access

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| L-01 | Unauthenticated | GET `/admin/inventory-locations` | Redirect to login |
| L-02 | `sales` | GET `/admin/inventory-locations` | 200 OK — list visible |
| L-03 | `sales` | GET `/admin/inventory-locations/create` | 403 Forbidden |
| L-04 | `sales` | POST `/admin/inventory-locations` | 403 Forbidden |
| L-05 | `sales` | GET `/admin/inventory-locations/{id}/edit` | 403 Forbidden |
| L-06 | `sales` | PUT `/admin/inventory-locations/{id}` | 403 Forbidden |
| L-07 | `sales` | DELETE `/admin/inventory-locations/{id}` | 403 Forbidden |
| L-08 | `sales` | POST `/admin/inventory-locations/{id}/restore` | 403 Forbidden |

---

## Happy Path — Create

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| L-09 | `admin` | GET `/admin/inventory-locations/create` | 200 — form renders |
| L-10 | `admin` | POST `{code: 'L99', name: 'Shelf L99'}` | Redirect to show; row in DB |
| L-11 | `manager` | POST `{code: 'L100', name: 'Shelf L100'}` | 200 — manager has full access |

---

## Validation — Create

| # | Actor | Input | Expected |
|---|-------|-------|----------|
| L-12 | `admin` | `code` blank | Re-render with "code required" error |
| L-13 | `admin` | `name` blank | Re-render with "name required" error |
| L-14 | `admin` | `code` = existing active code `L1` | Re-render with "code already taken" error |
| L-15 | `admin` | `code` = code of a soft-deleted location | **Succeeds** — composite `UNIQUE(code, deleted_at)` index + `withoutTrashed()` rule both allow reuse |
| L-16 | `admin` | `code` = 51 characters | Re-render with max-length error |
| L-17 | `admin` | `name` = 201 characters | Re-render with max-length error |

---

## Happy Path — Edit

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| L-18 | `admin` | GET `/admin/inventory-locations/{id}/edit` | 200 — form renders; code shown as read-only (not editable) |
| L-19 | `admin` | PUT `{name: 'Updated Name', description: 'new desc'}` | Redirect to show; new name in DB |

---

## Deactivate / Restore

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| L-20 | `admin` | Location has no in_stock serials | DELETE `/admin/inventory-locations/{id}` | Soft-deleted; `deleted_at` set in DB |
| L-21 | `admin` | Location has ≥1 in_stock serial | DELETE `/admin/inventory-locations/{id}` | 422 — "Cannot deactivate: location has active stock" |
| L-22 | `admin` | Location is soft-deleted | POST `/admin/inventory-locations/{id}/restore` | `deleted_at` cleared; back in active list |
| L-23 | `admin` | Active location | DELETE then POST restore | Status returns to active |

---

## List Behaviour

| # | Actor | Setup | Expected |
|---|-------|-------|----------|
| L-24 | `admin` | 25 locations exist | First page shown; pagination links in response |
| L-25 | `admin` | Soft-deleted location exists | Does NOT appear in main list |
| L-26 | `admin` | Soft-deleted location `L2` | `assertDontSee('L2 — Shelf L2')` — use the full option text, NOT just `'L2'`, which appears in SVG logo path data (`L251`, `L248`) and causes a false positive |

---

## Notes

- `restore` route uses `{id}` integer, not `{inventoryLocation}` — route model binding excludes
  soft-deleted records. Confirm that GET `/{soft_deleted_id}` returns 404 but POST `restore` succeeds.
- Factory state needed: `InventoryLocation::factory()->softDeleted()`
- **L-15:** DB has composite `UNIQUE(code, deleted_at)`. MySQL treats `NULL` as distinct, so a new
  row with the same code can coexist alongside a soft-deleted row. Without the composite index the
  FormRequest passes but the DB INSERT fails with a 500 constraint violation.
- **L-26:** Do not assert `assertDontSee('L2')`. The app layout SVG logo contains substrings like
  `L251`, `L248`, `L226` — all match `L2` and cause a false positive. Assert the full option label.
