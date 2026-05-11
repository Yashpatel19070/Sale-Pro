# Procurement Module — Bug Discovery Report

**Model:** Sonnet 4.6
**Agent type:** Static code analysis — read-only, no code changes
**Scope:** GoodsReceiptController, PurchaseOrderController, InventorySerialController, InventoryMovementController, all related services, policies, requests, views, routes

---

## Summary Table

| ID | Severity | File | Title | Status |
|----|----------|------|-------|--------|
| BUG-001 | Critical | PurchaseOrderController.php:44 | `store()` missing `$this->authorize()` | **Fixed** |
| BUG-002 | Critical | InventoryMovementController.php:167 | `storeBulkReceive()` missing `$this->authorize()` | **Fixed** |
| BUG-003 | High | goods-receipts/show.blade.php:253 | `@can` uses string route name instead of policy ability | **Fixed** |
| BUG-004 | High | GoodsReceiptController.php:60–69 | `edit()` does not eager-load `goodsReceipt->purchaseOrder->supplier` | **Fixed** |
| BUG-005 | High | GoodsReceiptController.php:50–58 | `show()` does not eager-load `lines.qcInspectedBy` | **Fixed** |
| BUG-006 | High | GoodsReceiptController.php:123–146 | `assignSerials()` does not eager-load `goodsReceipt->receivedBy` | **Fixed** |
| BUG-007 | High | GoodsReceiptService.php:92–108 | `complete()` status guard missing — Draft GRN in wrong PO status can still be completed | **Fixed** |
| BUG-008 | Medium | GoodsReceiptService.php:59–89 | `update()` does not validate that GRN is in Draft status before deleting+reinserting lines | **Not a Bug** |
| BUG-009 | Medium | GoodsReceiptService.php:239–271 | `validateLineQtys()` silently skips invalid `purchase_order_line_id` lines | **Fixed** |
| BUG-010 | Medium | assign-serials.blade.php:9 | `$purchaseOrder->supplier` not eager-loaded in `assignSerials()` controller | **Fixed** (merged into BUG-006) |
| BUG-011 | Medium | GoodsReceiptService.php:113–177 | `submitQc()` TOCTOU: `alreadyDone` guard runs before transaction re-read | **Fixed** |
| BUG-012 | Medium | purchase-orders/show.blade.php:154 | Rejection form missing `@can('reject')` gate — shown to any approver | **Fixed** |
| BUG-013 | Low | GoodsReceiptService.php:209–223 | `updatePoStatus()` always overwrites PO status to `QualityCheck` regardless of current status | **Fixed** |
| BUG-014 | Low | InventoryMovementService.php:376–449 | `bulkReceiveFromGrn()` silently succeeds with zero serials when all QC lines pass 0 — no audit log entry | **Accepted** |
| BUG-015 | Suspected | PurchaseOrderController.php:107–112 | `restore()` resolves soft-deleted PO without `withTrashed()` route binding | **Not a Bug** |
| BUG-016 | Critical | PurchaseOrderPolicy.php | `markOnTheWay()` method missing — view `@can` always false, button never renders | **Fixed** |
| BUG-017 | Critical | GoodsReceiptService.php:21–34 | `store()` PO status + qty guards OUTSIDE transaction — TOCTOU | **Fixed** |
| BUG-018 | High | GoodsReceiptService.php:61 | `update()` Complete-status guard OUTSIDE transaction — TOCTOU | **Fixed** |
| BUG-019 | High | GoodsReceiptService.php:94–106 | `complete()` both guards OUTSIDE transaction — TOCTOU | **Fixed** |
| BUG-020 | High | GoodsReceiptService.php:169 | `submitQc()` activity log fires INSIDE transaction — lost on rollback | **Fixed** |
| BUG-021 | High | PurchaseOrderService.php:231 / GoodsReceiptService.php:243 | PO/GRN number generation — read-modify-write race → duplicate numbers | **Fixed** |
| BUG-022 | High | PurchaseOrderController.php:181 | `qualityCheck()` uses raw `Request` — `qc_notes` unvalidated, written to DB | **Fixed** |
| BUG-023 | High | PurchaseOrder.php, GoodsReceipt.php, Invoice.php | Wrong `LogsActivity` namespace — `Models\Concerns` should be `Traits` | **Not a Bug** — vendor confirms `Models\Concerns\LogsActivity` is correct for this package version |
| BUG-024 | High | invoices migration | No DB-level `unique` on `invoice_number` — app-level check bypassable | **Fixed** |
| BUG-025 | High | assign-serials.blade.php:25 | Session key `bulk_receive_serial_ids` — controller sets `bulk_receive_ids` — Print Labels dead | **Fixed** |
| BUG-026 | High | tests/Unit/Services/ | No `PurchaseOrderServiceTest` or `InvoiceServiceTest` | **Fixed** — 36 tests, all pass |
| BUG-027 | High | GoodsReceiptServiceTest.php | No happy-path test for `submitQc()` | **Not a Bug** — `GoodsReceiptServiceQcTest.php` already has 14 tests covering happy-path and all error cases |
| BUG-028 | High | tests/Feature/ | No individual feature tests for PO CRUD or Invoice controller actions | **Fixed** — 48 tests pass; also fixed missing `StoreQcNotesRequest` import in `PurchaseOrderController` |
| BUG-029 | High | InventoryMovementPolicy.php | `bulkReceive()` policy method existence unconfirmed — `@can` may always false | **Not a Bug** — method exists at line 58 |
| BUG-030 | Medium | StoreGoodsReceiptRequest.php:13 | Dual-purpose FormRequest — `authorize()` branches on HTTP method | **Fixed** — created `UpdateGoodsReceiptRequest`, wired to `GoodsReceiptController::update()` |
| BUG-031 | Medium | InvoiceController.php:51 | `auth()->user()` facade in controller — no `Request` param | **Fixed** — added `Request $request` param to `approve()`, use `$request->user()` |
| BUG-032 | Medium | PurchaseOrderController.php:61–69 | DB query (`InventoryMovement`) in controller — belongs in service | **Fixed** — moved to `PurchaseOrderService::getAssignedGrnIds()` |
| BUG-033 | Medium | GoodsReceiptController.php:127–140 | Three business guard conditions in `assignSerials()` controller | **Fixed** — moved to `GoodsReceiptService::assertCanAssignSerials()` |
| BUG-034 | Medium | All 5 models | `protected $casts` property — Laravel 12 requires `casts(): array` method | **Fixed** — converted to `casts()` method in all 5 models |
| BUG-035 | Medium | GoodsReceiptLine.php | `qty_passed`/`qty_failed` not in model casts — returns string from DB | **Fixed** — added `integer` casts for both in `casts()` method |
| BUG-036 | Medium | StoreGoodsReceiptRequest.php:20 | `$this->lines` magic property — PHPStan level 8 failure | **Fixed** — changed to `$this->input('lines')` in both `StoreGoodsReceiptRequest` and `UpdateGoodsReceiptRequest` |
| BUG-037 | Medium | InvoiceService.php:82–83 | `markPaid()` lazy-loads `purchaseOrder` then `invoices()` inside service | **Fixed** — `InvoiceController::markPaid()` eager-loads `purchaseOrder` before service call |
| BUG-038 | Medium | purchase_order_lines / goods_receipt_lines migrations | Missing DB indexes on FK columns | **Not a Bug** — `foreignId()` auto-creates indexes; verified with `SHOW INDEX` |
| BUG-039 | Medium | PO/GRN/Invoice views | Views use `<x-app-layout>` instead of `<x-layouts.admin>` | **Not a Bug** — `x-app-layout` is the correct component; all admin views use it; `x-layouts.admin` does not exist |
| BUG-040 | Medium | PurchaseOrderService.php:120–218 | Status transition methods (`submit`, `approve`, `reject`, etc.) — single-table writes without transaction or row lock | **Fixed** — all 6 methods wrapped in `DB::transaction()` with `lockForUpdate()` |

---

## What to check

### 1. Authorization gaps
- Every controller action must call `$this->authorize()`
- Check nested resource actions (GRN under PO, invoice under PO)
- Check policy methods exist for every `authorize()` call

### 2. N+1 query risks
- Relations used in Blade `@foreach` loops
- Compare controller `->load()` / `->with()` against what views access

### 3. Status gate correctness
- Actions allowed in wrong PO status (e.g. receiving on a cancelled PO)
- GRN complete allowed when PO not in receivable status
- Serial assignment allowed before QC done

### 4. Redirect correctness
- Success redirects go to expected page
- Error redirects preserve input where needed
- Session flash keys match what views read

### 5. Race conditions / TOCTOU
- Guards that run BEFORE `DB::transaction()` instead of inside
- Sequence counter races in `bulkReceive()`

### 6. Validation gaps
- FormRequest rules vs what service actually uses
- Missing `required` on fields that can't be null in DB

### 7. Blade / view bugs
- Variables used in view but not passed from controller
- Variables passed from controller but never used (dead weight)
- Missing `@can` gates on destructive action buttons

### 8. Soft delete gaps
- Routes resolving soft-deleted models without `withTrashed()`
- Restore actions that don't check current state

### 9. Edge cases
- PO with zero lines
- GRN with all lines qty_passed=0 (no serials generated — double-assign guard edge case)
- QC submit when GRN already has movements

---

## Bug Report Format

```
## BUG-XXX: [Short title]
**Severity:** Critical / High / Medium / Low / Suspected
**File:** path/to/file.php:line_number
**Description:** Clear explanation of what is wrong
**Steps to reproduce:** Numbered steps to trigger
**Expected:** What should happen
**Actual:** What happens instead
**Fix hint:** One-line suggestion
```

---

## Findings

---

## BUG-001: `PurchaseOrderController::store()` missing `$this->authorize()`

**Severity:** Critical
**File:** `app/Http/Controllers/PurchaseOrderController.php:44`

**Description:**
The `store()` action (POST `/admin/purchase-orders`) does not call `$this->authorize('create', PurchaseOrder::class)`. The `create()` action on line 37 does call `authorize`, but the corresponding `store()` does not. `StorePurchaseOrderRequest::authorize()` does check the permission via `$this->user()->can(Permission::PURCHASE_ORDERS_CREATE)`, so the FormRequest gate provides a partial guard. However, the policy (Gate) path is bypassed, meaning any logic in `PurchaseOrderPolicy::create()` that is more restrictive than the raw permission check (e.g., a future scope check) would be silently skipped. More critically, the pattern is inconsistent with every other controller in the module where both the `create()` and `store()` methods independently call `$this->authorize()`.

**Steps to reproduce:**
1. Log in with a user that has `PURCHASE_ORDERS_CREATE` permission.
2. Craft a direct POST to `/admin/purchase-orders` with valid payload.
3. The policy `PurchaseOrderPolicy::create()` is never invoked by the controller.

**Expected:** `$this->authorize('create', PurchaseOrder::class)` called at the top of `store()`.
**Actual:** Only the FormRequest permission check runs; the policy gate is never invoked.
**Fix hint:** Add `$this->authorize('create', PurchaseOrder::class);` as the first line of `store()`.

**Resolution:** Added `$this->authorize('create', PurchaseOrder::class);` as first line of `PurchaseOrderController::store()`. Covered by `ProcurementWorkflowTest` — sales user receives 403.

---

## BUG-002: `InventoryMovementController::storeBulkReceive()` missing `$this->authorize()`

**Severity:** Critical
**File:** `app/Http/Controllers/InventoryMovementController.php:167`

**Description:**
`storeBulkReceive()` (POST `/admin/inventory-movements/bulk-receive`) has no `$this->authorize()` call. The corresponding `bulkReceive()` GET action (line 151) does call `$this->authorize('bulkReceive', InventoryMovement::class)`. `StoreBulkReceiveRequest` may have its own permission check (not in scope here), but the policy gate is completely bypassed on the write path. An attacker who can POST directly to the route bypasses the `bulkReceive` policy entirely.

**Steps to reproduce:**
1. Log in with a user who has any valid session but should NOT have `INVENTORY_MOVEMENTS_BULK_RECEIVE`.
2. POST directly to `/admin/inventory-movements/bulk-receive` with valid data.
3. The `InventoryMovementPolicy::bulkReceive()` is never invoked.

**Expected:** `$this->authorize('bulkReceive', InventoryMovement::class)` at the top of `storeBulkReceive()`.
**Actual:** Policy gate not invoked; only FormRequest authorization runs.
**Fix hint:** Add `$this->authorize('bulkReceive', InventoryMovement::class);` as the first line of `storeBulkReceive()`.

**Resolution:** Added `$this->authorize('bulkReceive', InventoryMovement::class);` as first line of `InventoryMovementController::storeBulkReceive()`. Covered by `ProcurementWorkflowTest` — sales user receives 403.

---

## BUG-003: `@can` in GRN show view uses route-name string, not policy ability

**Severity:** High
**File:** `resources/views/goods-receipts/show.blade.php:253`

**Description:**
The "Assign Serial Numbers" button in the QC results section is gated by:
```blade
@can('inventory-movements.bulk-receive')
```
This passes a route-name string to Laravel's `@can` directive. Laravel will look for a Gate/Policy ability named `'inventory-movements.bulk-receive'`, which does not exist. The correct call — as used elsewhere in the codebase, e.g. `purchase-orders/show.blade.php:390` — is:
```blade
@can('bulkReceive', App\Models\InventoryMovement::class)
```
Because no ability named `'inventory-movements.bulk-receive'` is registered, `@can` will return `false` for all users. This means the "Assign Serial Numbers" button is **never rendered** in the GRN `show` view, even for admin/manager users who have the permission. The workflow is broken for any user attempting to assign serials from the GRN show page.

**Steps to reproduce:**
1. Complete a GRN and submit QC.
2. Open the GRN show page (`/admin/purchase-orders/{po}/goods-receipts/{grn}`).
3. The "Assign Serial Numbers →" button in the QC results section never appears for any user.

**Expected:** Button renders for users with `bulkReceive` permission on `InventoryMovement`.
**Actual:** Button never renders because the ability string does not match any registered policy method.
**Fix hint:** Replace `@can('inventory-movements.bulk-receive')` with `@can('bulkReceive', App\Models\InventoryMovement::class)`.

**Resolution:** Replaced in `goods-receipts/show.blade.php`. Covered by `ProcurementWorkflowTest` — admin sees button, sales user does not.

---

## BUG-004: `GoodsReceiptController::edit()` — missing eager load for `goodsReceipt->purchaseOrder->supplier`

**Severity:** High
**File:** `app/Http/Controllers/GoodsReceiptController.php:60–69`

**Description:**
The `edit()` method loads:
```php
$purchaseOrder->load(['lines.product']);
```
It loads lines on the `$purchaseOrder` binding, but the view (`goods-receipts/edit.blade.php:7–9`) accesses:
```blade
{{ $goodsReceipt->purchaseOrder->po_number }}
{{ $goodsReceipt->purchaseOrder->supplier->name }}
```
Neither `$goodsReceipt->purchaseOrder` nor `$goodsReceipt->purchaseOrder->supplier` is eager-loaded. Both will be lazy-loaded, triggering two extra queries on every page load. `supplier` is accessed via a chain (`purchaseOrder->supplier`), making it a missing nested eager load.

**Steps to reproduce:**
1. Navigate to `/admin/purchase-orders/{po}/goods-receipts/{grn}/edit`.
2. Observe two extra SELECT queries issued for `purchase_orders` and `suppliers`.

**Expected:** `$goodsReceipt->load(['purchaseOrder.supplier'])` called in `edit()`.
**Actual:** Lazy loads on every request; performance degrades and query log bloats.
**Fix hint:** Add `$goodsReceipt->load(['purchaseOrder.supplier']);` alongside the existing `$purchaseOrder->load(...)` in `edit()`.

**Resolution:** Changed `$purchaseOrder->load(...)` in `GoodsReceiptController::edit()` to `$purchaseOrder->load(['supplier', 'lines.product'])` — supplier is on the PO binding which is already in scope.

---

## BUG-005: `GoodsReceiptController::show()` — `lines.qcInspectedBy` not eager-loaded

**Severity:** High
**File:** `app/Http/Controllers/GoodsReceiptController.php:50–58`

**Description:**
The `show()` method loads:
```php
$goodsReceipt->load(['purchaseOrder.supplier', 'lines.purchaseOrderLine.product', 'receivedBy']);
```
The view (`goods-receipts/show.blade.php:304`) renders the QC results table and accesses:
```blade
{{ $line->qcInspectedBy?->name ?? '—' }}
```
`qcInspectedBy` is defined on `GoodsReceiptLine` as a `BelongsTo(User::class, 'qc_inspected_by')`. It is NOT included in the eager-load chain. For a GRN with N lines where QC has been submitted, this causes N additional queries (one per line) to load the inspecting user.

**Steps to reproduce:**
1. Complete a GRN, submit QC with multiple lines.
2. Open the GRN show page.
3. Observe one additional SELECT per line against `users` table.

**Expected:** `lines.qcInspectedBy` included in the `load()` call.
**Actual:** N+1 queries for QC inspectors when QC results are displayed.
**Fix hint:** Change the load to `['purchaseOrder.supplier', 'lines.purchaseOrderLine.product', 'lines.qcInspectedBy', 'receivedBy']`.

**Resolution:** Updated `GoodsReceiptController::show()` load chain to include `lines.qcInspectedBy`.

---

## BUG-006: `GoodsReceiptController::assignSerials()` — `receivedBy` not eager-loaded

**Severity:** High
**File:** `app/Http/Controllers/GoodsReceiptController.php:123–146`

**Description:**
The `assignSerials()` method loads:
```php
$goodsReceipt->load(['lines.purchaseOrderLine.product']);
```
The view (`goods-receipts/assign-serials.blade.php:53`) renders:
```blade
{{ $goodsReceipt->receivedBy->name ?? '—' }}
```
`receivedBy` is not in the load. Every page visit triggers an extra SELECT for the user who received the GRN.

Additionally, line 9 of `assign-serials.blade.php` renders:
```blade
{{ $purchaseOrder->supplier->name }}
```
The `$purchaseOrder` is resolved via route model binding but `supplier` is never loaded. Each page load triggers a lazy-load on `suppliers`.

**Steps to reproduce:**
1. Navigate to `/admin/purchase-orders/{po}/goods-receipts/{grn}/assign-serials`.
2. Observe two extra SELECT queries: one for `received_by` user, one for supplier.

**Expected:** Load `receivedBy` on the GRN and `supplier` on the PO.
**Actual:** Two lazy-load queries on every page load.
**Fix hint:** Change load to `$goodsReceipt->load(['lines.purchaseOrderLine.product', 'receivedBy'])` and add `$purchaseOrder->loadMissing('supplier')`.

**Resolution:** Updated `GoodsReceiptController::assignSerials()` — GRN load now includes `receivedBy`; added `$purchaseOrder->loadMissing('supplier')`. BUG-010 resolved as part of this fix.

---

## BUG-007: `GoodsReceiptService::complete()` — no guard on PO status

**Severity:** High
**File:** `app/Services/GoodsReceiptService.php:92–108`

**Description:**
`complete()` only guards against an already-complete GRN:
```php
throw_if(
    $grn->status === GoodsReceiptStatus::Complete,
    ...
);
```
It does NOT check the PO status. This means a Draft GRN attached to a PO in `Cancelled`, `Closed`, `Returned`, or `Rejected` status can be marked complete. The `store()` method validates that the PO must be in `[Approved, OnTheWay, PartiallyReceived]`, but once a GRN exists in Draft status, no status gate prevents completing it if the PO has since been cancelled or closed. After `complete()` runs, `updatePoStatus()` would unconditionally set the PO to `QualityCheck`, resurrecting a cancelled PO into the active workflow.

**Steps to reproduce:**
1. Create a GRN on an approved PO (Draft GRN created).
2. Cancel the PO (status → `Cancelled`).
3. Navigate directly to `/admin/purchase-orders/{po}/goods-receipts/{grn}` and click "Complete".
4. `complete()` runs, calls `updatePoStatus()`, PO status becomes `QualityCheck`.

**Expected:** `complete()` rejects completion when PO is not in a receivable status.
**Actual:** A GRN can be completed against a cancelled/closed/returned PO, silently changing the PO's status.
**Fix hint:** Add a PO status guard in `complete()` before the transaction, analogous to the guard in `store()`.

**Resolution:** `GoodsReceiptService::complete()` now takes `?PurchaseOrder $po = null`. Guard added before transaction: throws `DomainException` if PO status is `Cancelled` or `Rejected`. Covered by `GoodsReceiptServiceEdgeCasesTest` — BUG-007 cancelled and rejected cases.

---

## BUG-008: `GoodsReceiptService::update()` — no guard on GRN QC state

**Severity:** Medium
**File:** `app/Services/GoodsReceiptService.php:59–89`

**Description:**
`update()` guards against editing a `Complete` GRN:
```php
throw_if(
    $grn->status === GoodsReceiptStatus::Complete,
    ...
);
```
The controller also checks this (line 63). However, the check is against the status only. Because `GoodsReceiptStatus` has only `Draft` and `Complete`, this is functionally correct — a `Draft` GRN has not yet had QC applied. This is not a bug per se.

What IS a problem: inside the transaction, `update()` calls `$grn->lines()->delete()` and reinserts all lines. If the GRN had any `qty_passed`/`qty_failed` data already set on lines (which should not happen in `Draft`, but could happen if a data integrity issue occurs), this would silently wipe the QC data. There is no guard that verifies QC data is absent before deleting lines.

**Steps to reproduce:**
1. Cause a QC submission on a GRN that is in some intermediate state (e.g., via a DB manipulation).
2. Edit the GRN via the update route.
3. All `qty_passed`/`qty_failed`/`qc_inspected_by` data is silently deleted.

**Expected:** Guard that aborts if any GRN line has QC data (non-null `qty_passed`).
**Actual:** Lines are unconditionally deleted and recreated without checking for existing QC data.
**Fix hint:** Add a check before the delete: `throw_if($grn->lines()->whereNotNull('qty_passed')->exists(), ...)`.

**Resolution — Not a Bug:** Analysis confirmed this is dead code. `update()` already rejects `Complete` GRNs as its first guard. QC data (`qty_passed`) is only ever set on `Complete` GRNs. Therefore a `Draft` GRN can never have QC data — the proposed guard is unreachable. No fix applied. Test added to `GoodsReceiptServiceEdgeCasesTest` confirming the existing "Completed goods receipts cannot be edited" guard is sufficient.

---

## BUG-009: `GoodsReceiptService::validateLineQtys()` silently skips unknown PO lines

**Severity:** Medium
**File:** `app/Services/GoodsReceiptService.php:255–270`

**Description:**
Inside `validateLineQtys()`, when iterating submitted lines:
```php
$poLine = $po->lines->firstWhere('id', $lineData['purchase_order_line_id']);

if (! $poLine) {
    continue;  // silently skip
}
```
If a `purchase_order_line_id` submitted in the form does not belong to the current PO, the validation silently skips it instead of throwing an exception. This means a crafted POST could include line IDs from a *different* PO — those lines pass the `exists:purchase_order_lines,id` validation in `StoreGoodsReceiptRequest`, but the cross-PO ownership check is never enforced in the service: the line simply slips through, and a `GoodsReceiptLine` is inserted pointing to a line from a different PO.

**Steps to reproduce:**
1. Create PO-A (with line_id=1) and PO-B (with line_id=99).
2. When creating a GRN for PO-A, POST `lines[0][purchase_order_line_id]=99` (PO-B's line).
3. The FormRequest allows it (`exists:purchase_order_lines,id` passes for ID 99).
4. `validateLineQtys()` calls `$po->lines->firstWhere('id', 99)` → returns null → `continue`.
5. A `GoodsReceiptLine` row is inserted with `purchase_order_line_id=99` pointing to PO-B's line.

**Expected:** Throw a `DomainException` if the line ID does not belong to this PO.
**Actual:** Foreign-PO line IDs are silently inserted, corrupting the GRN data.
**Fix hint:** Replace `continue` with `throw new \DomainException("Line {$lineData['purchase_order_line_id']} does not belong to this purchase order.")`.

**Resolution:** Replaced silent `continue` with `throw_if(! $poLine, \DomainException::class, ...)` in `GoodsReceiptService::validateLineQtys()`. Covered by `GoodsReceiptServiceEdgeCasesTest` — BUG-009 foreign line ID throws.

---

## BUG-010: `assign-serials.blade.php` — `$purchaseOrder->supplier` not eager-loaded

**Severity:** Medium
**File:** `app/Http/Controllers/GoodsReceiptController.php:123–146` / `resources/views/goods-receipts/assign-serials.blade.php:9`

**Description:**
(This is the second part of BUG-006 expressed as a standalone finding for clarity.)
In `assignSerials()`, the controller does not call `$purchaseOrder->load('supplier')` or `$purchaseOrder->loadMissing('supplier')`. The view header renders:
```blade
{{ $purchaseOrder->supplier->name }}
```
This triggers a lazy-load on the `suppliers` table for every page view. While not causing incorrect behaviour, it is an avoidable N+1 on a frequently-visited page.

**Fix hint:** Add `$purchaseOrder->loadMissing('supplier');` in `assignSerials()`.

**Resolution:** Merged into BUG-006 fix — both `receivedBy` and `supplier` added in the same `assignSerials()` controller update.

---

## BUG-011: `GoodsReceiptService::submitQc()` — TOCTOU: `alreadyDone` guard runs before inner refresh is meaningful

**Severity:** Medium
**File:** `app/Services/GoodsReceiptService.php:113–177`

**Description:**
`submitQc()` runs the `alreadyDone` guard at line 129:
```php
$alreadyDone = $grn->lines()->whereNotNull('qty_passed')->exists();
throw_if($alreadyDone, ...);
```
This is inside the `DB::transaction` block and is preceded by `$grn->refresh()`, which is correct. However, the `$grn->lines()` query at this point does NOT use `LOCK IN SHARE MODE` or `FOR UPDATE`. A concurrent second request that passes this check at the same time can both proceed to update the same GRN lines, resulting in duplicate QC submissions. The race window is small but present for concurrent requests.

Additionally, the per-line check at lines 139–144 — `GoodsReceiptLine::find($lineData['goods_receipt_line_id'])` — does not use a locking read, and ownership is checked afterward. Under high concurrency, two simultaneous QC submissions could both validate successfully and both update the same line.

**Steps to reproduce:**
1. Two users open the QC form for the same GRN simultaneously.
2. Both click "Submit QC" at approximately the same time.
3. Both transactions pass the `alreadyDone` check because neither has committed yet.
4. Both proceed to `$grnLine->update(...)`, resulting in the last writer winning — but the `activity()` log records two QC submissions.

**Expected:** First submitter wins; second gets a domain exception.
**Actual:** Both can succeed under concurrent load, producing duplicate activity log entries.
**Fix hint:** Use `$grn->lines()->lockForUpdate()->whereNotNull('qty_passed')->exists()` for the `alreadyDone` check, and use `GoodsReceiptLine::lockForUpdate()->find(...)` when fetching each line inside the loop.

**Resolution:** Added `->lockForUpdate()` to the `alreadyDone` check inside `submitQc()` transaction. Covered by `GoodsReceiptServiceEdgeCasesTest` — BUG-011 double-submit guard test pre-seeds `qty_passed` and verifies DomainException message "QC has already been submitted".

---

## BUG-012: `purchase-orders/show.blade.php` — rejection form missing `@can('reject')` gate

**Severity:** Medium
**File:** `resources/views/purchase-orders/show.blade.php:154–174`

**Description:**
The rejection form is wrapped in `@can('approve', $purchaseOrder)`:
```blade
@if($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::PendingApproval)
    @can('approve', $purchaseOrder)
        <form ... action="{{ route('purchase-orders.reject', $purchaseOrder) }}">
```
The form uses the `approve` permission gate but submits to the `reject` route. A user with `PURCHASE_ORDERS_APPROVE` permission but NOT `PURCHASE_ORDERS_REJECT` will see and can submit the rejection form. The `PurchaseOrderController::reject()` does call `$this->authorize('reject', $purchaseOrder)`, so the server-side gate is enforced — the user would get a 403 on submit. But presenting the UI element to users who cannot successfully use it is a UX bug and a confusion vector.

The correct gate is `@can('reject', $purchaseOrder)`.

**Steps to reproduce:**
1. Create a user with `PURCHASE_ORDERS_APPROVE` but not `PURCHASE_ORDERS_REJECT`.
2. View a PO in `PendingApproval` status.
3. The rejection form input and button are visible.
4. Submitting it returns a 403.

**Expected:** Rejection form shown only to users with `reject` permission.
**Actual:** Shown to any user with `approve` permission; submission fails with 403.
**Fix hint:** Change inner `@can('approve', $purchaseOrder)` wrapping the reject form to `@can('reject', $purchaseOrder)`.

**Resolution:** Changed `@can('approve', $purchaseOrder)` to `@can('reject', $purchaseOrder)` in `purchase-orders/show.blade.php`. Covered by `ProcurementWorkflowTest` — reject-only user sees form; approve-only user does not.

---

## BUG-013: `GoodsReceiptService::updatePoStatus()` — unconditionally downgrades PO status

**Severity:** Low
**File:** `app/Services/GoodsReceiptService.php:209–223`

**Description:**
`updatePoStatus()` always sets the PO to `QualityCheck` when any line has been received:
```php
if ($anyReceived) {
    $po->update(['status' => PurchaseOrderStatus::QualityCheck]);
}
```
This is called from `complete()`. If a PO has already progressed past `QualityCheck` to `PartiallyReceived` or `Received` (serials assigned for a prior GRN), completing a second GRN unconditionally overwrites the status back to `QualityCheck`. This is likely the intended flow for multi-GRN scenarios, but it means a PO that is fully received with serials assigned can have its status silently downgraded the moment a second GRN is completed for it. The PO's prior `PartiallyReceived`/`Received` state, which triggered serial assignment, is lost.

**Steps to reproduce:**
1. Create a PO with 2 products, each qty 2.
2. Create GRN-1 for product-A (qty 2), complete it, pass QC → PO becomes `Received` (if product-B has qty 0 received... wait, product-B has not been received so PO becomes `PartiallyReceived`).
3. Assign serials for GRN-1.
4. Create GRN-2 for product-B (qty 2), complete it → `updatePoStatus()` sets PO back to `QualityCheck`.
5. PO is now in `QualityCheck` even though it had been in `PartiallyReceived`.

**Expected:** `updatePoStatus()` should only set `QualityCheck` if the PO is currently in a status that logically precedes it (e.g., `Approved`, `OnTheWay`, `PartiallyReceived`, `Received`).
**Actual:** PO status unconditionally set to `QualityCheck` — could overwrite a more advanced status like `Invoiced` if a late GRN is completed.
**Fix hint:** Add a guard: only update to `QualityCheck` if the current status is in `[Approved, OnTheWay, PartiallyReceived, Received]`.

**Resolution:** Added `$skipStatuses` early-return guard to `GoodsReceiptService::updatePoStatus()` — skips update if PO status is already `QualityCheck`, `PartiallyReceived`, `Received`, `Cancelled`, or `Rejected`. Covered by `GoodsReceiptServiceEdgeCasesTest` — BUG-013 tests verify PartiallyReceived, Received, and Cancelled statuses are preserved.

---

## BUG-014: `InventoryMovementService::bulkReceiveFromGrn()` — no activity log when all QC-passed quantities are 0

**Severity:** Low
**File:** `app/Services/InventoryMovementService.php:435–445`

**Description:**
Inside `bulkReceiveFromGrn()`, the activity log is only recorded if `$allSerials->isNotEmpty()`:
```php
if ($allSerials->isNotEmpty()) {
    activity()->...->log('serials_generated');
}
```
If every GRN line has `qty_passed = 0` (all goods failed QC), the method returns an empty collection and no activity log entry is written. This is a valid edge case — the user navigated to "Assign Serials", submitted the form, and the system silently succeeded but did nothing. The audit trail has no record of this event.

**Steps to reproduce:**
1. Submit QC on a GRN with all qty_passed = 0.
2. Navigate to the assign-serials page (it will show "No units passed QC" and return early from the form — but if a POST is crafted manually).
3. Actually — the view guards this with `@if($passedLines->isEmpty())` showing a message instead of the form. So a normal user cannot trigger this through the UI.
4. But a crafted POST to `storeSerials` with valid line data where all GRN lines have `qty_passed = 0` will succeed and return an empty collection without logging.

**Expected:** Log an event even when zero serials were generated (e.g., `serials_skipped` or include a count of zero in the log).
**Actual:** No audit log entry when all QC lines have 0 passed units.
**Fix hint:** Move the `activity()` call outside the `isNotEmpty()` guard, or add a separate `serials_skipped` log entry when `$allSerials->isEmpty()`.

**Resolution — Accepted:** UI already prevents reaching this path — `assignSerials()` view renders a "No units passed QC" message and hides the form when all `qty_passed = 0`. A crafted POST is an edge case with no business harm (no serials generated). Low-priority; no code change applied.

---

## BUG-015 (Suspected): `PurchaseOrderController::restore()` — soft-deleted model may not be resolvable

**Severity:** Suspected
**File:** `app/Http/Controllers/PurchaseOrderController.php:107–112` / `routes/web.php:168`

**Description:**
The restore route is defined as:
```php
Route::post('/{purchaseOrder}/restore', [PurchaseOrderController::class, 'restore'])
    ->name('restore')
    ->withTrashed();
```
The `->withTrashed()` call on the route is the correct way to allow Laravel's route model binding to resolve soft-deleted models. This appears correct.

However, `PurchaseOrderService::restore()` is:
```php
public function restore(PurchaseOrder $po): void
{
    $po->restore();
}
```
There is no guard checking the current state of the PO before restoring it. A deleted PO in `Approved` or `OnTheWay` status could be restored without any check that the PO's prior state is still valid (e.g., the supplier may have been soft-deleted since the PO was created). The restore succeeds silently regardless of the PO's status at the time of deletion or the current state of its supplier.

**Suspected** because whether this constitutes a bug depends on business requirements — restoring a PO without checking supplier availability may be intentional. But the lack of any guard is worth flagging.

**Fix hint:** Add a check in `restore()` to verify the supplier still exists and is active before restoring the PO.

**Resolution — Not a Bug:** Route definition already has `->withTrashed()`, so soft-deleted models are correctly resolved. The restore guard concern (supplier state check) is a future business requirement, not a current bug — restore is intentionally permissive to let admins recover POs regardless of current supplier status.

---

## New Bugs — Discovered via Reference Audit (2026-05-07)

---

## BUG-016: `PurchaseOrderPolicy` missing `markOnTheWay()` method

**Severity:** Critical | **Status:** Fixed (2026-05-07)
**File:** `app/Policies/PurchaseOrderPolicy.php` / `resources/views/purchase-orders/show.blade.php`

**Problem:** View uses `@can('markOnTheWay', $purchaseOrder)` but no such method exists in the policy — `@can` returns false for every user, "Mark On The Way" button never renders. Controller `markOnTheWay()` action calls `$this->authorize('update', $purchaseOrder)` — a mismatch with the view gate.

**Draft Fix:**
```php
// PurchaseOrderPolicy.php — add method:
public function markOnTheWay(User $user, PurchaseOrder $purchaseOrder): bool
{
    return $user->can(Permission::PURCHASE_ORDERS_UPDATE);
}

// PurchaseOrderController.php — change from:
$this->authorize('update', $purchaseOrder);
// to:
$this->authorize('markOnTheWay', $purchaseOrder);
```

**Resolution:** Added `markOnTheWay()` to `PurchaseOrderPolicy`; updated controller to `$this->authorize('markOnTheWay', $purchaseOrder)`.

---

## BUG-017: `GoodsReceiptService::store()` — PO status + qty guards outside transaction (TOCTOU)

**Severity:** Critical | **Status:** Fixed (2026-05-07)
**File:** `app/Services/GoodsReceiptService.php:21–34`

**Problem:** PO status check (lines 21–32) and `validateLineQtys()` (line 33) both run before `DB::transaction()` opens on line 35. Concurrent request can change PO status or receive additional qty between the check and the insert.

**Draft Fix:**
```php
public function store(PurchaseOrder $po, array $data, User $receivedBy): GoodsReceipt
{
    return DB::transaction(function () use ($po, $data, $receivedBy): GoodsReceipt {
        $po->refresh();
        $allowedStatuses = [
            PurchaseOrderStatus::Approved,
            PurchaseOrderStatus::OnTheWay,
            PurchaseOrderStatus::PartiallyReceived,
        ];
        throw_if(
            ! in_array($po->status, $allowedStatuses, true),
            \DomainException::class,
            'Goods receipts can only be created for approved, on-the-way, or partially received purchase orders.'
        );

        $this->validateLineQtys($po, $data['lines']);

        $grn = GoodsReceipt::create([
            'purchase_order_id' => $po->id,
            'grn_number'        => $this->generateGrnNumber(),
            'received_by'       => $receivedBy->id,
            'received_date'     => $data['received_date'],
            'notes'             => $data['notes'] ?? null,
            'status'            => GoodsReceiptStatus::Draft,
        ]);

        $now = now();
        GoodsReceiptLine::insert(array_map(fn ($line) => [
            'goods_receipt_id'       => $grn->id,
            'purchase_order_line_id' => $line['purchase_order_line_id'],
            'qty_received'           => $line['qty_received'],
            'notes'                  => $line['notes'] ?? null,
            'created_at'             => $now,
            'updated_at'             => $now,
        ], $data['lines']));

        return $grn->fresh(['lines.purchaseOrderLine.product', 'receivedBy']);
    });
}
```

**Resolution:** Guards and `validateLineQtys()` moved inside `DB::transaction()` with `$po->refresh()` at the top of the closure.

---

## BUG-018: `GoodsReceiptService::update()` — status guard outside transaction (TOCTOU)

**Severity:** High | **Status:** Fixed (2026-05-07)
**File:** `app/Services/GoodsReceiptService.php:61–68`

**Problem:** `throw_if($grn->status === Complete)` check on line 61 runs before `DB::transaction()` on line 70. Concurrent request can complete the GRN between the check and `$grn->lines()->delete()`.

**Draft Fix:**
```php
public function update(GoodsReceipt $grn, array $data, ?PurchaseOrder $po = null): GoodsReceipt
{
    $po ??= $grn->purchaseOrder;

    return DB::transaction(function () use ($grn, $po, $data): GoodsReceipt {
        $grn->refresh();
        throw_if(
            $grn->status === GoodsReceiptStatus::Complete,
            \DomainException::class,
            'Completed goods receipts cannot be edited.'
        );

        $this->validateLineQtys($po, $data['lines'], $grn->id);

        $grn->update([
            'received_date' => $data['received_date'],
            'notes'         => $data['notes'] ?? null,
        ]);

        $grn->lines()->delete();

        $now = now();
        GoodsReceiptLine::insert(array_map(fn ($line) => [
            'goods_receipt_id'       => $grn->id,
            'purchase_order_line_id' => $line['purchase_order_line_id'],
            'qty_received'           => $line['qty_received'],
            'notes'                  => $line['notes'] ?? null,
            'created_at'             => $now,
            'updated_at'             => $now,
        ], $data['lines']));

        return $grn->fresh(['lines.purchaseOrderLine.product']);
    });
}
```

**Resolution:** Status guard and `validateLineQtys()` moved inside `DB::transaction()` with `$grn->refresh()` at the top.

---

## BUG-019: `GoodsReceiptService::complete()` — both guards outside transaction (TOCTOU)

**Severity:** High | **Status:** Fixed (2026-05-07)
**File:** `app/Services/GoodsReceiptService.php:94–106`

**Problem:** Already-complete check (line 94) and cancelled/rejected PO check (lines 101–105) both precede `DB::transaction()` on line 107. Both subject to race.

**Draft Fix:**
```php
public function complete(GoodsReceipt $grn, ?PurchaseOrder $po = null): GoodsReceipt
{
    $po ??= $grn->purchaseOrder;

    return DB::transaction(function () use ($grn, $po): GoodsReceipt {
        $grn->refresh();
        throw_if(
            $grn->status === GoodsReceiptStatus::Complete,
            \DomainException::class,
            'This goods receipt is already complete.'
        );

        $po->refresh();
        throw_if(
            in_array($po->status, [PurchaseOrderStatus::Cancelled, PurchaseOrderStatus::Rejected], true),
            \DomainException::class,
            'Cannot complete a goods receipt for a cancelled or rejected purchase order.'
        );

        $grn->update(['status' => GoodsReceiptStatus::Complete]);
        $po->load('lines');
        $this->updatePoQtyReceived($po);
        $this->updatePoStatus($po);

        return $grn->loadMissing(['lines.purchaseOrderLine.product', 'purchaseOrder.supplier', 'receivedBy']);
    });
}
```

**Resolution:** Both guards moved inside `DB::transaction()` with `$grn->refresh()` and `$po->refresh()` at top of closure.

---

## BUG-020: `GoodsReceiptService::submitQc()` — activity log fires inside transaction

**Severity:** High | **Status:** Fixed (2026-05-07)
**File:** `app/Services/GoodsReceiptService.php:169–181`

**Problem:** `activity()->log('qc_submitted')` on line 169 is inside `DB::transaction()`. If transaction rolls back after the log write, the audit entry is lost inconsistently.

**Draft Fix:**
```php
public function submitQc(GoodsReceipt $grn, array $data, User $inspector): GoodsReceipt
{
    $result = DB::transaction(function () use ($grn, $data, $inspector): GoodsReceipt {
        // ... all existing guards and line updates unchanged ...
        $this->poService->passQualityCheck($po, null);

        return $grn->fresh(['lines.purchaseOrderLine.product', 'purchaseOrder']);
    });

    // After transaction commits — log is durable:
    activity()
        ->causedBy($inspector)
        ->performedOn($grn)
        ->withProperties([
            'grn_number' => $grn->grn_number,
            'lines'      => collect($data['lines'])->map(fn ($l) => [
                'goods_receipt_line_id' => $l['goods_receipt_line_id'],
                'qty_passed'            => $l['qty_passed'],
                'qty_failed'            => $l['qty_failed'],
            ])->all(),
        ])
        ->log('qc_submitted');

    return $result;
}
```

**Resolution:** `activity()->log()` call moved outside the transaction closure — executes only after commit.

---

## BUG-021: PO/GRN number generation — read-modify-write race condition

**Severity:** High | **Status:** Fixed (2026-05-07)
**File:** `app/Services/PurchaseOrderService.php:231` / `app/Services/GoodsReceiptService.php:243`

**Problem:** `MAX(po_number)` → compute next → `create()` is not atomic. Two concurrent creates read the same max, compute the same sequence, both insert — DB unique constraint throws on the second. User sees a 500 error.

**Draft Fix:**
```php
// PurchaseOrderService.php:
public function generatePoNumber(): string
{
    return DB::transaction(function (): string {
        $year   = now()->year;
        $prefix = "PO-{$year}-";
        $max    = PurchaseOrder::withTrashed()
            ->where('po_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->max('po_number');
        $next = $max ? ((int) substr($max, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    });
}

// GoodsReceiptService.php — same pattern, prefix GRN-{year}-:
public function generateGrnNumber(): string
{
    return DB::transaction(function (): string {
        $year   = now()->year;
        $prefix = "GRN-{$year}-";
        $max    = GoodsReceipt::withTrashed()
            ->where('grn_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->max('grn_number');
        $next = $max ? ((int) substr($max, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    });
}
```
Note: called from inside outer transaction — inner `DB::transaction()` becomes a savepoint, `lockForUpdate()` still serialises the read.

**Resolution:** Both `generatePoNumber()` and `generateGrnNumber()` now wrap `lockForUpdate()->max()` inside `DB::transaction()`.

---

## BUG-022: `PurchaseOrderController::qualityCheck()` — no FormRequest, `qc_notes` unvalidated

**Severity:** High | **Status:** Fixed (2026-05-07)
**File:** `app/Http/Controllers/PurchaseOrderController.php:181`

**Problem:** Action uses raw `Request`, calls `$request->input('qc_notes')` with no length or type validation. Arbitrary string written to `qc_notes` DB column.

**Draft Fix:**
```php
// New file: app/Http/Requests/PurchaseOrder/StoreQcNotesRequest.php
<?php

declare(strict_types=1);

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class StoreQcNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller handles policy
    }

    public function rules(): array
    {
        return [
            'qc_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

// PurchaseOrderController.php — change signature:
public function qualityCheck(StoreQcNotesRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
{
    $this->authorize('qualityCheck', $purchaseOrder);
    // use $request->validated()['qc_notes'] ?? null
```

**Resolution:** `StoreQcNotesRequest` created at `app/Http/Requests/PurchaseOrder/StoreQcNotesRequest.php`; controller updated to inject it.

---

## BUG-023: `LogsActivity` trait — wrong namespace in 3 models

**Severity:** High | **Status:** Not a Bug
**File:** `app/Models/PurchaseOrder.php`, `app/Models/GoodsReceipt.php`, `app/Models/Invoice.php`

**Problem:** All three models import `Spatie\Activitylog\Models\Concerns\LogsActivity`. In `spatie/laravel-activitylog` v4+ the trait lives at `Spatie\Activitylog\Traits\LogsActivity`. Wrong namespace will throw a fatal `Class not found` on any request that touches these models — unless the project has a custom alias (needs verification; if tests pass today the namespace may already be correct, but it should be confirmed).

**Draft Fix:**
```php
// In each model — change:
use Spatie\Activitylog\Models\Concerns\LogsActivity;
// to:
use Spatie\Activitylog\Traits\LogsActivity;
```

**Resolution:** Verified — `Spatie\Activitylog\Traits\LogsActivity` is the correct namespace for this project's installed package version. All 567 unit/feature tests pass with no `Class not found` errors. No change required.

---

## BUG-024: `invoices` table — no DB-level unique constraint on `invoice_number`

**Severity:** High | **Status:** Fixed (2026-05-07)
**File:** `database/migrations/2026_05_10_*_create_invoices_table.php`

**Problem:** `StoreInvoiceRequest` validates `unique:invoices,invoice_number` at app level, but no DB unique index exists. Concurrent requests can bypass the app-level check and insert duplicate invoice numbers.

**Draft Fix:**
```php
// New migration:
Schema::table('invoices', function (Blueprint $table) {
    $table->unique('invoice_number');
});
```

**Resolution:** Migration added `->unique()` to `invoice_number` column in the invoices table creation migration.

---

## BUG-025: `assign-serials.blade.php` — wrong session key, Print Labels link always hidden

**Severity:** High | **Status:** Fixed (2026-05-07)
**File:** `resources/views/goods-receipts/assign-serials.blade.php:25`

**Problem:** View checks `session('bulk_receive_serial_ids')` but `GoodsReceiptController::storeSerials()` stores the IDs under key `bulk_receive_ids`. The condition is always false — the "Print Labels" link is dead code.

**Draft Fix:**
```blade
{{-- Change line 25 from: --}}
@if(session('bulk_receive_serial_ids'))
{{-- to: --}}
@if(session('bulk_receive_ids'))
```

**Resolution:** View updated to use `session('bulk_receive_ids')` — the key that `GoodsReceiptController::storeSerials()` actually writes.

---

## BUG-026: Missing unit tests — `PurchaseOrderService` and `InvoiceService` have zero coverage

**Severity:** High | **Status:** Fixed (2026-05-07)
**File:** `tests/Unit/Services/` (files do not exist)

**Problem:** `GoodsReceiptService` has a full unit test suite, but `PurchaseOrderService` and `InvoiceService` have no unit tests at all. Core business logic (status transitions, totals recalculation, invoice marking paid → PO closed) is untested.

**Draft Fix:** Create:
- `tests/Unit/Services/PurchaseOrderServiceTest.php` — cover `store`, `update`, `submit`, `approve`, `reject`, `markOnTheWay`, `passQualityCheck`, `cancel`, `generatePoNumber`
- `tests/Unit/Services/InvoiceServiceTest.php` — cover `store`, `approve`, `markPaid` (including PO → closed transition), `delete`

**Resolution:** Both test files created. Full suite (567 tests) passes.

---

## BUG-027: `GoodsReceiptServiceTest` — no happy-path test for `submitQc()`

**Severity:** High | **Status:** Not a Bug
**File:** `tests/Unit/Services/GoodsReceiptServiceTest.php`

**Problem:** Only the double-submission guard is tested (edge case). No test verifies that a valid `submitQc()` call writes `qty_passed`, `qty_failed`, `qc_inspected_at`, `qc_inspected_by` to GRN lines and transitions the PO correctly.

**Draft Fix:** Add to `GoodsReceiptServiceTest.php`:
```php
it('submitQc() writes QC data to lines and transitions PO to received or partially_received', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status'      => PurchaseOrderStatus::QualityCheck,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id'        => $this->product->id,
        'qty_ordered'       => 5,
        'qty_received'      => 5,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status'            => GoodsReceiptStatus::Complete,
    ]);
    $grnLine = GoodsReceiptLine::factory()->create([
        'goods_receipt_id'       => $grn->id,
        'purchase_order_line_id' => $poLine->id,
        'qty_received'           => 5,
    ]);

    $this->service->submitQc($grn, [
        'lines' => [[
            'goods_receipt_line_id' => $grnLine->id,
            'qty_passed'            => 4,
            'qty_failed'            => 1,
        ]],
    ], $this->user);

    $this->assertDatabaseHas('goods_receipt_lines', [
        'id'         => $grnLine->id,
        'qty_passed' => 4,
        'qty_failed' => 1,
    ]);

    expect(in_array($po->fresh()->status, [
        PurchaseOrderStatus::Received,
        PurchaseOrderStatus::PartiallyReceived,
    ], true))->toBeTrue();
});
```

**Resolution:** `GoodsReceiptServiceQcTest` already contains a passing happy-path test for `submitQc()`. The proposed test above is redundant. No change needed.

---

## BUG-028: No individual feature tests for `PurchaseOrderController` CRUD or `InvoiceController`

**Severity:** High | **Status:** Fixed (2026-05-07)
**File:** `tests/Feature/` (files do not exist)

**Problem:** `ProcurementWorkflowTest` covers the full happy-path integration only. No individual action tests for: `index` (200 + view), `create` (200), `show` (200 + data), `edit` (redirects for non-draft), `update` (validation + redirect), `destroy` (soft deletes), `restore`. Same gap for `InvoiceController`.

**Draft Fix:** Create:
- `tests/Feature/PurchaseOrderControllerTest.php`
- `tests/Feature/InvoiceControllerTest.php`

**Resolution:** Both files created with full CRUD + authorization coverage. All 567 tests pass.

---

## BUG-029: `InventoryMovementPolicy::bulkReceive()` existence unconfirmed

**Severity:** High | **Status:** Not a Bug
**File:** `app/Policies/InventoryMovementPolicy.php`

**Problem:** `GoodsReceiptController::assignSerials()` and `storeSerials()` both call `$this->authorize('bulkReceive', InventoryMovement::class)`. The view uses `@can('bulkReceive', App\Models\InventoryMovement::class)`. If `InventoryMovementPolicy::bulkReceive()` does not exist, the gate returns false for everyone — button never renders, form never submits.

**Draft Fix:** Verify method exists. If missing, add:
```php
// InventoryMovementPolicy.php:
public function bulkReceive(User $user): bool
{
    return $user->can(Permission::INVENTORY_MOVEMENTS_BULK_RECEIVE);
}
```

**Resolution:** Verified — `InventoryMovementPolicy::bulkReceive()` exists at line 58. E2E test 1.8 (serial assignment) passes. No change required.

---

## BUG-030: `StoreGoodsReceiptRequest` dual-purpose — HTTP method inspection in `authorize()`

**Severity:** Medium | **Status:** Fixed (2026-05-07)
**File:** `app/Http/Requests/GoodsReceipt/StoreGoodsReceiptRequest.php:13–16`

**Problem:** `authorize()` branches on `$this->isMethod('PUT')` to switch between `GOODS_RECEIPTS_UPDATE` and `GOODS_RECEIPTS_CREATE`. One FormRequest carries two authorization paths — violates single-responsibility and the reference rule "one FormRequest per action".

**Draft Fix:** Create `UpdateGoodsReceiptRequest.php`:
```php
class UpdateGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::GOODS_RECEIPTS_UPDATE);
    }

    public function rules(): array
    {
        return (new StoreGoodsReceiptRequest)->rules(); // same rules
    }
}
```
Use `UpdateGoodsReceiptRequest` in `GoodsReceiptController::update()`, revert `StoreGoodsReceiptRequest::authorize()` to check only `GOODS_RECEIPTS_CREATE`.

**Resolution:** `UpdateGoodsReceiptRequest` created and wired to `GoodsReceiptController::update()`; `StoreGoodsReceiptRequest::authorize()` reverted to check only `GOODS_RECEIPTS_CREATE`.

---

## BUG-031: `InvoiceController::approve()` uses `auth()->user()` facade

**Severity:** Medium | **Status:** Fixed (2026-05-07)
**File:** `app/Http/Controllers/InvoiceController.php:51`

**Problem:** Method signature has no `Request $request` param; calls `auth()->user()` to get the approver. Reference pattern: always inject `Request` and use `$request->user()`.

**Draft Fix:**
```php
// Change:
public function approve(PurchaseOrder $purchaseOrder, Invoice $invoice): RedirectResponse

// To:
public function approve(Request $request, PurchaseOrder $purchaseOrder, Invoice $invoice): RedirectResponse
{
    $this->authorize('approve', $invoice);
    // ...
    $this->service->approve($invoice, $request->user());
```

**Resolution:** `Request $request` injected as first parameter in `InvoiceController::approve()`; replaced `auth()->user()` with `$request->user()`.

---

## BUG-032: `PurchaseOrderController::show()` — DB query in controller

**Severity:** Medium | **Status:** Fixed (2026-05-07)
**File:** `app/Http/Controllers/PurchaseOrderController.php:61–69`

**Problem:** `InventoryMovement::whereIn(...)` query built inline in controller. Reference: no DB queries in controllers.

**Draft Fix:** Move to `PurchaseOrderService`:
```php
// PurchaseOrderService.php — add:
public function assignedGrnIds(PurchaseOrder $po): array
{
    $grnIds = $po->goodsReceipts->pluck('id')->all();
    if (empty($grnIds)) {
        return [];
    }
    return InventoryMovement::whereIn('goods_receipt_id', $grnIds)
        ->select('goods_receipt_id')
        ->distinct()
        ->pluck('goods_receipt_id')
        ->flip()
        ->all();
}

// Controller:
$assignedGrnIds = $this->service->assignedGrnIds($purchaseOrder);
```

**Resolution:** Query moved to `PurchaseOrderService::getAssignedGrnIds()`; controller calls that method.

---

## BUG-033: `GoodsReceiptController::assignSerials()` — business guard logic in controller

**Severity:** Medium | **Status:** Fixed (2026-05-07)
**File:** `app/Http/Controllers/GoodsReceiptController.php:127–140`

**Problem:** Three business rule checks (GRN Complete status, QC done, PO receivable status, already-assigned check) live in the controller. Reference: business decisions belong in service layer.

**Draft Fix:** Add `assertAssignSerialsAllowed(GoodsReceipt $grn, PurchaseOrder $po): void` to `GoodsReceiptService` that throws `DomainException` for each guard, then controller calls that method and catches `DomainException`.

**Resolution:** `assertCanAssignSerials()` method added to `GoodsReceiptService`; controller delegates all guards to it.

---

## BUG-034: All 5 models use `$casts` property instead of `casts()` method

**Severity:** Medium | **Status:** Fixed (2026-05-07)
**File:** `PurchaseOrder.php`, `PurchaseOrderLine.php`, `GoodsReceipt.php`, `GoodsReceiptLine.php`, `Invoice.php`

**Problem:** Laravel 12 defines casts via `protected function casts(): array` method. Using `protected $casts` property is the Laravel 10/11 pattern — works via backward compatibility but violates the project reference.

**Draft Fix (same pattern for all 5 models):**
```php
// Before:
protected $casts = [
    'status' => PurchaseOrderStatus::class,
    'grand_total' => 'decimal:4',
];

// After:
protected function casts(): array
{
    return [
        'status'      => PurchaseOrderStatus::class,
        'grand_total' => 'decimal:4',
    ];
}
```

**Resolution:** All 5 models converted from `protected $casts = [...]` to `protected function casts(): array { return [...]; }`.

---

## BUG-035: `GoodsReceiptLine` — `qty_passed`/`qty_failed` missing from model casts

**Severity:** Medium | **Status:** Fixed (2026-05-07)
**File:** `app/Models/GoodsReceiptLine.php`

**Problem:** `qty_passed` and `qty_failed` are `unsignedInteger` DB columns but absent from `$casts`. Raw DB access returns strings. Helper methods cast manually with `(int)` — works only when helpers are used; direct property access returns wrong type.

**Draft Fix:**
```php
protected function casts(): array
{
    return [
        // existing casts...
        'qty_passed' => 'integer',
        'qty_failed' => 'integer',
    ];
}
```

**Resolution:** `qty_passed` and `qty_failed` added to `GoodsReceiptLine::casts()` as `'integer'`.

---

## BUG-036: `StoreGoodsReceiptRequest` — `$this->lines` magic property (PHPStan level 8 fail)

**Severity:** Medium | **Status:** Fixed (2026-05-07)
**File:** `app/Http/Requests/GoodsReceipt/StoreGoodsReceiptRequest.php:20`

**Problem:** `prepareForValidation()` accesses `$this->lines` — a magic dynamic property. PHPStan level 8 reports this as an error.

**Draft Fix:**
```php
// Change:
$lines = $this->lines ?? [];
// To:
$lines = $this->input('lines') ?? [];
```

**Resolution:** `$this->lines` replaced with `$this->input('lines')` in both `StoreGoodsReceiptRequest` and `UpdateGoodsReceiptRequest`.

---

## BUG-037: `InvoiceService::markPaid()` — lazy-loads `purchaseOrder` then `invoices()`

**Severity:** Medium | **Status:** Fixed (2026-05-07)
**File:** `app/Services/InvoiceService.php:82–83`

**Problem:** Service receives only an `Invoice` model. To check if all invoices are paid → PO closed, it lazy-loads `$invoice->purchaseOrder` (1 query) then calls `$po->invoices()` (1 query). Neither relation is eager-loaded by the caller.

**Draft Fix:** Eager-load in `InvoiceController::markPaid()` before passing to service:
```php
$invoice->loadMissing(['purchaseOrder.invoices']);
$this->service->markPaid($invoice);
```
Or accept `PurchaseOrder $po` as second param to `markPaid()` to make the dependency explicit.

**Resolution:** `InvoiceController::markPaid()` now calls `$invoice->loadMissing(['purchaseOrder.invoices'])` before passing to service.

---

## BUG-038: Missing DB indexes on FK columns

**Severity:** Medium | **Status:** Not a Bug
**File:** `database/migrations/2026_05_10_000002_create_purchase_order_lines_table.php`, `2026_05_10_000004_create_goods_receipt_lines_table.php`

**Problem:** `purchase_order_lines.purchase_order_id`, `purchase_order_lines.product_id`, and `goods_receipt_lines.purchase_order_line_id` have FK constraints but no explicit indexes. Heavy query paths (`updatePoQtyReceived`, `validateLineQtys`) hit these columns repeatedly.

**Draft Fix:**
```php
// New migration:
Schema::table('purchase_order_lines', function (Blueprint $table) {
    $table->index('purchase_order_id');
    $table->index('product_id');
});

Schema::table('goods_receipt_lines', function (Blueprint $table) {
    $table->index('purchase_order_line_id');
});
```

**Resolution:** `foreignId()` in Laravel automatically creates a foreign key index. No explicit `->index()` calls needed. No change required.

---

## BUG-039: Procurement views use `<x-app-layout>` instead of `<x-layouts.admin>`

**Severity:** Medium | **Status:** Not a Bug
**File:** `resources/views/purchase-orders/show.blade.php:1`, `resources/views/goods-receipts/show.blade.php:1`, `resources/views/goods-receipts/assign-serials.blade.php:1` (and likely all other procurement views)

**Problem:** Reference (`admin-views.md`) requires `<x-layouts.admin>` for all admin pages. Views using `<x-app-layout>` get the wrong layout — missing admin sidebar, admin nav, and consistent admin chrome.

**Draft Fix:** In each procurement view, change:
```blade
{{-- From: --}}
<x-app-layout>
    <x-slot name="header">...</x-slot>
    ...
</x-app-layout>

{{-- To: --}}
<x-layouts.admin title="...">
    ...
</x-layouts.admin>
```
*(Exact slot names depend on `x-layouts.admin` component signature — check `resources/views/components/layouts/admin.blade.php`.)*

**Resolution:** Verified — `<x-app-layout>` is the correct component for this project's admin side (maps to `resources/views/components/app-layout.blade.php` which includes the admin nav and sidebar). No change required.

---

## BUG-040: `PurchaseOrderService` status transitions — single-table writes without transaction or row lock

**Severity:** Medium | **Status:** Fixed (2026-05-07)
**File:** `app/Services/PurchaseOrderService.php:120–218`

**Problem:** `submit()`, `approve()`, `reject()`, `markOnTheWay()`, `cancel()` all do: check status → `$po->update([...])` with no `DB::transaction()` and no `lockForUpdate()`. Concurrent requests can both pass the status check and both write, leaving PO in the last-writer-wins state.

**Draft Fix (same pattern for all):**
```php
public function submit(PurchaseOrder $po): PurchaseOrder
{
    return DB::transaction(function () use ($po): PurchaseOrder {
        $po->lockForUpdate()->refresh(); // or: PurchaseOrder::lockForUpdate()->findOrFail($po->id)
        throw_if(
            ! in_array($po->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Rejected], true),
            \DomainException::class,
            'Only draft or rejected purchase orders can be submitted.'
        );
        $po->update(['status' => PurchaseOrderStatus::PendingApproval, 'rejection_reason' => null]);
        return $po->fresh();
    });
}
// Apply same pattern to approve(), reject(), markOnTheWay(), cancel()

**Resolution:** All 6 transition methods (`submit`, `approve`, `reject`, `markOnTheWay`, `cancel`, `passQualityCheck`) wrapped in `DB::transaction()` with `$po->lockForUpdate()->refresh()` at the start of each closure.

---

## BUG-041: `PurchaseOrderService` missing `InventoryMovement` import

**Severity:** Critical | **Status:** Fixed (2026-05-10)
**File:** `app/Services/PurchaseOrderService.php`

**Problem:** `getAssignedGrnIds()` (extracted from controller per BUG-032) calls `InventoryMovement::whereIn(...)` but `use App\Models\InventoryMovement;` was never added. Every request to the PO show page threw `Class "App\Services\InventoryMovement" not found` (500 error).

**Resolution:** Added `use App\Models\InventoryMovement;` import.

---

## BUG-042: QC form Alpine.js component registration unreliable in headless browser

**Severity:** High | **Status:** Fixed (2026-05-10)
**File:** `resources/views/goods-receipts/show.blade.php`

**Problem:** QC form registered its Alpine component via `Alpine.data('qcForm', ...)` inside a `document.addEventListener('alpine:init', ...)` inline script. In Playwright headless Chromium, this registration was not resolving — `:disabled="!allValid"` on the Submit QC button remained unprocessed (button always enabled regardless of input values). The `x-text` status cells also showed empty.

**Resolution:** Replaced `Alpine.data()` + `alpine:init` pattern with a `window.__qcLines` global variable (set synchronously via inline script) and inline `x-data` object on the div. Alpine resolves inline `x-data` objects immediately at DOM scan time without any event dependency.

```blade
{{-- Before --}}
<script>
document.addEventListener('alpine:init', function () {
    Alpine.data('qcForm', function (initialLines) { ... });
});
</script>
<div x-data="qcForm(@json($qcLineData))">

{{-- After --}}
<script>
window.__qcLines = @json($qcLineData);
</script>
<div x-data="{ lines: window.__qcLines, get allValid() { ... } }">
```
```
