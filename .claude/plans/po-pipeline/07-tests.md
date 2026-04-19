# PO Pipeline Module — Tests

## Feature Tests: `PipelineControllerTest`

File: `tests/Feature/PipelineControllerTest.php`

### Setup
```php
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        SupplierPermissionSeeder::class,
        PurchaseOrderPermissionSeeder::class,
        PipelinePermissionSeeder::class,
    ]);

    $this->admin       = User::factory()->create()->assignRole('admin');
    $this->procurement = User::factory()->create()->assignRole('procurement');
    $this->warehouse   = User::factory()->create()->assignRole('warehouse');
    $this->tech        = User::factory()->create()->assignRole('tech');
    $this->qa          = User::factory()->create()->assignRole('qa');

    $this->supplier = Supplier::factory()->create();
    $this->product  = Product::factory()->create();
    $this->po       = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id]);
    $this->line     = PoLine::factory()->create(['purchase_order_id' => $this->po->id, 'product_id' => $this->product->id]);
});
```

### Queue (GET /admin/pipeline)
- `queue returns 200 for warehouse with visual/serial_assign/shelf jobs`
- `queue returns 200 for tech with tech-stage jobs`
- `queue returns 200 for qa with qa-stage jobs`
- `queue returns 200 for procurement (empty — no pending receive-stage jobs exist after createJob())`
- `queue returns 200 for admin showing all pending jobs across all stages`
- `queue only shows pending jobs (excludes in_progress and terminal)`
- `queue filters by purchase_order_id`

### Show (GET /admin/pipeline/jobs/{unitJob})
- `show returns 200 for admin`
- `show returns 200 for warehouse on visual-stage job`
- `show returns 200 for tech on visual-stage job` — tech has pipeline.viewAny, so view is allowed even for wrong stage
- `show returns 403 for sales on any pipeline job` — sales has no pipeline permissions at all
- `show loads event history`

### Start — Claim Job (POST /admin/pipeline/jobs/{unitJob}/start)
- `start sets job status to in_progress`
- `start assigns authenticated user to job`
- `start writes start event to po_unit_events`
- `start redirects to pipeline.show`
- `start returns error when job is already in_progress`
- `start returns error when job is terminal (passed/failed/skipped)`
- `start returns 403 for wrong-stage user` — qa trying to claim visual-stage job
- `start returns 403 for admin` — admin has viewAny but not stage permissions

### Store — Create Job (POST /admin/pipeline/jobs)
- `store creates unit job for open PO line` — procurement can create
- `store writes receive event`
- `store increments qty_received on PO line`
- `store advances job to visual stage`
- `store returns error when PO is not open`
- `store returns error when line is fulfilled`
- `store returns 403 for warehouse` — not procurement

### Pass (POST /admin/pipeline/jobs/{unitJob}/pass)
- `pass at visual stage advances to serial_assign`
- `pass at serial_assign requires serial_number`
- `pass at serial_assign rejects serial_number already in inventory_serials` — unique:inventory_serials,serial_number
- `pass at serial_assign rejects serial_number already in po_unit_jobs pending_serial_number` — unique:po_unit_jobs,pending_serial_number race guard
- `pass at serial_assign advances to tech`
- `pass at tech advances to qa`
- `pass at qa advances to shelf`
- `pass at shelf creates InventorySerial` — calls receive() on movement service
- `pass at shelf creates InventoryMovement of type receive`
- `pass at shelf marks job as passed`
- `pass at shelf calls checkAndClose on PO`
- `pass returns error on already-terminal job`
- `pass returns 403 for wrong-stage user` — qa trying to pass visual

### Pass with Skip Flags
- `pass skips tech stage when skip_tech=true on PO` — serial_assign → qa
- `pass skips qa stage when skip_qa=true on PO` — tech → shelf
- `pass skips both tech and qa when both flags set` — serial_assign → shelf
- `skip events written to po_unit_events with action=skip`

### Fail (POST /admin/pipeline/jobs/{unitJob}/fail)
- `fail marks job as failed`
- `fail writes fail event to po_unit_events`
- `fail auto-creates return PO`
- `fail requires notes`
- `fail returns error on already-terminal job`
- `fail returns 403 for wrong-stage user`

---

## Unit Tests: `PipelineServiceTest`

File: `tests/Unit/Services/PipelineServiceTest.php`

### Setup
```php
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service      = app(PipelineService::class);
    $this->user         = User::factory()->create();
    $this->supplier     = Supplier::factory()->create();
    $this->location     = InventoryLocation::factory()->create();
    $this->product      = Product::factory()->create();
    $this->po           = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id]);
    $this->line         = PoLine::factory()->create(['purchase_order_id' => $this->po->id, 'product_id' => $this->product->id, 'qty_ordered' => 5]);
});
```

### createJob()
- `createJob() creates PoUnitJob at receive stage`
- `createJob() writes receive pass event`
- `createJob() increments line qty_received`
- `createJob() advances job to visual stage`
- `createJob() sets PO status to partial`
- `createJob() throws DomainException when PO is not open`
- `createJob() throws DomainException when line is fully received`

### start()
- `start() sets status to in_progress`
- `start() assigns user to assigned_to_user_id`
- `start() writes start event`
- `start() throws DomainException when job is already in_progress`
- `start() throws DomainException when job is terminal`
- `start() is TOCTOU-safe — second concurrent claim fails inside transaction`

### pass() — stage progression
- `pass() at visual advances to serial_assign`
- `pass() at serial_assign stores serial in pending_serial_number`
- `pass() at serial_assign advances to tech`
- `pass() at tech advances to qa`
- `pass() at qa advances to shelf`
- `pass() writes pass event at each stage`
- `pass() throws DomainException when job is pending (not yet claimed)`
- `pass() throws DomainException when job is terminal`
- `pass() throws DomainException when called by a different user than assigned`

### pass() — skip flags
- `pass() skips tech and writes skip event when skip_tech=true`
- `pass() skips qa and writes skip event when skip_qa=true`
- `pass() skips both tech and qa directly to shelf when both flags set`

### pass() — shelf stage
- `pass() at shelf calls InventoryMovementService::receive()`
- `pass() at shelf creates InventorySerial`
- `pass() at shelf links serial to job via inventory_serial_id`
- `pass() at shelf marks job status as passed`
- `pass() at shelf calls PurchaseOrderService::checkAndClose()`

### fail()
- `fail() marks job status as failed`
- `fail() writes fail event`
- `fail() auto-creates return PO via PoReturnService`
- `fail() calls checkAndClose on PO`
- `fail() throws DomainException when job is pending (not yet claimed)`
- `fail() throws DomainException when job is already terminal`
- `fail() throws DomainException when called by a different user than assigned`

### queue()
- `queue() returns active jobs at specified stages`
- `queue() excludes terminal jobs`
- `queue() returns oldest first`
- `queue() paginates results`
- `queue() filters by purchase_order_id`
