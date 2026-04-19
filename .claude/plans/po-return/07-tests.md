# PO Return Module — Tests

## Feature Tests: `PoReturnControllerTest`

File: `tests/Feature/PoReturnControllerTest.php`

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
    $this->manager     = User::factory()->create()->assignRole('manager');
    $this->procurement = User::factory()->create()->assignRole('procurement');
    $this->warehouse   = User::factory()->create()->assignRole('warehouse');

    $this->supplier = Supplier::factory()->create();
    $this->originalPo = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id]);
    $this->product  = Product::factory()->create();
    $this->line     = PoLine::factory()->create([
        'purchase_order_id' => $this->originalPo->id,
        'product_id'        => $this->product->id,
        'unit_price'        => '250.00',
    ]);

    // A pre-existing return PO for controller tests
    $this->returnPo = PurchaseOrder::factory()->returnType()->create([
        'parent_po_id' => $this->originalPo->id,
        'supplier_id'  => $this->supplier->id,
        'status'       => 'open',
    ]);
});
```

### Index (GET /admin/po-returns)
- `index returns 200 for admin`
- `index returns 200 for procurement`
- `index returns 403 for warehouse`
- `index only shows type=return POs`
- `index filters by search (po_number)`
- `index filters by status`

### Show (GET /admin/po-returns/{id})
- `show returns 200 for admin`
- `show returns 200 for procurement`
- `show returns 403 for warehouse`
- `show returns 404 for a purchase-type PO` — type guard
- `show loads parent PO and supplier`

### Close (POST /admin/po-returns/{id}/close)
- `close sets status to closed and sets closed_at`
- `close redirects to show with success message`
- `close returns 404 for purchase-type PO`
- `close returns error when return PO is already closed`
- `close returns 403 for procurement`
- `close succeeds for manager`

---

## Unit Tests: `PoReturnServiceTest`

File: `tests/Unit/Services/PoReturnServiceTest.php`

### Setup
```php
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service    = app(PoReturnService::class);
    $this->user       = User::factory()->create();
    $this->supplier   = Supplier::factory()->create();
    $this->product    = Product::factory()->create();
    $this->po         = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id]);
    $this->line       = PoLine::factory()->create([
        'purchase_order_id' => $this->po->id,
        'product_id'        => $this->product->id,
        'unit_price'        => '350.00',
        'qty_ordered'       => 5,
    ]);
    $this->job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id'        => $this->line->id,
    ]);
});
```

### createForFailedUnit()
- `createForFailedUnit() creates a return PO with type=return`
- `createForFailedUnit() sets parent_po_id to original PO`
- `createForFailedUnit() copies supplier from original PO`
- `createForFailedUnit() sets status to open`
- `createForFailedUnit() sets confirmed_at`
- `createForFailedUnit() creates one PO line with qty_ordered=1`
- `createForFailedUnit() copies product_id from the failed job's line`
- `createForFailedUnit() copies unit_price from the failed job's line`
- `createForFailedUnit() sets notes with job id and stage`
- `createForFailedUnit() sets created_by_user_id to the user who triggered the failure`
- `createForFailedUnit() generates a PO number`

### list()
- `list() returns only type=return POs`
- `list() paginates results`
- `list() filters by search`
- `list() filters by status`

### close()
- `close() sets status to closed`
- `close() sets closed_at timestamp`
- `close() throws DomainException when PO is not type=return`
- `close() throws DomainException when return PO is already closed`

### generateReturnPoNumber()
- `generateReturnPoNumber() returns PO-YYYY-0001 when no POs exist`
- `generateReturnPoNumber() increments correctly when POs exist (including purchase type)`
