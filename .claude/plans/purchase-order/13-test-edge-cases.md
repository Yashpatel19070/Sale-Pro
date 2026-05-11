# Purchase Order Module — Edge Case Tests

**File pattern:** Follow `09-tests.md` for setup helpers and `RefreshDatabase` usage.
**Scope:** Edge cases NOT covered by the happy-path tests in `09-tests.md`.
These tests directly guard against the bugs catalogued in `12-bugs.md`.

---

## Setup

Same `beforeEach` and user helpers as `09-tests.md` (poAdminUser, poManagerUser, poSalesUser).
Add one more helper:

```php
function inventoryManagerUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::INVENTORY_MOVEMENTS_VIEW,
        Permission::INVENTORY_MOVEMENTS_BULK_RECEIVE,
    ]);
    return $user;
}
```

---

## BUG-001 — `PurchaseOrderController::store()` missing authorization

**File:** `tests/Feature/PurchaseOrderControllerTest.php` (add to existing Auth section)

```php
it('returns 403 when sales user posts to store without create permission', function () {
    $supplier = Supplier::factory()->create();
    $product  = Product::factory()->create();

    actingAs(poSalesUser())
        ->post(route('purchase-orders.store'), [
            'supplier_id'       => $supplier->id,
            'expected_delivery' => now()->addMonth()->toDateString(),
            'lines'             => [
                ['product_id' => $product->id, 'qty_ordered' => 2, 'unit_cost' => 50, 'description' => 'Test'],
            ],
        ])
        ->assertForbidden();
});
```

---

## BUG-002 — `InventoryMovementController::storeBulkReceive()` missing authorization

**File:** `tests/Feature/InventoryMovementControllerTest.php`

```php
it('returns 403 when sales user posts to storeBulkReceive without bulk-receive permission', function () {
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    actingAs(poSalesUser())
        ->post(route('inventory-movements.bulk-receive'), [
            'product_id'           => $product->id,
            'qty'                  => 3,
            'inventory_location_id' => $location->id,
            'purchase_price'       => '100.00',
        ])
        ->assertForbidden();
});

it('allows inventory manager to post to storeBulkReceive', function () {
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    actingAs(inventoryManagerUser())
        ->post(route('inventory-movements.bulk-receive'), [
            'product_id'           => $product->id,
            'qty'                  => 2,
            'inventory_location_id' => $location->id,
            'purchase_price'       => '50.00',
        ])
        ->assertRedirect(route('inventory-movements.bulk-receive-print'));
});
```

---

## BUG-003 — Wrong `@can` gate hides "Assign Serial Numbers" button

**File:** `tests/Feature/GoodsReceiptControllerTest.php`

```php
it('renders Assign Serial Numbers button for user with bulkReceive permission', function () {
    [$po, $grn] = createCompletedGrnWithQcPassed(); // helper: creates PO→GRN→complete→QC

    actingAs(inventoryManagerUser())
        ->get(route('purchase-orders.goods-receipts.show', [$po, $grn]))
        ->assertOk()
        ->assertSee('Assign Serial Numbers');
});

it('does NOT render Assign Serial Numbers button for sales user', function () {
    [$po, $grn] = createCompletedGrnWithQcPassed();

    actingAs(poSalesUser())
        ->get(route('purchase-orders.goods-receipts.show', [$po, $grn]))
        ->assertOk()
        ->assertDontSee('Assign Serial Numbers');
});

it('does NOT render Assign Serial Numbers button when serials already assigned', function () {
    [$po, $grn, $serials] = createGrnWithSerialsAssigned(); // helper: full flow through storeSerials

    actingAs(inventoryManagerUser())
        ->get(route('purchase-orders.goods-receipts.show', [$po, $grn]))
        ->assertOk()
        ->assertDontSee('Assign Serial Numbers')
        ->assertSee('Serials Assigned');
});
```

---

## BUG-007 — `GoodsReceiptService::complete()` allows completing for cancelled PO

**File:** `tests/Unit/GoodsReceiptServiceTest.php`

```php
it('throws DomainException when completing GRN for a cancelled purchase order', function () {
    $po  = PurchaseOrder::factory()->cancelled()->create();
    $grn = GoodsReceipt::factory()->for($po)->draft()->create();

    expect(fn () => app(GoodsReceiptService::class)->complete($grn, $po))
        ->toThrow(\DomainException::class, 'cancelled');
});

it('throws DomainException when completing GRN for a rejected purchase order', function () {
    $po  = PurchaseOrder::factory()->rejected()->create();
    $grn = GoodsReceipt::factory()->for($po)->draft()->create();

    expect(fn () => app(GoodsReceiptService::class)->complete($grn, $po))
        ->toThrow(\DomainException::class, 'rejected');
});
```

---

## BUG-008 — `GoodsReceiptService::update()` wipes QC data after QC submitted

**File:** `tests/Unit/GoodsReceiptServiceTest.php`

```php
it('throws DomainException on update when QC already submitted for any line', function () {
    $grn = GoodsReceipt::factory()->complete()->has(
        GoodsReceiptLine::factory()->qcSubmitted()->count(1), 'lines'
    )->create();

    expect(fn () => app(GoodsReceiptService::class)->update($grn, [
        'received_date' => now()->toDateString(),
        'notes'         => null,
        'lines'         => [['purchase_order_line_id' => $grn->lines->first()->purchase_order_line_id, 'qty_received' => 1]],
    ]))->toThrow(\DomainException::class, 'QC has already been submitted');
});

it('allows updating GRN before QC is submitted', function () {
    $grn = GoodsReceipt::factory()->draft()->has(
        GoodsReceiptLine::factory()->count(1), 'lines'
    )->create();

    $result = app(GoodsReceiptService::class)->update($grn, [
        'received_date' => now()->toDateString(),
        'notes'         => 'updated',
        'lines'         => [['purchase_order_line_id' => $grn->lines->first()->purchase_order_line_id, 'qty_received' => 1]],
    ]);

    expect($result->notes)->toBe('updated');
});
```

---

## BUG-009 — `validateLineQtys()` silently skips unknown purchase order line IDs

**File:** `tests/Unit/GoodsReceiptServiceTest.php`

```php
it('throws DomainException when a line references a purchase_order_line_id not on the PO', function () {
    $po           = PurchaseOrder::factory()->approved()->create();
    $foreignLineId = 999999; // does not belong to $po

    expect(fn () => app(GoodsReceiptService::class)->store($po, [
        'received_date' => now()->toDateString(),
        'lines'         => [['purchase_order_line_id' => $foreignLineId, 'qty_received' => 1]],
    ], User::factory()->create()))
        ->toThrow(\DomainException::class);
});
```

---

## BUG-011 — `submitQc()` double-submit race condition

**File:** `tests/Unit/GoodsReceiptServiceQcTest.php`

```php
it('throws DomainException on second concurrent QC submission (double-submit guard)', function () {
    [$grn, $inspector] = createQcReadyGrn(); // helper: creates GRN in complete status, PO in quality_check

    $data = [
        'lines' => $grn->lines->map(fn ($l) => [
            'goods_receipt_line_id' => $l->id,
            'qty_passed'            => $l->qty_received,
            'qty_failed'            => 0,
        ])->all(),
    ];

    // First submission succeeds
    app(GoodsReceiptService::class)->submitQc($grn, $data, $inspector);

    // Second submission rejected — guard fires
    expect(fn () => app(GoodsReceiptService::class)->submitQc($grn, $data, $inspector))
        ->toThrow(\DomainException::class, 'QC has already been submitted');
});
```

---

## BUG-012 — Rejection form visible to approve-only users, hidden from reject-only users

**File:** `tests/Feature/PurchaseOrderControllerTest.php`

```php
it('shows rejection form to user with reject permission', function () {
    $po = PurchaseOrder::factory()->pendingApproval()->create();

    $rejecter = User::factory()->create();
    $rejecter->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::PURCHASE_ORDERS_REJECT,
    ]);

    actingAs($rejecter)
        ->get(route('purchase-orders.show', $po))
        ->assertOk()
        ->assertSee('rejection_reason')
        ->assertSee(route('purchase-orders.reject', $po));
});

it('hides rejection form from user with only approve permission (not reject)', function () {
    $po = PurchaseOrder::factory()->pendingApproval()->create();

    $approver = User::factory()->create();
    $approver->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::PURCHASE_ORDERS_APPROVE,
        // NO Permission::PURCHASE_ORDERS_REJECT
    ]);

    actingAs($approver)
        ->get(route('purchase-orders.show', $po))
        ->assertOk()
        ->assertDontSee(route('purchase-orders.reject', $po));
});
```

---

## BUG-013 — `updatePoStatus()` overwrites terminal PO status after completing second GRN

**File:** `tests/Unit/GoodsReceiptServiceTest.php`

```php
it('does not overwrite PO status to quality_check when PO is already received', function () {
    $po  = PurchaseOrder::factory()->received()->has(PurchaseOrderLine::factory()->count(1))->create();
    $grn = GoodsReceipt::factory()->for($po)->draft()->create();

    app(GoodsReceiptService::class)->updatePoStatus($po);

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Received);
});

it('does not overwrite PO status to quality_check when PO is cancelled', function () {
    $po = PurchaseOrder::factory()->cancelled()->has(PurchaseOrderLine::factory()->count(1))->create();

    app(GoodsReceiptService::class)->updatePoStatus($po);

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Cancelled);
});

it('does not overwrite PO status to quality_check when PO is partially_received', function () {
    $po = PurchaseOrder::factory()->partiallyReceived()->has(PurchaseOrderLine::factory()->count(1))->create();

    // Simulate a second GRN completed — updatePoStatus should NOT downgrade status
    $po->lines->each(fn ($l) => $l->update(['qty_received' => 1]));
    app(GoodsReceiptService::class)->updatePoStatus($po);

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived);
});
```

---

## GRN Edit — Missing supplier on edit form

**File:** `tests/Feature/GoodsReceiptControllerTest.php`

```php
it('loads supplier on purchaseOrder when rendering edit form', function () {
    $supplier = Supplier::factory()->create(['name' => 'Edit Supplier']);
    $po       = PurchaseOrder::factory()->for($supplier)->approved()->create();
    $grn      = GoodsReceipt::factory()->for($po)->draft()->create();

    actingAs(poAdminUser())
        ->get(route('purchase-orders.goods-receipts.edit', [$po, $grn]))
        ->assertOk()
        ->assertSee('Edit Supplier'); // supplier.name visible in breadcrumb / form header
});
```

---

## Assign Serials — Missing supplier/receivedBy on assign-serials form

**File:** `tests/Feature/GoodsReceiptControllerTest.php`

```php
it('loads supplier and receivedBy when rendering assign-serials form', function () {
    $supplier  = Supplier::factory()->create(['name' => 'Assign Supplier']);
    $receiver  = User::factory()->create(['name' => 'John Receiver']);
    [$po, $grn] = createCompletedGrnWithQcPassed(supplier: $supplier, receiver: $receiver);

    actingAs(inventoryManagerUser())
        ->get(route('purchase-orders.goods-receipts.assignSerials', [$po, $grn]))
        ->assertOk()
        ->assertSee('Assign Supplier')
        ->assertSee('John Receiver');
});
```

---

## Serial Show — Supplier and GRN source populated from GRN flow

**File:** `tests/Feature/InventorySerialControllerTest.php`

```php
it('populates supplier_name on serial created via GRN flow', function () {
    $supplier = Supplier::factory()->create(['name' => 'Acme Corp']);
    [$po, $grn, $serials] = createGrnWithSerialsAssigned(supplier: $supplier);

    $serial = $serials->first()->fresh();

    expect($serial->supplier_name)->toBe('Acme Corp');
});

it('shows GRN number and PO number in serial movement history', function () {
    $supplier = Supplier::factory()->create(['name' => 'Acme Corp']);
    [$po, $grn, $serials] = createGrnWithSerialsAssigned(supplier: $supplier);

    actingAs(poAdminUser())
        ->get(route('inventory-serials.show', $serials->first()))
        ->assertOk()
        ->assertSee($grn->grn_number)
        ->assertSee($po->po_number)
        ->assertSee('Acme Corp');
});

it('shows dash in source column for serials received via standalone flow (not GRN)', function () {
    $serial = InventorySerial::factory()->create(['supplier_name' => null]);
    InventoryMovement::factory()->for($serial, 'serial')->receive()->create(['goods_receipt_id' => null]);

    actingAs(poAdminUser())
        ->get(route('inventory-serials.show', $serial))
        ->assertOk()
        ->assertSee('—'); // dash shown in Source column
});
```

---

## Helpers (add to `tests/helpers.php` or `tests/Pest.php`)

```php
function createCompletedGrnWithQcPassed(
    ?Supplier $supplier = null,
    ?User $receiver = null,
): array {
    $supplier ??= Supplier::factory()->create();
    $receiver ??= User::factory()->create();
    $product  = Product::factory()->create();
    $po = PurchaseOrder::factory()
        ->for($supplier)
        ->qualityCheck()
        ->has(PurchaseOrderLine::factory()->for($product)->state(['qty_ordered' => 5, 'qty_received' => 5]))
        ->create();
    $grn = GoodsReceipt::factory()
        ->for($po)
        ->complete()
        ->has(
            GoodsReceiptLine::factory()
                ->for($po->lines->first(), 'purchaseOrderLine')
                ->qcSubmitted(['qty_received' => 5, 'qty_passed' => 5, 'qty_failed' => 0])
        )
        ->create(['received_by' => $receiver->id]);

    return [$po, $grn];
}

function createGrnWithSerialsAssigned(?Supplier $supplier = null): array
{
    $user = User::factory()->create()->tap(fn ($u) => $u->givePermissionTo([
        Permission::INVENTORY_MOVEMENTS_BULK_RECEIVE,
    ]));
    $location = InventoryLocation::factory()->create();
    [$po, $grn] = createCompletedGrnWithQcPassed(supplier: $supplier);

    $serials = app(\App\Services\InventoryMovementService::class)->bulkReceiveFromGrn($grn, [
        'lines' => [
            [
                'goods_receipt_line_id'  => $grn->lines->first()->id,
                'inventory_location_id'  => $location->id,
                'purchase_price'         => '100.00',
            ],
        ],
    ], $user);

    return [$po, $grn, $serials];
}
```
