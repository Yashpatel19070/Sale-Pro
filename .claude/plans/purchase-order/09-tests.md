# Purchase Order Module — Tests

---

## Setup (all test files)

```php
beforeEach(function () {
    $this->seed(PurchaseOrderPermissionSeeder::class);
});
```

Use `RefreshDatabase` trait on all test classes.

### User Helpers

```php
function poAdminUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::PURCHASE_ORDERS_CREATE,
        Permission::PURCHASE_ORDERS_UPDATE,
        Permission::PURCHASE_ORDERS_DELETE,
        Permission::PURCHASE_ORDERS_RESTORE,
        Permission::PURCHASE_ORDERS_SUBMIT,
        Permission::PURCHASE_ORDERS_APPROVE,
        Permission::PURCHASE_ORDERS_REJECT,
        Permission::PURCHASE_ORDERS_CANCEL,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::GOODS_RECEIPTS_CREATE,
        Permission::GOODS_RECEIPTS_DELETE,
        Permission::INVOICES_VIEW_ANY,
        Permission::INVOICES_VIEW,
        Permission::INVOICES_CREATE,
        Permission::INVOICES_APPROVE,
        Permission::INVOICES_MARK_PAID,
        Permission::INVOICES_DELETE,
    ]);
    return $user;
}

function poManagerUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::PURCHASE_ORDERS_CREATE,
        Permission::PURCHASE_ORDERS_UPDATE,
        Permission::PURCHASE_ORDERS_SUBMIT,
        Permission::PURCHASE_ORDERS_APPROVE,
        Permission::PURCHASE_ORDERS_REJECT,
        Permission::PURCHASE_ORDERS_CANCEL,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::GOODS_RECEIPTS_CREATE,
        Permission::INVOICES_VIEW_ANY,
        Permission::INVOICES_VIEW,
        Permission::INVOICES_CREATE,
        Permission::INVOICES_APPROVE,
        Permission::INVOICES_MARK_PAID,
    ]);
    return $user;
}

function poSalesUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::INVOICES_VIEW_ANY,
        Permission::INVOICES_VIEW,
    ]);
    return $user;
}
```

---

## Feature: `PurchaseOrderControllerTest`

**File:** `tests/Feature/PurchaseOrderControllerTest.php`

### Auth / Access
- [ ] Guest redirected to login on all routes
- [ ] Sales user can GET index (200)
- [ ] Sales user cannot GET create (403)
- [ ] Sales user cannot POST store (403)

### Index
- [ ] Admin can list POs (200)
- [ ] Filter by status returns correct subset
- [ ] Filter by supplier returns correct subset
- [ ] Search by po_number returns correct result

### Create / Store
- [ ] Admin can GET create (200)
- [ ] Admin can POST store with valid data → PO created, redirected to show
- [ ] Store validation fails: missing supplier_id → errors
- [ ] Store validation fails: empty lines → errors
- [ ] Store validation fails: lines.0.qty_ordered = 0 → errors
- [ ] PO created with correct po_number format `PO-{YEAR}-XXXX`
- [ ] Totals calculated correctly on store

### Show
- [ ] Admin can view PO (200)
- [ ] PO loads supplier, lines, GRNs, invoices without N+1

### Edit / Update
- [ ] Admin can GET edit for draft PO (200)
- [ ] Admin redirected back if PO not draft/rejected
- [ ] Admin can PUT update draft PO
- [ ] Lines replaced on update

### Submit
- [ ] Admin can submit draft PO → status = pending_approval
- [ ] Cannot submit approved PO → error flash

### Approve / Reject
- [ ] Manager can approve pending_approval PO → status = approved, approved_by set
- [ ] Manager can reject pending_approval PO → status = rejected, rejection_reason set
- [ ] Cannot approve draft PO → error flash

### Cancel
- [ ] Admin can cancel approved PO → status = cancelled
- [ ] Cannot cancel closed PO → error flash

### Soft Delete / Restore
- [ ] Admin can delete PO
- [ ] Deleted PO appears on index (withTrashed)
- [ ] Admin can restore deleted PO
- [ ] Sales cannot delete (403)

### Print
- [ ] Admin can GET print view (200)
- [ ] Sales can GET print view (200) — view permission sufficient

---

## Feature: `GoodsReceiptControllerTest`

**File:** `tests/Feature/GoodsReceiptControllerTest.php`

- [ ] Guest redirected to login
- [ ] Sales cannot create GRN (403)
- [ ] Admin can GET create GRN form for approved PO (200)
- [ ] Admin can POST store GRN → status = draft, PO qty unchanged
- [ ] Cannot create GRN for draft PO → error flash
- [ ] Cannot receive more than remaining qty → validation error
- [ ] Admin can GET edit GRN (draft only, 200)
- [ ] Admin can PUT update draft GRN → lines replaced
- [ ] Cannot edit complete GRN → error flash
- [ ] Admin can POST complete GRN → status = complete, PO qty_received updated
- [ ] Complete with partial qty → PO status = partially_received
- [ ] Complete with full qty → PO status = received
- [ ] Admin can view GRN show (200)
- [ ] Admin can delete draft GRN
- [ ] Cannot delete complete GRN → error flash
- [ ] Sales cannot delete (403)

---

## Feature: `InvoiceControllerTest`

**File:** `tests/Feature/InvoiceControllerTest.php`

- [ ] Guest redirected to login
- [ ] Sales cannot create invoice (403)
- [ ] Admin can GET create invoice form for received PO (200)
- [ ] Admin can POST store invoice → status = pending
- [ ] Cannot create invoice for draft PO → error flash
- [ ] Admin can approve invoice → status = approved
- [ ] Admin can mark invoice as paid → status = paid, paid_at set
- [ ] Cannot mark pending invoice as paid → error flash
- [ ] Admin can delete pending invoice
- [ ] Cannot delete paid invoice → error flash
- [ ] Admin can view invoice show (200)

---

## Unit: `PurchaseOrderServiceTest`

**File:** `tests/Unit/PurchaseOrderServiceTest.php`

- [ ] `store()` creates PO with correct po_number
- [ ] `store()` creates correct number of lines
- [ ] `store()` calculates subtotal correctly
- [ ] `store()` calculates tax_total correctly
- [ ] `store()` calculates grand_total correctly
- [ ] `update()` replaces lines on update
- [ ] `update()` throws DomainException if status = approved
- [ ] `submit()` changes status to pending_approval
- [ ] `submit()` throws DomainException if status = approved
- [ ] `approve()` sets approved_by and approved_at
- [ ] `approve()` throws DomainException if status != pending_approval
- [ ] `reject()` sets rejection_reason
- [ ] `reject()` throws DomainException if status != pending_approval
- [ ] `cancel()` changes status to cancelled
- [ ] `cancel()` throws DomainException if status = closed
- [ ] `generatePoNumber()` returns correct format
- [ ] `generatePoNumber()` increments sequence correctly

---

## Unit: `GoodsReceiptServiceTest`

**File:** `tests/Unit/GoodsReceiptServiceTest.php`

- [ ] `store()` creates GRN with correct grn_number and status = draft
- [ ] `store()` creates GRN lines
- [ ] `store()` does NOT update qty_received on PO lines
- [ ] `store()` throws DomainException if PO status = draft
- [ ] `store()` throws DomainException if qty exceeds remaining
- [ ] `update()` replaces GRN lines
- [ ] `update()` throws DomainException if status = complete
- [ ] `complete()` updates status to complete
- [ ] `complete()` increments qty_received on PO lines
- [ ] `complete()` only counts completed GRN lines — draft GRN qty NOT included in sum (tests `updatePoQtyReceived` filtering)
- [ ] `complete()` sets PO status to partially_received for partial delivery
- [ ] `complete()` sets PO status to received for full delivery
- [ ] `delete()` soft deletes draft GRN
- [ ] `delete()` throws DomainException if status = complete

---

## Unit: `InvoiceServiceTest`

**File:** `tests/Unit/InvoiceServiceTest.php`

- [ ] `store()` creates invoice with status = pending
- [ ] `store()` transitions PO status to `invoiced` when PO is `received`
- [ ] `store()` does NOT change PO status when PO is `approved`, `on_the_way`, or `partially_received`
- [ ] `store()` throws DomainException if PO status = draft
- [ ] `approve()` sets approved_by and approved_at
- [ ] `approve()` throws DomainException if status != pending
- [ ] `markPaid()` sets paid_at
- [ ] `markPaid()` updates PO status to `closed` (not `invoiced`) when all invoices are paid
- [ ] `markPaid()` throws DomainException if status != approved
- [ ] `delete()` throws DomainException if status = paid
