# Purchase Order Module — HTTP E2E Tests

```
Framework:   PHPUnit / Pest  (no browser, no JavaScript)
Test file:   tests/E2E/PurchaseOrderE2ETest.php
Run command: XDEBUG_MODE=off vendor/bin/pest tests/E2E/PurchaseOrderE2ETest.php --no-coverage
```

## Seed / beforeEach

```php
$this->seed(RoleSeeder::class);
$this->seed(PurchaseOrderPermissionSeeder::class);

$this->admin   = User::factory()->create()->assignRole('admin');
$this->manager = User::factory()->create()->assignRole('manager');
$this->sales   = User::factory()->create()->assignRole('sales');

$this->supplier  = Supplier::factory()->create();
$this->product   = Product::factory()->create();
```

---

## MODULE PO — Purchase Orders

### Auth & Access

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| PO-01 | Guest | GET `/admin/purchase-orders` | Redirect to login |
| PO-02 | Guest | GET `/admin/purchase-orders/create` | Redirect to login |
| PO-03 | `sales` | GET `/admin/purchase-orders` | 200 OK — view only |
| PO-04 | `sales` | GET `/admin/purchase-orders/create` | 403 Forbidden |
| PO-05 | `sales` | POST `/admin/purchase-orders` | 403 Forbidden |

### Create / Store

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| PO-06 | `admin` | GET `/admin/purchase-orders/create` | 200 OK; suppliers and products in view |
| PO-07 | `admin` | POST valid PO payload | Redirect to show; PO in DB with status=draft, po_number set |
| PO-08 | `admin` | POST with empty lines | Validation error |
| PO-09 | `admin` | POST with invalid supplier_id | Validation error |

### Edit / Update

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| PO-10 | `admin` | GET edit on draft PO | 200 OK |
| PO-11 | `admin` | GET edit on submitted PO | Redirect to show with error |
| PO-12 | `admin` | PUT valid update on draft PO | Redirect to show; totals recalculated |
| PO-13 | `admin` | PUT on non-draft PO | DomainException → redirect with error |

### Status Transitions

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| PO-14 | `admin` | Draft PO | POST submit | Status → pending_approval |
| PO-15 | `admin` | Rejected PO | POST submit | Status → pending_approval (resubmit) |
| PO-16 | `admin` | Draft PO | POST submit again | Error — not pending |
| PO-17 | `admin` | Pending PO | POST approve | Status → approved; approved_by, approved_at set |
| PO-18 | `admin` | Pending PO | POST reject with reason | Status → rejected; rejection_reason set |
| PO-19 | `admin` | Draft PO | POST approve | Error — wrong status |
| PO-20 | `admin` | Approved PO | POST on-the-way | Status → on_the_way |
| PO-21 | `admin` | Approved PO | POST cancel | Status → cancelled |
| PO-22 | `admin` | Cancelled PO | POST cancel again | Error — already cancelled |
| PO-23 | `manager` | Draft PO | POST submit | 200-flow — manager has submit permission |

### Delete / Restore

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| PO-24 | `admin` | Draft PO | DELETE | Soft-deleted; not in default listing |
| PO-25 | `admin` | Deleted PO | POST restore | Restored; visible in listing |
| PO-26 | `sales` | Any PO | DELETE | 403 Forbidden |

### Show / Print

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| PO-27 | `admin` | GET show | 200 OK; loads GRNs, invoices, supplier |
| PO-28 | `admin` | GET print | 200 OK |
| PO-29 | `sales` | GET show | 200 OK (view permission) |

---

## MODULE GRN — Goods Receipts

### Auth & Access

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| GRN-01 | Guest | Approved PO | GET create | Redirect to login |
| GRN-02 | `sales` | Approved PO | GET create | 403 Forbidden |
| GRN-03 | `sales` | Any GRN | GET show | 200 OK (view permission) |
| GRN-04 | `sales` | Any GRN | PUT update | 403 Forbidden |

### Create / Store

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| GRN-05 | `admin` | Approved PO | GET create | 200 OK; PO lines loaded |
| GRN-06 | `admin` | Approved PO | POST valid GRN | Redirect to GRN show; GRN status=draft; PO qty_received unchanged |
| GRN-07 | `admin` | OnTheWay PO | POST valid GRN | Success — on_the_way is allowed status |
| GRN-08 | `admin` | Draft PO | POST GRN | Error — draft PO not allowed |
| GRN-09 | `admin` | Approved PO | POST with qty > qty_ordered | Error — exceeds remaining |
| GRN-10 | `admin` | Approved PO | POST no lines | Validation error |

### Edit / Update

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| GRN-11 | `admin` | Draft GRN | GET edit | 200 OK |
| GRN-12 | `admin` | Complete GRN | GET edit | Redirect with error — cannot edit complete |
| GRN-13 | `admin` | Draft GRN | PUT valid update | Notes updated; lines replaced |
| GRN-14 | `admin` | Draft GRN | PUT qty > remaining | Error — over-receive guard |
| GRN-15 | `sales` | Draft GRN | PUT | 403 Forbidden (missing UPDATE permission) |

### Complete

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| GRN-16 | `admin` | Draft GRN with full qty | POST complete | GRN status=complete; PO status=received; PO line qty_received updated |
| GRN-17 | `admin` | Draft GRN with partial qty | POST complete | GRN status=complete; PO status=partially_received |
| GRN-18 | `admin` | Already complete GRN | POST complete | Error — already complete |

### Delete

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| GRN-19 | `admin` | Draft GRN | DELETE | Soft-deleted |
| GRN-20 | `admin` | Complete GRN | DELETE | Error — cannot delete complete GRN |
| GRN-21 | `sales` | Draft GRN | DELETE | 403 Forbidden |

---

## MODULE INV — Invoices

### Auth & Access

| # | Actor | Action | Expected |
|---|-------|--------|----------|
| INV-01 | Guest | GET invoice create | Redirect to login |
| INV-02 | `sales` | GET invoice create | 403 Forbidden |
| INV-03 | `sales` | GET invoice show | 200 OK |

### Create / Store

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| INV-04 | `admin` | Received PO | POST valid invoice | Invoice status=pending; PO status=invoiced |
| INV-05 | `admin` | Approved PO | POST valid invoice | Invoice created (approved PO is allowed) |
| INV-06 | `admin` | Draft PO | POST invoice | Error — draft PO not allowed |
| INV-07 | `admin` | Received PO | POST duplicate invoice_number | Validation error — unique |

### Approve

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| INV-08 | `admin` | Pending invoice | POST approve | Status=approved; approved_by, approved_at set |
| INV-09 | `admin` | Paid invoice | POST approve | Error — cannot approve paid |
| INV-10 | `sales` | Pending invoice | POST approve | 403 Forbidden |

### Mark Paid

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| INV-11 | `admin` | Approved invoice | POST mark-paid | Status=paid; paid_at set |
| INV-12 | `admin` | Pending invoice | POST mark-paid | Error — must be approved first |
| INV-13 | `admin` | Last unpaid → paid | POST mark-paid | PO status=closed |
| INV-14 | `admin` | One of two paid | POST mark-paid | PO NOT closed (sibling still pending) |

### Delete

| # | Actor | Setup | Action | Expected |
|---|-------|-------|--------|----------|
| INV-15 | `admin` | Pending invoice | DELETE | Soft-deleted |
| INV-16 | `admin` | Paid invoice | DELETE | Error — cannot delete paid |
| INV-17 | `sales` | Pending invoice | DELETE | 403 Forbidden |

---

## MODULE J — Cross-Module Journeys

| # | Description | Steps |
|---|-------------|-------|
| J-01 | Full PO state machine | Draft → submit → approve → on-the-way → (receive via GRN complete) → invoiced (via invoice) → closed (via mark-paid) |
| J-02 | Partial then full receive | Create PO → approve → GRN partial → PO partially_received → GRN full → PO received |
| J-03 | Reject and resubmit | Submit → reject → edit → resubmit → approve |
| J-04 | Multiple invoices | Create 2 invoices → pay one → PO not closed → pay second → PO closed |
| J-05 | Sales read-only | Sales can GET index/show for PO, GRN, invoice; cannot POST/PUT/DELETE any |
