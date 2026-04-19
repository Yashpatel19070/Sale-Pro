# Purchase Order Module — Tests

## Feature Tests: `PurchaseOrderControllerTest`

File: `tests/Feature/PurchaseOrderControllerTest.php`

### Setup
```php
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([SupplierPermissionSeeder::class, PurchaseOrderPermissionSeeder::class]);

    $this->admin       = User::factory()->create()->assignRole('admin');
    $this->manager     = User::factory()->create()->assignRole('manager');
    $this->procurement = User::factory()->create()->assignRole('procurement');
    $this->warehouse   = User::factory()->create()->assignRole('warehouse');

    $this->supplier = Supplier::factory()->create();
    $this->product  = Product::factory()->create();
});
```

### Index (GET /admin/purchase-orders)
- `index returns 200 for admin`
- `index returns 200 for procurement`
- `index returns 403 for warehouse`
- `index filters by po_number search`
- `index filters by supplier name search`
- `index filters by status`

### Show (GET /admin/purchase-orders/{id})
- `show returns 200 for admin with lines loaded`
- `show returns 200 for procurement`
- `show returns 403 for warehouse`

### Create / Store (GET + POST)
- `create returns 200 for admin`
- `create returns 200 for procurement`
- `create returns 403 for warehouse`
- `store creates draft PO with lines` — po_number auto-generated, status=draft, lines created
- `store requires supplier_id`
- `store requires at least one line`
- `store validates line product exists`
- `store validates line qty_ordered >= 1`
- `store rejects line qty_ordered > 10000` — max:10000 validation
- `store validates line unit_price >= 0.01`
- `store returns 403 for warehouse`

### Edit / Update (GET + PATCH)
- `edit returns 200 for admin on draft PO`
- `edit returns 403 on non-draft PO` — policy blocks update on non-draft
- `update replaces lines on draft PO`
- `update rejects line qty_ordered > 10000` — max:10000 validation
- `update returns error when PO is not draft` — DomainException from service
- `update returns 403 for procurement`

### Confirm (POST /confirm)
- `confirm moves draft to open and sets confirmed_at`
- `confirm returns error when PO has no lines`
- `confirm returns error when PO is not draft`
- `confirm returns 403 for warehouse`

### Cancel (POST /cancel)
- `cancel moves draft to cancelled and stores cancel_notes`
- `cancel moves open PO with no received units to cancelled`
- `cancel stores cancel_notes on the PO record`
- `cancel rejects cancel_notes shorter than 10 characters` — min:10 validation
- `cancel rejects missing cancel_notes` — required validation
- `cancel returns error when PO has received units`
- `cancel returns error on partial or closed PO`
- `cancel returns 403 for procurement`

### Reopen (POST /reopen)
- `reopen moves closed PO to open and increments reopen_count`
- `reopen first time succeeds for manager`
- `reopen second time succeeds for manager`
- `reopen third time returns error for manager`
- `reopen third time succeeds for super-admin`
- `reopen returns error when unit is on shelf`
- `reopen returns error when PO is not closed`
- `reopen returns 403 for procurement`

---

## Unit Tests: `PurchaseOrderServiceTest`

File: `tests/Unit/Services/PurchaseOrderServiceTest.php`

### Setup
```php
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service   = app(PurchaseOrderService::class);
    $this->supplier  = Supplier::factory()->create();
    $this->product   = Product::factory()->create();
    $this->user      = User::factory()->create();
    $this->superAdmin = User::factory()->create()->assignRole('super-admin');
    $this->manager    = User::factory()->create()->assignRole('manager');
});
```

### list()
- `list() returns paginated results` — 30 POs → total=30, per_page=25
- `list() filters by po_number`
- `list() filters by status`
- `list() filters by supplier_id`
- `list() filters by type`
- `list() ignores empty string filters`

### create()
- `create() persists PO and lines in transaction`
- `create() generates PO number PO-YYYY-0001 for first PO`
- `create() generates sequential PO numbers within year`
- `create() sets status draft`
- `create() sets created_by_user_id`
- `create() stores skip_tech and skip_qa flags`

### update()
- `update() replaces all lines when lines key provided`
- `update() updates supplier and notes`
- `update() throws DomainException when PO is not draft`

### confirm()
- `confirm() sets status open and confirmed_at`
- `confirm() throws DomainException when not draft`
- `confirm() throws DomainException when no lines`

### cancel()
- `cancel() sets status cancelled and cancelled_at on draft`
- `cancel() sets status cancelled on open PO with no received units`
- `cancel() persists cancel_notes on the PO record`
- `cancel() throws DomainException when units received`
- `cancel() throws DomainException when status is partial or closed`

### reopen()
- `reopen() sets status open and increments reopen_count`
- `reopen() succeeds for manager on 1st reopen (count=0)`
- `reopen() succeeds for manager on 2nd reopen (count=1)`
- `reopen() throws DomainException for manager on 3rd reopen (count=2)`
- `reopen() succeeds for super-admin on 3rd reopen (count=2)`
- `reopen() throws DomainException when unit is on shelf` — asserts query uses PipelineStage::Shelf + UnitJobStatus::Passed, not hardcoded strings
- `reopen() does not block reopen when shelf job exists but status is not passed` — skipped/failed jobs should not block
- `reopen() throws DomainException when PO is not closed`

### incrementReceived()
- `incrementReceived() adds 1 to qty_received`
- `incrementReceived() sets PO status to partial when was open`
- `incrementReceived() throws DomainException when line already fulfilled`

### checkAndClose()
- `checkAndClose() closes PO when all lines fulfilled and all jobs terminal`
- `checkAndClose() does not close when lines not fulfilled`
- `checkAndClose() does not close when jobs not terminal`
- `checkAndClose() sets closed_at timestamp`

### generatePoNumber()
- `generatePoNumber() returns PO-YYYY-0001 when no POs exist`
- `generatePoNumber() increments within year`
- `generatePoNumber() resets at year boundary` — create PO in prev year, check count for new year
