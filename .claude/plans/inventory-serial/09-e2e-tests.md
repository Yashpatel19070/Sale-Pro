# InventorySerial — E2E Test Cases

## Test Setup

```
Roles:     admin, manager, sales
Products:  WIDGET-001, WIDGET-002 (seeded via ProductSeeder)
Locations: L1, L2, L45 (seeded via InventoryLocationSeeder — must run first)
```

---

## Auth & Access

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| S-01 | Unauthenticated | GET `/admin/inventory-serials` | Redirect to login |
| S-02 | `sales` | GET `/admin/inventory-serials` | 200 OK |
| S-03 | `sales` | GET `/admin/inventory-serials/create` | 200 OK — sales can create |
| S-04 | `sales` | POST valid serial | Redirect to show |
| S-05 | `sales` | GET `/admin/inventory-serials/{id}/edit` | 200 OK — sales can edit notes |

---

## Happy Path — Receive (Create)

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| S-06 | `admin` | POST `{serial_number: 'SN-001', product_id: WIDGET-001, inventory_location_id: L1, purchase_price: 49.99, received_at: today}` | Redirect to show; status = in_stock; one `receive` movement row created automatically |
| S-07 | `admin` | POST SN-001 again for the same product | Validation error: "serial number already used for this product" |
| S-08 | `admin` | POST SN-001 for a different product | Succeeds — serial_number unique per product only |
| S-09 | `admin` | POST with inactive (soft-deleted) location | Validation error: "location is not active" |
| S-10 | `admin` | POST with `purchase_price` = 0 | Succeeds — zero price is valid |
| S-11 | `admin` | POST with negative `purchase_price` | Validation error: must be ≥ 0 |

---

## Serial Show Page

| # | Actor | Setup | Expected |
|---|-------|-------|----------|
| S-12 | `admin` | Serial has 5 movements | Show page displays all 5 in movement table; no pagination |
| S-13 | `admin` | Serial has 25 movements | Show page shows first 15; pagination links appear |
| S-14 | `admin` | Serial status = in_stock | "Record Adjustment" link present; goes to `inventory-movements.create?serial_id={id}&type=adjustment` |
| S-15 | `admin` | Serial status = sold | "Record Adjustment" link NOT shown |
| S-16 | `admin` | Serial status = damaged | "Record Adjustment" link NOT shown |
| S-17 | `admin` | View any serial | `purchase_price` is visible (admin has viewPurchasePrice) |
| S-18 | `manager` | View any serial | `purchase_price` is visible (manager has viewPurchasePrice) |
| S-19 | `sales` | View any serial | `purchase_price` section is NOT rendered at all |

---

## Edit Notes

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| S-20 | `admin` | PUT `{notes: 'Damaged corner box'}` | Redirect to show; notes updated |
| S-21 | `admin` | PUT with `serial_number` in body | Ignored — serial_number not in validated fields; remains unchanged |
| S-22 | `admin` | PUT with `purchase_price` in body | Ignored — purchase_price not in validated fields |
| S-23 | `admin` | PUT `{notes: 2001 characters}` | Validation error: max 2000 chars |

---

## List Behaviour

| # | Actor | Setup | Expected |
|---|-------|-------|----------|
| S-24 | `admin` | 25 serials exist | Paginated list |
| S-25 | `admin` | Mix of in_stock, sold, damaged serials | All statuses visible — list is not filtered by status |
| S-26 | `sales` | View list | `purchase_price` column NOT rendered |

---

## Notes

- Factory states needed:
  - `InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)`
  - `InventorySerial::factory()->sold()`
  - `InventorySerial::factory()->damaged()`
- The `receive` movement is created automatically inside `InventorySerialService::receive()`.
  Feature tests should verify the movement row exists after a successful store.
- `markDamaged()` and `markMissing()` routes do NOT exist on this module. Status changes
  go through `inventory-movements.create` with type=adjustment.
- Build dependency: `inventory-location` module must be migrated before these tests run.
