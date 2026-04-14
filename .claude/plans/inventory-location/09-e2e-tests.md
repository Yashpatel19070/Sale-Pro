# InventoryLocation — E2E Test Cases

## Test Setup

```
Roles:     admin, manager, sales
Locations: L1, L2, L45 (seeded via InventoryLocationSeeder)
```

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
| L-09 | `admin` | GET `/admin/inventory-locations/create` | Form renders |
| L-10 | `admin` | POST valid `{code: 'L99', name: 'Shelf L99'}` | Redirect to show; L99 appears in list |
| L-11 | `manager` | POST valid `{code: 'L100', name: 'Shelf L100'}` | 200 — manager has full access |

---

## Validation — Create

| # | Actor | Input | Expected |
|---|-------|-------|----------|
| L-12 | `admin` | `code` blank | Re-render with "code required" error |
| L-13 | `admin` | `name` blank | Re-render with "name required" error |
| L-14 | `admin` | `code` = existing active code `L1` | Re-render with "code already taken" error |
| L-15 | `admin` | `code` = code of a soft-deleted location | SUCCEEDS — `withoutTrashed()` means deleted codes are reusable |
| L-16 | `admin` | `code` = 51 characters | Re-render with max-length error |
| L-17 | `admin` | `name` = 201 characters | Re-render with max-length error |

---

## Happy Path — Edit

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| L-18 | `admin` | GET `/admin/inventory-locations/{id}/edit` | Form renders; code shown as read-only display (not editable input) |
| L-19 | `admin` | PUT `{name: 'Updated Name', description: 'new desc'}` | Redirect to show; new name visible |

---

## Deactivate / Restore

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| L-20 | `admin` | Location has no in_stock serials | DELETE `/admin/inventory-locations/{id}` | Soft-deleted; disappears from active list |
| L-21 | `admin` | Location has ≥1 in_stock serial | DELETE `/admin/inventory-locations/{id}` | 422 — "Cannot deactivate: location has active stock" |
| L-22 | `admin` | Location is soft-deleted | POST `/admin/inventory-locations/{id}/restore` | Location reappears in active list |
| L-23 | `admin` | Active location | DELETE then POST restore | Location status returns to active |

---

## List Behaviour

| # | Actor | Setup | Expected |
|---|-------|-------|----------|
| L-24 | `admin` | 25 locations exist | List shows first page; pagination links appear |
| L-25 | `admin` | Soft-deleted location exists | Soft-deleted record does NOT appear in main list |
| L-26 | `admin` | Soft-deleted location exists | Does NOT appear in serial create / movement dropdowns |

---

## Notes

- `restore` route uses a plain `{id}` integer, not `{inventoryLocation}`, because route model
  binding excludes soft-deleted records by default. Test that GET `/admin/inventory-locations/{soft_deleted_id}`
  returns 404, but POST `restore` on the same ID succeeds.
- Factory state needed: `InventoryLocation::factory()->softDeleted()`
