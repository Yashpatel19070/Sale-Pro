# Inventory (Stock View) — Playwright Browser Tests

```
Framework:   Playwright (real Chromium browser, real MySQL)
Test file:   tests/Browser/inventory.spec.ts
Section:     Stock Dashboard, Stock by SKU, Serials at SKU + Location
Run command: npm run test:e2e
```

## What `npm run test:e2e` does

```
1. php artisan migrate:fresh --seed --seeder=E2ESeeder   ← wipes MySQL, loads fixtures
2. npx playwright test                                    ← runs all tests/Browser/ specs
3. php artisan db:seed                                    ← restores dev data (always runs)
```

## Seed Data (E2ESeeder — `database/seeders/E2ESeeder.php`)

```
Users:
  admin@sale-pro.test / password   (admin role)
  sales@sale-pro.test / password   (sales role)

Locations:
  L1  Shelf L1  active
  L2  Shelf L2  active

Products:
  WIDGET-001  Widget Alpha
  WIDGET-002  Widget Beta

Serials:
  SN-E2E-001  WIDGET-001  L1  in_stock   ← Transfer test mutates this
  SN-E2E-002  WIDGET-001  L1  in_stock   ← Adjustment test mutates this
  SN-E2E-003  WIDGET-001  L2  in_stock
  SN-E2E-SOLD WIDGET-001  —   sold
  SN-E2E-004  WIDGET-002  L1  in_stock   ← Sale test mutates this
```

**Stock state after seeding (before any test runs):**
- WIDGET-001 → qty 3  (L1: 2, L2: 1)
- WIDGET-002 → qty 1  (L1: 1)

## Test Ordering

Tests run sequentially (`workers: 1`). The stock view tests run **before** movement tests:
- Stock Dashboard and Stock by SKU tests read the initial seeded state
- Transfer / Sale / Adjustment tests (in `inventory-movement/09-e2e-playwright.md`) mutate state
- The "after transfer" and "after sale" assertions depend on movement tests having run first

Do NOT add tests that reset or conflict with this order. If extra data is needed, add new serials
(e.g. `SN-E2E-005`) in `E2ESeeder.php`.

---

## Stock Dashboard

| # | Setup | Action | Expected |
|---|-------|--------|----------|
| I-05p | Seeded state | Log in as admin, visit `/admin/inventory` | WIDGET-001 row visible; qty = 3 |
| I-06p | Seeded state | Same page | WIDGET-002 row visible; qty = 1 |
| I-sold | Seeded state (SN-E2E-SOLD exists but sold) | Check WIDGET-001 row | Does NOT show qty = 4 |
| I-sales | Seeded state | Log in as sales, visit `/admin/inventory` | 200 OK; WIDGET-001 visible |

---

## Drill-Down: Stock by SKU

| # | Setup | Action | Expected |
|---|-------|--------|----------|
| I-09p | Seeded state | Click "View" on WIDGET-001 row | URL matches `/inventory/\d+`; WIDGET-001 heading visible |
| I-10p | On WIDGET-001 SKU page | Read page | L1 row shows 2; L2 row shows 1 |
| I-total | On WIDGET-001 SKU page | Read "Total On Hand" card | Card shows value 3 |
| I-back | On any SKU detail page | Click "← Stock Overview" link | URL returns to `/admin/inventory` |

---

## Drill-Down: Serials at SKU + Location

| # | Setup | Action | Expected |
|---|-------|--------|----------|
| I-13p | WIDGET-001 SKU page | Click "View Serials" on L1 row | SN-E2E-001 and SN-E2E-002 visible |
| I-13b | Same page | Check L2 serials are absent | SN-E2E-003 NOT visible on L1 serial list |
| I-14p | Serial list page | Inspect SN-E2E-001 row | "Detail" link visible on the row |

---

## Notes

- These tests cover **read-only stock views** only. All data mutations happen via movement tests.
- Selector pattern for row scoping: `page.locator('tr', { has: page.getByText('WIDGET-001') })`
- The "Total On Hand" card assertion: `page.locator('p').filter({ hasText: 'Total On Hand' }).getByText('3')`
- After Transfer tests run, L1 will show 1 and L2 will show 2 for WIDGET-001 — do not add assertions
  that depend on the pre-transfer count after the Transfer describe block has run.
