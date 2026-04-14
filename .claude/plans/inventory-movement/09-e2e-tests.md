# InventoryMovement — E2E Test Cases

## Test Setup

```
Roles:     admin, manager, sales
Products:  WIDGET-001, WIDGET-002 (seeded)
Locations: L1, L2, L45 (seeded)
Serials:   several in_stock serials at known locations (seeded)
```

---

## Auth & Access

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| M-01 | Unauthenticated | GET `/admin/inventory-movements` | Redirect to login |
| M-02 | `sales` | GET `/admin/inventory-movements` | 200 OK |
| M-03 | `sales` | GET `/admin/inventory-movements/create` | 200 OK — sales can access form |
| M-04 | `sales` | "Record Movement" button on index | Visible — sales has create permission |
| M-05 | `sales` | Create form: "Adjustment" radio button | NOT rendered — sales lacks ADJUST permission |
| M-06 | `sales` | POST `{type: adjustment, ...}` directly | 403 — FormRequest authorize() blocks it |

---

## Happy Path — Transfer

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| M-07 | `admin` | SN-001 at L1 (in_stock) | POST `{type: transfer, inventory_serial_id: SN-001, from_location_id: L1, to_location_id: L2}` | Redirect to index with success flash; serial now at L2; movement row type=transfer created |
| M-08 | `manager` | SN-002 at L45 | Transfer SN-002 to L1 | Succeeds — manager has transfer permission |
| M-09 | `sales` | SN-003 at L1 | Transfer SN-003 to L2 | Succeeds — sales has transfer permission |

---

## Transfer Validation

| # | Actor | Input | Expected |
|---|-------|-------|----------|
| M-10 | `admin` | `from_location_id` = L2, but SN-001 is actually at L1 | Validation error: "Serial SN-001 is not at that location" |
| M-11 | `admin` | `from_location_id` = `to_location_id` (L1 → L1) | Validation error: from and to locations must be different |
| M-12 | `admin` | Serial status = sold (not in_stock) | Validation error: "Serial is not in stock" |
| M-13 | `admin` | `to_location_id` missing | Validation error: destination location required |
| M-14 | `admin` | `inventory_serial_id` = nonexistent ID | Validation error: serial does not exist |

---

## Happy Path — Sale

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| M-15 | `admin` | SN-010 at L1 (in_stock) | POST `{type: sale, inventory_serial_id: SN-010, sale_location_id: L1, reference: 'ORD-001'}` | Redirect; serial status = sold; serial excluded from stock dashboard; movement type=sale created |
| M-16 | `sales` | SN-011 at L2 | POST sale | Succeeds — sales has sell permission |

---

## Sale Validation

| # | Actor | Input | Expected |
|---|-------|-------|----------|
| M-17 | `admin` | `sale_location_id` doesn't match serial's actual location | Validation error: location mismatch |
| M-18 | `admin` | Serial already sold | Validation error: not in stock |
| M-19 | `admin` | `to_location_id` sent with type=sale | Validation error: to_location_id is prohibited for sale type |

---

## Happy Path — Adjustment

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| M-20 | `admin` | SN-020 at L1 (in_stock) | POST `{type: adjustment, inventory_serial_id: SN-020, adjustment_status: damaged}` | Redirect; serial status = damaged; excluded from stock dashboard; movement row created |
| M-21 | `admin` | SN-021 at L45 (in_stock) | POST `{type: adjustment, adjustment_status: missing}` | Serial status = missing; removed from stock count |
| M-22 | `manager` | SN-022 in_stock | POST adjustment | Succeeds — manager has adjust permission |

---

## Adjustment Guards

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| M-23 | `admin` | SN-020 is now `damaged` | POST adjustment again on SN-020 | Error: "Serial SN-020 is not in stock (current status: damaged)" |
| M-24 | `admin` | SN-010 is now `sold` | POST adjustment on SN-010 | Error: "Serial is not in stock" |
| M-25 | `sales` | Any in_stock serial | POST `{type: adjustment, ...}` | 403 Forbidden |
| M-26 | `admin` | Any serial | POST `{type: receive, ...}` | Validation error: "Receive movements cannot be recorded manually" |

---

## Adjustment Validation

| # | Actor | Input | Expected |
|---|-------|-------|----------|
| M-27 | `admin` | `adjustment_status` = 'sold' | Validation error: must be damaged or missing |
| M-28 | `admin` | `adjustment_status` missing when type=adjustment | Validation error: adjustment_status required |
| M-29 | `admin` | `from_location_id` sent with type=adjustment | Validation error: from_location_id is prohibited for adjustment |

---

## Movement Index — Filters

| # | Filter | Setup | Expected |
|---|--------|-------|----------|
| M-30 | `serial_number=SN-001` | Multiple serials in DB | Only movements for SN-001 |
| M-31 | `location_id=L1` | Movements at various locations | Only movements involving L1 |
| M-32 | `type=transfer` | Mix of transfer/sale/adjustment | Only transfer rows |
| M-33 | `date_from=today` | Old and new movements | Only today's movements |
| M-34 | `date_from=today&date_to=today` | Full date range | Only movements on today's date |
| M-35 | Reset filter | Active filters applied | All movements visible; URL has no filter params |
| M-36 | No filter, 25+ movements | Pagination | First page shown; pagination controls visible |

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
| M-40 | `admin` | SN-001 has 3 movements | GET `/admin/inventory-serials/{id}/movements` | Timeline shows 3 events in order |
| M-41 | `admin` | SN-001 has 25 movements | GET timeline | Paginated; 15 per page; pagination links visible |
| M-42 | `admin` | SN-001 has 0 movements | GET timeline | "No movements recorded yet" message |
| M-43 | `sales` | Any serial | GET timeline | 200 OK — all roles can view |
| M-44 | `admin` | View timeline | "← Back to serial" link | Goes to `inventory-serials.show` for that serial |

---

## Create Form — Pre-population

| # | Source | Action | Expected |
|---|--------|--------|----------|
| M-45 | Serial show "Record Adjustment" link | Click link | Form opens with serial pre-selected AND type=adjustment radio checked |
| M-46 | Manual navigation | GET `/admin/inventory-movements/create` | Form opens with type=transfer selected by default; no serial pre-selected |
| M-47 | GET `?type=sale` | Open create form | Sale radio pre-checked; to_location_id row is hidden |
| M-48 | GET `?type=adjustment` | Open create form | Adjustment radio pre-checked; adjustment_status dropdown is visible |

---

## Notes

- Factory states needed:
  - `InventoryMovement::factory()->transfer()`
  - `InventoryMovement::factory()->sale()`
  - `InventoryMovement::factory()->adjustment()`
  - `InventoryMovement::factory()->receive()` — for seeding history only; not a UI action
- Build dependency: `inventory-location` and `inventory-serial` must be migrated before
  these tests run.
- The `DB::transaction()` + `$serial->refresh()` TOCTOU guard is tested at the unit level
  (InventoryMovementServiceTest). E2E tests cover the observable outcomes only.
- `sales` role cannot adjust — verify both that the radio button is absent in the form
  AND that a direct POST returns 403 (defence in depth).
