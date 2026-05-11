# Procurement Module — E2E Test Plan

**Model:** Haiku
**Tooling:** Playwright (via e2e-runner agent)
**Scope:** Full procurement workflow — PO → GRN → QC → Serial Assignment → Serial Show
**No code changes.** This file is the plan. Tests live in `tests/E2E/`.

---

## Setup Requirements

### Seeded state needed before each journey
- At least one active `Supplier`
- At least one active `Product` with SKU
- At least one `InventoryLocation` (e.g. L1)
- Admin user with all procurement + inventory permissions
- Sales user with view-only permissions (no create/update)

### Base URL
`http://localhost:8000`

### Auth
Login as admin: `POST /admin/login` with admin credentials.

---

## Journey 1 — Full Happy Path

**Goal:** Full procurement cycle from PO creation to serial on shelf with traceability verified.

| Step | URL | Action | Playwright Hints | Assert |
|------|-----|--------|------------------|--------|
| 1 | `/admin/purchase-orders/create` | Fill supplier, expected delivery, add 1 line (product, qty=5, unit cost=100) | `page.getByLabel('Supplier').selectOption({label: 'Acme Corp'})`, `page.getByLabel('Expected Delivery').fill('2026-06-01')`, Product select + qty/unit cost inputs | Form renders with line builder |
| 2 | Submit form | Click "Submit" button | `page.getByRole('button', {name: 'Generate'}).first()` (line builder generates serials button) | Redirected to PO show, status badge = "Draft" |
| 3 | PO show | Click "Submit" (submit for approval) | `page.getByRole('button', {name: 'Submit'}).first()` — Draft section only | Status badge = "Pending Approval", `expect(page.getByText('Pending Approval')).toBeVisible()` |
| 4 | PO show | Click "Approve" | `page.getByRole('button', {name: 'Approve'})` — visible only when status = pending_approval | Status badge = "Approved", `expect(page.getByText('Approved')).toBeVisible()` |
| 5 | PO show | Click "Receive Goods" | `page.getByRole('link', {name: 'Receive Goods'})` | Redirected to `/admin/purchase-orders/{id}/goods-receipts/create` |
| 6 | GRN create | Fill received date, enter qty=5 for line | `page.getByLabel('Received Date').fill('2026-05-05')`, per-line qty input: `page.locator('input[name="lines[0][qty_to_receive]"]').fill('5')` | Form pre-fills product, ordered qty, remaining qty |
| 7 | Submit GRN | Click "Save" button | `page.getByRole('button', {name: /Save|Submit/i})` | Redirected to GRN show, `expect(page.getByText(grnNumber)).toBeVisible()`, status badge = "Draft" |
| 8 | GRN show | Click "Complete" button | `page.getByRole('button', {name: 'Complete'})` — only visible when status = draft | GRN status = "Complete", PO status = "Quality Check", `expect(page.getByText('Complete')).toBeVisible()`, `page.waitForURL(/goods-receipts\/\d+$/)` |
| 9 | GRN show | QC form visible, enter qty_passed=5, qty_failed=0 | `page.locator('input[name="lines[0][qty_passed]"]').fill('5')`, `page.locator('input[name="lines[0][qty_failed]"]').fill('0')`, Alpine live-checks input | Submit button enabled when pass+fail === received for all lines |
| 10 | Submit QC | Click "Submit QC" button | `page.getByRole('button', {name: 'Submit QC'})` — enabled only when validation passes | Redirected to GRN show, `expect(page.getByText('QC Results')).toBeVisible()` |
| 11 | GRN show | "Assign Serial Numbers →" button visible | `page.getByRole('link', {name: 'Assign Serial Numbers →'})` — visible only if serialsAssigned = false | — |
| 12 | Click "Assign Serial Numbers →" | — | Click link | Assign Serials form renders, `expect(page.getByText('Assign Serial Numbers')).toBeVisible()`, product + SKU pre-filled |
| 13 | Assign Serials | Select location L1, verify purchase price = 100.00 | `page.locator('select[id="location_0"]').selectOption({label: /L1/})`, `page.locator('input[name="lines[0][purchase_price]"]').inputValue()` should return "100" or "100.00" | — |
| 14 | Click "Generate 5 Serials" | — | `page.getByRole('button', {name: /Generate.*Serial/})` | Redirected to bulk-receive-print page (`/admin/inventory-movements/bulk-receive/print`), success flash: `expect(page.getByText(/Generated.*serial/i)).toBeVisible()` |
| 15 | Navigate to Serials index | `/admin/inventory-serials` | `page.goto('/admin/inventory-serials')` | 5 new serials visible in table with status "In Stock", `expect(page.getByText(/SN-2026-/)).toHaveCount(5)` |
| 16 | Click first serial | Serial show page | `page.locator('table tbody tr').first().getByRole('link').first().click()` → `/admin/inventory-serials/{id}` | Supplier name = supplier used on PO (NOT "—"), `expect(page.getByText('Acme Corp')).toBeVisible()`, **not** "—" |
| 17 | Serial show | Movement History table visible | Scroll to Movement History section, `expect(page.getByText('Movement History')).toBeVisible()` | Source column shows GRN number as clickable link, `page.locator('table tbody a').first().textContent()` contains GRN number |
| 18 | Serial show | Movement History source details | Look at first movement row Source cell | PO number + supplier name visible below GRN number: `expect(page.getByText(poNumber)).toBeVisible()`, `expect(page.getByText('Acme Corp')).toBeVisible()` |

**Critical assertions:** steps 16, 17, 18 (traceability bug we fixed).

**Playwright Waiters:**
- Step 8: `page.waitForURL(/goods-receipts\/\d+$/)` to ensure complete form disappeared
- Step 14: `page.waitForURL(/bulk-receive\/print$/)` before checking print page
- Step 15: `page.waitForURL(/inventory-serials$/)` before checking table count

---

## Journey 2 — Partial Receipt + QC Fail

**Goal:** Only qty_passed serials are generated, not qty_received.

| Step | Action | Playwright Hints | Assert |
|------|--------|------------------|--------|
| 1 | Create PO with 1 line qty=10 | Create via form, verify count in form | — |
| 2 | Approve PO | Submit → Approve | — |
| 3 | Create GRN, receive qty=8 (partial) | GRN form: `page.locator('input[name="lines[0][qty_to_receive]"]').fill('8')` | After complete, PO status = "Partially Received", `expect(page.getByText('Partially Received')).toBeVisible()` |
| 4 | Complete GRN | Click Complete button | GRN status = Complete, `expect(page.getByText('Complete')).toBeVisible()` |
| 5 | Submit QC: qty_passed=5, qty_failed=3 | QC form: qty_passed input fill('5'), qty_failed fill('3') | QC results show 5 passed / 3 failed badges, `expect(page.getByText(/5.*passed/)).toBeVisible()`, `expect(page.getByText(/3.*failed/)).toBeVisible()` |
| 6 | Click "Assign Serial Numbers →" | Link visible in QC Results section | Assign serials form shows qty=5 (not 8), read-only field: `page.locator('p:has-text("5")').isVisible()` |
| 7 | Generate serials | Select location L1, purchase_price pre-filled, submit | Exactly 5 serials created in DB, success flash: `expect(page.getByText(/Generated 5 serial/i)).toBeVisible()` |
| 8 | Serial index | Navigate to `/admin/inventory-serials` | 5 new serials visible (only 5, not 8), `expect(page.locator('table tbody tr')).toHaveCount(5)` |

**Critical assertion:** step 7 — qty matches `qty_passed`, never `qty_received`.

---

## Journey 3 — Double-Assignment Prevention

**Goal:** Cannot generate serials twice for the same GRN.

| Step | Action | Playwright Hints | Assert |
|------|--------|------------------|--------|
| 1 | Complete Journey 1 through serial generation | Run full happy path to step 14 | — |
| 2 | Manually navigate to `assign-serials` URL for same GRN | `page.goto('/admin/purchase-orders/1/goods-receipts/1/assign-serials')` (use real IDs from journey 1) | Redirected to GRN show, `page.url().includes('goods-receipts')` but not 'assign-serials' |
| 3 | GRN show | Error message expected | Flash error visible: `expect(page.locator('.rounded-md.bg-red-100')).toBeVisible()` or `expect(page.getByText(/already assigned/i)).toBeVisible()` |
| 4 | GRN show | "Assign Serial Numbers →" button replaced by badge | Button should NOT exist: `expect(page.getByRole('link', {name: 'Assign Serial Numbers →'})).not.toBeVisible()`, Badge visible: `expect(page.getByText(/Serials Assigned ✓/)).toBeVisible()` |
| 5 | PO show GRN table | "Serials Assigned" green badge visible on that GRN row | Navigate back to PO show, Goods Receipts section, find that GRN row, badge visible: `expect(page.locator('table tbody').getByText(/Serials Assigned ✓/)).toBeVisible()` |

**Critical assertion:** steps 3 + 4 — idempotency enforced at UI level.

---

## Journey 4 — Serial Show Traceability

**Goal:** Every serial generated from GRN flow has full procurement traceability.

| Field | Playwright Hint | Expected value | Critical? |
|-------|------------------|---------------|-----------|
| Supplier | `page.locator('dt:has-text("Supplier") + dd').textContent()` | Supplier name from PO (e.g. "Acme Corp") | YES |
| Movement History → Source → GRN | First row in Movement History, Source column link: `page.locator('table tbody tr').first().locator('a').first()` | GRN number, clickable link to GRN show | YES |
| Movement History → Source → PO | First row Source cell second line link: `page.locator('table tbody tr').first().locator('a').nth(1)` | PO number, clickable link to PO show | YES |
| Movement History → Source → Supplier | First row Source cell text below links: `page.locator('table tbody tr').first().locator('td').nth(4).textContent()` | Supplier name (e.g. "Acme Corp") below PO number | YES |
| Movement History → Type | Badge in Type column: `page.locator('table tbody tr').first().locator('span').first()` | "Receive" badge (green/gray) | yes |
| Movement History → To | Location code in To column: `page.locator('table tbody tr').first().locator('td').nth(3)` | Location code (e.g. "L1") | yes |

---

## Journey 5 — Permission Gates

**Goal:** Sales user (view-only) cannot perform write actions.

| URL | Method | Playwright Hint | Sales user result |
|-----|--------|-----------------|------------------|
| `/admin/purchase-orders/{id}/goods-receipts/create` | GET | `page.goto(url)` | 403 Forbidden page, `expect(page.getByText(/403\|Forbidden\|not authorized/i)).toBeVisible()` |
| `/admin/purchase-orders/{id}/goods-receipts` | POST | `page.request.post(url, {data: ...})` | 403 in response headers, `response.status() === 403` |
| `/admin/purchase-orders/{id}/goods-receipts/{id}/qc` | POST | Form submit: `page.getByRole('button', {name: 'Submit QC'}).click()` | 403 response or button not rendered (`not.toBeVisible()`) |
| `/admin/purchase-orders/{id}/goods-receipts/{id}/assign-serials` | GET | `page.goto(url)` | 403 Forbidden, `expect(page.getByText(/403\|Forbidden/i)).toBeVisible()` |
| `/admin/purchase-orders/{id}/goods-receipts/{id}/assign-serials` | POST | Form submit on page (should not load) | 403 response (if form somehow loads) |

---

## Test Data Factory

### Factory Calls (PHP)

Each journey needs the following seeded state before running:

```php
// Roles & Permissions (run once)
\Database\Seeders\RoleSeeder::class,
\Database\Seeders\PurchaseOrderPermissionSeeder::class,
\Database\Seeders\InventoryMovementPermissionSeeder::class,

// Journey 1 + 2 data
$admin = User::factory()->create();
$admin->assignRole('admin');

$supplier = Supplier::factory()->active()->create(['name' => 'Acme Corp']);
$product = Product::factory()->active()->create(['name' => 'Test Widget', 'sku' => 'WIDGET-001']);
$location = InventoryLocation::factory()->create(['code' => 'L1', 'name' => 'Shelf 1']);

// Journey 5 — sales user
$salesUser = User::factory()->create();
$salesUser->assignRole('sales');

// Seeder to run before E2E
\Database\Seeders\PermissionSeeder::class, // populates permission db

// Test runs as admin for journey 1-4; as sales user for journey 5
```

### Session Keys
- `bulk_receive_ids`: Session key set after serial generation (in GoodsReceiptController::storeSerials) — passed to bulk-receive-print route
- Session value contains array of created serial IDs for print label functionality

---

## Known Bugs Fixed

These tests specifically guard against regression of bugs we fixed:

### Bug 1: Supplier blank on serial show page
**Issue:** Serial show page displayed "—" instead of supplier name from PO.
**Fix:** InventoryMovementService::bulkReceiveFromGrn now passes `supplier_name` when creating serials from GRN. On GoodsReceiptController, the supplier is read from `$goodsReceipt->purchaseOrder->supplier->name` and passed via service data array.
**Test Guards:** Journey 1, step 16 — assert serial->supplier_name = PO supplier name, never "—".

### Bug 2: Session key mismatch bulk_receive_ids
**Issue:** After storeSerials, session key was missing or inconsistently named, so print route could not find generated serials.
**Fix:** GoodsReceiptController::storeSerials now stores IDs in session under key 'bulk_receive_ids' (matches what InventoryMovementController::printBulkReceive expects).
**Test Guards:** Journey 1, step 14 — success flash appears with "Print Labels" link; print page loads.

### Bug 3: Redirect after storeSerials went back to assignSerials causing "already assigned" error
**Issue:** Redirect logic sent user back to assignSerials form, but the double-assignment guard immediately rejected with error because movements already existed for that GRN.
**Fix:** GoodsReceiptController::storeSerials now redirects to `inventory-movements.bulk-receive-print` (not back to assignSerials).
**Test Guards:** Journey 1, step 14 — redirect goes to print page, not back to assign-serials. Journey 3, step 2 — manual navigation to assign-serials is blocked with error + redirect to GRN show.

### Bug 4: ->distinct() wrong syntax on goods_receipt_id query
**Issue:** PurchaseOrderController::show used `->distinct()` with no column argument, which was invalid syntax in some database drivers.
**Fix:** Query now uses `->select('goods_receipt_id')->distinct()` to properly specify which column to deduplicate on, or refactored to use `->pluck('goods_receipt_id')` directly.
**Test Guards:** Journey 1, step 3 — PO show page loads without SQL errors; GRN list renders correctly.

---

## Test File Location
`tests/E2E/ProcurementWorkflowTest.php`

## Suggested Pest E2E structure
```
it('completes full procurement workflow and verifies serial traceability', ...)
it('generates only qty_passed serials after partial receipt and QC fail', ...)
it('prevents double serial assignment for same GRN', ...)
it('shows supplier and GRN source on serial show page', ...)
it('blocks sales user from all write actions in procurement flow', ...)
```

---

## Critical Route Map for Tests

| Route Name | Full Path | Used In |
|------------|-----------|---------|
| purchase-orders.create | `/admin/purchase-orders/create` | Journey 1-5 |
| purchase-orders.show | `/admin/purchase-orders/{id}` | All journeys |
| purchase-orders.submit | POST to `/admin/purchase-orders/{id}/submit` | Journey 1 step 3 |
| purchase-orders.approve | POST to `/admin/purchase-orders/{id}/approve` | Journey 1 step 4 |
| purchase-orders.goods-receipts.create | `/admin/purchase-orders/{id}/goods-receipts/create` | Journey 1 step 5 |
| purchase-orders.goods-receipts.store | POST to `/admin/purchase-orders/{id}/goods-receipts` | Journey 1 step 7 |
| purchase-orders.goods-receipts.show | `/admin/purchase-orders/{id}/goods-receipts/{id}` | Journey 1 step 8-12 |
| purchase-orders.goods-receipts.complete | POST to `/admin/purchase-orders/{id}/goods-receipts/{id}/complete` | Journey 1 step 8 |
| purchase-orders.goods-receipts.submitQc | POST to `/admin/purchase-orders/{id}/goods-receipts/{id}/qc` | Journey 1 step 10, Journey 2 step 5 |
| purchase-orders.goods-receipts.assignSerials | GET `/admin/purchase-orders/{id}/goods-receipts/{id}/assign-serials` | Journey 1 step 12, Journey 3 step 2 |
| purchase-orders.goods-receipts.storeSerials | POST to `/admin/purchase-orders/{id}/goods-receipts/{id}/assign-serials` | Journey 1 step 14 |
| inventory-movements.bulk-receive-print | `/admin/inventory-movements/bulk-receive/print` | Journey 1 step 14 |
| inventory-serials.index | `/admin/inventory-serials` | Journey 1 step 15 |
| inventory-serials.show | `/admin/inventory-serials/{id}` | Journey 1 step 16-18 |
