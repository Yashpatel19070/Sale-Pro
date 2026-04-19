# PO Pipeline Module — Playwright Browser Tests

## Agent Execution Instructions

> **Model:** `claude-haiku-4-5-20251001` (Haiku — lightweight runner)
> **Mode:** RUN AND REPORT ONLY — do NOT edit any source files.
>
> Steps:
> 1. Run: `npx playwright test pipeline`
> 2. Collect all failures (failed assertions, timeouts, unexpected 403/404s).
> 3. Report results grouped by test ID (e.g. PL-B22, PL-B42).
> 4. For each failure: quote exact Playwright error, the selector/URL involved, and expected vs actual.
> 5. Attach screenshot paths from `test-results/` if available.
> 6. Do NOT fix anything. Do NOT edit any source file. Report only.

```
Framework:   Playwright + TypeScript
Test file:   tests/Browser/pipeline.spec.ts
Run command: npx playwright test pipeline
Fixtures:    E2ESeeder — seeds roles, permissions, open PO, line, location
```

## Credentials (seeded by E2ESeeder)

```ts
const ADMIN       = { email: 'admin@sale-pro.test',       password: 'password' };
const PROCUREMENT = { email: 'procurement@sale-pro.test', password: 'password' };
const WAREHOUSE   = { email: 'warehouse@sale-pro.test',   password: 'password' };
const TECH        = { email: 'tech@sale-pro.test',        password: 'password' };
const QA          = { email: 'qa@sale-pro.test',          password: 'password' };
```

---

## Queue Page

| # | Test | Actor | Assert |
|---|------|-------|--------|
| PL-B01 | Queue visible in nav | `warehouse` | "Pipeline" or "Queue" nav link visible |
| PL-B02 | Queue empty state | `warehouse` (no jobs exist) | "No jobs" / empty state message |
| PL-B03 | Queue shows PO number and product | `warehouse` (job at visual stage) | Job row shows PO number and product name |
| PL-B04 | Queue shows "Take" button on pending job | `warehouse` | "Take" / "Claim" button visible on job row |
| PL-B05 | In-progress job not shown on queue | `warehouse` after claiming | Job disappears from pending queue |
| PL-B06 | Filter by PO number | `warehouse` | Only jobs from that PO visible |
| PL-B07 | Tech sees only tech-stage jobs | `tech` | Jobs at other stages not shown |
| PL-B08 | Admin sees all pending across all stages | `admin` | All stage jobs visible |

---

## Claim Job (Start)

| # | Test | Steps | Assert |
|---|------|-------|--------|
| PL-B10 | Claim visual job | Login as warehouse → click "Take" on visual job | Redirected to job detail page; status shows "In Progress"; assigned to current user |
| PL-B11 | Wrong-stage user cannot claim | Login as qa → attempt to access visual job start | 403 page or redirect |
| PL-B12 | Already-claimed job shows "taken" | Login as second warehouse user → view same job | "Claimed" / no Take button; error if attempting |

---

## Pass Stages — UI Flow

| # | Test | Steps | Assert |
|---|------|-------|--------|
| PL-B20 | Pass visual | Claim visual job → click Pass → confirm | Stage advances to "Serial Assign"; success message shown |
| PL-B21 | Pass serial_assign — serial input required | Claim serial_assign job → click Pass without entering serial | Validation error: serial number required |
| PL-B22 | Pass serial_assign — enter serial | Enter `SN-TEST-001` → submit | Stage advances to "Tech"; serial number stored |
| PL-B23 | Pass serial_assign — duplicate serial shows error | Enter serial that already exists in inventory | Form error: serial already assigned |
| PL-B24 | Pass tech | Claim tech job → click Pass | Stage advances to "QA" |
| PL-B25 | Pass qa | Claim qa job → click Pass | Stage advances to "Shelf" |
| PL-B26 | Pass shelf — location required | Claim shelf job → click Pass without location | Validation error: location required |
| PL-B27 | Pass shelf — select location | Select location → submit | Job shows "Passed"; serial created; inventory movement recorded |
| PL-B28 | Passed job shows serial number on detail page | After shelf pass | Serial number badge/field visible on show page |

---

## Skip Flag Flows — UI

| # | Test | Setup | Assert |
|---|------|-------|--------|
| PL-B30 | Tech stage skipped | PO with `skip_tech=true`; pass serial_assign | Stage jumps to QA; no tech stage in event log |
| PL-B31 | QA stage skipped | PO with `skip_qa=true`; pass tech | Stage jumps to Shelf |
| PL-B32 | Both skipped | PO with both flags; pass serial_assign | Stage jumps directly to Shelf |
| PL-B33 | Skip events visible in timeline | After skip | Event history shows "Skipped" entries for skipped stages |

---

## Fail Flow — UI

| # | Test | Steps | Assert |
|---|------|-------|--------|
| PL-B40 | Fail button visible on in-progress job | Claim job → view detail | "Fail" button visible alongside "Pass" |
| PL-B41 | Fail requires reason | Click Fail → submit empty notes | Validation error: reason required |
| PL-B42 | Fail with reason | Enter reason → submit | Job shows "Failed"; fail event in timeline; success message |
| PL-B43 | Return PO auto-created | After fail | Link to Return PO visible on job detail page |
| PL-B44 | Return PO has correct supplier | Navigate to return PO | Same supplier as original PO |
| PL-B45 | Cannot pass a failed job | Navigate back to failed job | Pass and Fail buttons not visible |

---

## Event History — UI

| # | Test | Assert |
|---|------|--------|
| PL-B50 | Timeline shows all events | Job detail page shows event list: receive, start, pass (per stage) |
| PL-B51 | Event shows actor name | Each event row shows username of who took the action |
| PL-B52 | Event shows timestamp | Each event row shows date/time |
| PL-B53 | Skip events labeled | Skipped events clearly labeled "Skipped" with reason |
| PL-B54 | Fail event shows reason | Fail event row shows the notes/reason entered |

---

## Job Detail Show Page

| # | Test | Assert |
|---|------|--------|
| PL-B60 | Shows PO number link | PO number is a link to PO show page |
| PL-B61 | Shows product name and SKU | Product info visible |
| PL-B62 | Shows current stage badge | Stage badge matches current_stage |
| PL-B63 | Shows assigned user | "Assigned to: [name]" when in_progress |
| PL-B64 | Shows pending serial on shelf stage | "Serial: SN-XXX-XXX" shown when job is at shelf stage |

---

## Notes

- E2ESeeder must create: open PO with 1 line, all pipeline role users, 1 inventory location.
- Happy path browser tests (PL-B20 → PL-B27) are sequential across stages — each step depends on prior.
- Playwright tests run against real DB with `workers: 1` — test data must not bleed between tests. Use `test.beforeEach` reset or fixed seeder users with deterministic factory state.
- `pending_serial_number` shown on shelf-stage job detail page helps warehouse worker confirm the serial before scanning location — include in view spec.
