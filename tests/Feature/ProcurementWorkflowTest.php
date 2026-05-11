<?php

declare(strict_types=1);

use App\Enums\GoodsReceiptStatus;
use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\InventoryMovementPermissionSeeder;
use Database\Seeders\InventorySerialPermissionSeeder;
use Database\Seeders\PurchaseOrderPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function workflowAdmin(): User
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
        Permission::GOODS_RECEIPTS_UPDATE,
        Permission::GOODS_RECEIPTS_DELETE,
        Permission::INVENTORY_MOVEMENTS_VIEW,
        Permission::INVENTORY_MOVEMENTS_BULK_RECEIVE,
        Permission::INVENTORY_SERIALS_VIEW_ANY,
        Permission::INVENTORY_SERIALS_VIEW,
    ]);

    return $user;
}

function workflowSalesUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::INVENTORY_MOVEMENTS_VIEW,
    ]);

    return $user;
}

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PurchaseOrderPermissionSeeder::class);
    $this->seed(InventoryMovementPermissionSeeder::class);
    $this->seed(InventorySerialPermissionSeeder::class);

    $this->supplier = Supplier::factory()->create(['name' => 'Acme Supplier']);
    $this->product = Product::factory()->create(['name' => 'Widget A', 'sku' => 'WGT-001']);
    $this->location = InventoryLocation::factory()->create(['code' => 'L1', 'name' => 'Shelf 1']);
});

// ── BUG-001: PurchaseOrderController::store() must be authorized ──────────────

it('sales user cannot POST to purchase-orders.store (403)', function () {
    $this->actingAs(workflowSalesUser())
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'description' => 'Test line',
                'qty_ordered' => 2,
                'unit_cost' => 50,
                'tax_rate' => 0,
            ]],
        ])
        ->assertForbidden();
});

// ── BUG-002: InventoryMovementController::storeBulkReceive() must be authorized ─

it('sales user cannot POST to inventory-movements.bulk-receive (403)', function () {
    $this->actingAs(workflowSalesUser())
        ->post(route('inventory-movements.bulk-receive'), [
            'product_id' => $this->product->id,
            'qty' => 3,
            'inventory_location_id' => $this->location->id,
            'purchase_price' => '100.00',
        ])
        ->assertForbidden();
});

// ── BUG-012: Rejection form gated by reject permission, not approve ───────────

it('user with only reject permission sees rejection form on PO show', function () {
    $rejecter = User::factory()->create();
    $rejecter->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::PURCHASE_ORDERS_REJECT,
        // deliberately NO Permission::PURCHASE_ORDERS_APPROVE
    ]);

    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::PendingApproval,
    ]);

    $this->actingAs($rejecter)
        ->get(route('purchase-orders.show', $po))
        ->assertOk()
        ->assertSee('purchase-orders/'.$po->id.'/reject', false);
});

it('user with only approve permission does NOT see rejection form on PO show', function () {
    $approver = User::factory()->create();
    $approver->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::PURCHASE_ORDERS_APPROVE,
        // deliberately NO Permission::PURCHASE_ORDERS_REJECT
    ]);

    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::PendingApproval,
    ]);

    $this->actingAs($approver)
        ->get(route('purchase-orders.show', $po))
        ->assertOk()
        ->assertDontSee('purchase-orders/'.$po->id.'/reject', false);
});

// ── Full procurement workflow: PO → GRN → QC → Serials → Traceability ────────

it('completes full procurement workflow and serials carry supplier and GRN traceability', function () {
    $admin = workflowAdmin();

    // Step 1: Create PO
    $response = $this->actingAs($admin)->post(route('purchase-orders.store'), [
        'supplier_id' => $this->supplier->id,
        'notes' => null,
        'lines' => [[
            'product_id' => $this->product->id,
            'description' => 'Widget A — batch 1',
            'qty_ordered' => 5,
            'unit_cost' => 100,
            'tax_rate' => 0,
        ]],
    ]);

    $po = PurchaseOrder::latest()->first();
    $response->assertRedirect(route('purchase-orders.show', $po));
    expect($po->status)->toBe(PurchaseOrderStatus::Draft);

    // Step 2: Submit for approval
    $this->actingAs($admin)
        ->post(route('purchase-orders.submit', $po))
        ->assertRedirect();

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PendingApproval);

    // Step 3: Approve
    $this->actingAs($admin)
        ->post(route('purchase-orders.approve', $po))
        ->assertRedirect();

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Approved);

    // Step 4: Create GRN — receive 5 units
    $poLine = $po->lines()->first();

    $this->actingAs($admin)->post(route('purchase-orders.goods-receipts.store', $po), [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [[
            'purchase_order_line_id' => $poLine->id,
            'qty_received' => 5,
            'notes' => null,
        ]],
    ])->assertRedirect();

    $grn = GoodsReceipt::latest()->first();
    expect($grn->status)->toBe(GoodsReceiptStatus::Draft);

    // Step 5: Complete GRN → PO moves to QualityCheck
    $this->actingAs($admin)
        ->post(route('purchase-orders.goods-receipts.complete', [$po, $grn]))
        ->assertRedirect();

    expect($grn->fresh()->status)->toBe(GoodsReceiptStatus::Complete);
    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::QualityCheck);

    // Step 6: Submit QC — 4 pass, 1 fail
    $grnLine = $grn->lines()->first();

    $this->actingAs($admin)->post(route('purchase-orders.goods-receipts.submitQc', [$po, $grn]), [
        'lines' => [[
            'goods_receipt_line_id' => $grnLine->id,
            'qty_passed' => 4,
            'qty_failed' => 1,
        ]],
    ])->assertRedirect(route('purchase-orders.goods-receipts.show', [$po, $grn]));

    // PO must transition to PartiallyReceived or Received — not stay at QualityCheck
    expect(in_array($po->fresh()->status, [
        PurchaseOrderStatus::PartiallyReceived,
        PurchaseOrderStatus::Received,
    ], true))->toBeTrue();

    // Step 7: Assign serials — only 4 (qty_passed), not 5 (qty_received)
    $this->actingAs($admin)->post(route('purchase-orders.goods-receipts.storeSerials', [$po, $grn]), [
        'lines' => [[
            'goods_receipt_line_id' => $grnLine->id,
            'inventory_location_id' => $this->location->id,
            'purchase_price' => '100.00',
        ]],
    ])->assertRedirect(route('inventory-movements.bulk-receive-print'));

    // Exactly 4 serials created — qty_passed not qty_received
    expect(InventorySerial::count())->toBe(4);

    // Step 8: Verify serial traceability — supplier name populated
    $serial = InventorySerial::first();
    expect($serial->supplier_name)->toBe('Acme Supplier');

    // Step 9: Movement has goods_receipt_id set
    $movement = InventoryMovement::where('inventory_serial_id', $serial->id)->first();
    expect($movement->goods_receipt_id)->toBe($grn->id);

    // Step 10: Serial show page renders GRN and PO source
    $this->actingAs($admin)
        ->get(route('inventory-serials.show', $serial))
        ->assertOk()
        ->assertSee($grn->grn_number)
        ->assertSee($po->po_number)
        ->assertSee('Acme Supplier');

    // Step 11: Double-assignment blocked — trying again redirects with error
    $this->actingAs($admin)
        ->get(route('purchase-orders.goods-receipts.assignSerials', [$po, $grn]))
        ->assertRedirect()
        ->assertSessionHasErrors('error');

    // Step 12: updatePoStatus does not downgrade PO now that serials are assigned
    // (PO should remain PartiallyReceived or Received, not revert to QualityCheck)
    $poStatusAfter = $po->fresh()->status;
    expect(in_array($poStatusAfter, [
        PurchaseOrderStatus::PartiallyReceived,
        PurchaseOrderStatus::Received,
    ], true))->toBeTrue();
});

// ── BUG-003: Assign Serial Numbers button requires correct policy gate ─────────

it('GRN show renders Assign Serial Numbers for user with bulkReceive permission', function () {
    $admin = workflowAdmin();

    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);

    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 3,
        'qty_received' => 3,
    ]);

    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn->id,
        'purchase_order_line_id' => $poLine->id,
        'qty_received' => 3,
        'qty_passed' => 3,
        'qty_failed' => 0,
        'qc_inspected_at' => now(),
        'qc_inspected_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('purchase-orders.goods-receipts.show', [$po, $grn]))
        ->assertOk()
        ->assertSee('Assign Serial Numbers');
});

it('GRN show hides Assign Serial Numbers for sales user without bulkReceive permission', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => PurchaseOrderStatus::Received]);
    $grn = GoodsReceipt::factory()->create(['purchase_order_id' => $po->id, 'status' => GoodsReceiptStatus::Complete]);

    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn->id,
        'purchase_order_line_id' => PurchaseOrderLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id])->id,
        'qty_received' => 2,
        'qty_passed' => 2,
        'qty_failed' => 0,
        'qc_inspected_at' => now(),
        'qc_inspected_by' => workflowSalesUser()->id,
    ]);

    $this->actingAs(workflowSalesUser())
        ->get(route('purchase-orders.goods-receipts.show', [$po, $grn]))
        ->assertOk()
        ->assertDontSee('Assign Serial Numbers');
});
