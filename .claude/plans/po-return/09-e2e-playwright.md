# PO Return Module — Playwright Browser Tests

## Agent Execution Instructions

> **Model:** `claude-haiku-4-5-20251001` (Haiku — lightweight runner)
> **Mode:** RUN AND REPORT ONLY — do NOT edit any source files.
>
> Steps:
> 1. Run: `npx playwright test po-returns`
> 2. Collect all failures (failed assertions, timeouts, unexpected 403/404s).
> 3. Report results grouped by test ID (e.g. RT-B30, RT-B41).
> 4. For each failure: quote exact Playwright error, the selector/URL involved, and expected vs actual.
> 5. Attach screenshot paths from `test-results/` if available.
> 6. Do NOT fix anything. Do NOT edit any source file. Report only.

```
Framework:   Playwright + TypeScript
Test file:   tests/Browser/po-returns.spec.ts
Run command: npx playwright test po-returns
Fixtures:    E2ESeeder — seeds roles, permissions, original PO, return PO, supplier
```

## Credentials (seeded by E2ESeeder)

```ts
const ADMIN       = { email: 'admin@sale-pro.test',       password: 'password' };
const MANAGER     = { email: 'manager@sale-pro.test',     password: 'password' };
const PROCUREMENT = { email: 'procurement@sale-pro.test', password: 'password' };
```

---

## Index Page

| # | Test | Actor | Assert |
|---|------|-------|--------|
| RT-B01 | Return POs nav link visible | `admin` | "Return Orders" or "PO Returns" link in nav |
| RT-B02 | Index shows return PO number | `admin` | Return PO number (e.g. `PO-2026-R001`) visible in table |
| RT-B03 | Index does NOT show purchase POs | `admin` | No purchase-type PO numbers in return index |
| RT-B04 | Status badge visible | `admin` | Badge shows "Open" or "Closed" per return PO |
| RT-B05 | Parent PO link visible | `admin` | Column links back to original purchase PO |
| RT-B06 | Procurement sees index | `procurement` | 200; table visible |
| RT-B07 | Warehouse gets blocked | Login as warehouse → `/admin/po-returns` | 403 or "not authorized" |
| RT-B08 | Empty state | No return POs seeded | "No return orders" message |
| RT-B09 | Search by PO number | Type partial number → submit | Only matching return PO shown |
| RT-B10 | Filter by status | Select "Open" filter | Only open return POs |

---

## Show Page

| # | Test | Assert |
|---|------|--------|
| RT-B20 | Shows return PO number | Heading or field contains PO number |
| RT-B21 | Shows parent PO link | "Original PO: PO-YYYY-XXXX" — clickable link |
| RT-B22 | Shows supplier name | Supplier name visible |
| RT-B23 | Shows line items | Product name, qty=1, unit price per row |
| RT-B24 | Shows auto-created notes | Notes field: "Return for failed unit in job #X at stage Y." |
| RT-B25 | Close button visible for manager | "Close Return PO" button visible when status=open |
| RT-B26 | Close button hidden for procurement | Logged in as procurement — Close button not visible |
| RT-B27 | No Close button when already closed | Return PO with status=closed — button not shown |

---

## Close Return PO Flow

| # | Test | Steps | Assert |
|---|------|-------|--------|
| RT-B30 | Manager closes return PO | Login as manager → open return PO show → click "Close Return PO" → confirm | Status badge changes to "Closed"; `closed_at` timestamp visible; success flash message |
| RT-B31 | Procurement cannot close | Login as procurement → return PO show | Close button not visible (no access) |
| RT-B32 | Already-closed return PO shows no action | Status=closed | Close button not visible; status badge "Closed" |
| RT-B33 | Redirect after close | Click close | Stays on return PO show page (302 → same page) |

---

## Return PO Auto-Created via Fail

This flow verifies the UI reflects what PipelineService creates:

| # | Test | Steps | Assert |
|---|------|-------|--------|
| RT-B40 | Failed job shows return PO link | Navigate to a failed pipeline job (seeded) | Job detail page shows "Return PO: PO-YYYY-XXXX" link |
| RT-B41 | Return PO links to correct supplier | Follow link from job detail → return PO show | Supplier name matches original PO |
| RT-B42 | Return PO in po-returns index | Navigate to `/admin/po-returns` | Auto-created return PO appears in list |

---

## Notes

- E2ESeeder must create: 1 open purchase PO, 1 pre-built return PO (open), and 1 closed return PO for status display tests.
- RT-B40 through RT-B42 require a seeded PoUnitJob with status=failed AND an associated return PO already created (seeded, not driven via UI).
- No create form for return POs — they are system-created only via `PipelineService::fail()`. No "New Return PO" button should exist in the UI.
- Playwright tests run `workers: 1` — sequential, shared DB.
