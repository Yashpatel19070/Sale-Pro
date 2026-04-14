# Inventory (Stock View) — E2E Test Cases

Module-specific E2E cases for the other 3 inventory modules live in their own plan directories:
- `inventory-location/09-e2e-tests.md`
- `inventory-serial/09-e2e-tests.md`
- `inventory-movement/09-e2e-tests.md`

This file covers the stock visibility views (read-only) and the cross-module journeys
that validate the full system working together.

## Test Setup

```
Roles:     admin, manager, sales
Products:  WIDGET-001 (3 in_stock at L1+L45), WIDGET-002 (0 in_stock — all sold/damaged)
Locations: L1, L2, L45
```

---

## Auth & Access

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| I-01 | Unauthenticated | GET `/admin/inventory` | Redirect to login |
| I-02 | `admin` | GET `/admin/inventory` | 200 OK |
| I-03 | `manager` | GET `/admin/inventory` | 200 OK |
| I-04 | `sales` | GET `/admin/inventory` | 200 OK — all 3 roles have read access |

---

## Stock Dashboard (index)

| # | Setup | Expected |
|---|-------|----------|
| I-05 | WIDGET-001: 3 in_stock (L1=2, L45=1), 1 sold | Dashboard shows WIDGET-001 with qty = 3 (sold excluded) |
| I-06 | WIDGET-002: 0 in_stock (all sold/damaged) | WIDGET-002 does NOT appear on dashboard |
| I-07 | No serials at all | Empty state message rendered |
| I-08 | Product is soft-deleted but has in_stock serials | Still appears — stock derives from serials, not product active status |

---

## Drill-Down: Stock by SKU (showBySku)

| # | Setup | Action | Expected |
|---|-------|--------|----------|
| I-09 | WIDGET-001: L1=2, L45=1 | Click "View" on WIDGET-001 | 2 rows: L1 (2 units), L45 (1 unit) |
| I-10 | WIDGET-001 | View page | "Total On Hand" card shows 3 |
| I-11 | Any SKU detail page | Click back link | Returns to stock overview (`inventory.index`) |
| I-12 | Unknown product ID | GET `/admin/inventory/99999` | 404 |

---

## Drill-Down: Serials at SKU + Location (showBySkuAtLocation)

| # | Setup | Action | Expected |
|---|-------|--------|----------|
| I-13 | WIDGET-001 at L1: SN-001, SN-002 | Click "View Serials" for L1 | Shows SN-001, SN-002 ordered by serial_number |
| I-14 | Serial row | View | "Detail" link → `inventory-serials.show` for that serial |
| I-15 | WIDGET-001 has no in_stock serials at L45 | GET `inventory.by-sku-at-location` for L45 | Empty state message |
| I-16 | Unknown location ID | GET `/admin/inventory/{product}/99999` | 404 |
| I-17 | Soft-deleted location | GET `inventory.by-sku-at-location` for that location | 404 — route model binding excludes soft-deleted |

---

## Cross-Module Journeys

### Journey 1: Full Lifecycle (receive → transfer → sell → off dashboard)

| Step | Action | Verify |
|------|--------|--------|
| 1 | Admin creates serial SN-999 for WIDGET-001 at L1 (purchase_price=99.99) | status=in_stock; 1 receive movement created |
| 2 | Stock dashboard | WIDGET-001 count +1 to include SN-999 |
| 3 | Admin transfers SN-999 from L1 to L45 | Serial now at L45; transfer movement created |
| 4 | showBySku for WIDGET-001 | L1 count −1; L45 count +1 |
| 5 | Admin sells SN-999 from L45 (reference: ORD-999) | status=sold; sale movement created |
| 6 | Stock dashboard | WIDGET-001 count −1; SN-999 gone |
| 7 | Serial timeline for SN-999 | 3 events in order: receive → transfer → sale |

### Journey 2: Adjustment Flow (receive → dashboard → adjust → removed from count)

| Step | Action | Verify |
|------|--------|--------|
| 1 | Admin creates serial SN-888 for WIDGET-002 at L2 | status=in_stock |
| 2 | Dashboard | WIDGET-002 appears with qty=1 |
| 3 | Admin records adjustment: SN-888 → damaged | status=damaged; movement row created |
| 4 | Dashboard | WIDGET-002 disappears (0 in_stock) |
| 5 | Admin tries to adjust SN-888 again | Error: "not in stock (current status: damaged)" |
| 6 | Serial show for SN-888 | "Record Adjustment" link is hidden |

### Journey 3: Permission Boundary (sales role sees everything but can't write admin actions)

| Step | Action | Verify |
|------|--------|--------|
| 1 | Sales user logs in | Redirected to dashboard |
| 2 | GET `/admin/inventory` | 200 OK |
| 3 | Drills through all 3 stock views | All accessible |
| 4 | Views serial list and show | 200 OK; purchase_price not rendered |
| 5 | Opens movement create form | OK; Adjustment radio not rendered |
| 6 | Submits transfer | Succeeds |
| 7 | POST adjustment directly | 403 Forbidden |
| 8 | GET `/admin/inventory-locations/create` | 403 Forbidden |

---

## Notes

- **Cross-module route:** `show-by-sku-at-location.blade.php` links to `inventory-serials.show`.
  The inventory-serial module must be built and its routes registered before these tests run.
- **No writes in this module.** All data mutations happen via inventory-serial or inventory-movement.
  The stock view tests only assert what is displayed, not what was stored.
- Factory states needed (defined in other modules, reused here):
  - `InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)`
  - `InventorySerial::factory()->sold()`
  - `InventorySerial::factory()->damaged()`
