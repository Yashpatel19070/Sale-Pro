# InventoryMovement — Playwright Browser Tests

```
Framework:   Playwright (real Chromium browser, real MySQL)
Test file:   tests/Browser/inventory.spec.ts
Sections:    Transfer Movement, Sale Movement, Adjustment Movement, Movement History
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
  SN-E2E-001  WIDGET-001  L1  in_stock   ← Transfer test: move L1 → L2
  SN-E2E-002  WIDGET-001  L1  in_stock   ← Adjustment test: mark damaged
  SN-E2E-003  WIDGET-001  L2  in_stock
  SN-E2E-SOLD WIDGET-001  —   sold
  SN-E2E-004  WIDGET-002  L1  in_stock   ← Sale test: sell from L1
```

## CRITICAL — Test Ordering

Playwright tests run **sequentially** (`workers: 1`). Movement describe blocks run in this order
and are **stateful** — each mutates DB state that later tests depend on:

| Order | Describe block | What it mutates |
|-------|----------------|-----------------|
| 1st | Stock Dashboard (read-only) | Nothing |
| 2nd | Stock by SKU (read-only) | Nothing |
| 3rd | Serials at SKU + Location (read-only) | Nothing |
| 4th | **Transfer Movement** | SN-E2E-001 moves L1 → L2 |
| 5th | **Sale Movement** | SN-E2E-004 sold; WIDGET-002 disappears |
| 6th | **Adjustment Movement** | SN-E2E-002 marked damaged |
| 7th | **Movement History** | Reads the 3 movements above |

Do NOT reorder describe blocks. Do NOT add tests that reset this serial state.
If extra data is needed, add new serials (e.g. `SN-E2E-005`) in `E2ESeeder.php`.

---

## Transfer Movement

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| M-07p | `admin` | Visit `/admin/inventory-movements/create`, check "Transfer" radio, select SN-E2E-001, from=L1, to=L2, submit | Redirect to movement index; SN-E2E-001 visible in list |
| M-07b | `admin` | After transfer: visit WIDGET-001 SKU page | L1 shows 1 unit; L2 shows 2 units |

---

## Sale Movement

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| M-15p | `admin` | Visit create form, check "Sale" radio, select SN-E2E-004, from=L1, submit | Redirect to index; SN-E2E-004 in list |
| M-15b | `admin` | After sale: visit `/admin/inventory` | WIDGET-002 row NOT visible (0 in_stock) |

---

## Adjustment Movement

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| M-20p | `admin` | Visit create form, check "Adjustment" radio, select SN-E2E-002, select `damaged`, submit | Redirect to index; SN-E2E-002 in list |
| M-05p | `sales` | Visit create form | Adjustment radio is NOT visible in the form |
| M-06p | `sales` | POST adjustment directly via `page.evaluate` fetch | Response status = 403 |

---

## Movement History (Immutability)

Run after Transfer, Sale, and Adjustment tests — movements from those tests exist in DB.

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| M-37p | `admin` | Visit `/admin/inventory-movements` | Table visible with recorded movements |
| M-38p | `admin` | Scan all rows for edit/delete controls | No "Edit" links; no "Delete" buttons |
| M-39p | `admin` | GET `/admin/inventory-movements/1/edit` | Response status = 404 |

---

## Create Form — JS Field Behaviour

| # | Action | Expected |
|---|--------|----------|
| M-47p | Visit `/admin/inventory-movements/create` with default (transfer) selected | `to_location_id` dropdown row is visible |
| M-47b | Select "Sale" radio on the form | `to_location_id` row is hidden (Alpine.js conditional) |
| M-48p | Select "Adjustment" radio | `adjustment_status` dropdown is visible; `to_location_id` row hidden |

---

## Notes

- JS field show/hide (`to_location_id` hidden for sale, `adjustment_status` visible for adjustment)
  is driven by Alpine.js — only testable with a real browser. HTTP tests cannot verify this.
- The `page.evaluate` fetch in M-06p bypasses the UI to confirm the server enforces 403 regardless
  of what the form renders. Include the CSRF token: `document.querySelector('meta[name="csrf-token"]')`.
- Selector for radio check: `page.check('input[name="type"][value="transfer"]')`
- Selector for select: `page.selectOption('select[name="inventory_serial_id"]', { label: /SN-E2E-001/ })`
