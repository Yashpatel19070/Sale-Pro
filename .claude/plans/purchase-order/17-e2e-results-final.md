# Procurement Module — E2E Test Results (Final)

**Date:** 2026-05-10
**File:** `tests/Browser/procurement.spec.ts`
**Result:** ✅ 37/37 passed (0 failed, 0 skipped)
**Duration:** ~39 seconds

---

## Bugs Fixed to Reach Green

### BUG-041: Missing `InventoryMovement` import in `PurchaseOrderService`

**Severity:** Critical
**File:** `app/Services/PurchaseOrderService.php`
**Problem:** `getAssignedGrnIds()` (extracted from controller per BUG-032) used `InventoryMovement::whereIn(...)` but the class was not imported. Every request to PO show page threw `Class "App\Services\InventoryMovement" not found` (500).
**Fix:** Added `use App\Models\InventoryMovement;` import.
**Tests unblocked:** 1.6, 1.8–1.12, 33

### BUG-042: Alpine.js `qcForm` component registration timing — `Alpine.data()` via `alpine:init` unreliable

**Severity:** High
**File:** `resources/views/goods-receipts/show.blade.php`
**Problem:** QC form used `Alpine.data('qcForm', ...)` registered inside an `alpine:init` event listener. In Playwright's headless Chromium, the component was not resolving — `:disabled="!allValid"` remained unprocessed and the Submit QC button was always enabled regardless of input values.
**Fix:** Replaced `Alpine.data()` registration with `window.__qcLines` global + inline `x-data` object on the component div. Eliminates `alpine:init` event dependency entirely.
**Tests unblocked:** 28

---

## Test Code Fixes

| Tests | Root Cause | Fix Applied |
|-------|-----------|-------------|
| 21, 22 — PO validation | `document.querySelector('form')` returned the nav logout form, not the PO create form; `novalidate` was set on the wrong element; native HTML5 validation blocked submit | Changed to `document.querySelectorAll('form').forEach(...)` + switched to `waitForResponse` + `waitForLoadState` pattern |
| 26 — GRN validation | Same wrong-form `novalidate` issue + `waitForNavigation` timing out | Same fix: all-forms `novalidate` + `waitForResponse` |
| 36, 37 — Reject flow | `getByText('Rejected')` strict mode: matched both the status badge AND the "Purchase order rejected." flash message (substring match) | Changed to `getByText('Rejected', { exact: true })` |
| 1.8 — Serial assignment | Regex `bulk-receive-print` didn't match URL path `bulk-receive/print` (dash vs slash) | Fixed to `bulk-receive\/print` |
| 1.12 — Index filter | `getByText('Closed')` matched the `<option>` in the status filter dropdown AND multiple table badges | Scoped to `tbody` — `page.locator('tbody').getByText('Closed').first()` |
| 21 — PO validation error | Locator `.text-red-600, .bg-red-50` matched both the summary block and per-field error in strict mode | Added `.first()` |
| Draft PO GRN/Invoice create | `body.includes('error')` was accidentally true before InventoryMovement fix (PO show 500 generated error content); after fix, form shows cleanly and assertion failed | Replaced fragile `body.includes('error')` check with proper form-submit + error-message assertion |

---

## Test Coverage

### Happy Path (12 steps)
1. Create PO with multiple lines
2. Submit PO for approval
3. Approve PO
4. Admin marks as on the way
5. Create GRN
6. Complete GRN
7. QC: pass some, fail some
8. Assign serial numbers
9. Create invoice
10. Approve invoice
11. Mark invoice as paid → PO closes
12. Closed PO appears on index with Closed filter

### Authorization (8 tests)
- Guest redirects to login (PO index, create, show)
- Sales user 403 on restricted actions
- PO 1 not accessible to unauthorized users

### PO Validation (5 tests)
- Cannot create without supplier
- Cannot create with no product lines
- Cannot edit an approved PO
- Cannot submit already-approved PO
- Cannot submit already-submitted PO (via API)

### GRN Validation (4 tests)
- Cannot create GRN for Draft PO (domain rule via submission)
- Cannot receive more than remaining qty
- Cannot edit a Complete GRN

### QC Validation (2 tests)
- Submit QC button disabled when pass + fail ≠ received qty (Alpine.js `:disabled`)
- Cannot submit QC twice

### Serial Assignment Validation (2 tests)
- Cannot access assign-serials for Draft GRN
- Cannot assign serials twice

### Invoice Validation (3 tests)
- Cannot create invoice for Draft PO (domain rule via submission)
- Cannot approve already-paid invoice
- Cannot delete a paid invoice

### Print View (1 test)
- PO print page renders with correct data

### Reject Flow (2 tests)
- Reject with reason → reason appears on show page
- Rejected PO can be resubmitted and approved

---

## Architecture Notes

- Test file: `tests/Browser/procurement.spec.ts` (885 lines, 37 tests)
- Shared state: happy-path tests 1.1–1.12 share `poId`/`grnId` via outer scope variables
- Each describe block has `test.beforeEach` for login
- Seeded via `php artisan db:seed --class=E2ESeeder`
- Alpine.js components: use `window.__varName` global + inline `x-data` (not `Alpine.data()`) for reliability in headless environments
