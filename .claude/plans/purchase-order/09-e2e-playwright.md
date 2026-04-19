# Purchase Order Module — Playwright Browser Tests

## Agent Execution Instructions

> **Model:** `claude-haiku-4-5-20251001` (Haiku — lightweight runner)
> **Mode:** RUN AND REPORT ONLY — do NOT edit any source files.
>
> Steps:
> 1. Run: `npx playwright test purchase-orders`
> 2. Collect all failures (failed assertions, timeouts, unexpected 403/404s).
> 3. Report results grouped by test ID (e.g. PO-B14, PO-B32).
> 4. For each failure: quote exact Playwright error, the selector/URL involved, and expected vs actual.
> 5. Attach screenshot paths from `test-results/` if available.
> 6. Do NOT fix anything. Do NOT edit any source file. Report only.

```
Framework:   Playwright + TypeScript
Test file:   tests/Browser/purchase-orders.spec.ts
Run command: npx playwright test purchase-orders
Fixtures:    E2ESeeder — seeds roles, permissions, supplier, products, location
```

## Credentials (seeded by E2ESeeder)

```ts
const ADMIN       = { email: 'admin@sale-pro.test',       password: 'password' };
const PROCUREMENT = { email: 'procurement@sale-pro.test', password: 'password' };
const MANAGER     = { email: 'manager@sale-pro.test',     password: 'password' };
const SUPER_ADMIN = { email: 'super-admin@sale-pro.test', password: 'password' };
```

---

## Auth

| # | Test | Steps | Assert |
|---|------|-------|--------|
| PO-B01 | Admin sees Purchase Orders nav link | Login as admin | Nav contains "Purchase Orders" link |
| PO-B02 | Procurement can access index | Login as procurement → `/admin/purchase-orders` | 200; table visible |
| PO-B03 | Warehouse gets 403 page | Login as warehouse → `/admin/purchase-orders` | 403 or redirect; "not authorized" visible |

---

## Create PO — Happy Path

| # | Test | Steps | Assert |
|---|------|-------|--------|
| PO-B10 | Create PO with 1 line | Login → click "New Purchase Order" → fill supplier, qty=5, price=100 → submit | Redirected to show page; PO number `PO-YYYY-XXXX` visible; status badge "Draft" |
| PO-B11 | Create PO with 2 lines | Add 2 line rows via "+ Add Line" button → fill both → submit | Show page lists 2 line items |
| PO-B12 | Skip flags visible on form | Open create form | "Skip Tech" and "Skip QA" checkboxes visible |
| PO-B13 | Duplicate product in lines | Add same product twice | Both lines saved (no unique constraint at form level) |
| PO-B14 | qty > 10000 shows inline error | Enter qty=99999 → submit | Form validation error on qty field: "max 10000" |
| PO-B15 | price < 0.01 shows inline error | Enter price=0 → submit | Form validation error on price field |

---

## Confirm PO

| # | Test | Steps | Assert |
|---|------|-------|--------|
| PO-B20 | Confirm button visible on draft PO | Open draft PO show page | "Confirm" button visible |
| PO-B21 | Confirm moves to open | Click "Confirm" → confirm modal → submit | Status badge changes to "Open"; "Confirmed" timestamp shown |
| PO-B22 | Edit hidden after confirm | Open open PO | "Edit" button not visible |
| PO-B23 | Confirm button hidden on open PO | Open open PO | "Confirm" button not visible |

---

## Cancel PO

| # | Test | Steps | Assert |
|---|------|-------|--------|
| PO-B30 | Cancel shows notes textarea | Click "Cancel" on draft PO | Modal/form with cancel reason textarea appears |
| PO-B31 | Cancel with valid reason succeeds | Fill reason (≥ 10 chars) → submit | Status badge "Cancelled"; cancel reason visible on show page |
| PO-B32 | Cancel with short reason shows error | Fill reason "ok" (< 10 chars) → submit | Inline validation error on reason field |
| PO-B33 | Cancel form requires reason | Submit cancel with empty textarea | Validation error: reason required |
| PO-B34 | Cancelled PO shows reason on show page | After cancel | "Cancellation Reason:" label + reason text visible |
| PO-B35 | Procurement cannot see cancel button | Login as procurement → open PO | "Cancel" button not visible |

---

## Reopen PO

| # | Test | Steps | Assert |
|---|------|-------|--------|
| PO-B40 | Reopen button visible on closed PO | Open closed PO as manager | "Reopen" button visible |
| PO-B41 | Reopen moves to open | Click "Reopen" → confirm → submit | Status badge "Open"; reopen_count incremented |
| PO-B42 | Third reopen blocked for manager | Closed PO with reopen_count=2, logged in as manager | "Reopen" attempt shows error: "Super Admin approval required" |
| PO-B43 | Reopen blocked when unit on shelf | Closed PO with shelved unit, logged in as manager | Error: "units from this PO are currently on the shelf" |

---

## Index — Search & Filter

| # | Test | Steps | Assert |
|---|------|-------|--------|
| PO-B50 | Search by PO number | Type partial PO number in search box → submit | Only matching PO(s) shown |
| PO-B51 | Filter by status=draft | Select "Draft" in status filter | Only draft POs in table |
| PO-B52 | Filter by supplier | Select supplier in dropdown | Only that supplier's POs shown |
| PO-B53 | No results shows empty state | Search for non-existent number | "No purchase orders found" message |
| PO-B54 | Pagination — 26 POs | Seed 26 POs | Page 1 has 25 rows; "Next" link visible |

---

## Show Page — Content

| # | Test | Assert |
|---|------|--------|
| PO-B60 | Lines table visible | Product name, qty ordered, qty received, unit price, line total per row |
| PO-B61 | Snapshot columns shown | "Stock at order" and "On order at order" columns visible per line |
| PO-B62 | Action buttons match status | Draft: Confirm + Edit + Cancel. Open: Cancel. Closed: Reopen. Cancelled: no actions |

---

## Notes

- E2ESeeder must include: `procurement` user, `super-admin` user, 1 active supplier, 2+ products, 1 inventory location.
- All Playwright tests run sequentially (`workers: 1`) against shared DB — seed state must be consistent.
- Reopen test PO-B42 requires pre-seeding a closed PO with `reopen_count=2` via factory in E2ESeeder.
