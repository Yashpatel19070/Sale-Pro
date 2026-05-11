<?php

declare(strict_types=1);

use App\Enums\GoodsReceiptStatus;
use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\InventoryMovementPermissionSeeder;
use Database\Seeders\PurchaseOrderPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PurchaseOrderPermissionSeeder::class);
    $this->seed(InventoryMovementPermissionSeeder::class);

    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();

    $this->po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $this->poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $this->po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);
});

// ── Guest redirects ───────────────────────────────────────────────────────────

it('guest is redirected to login on GRN create', function () {
    $this->get(route('purchase-orders.goods-receipts.create', $this->po))
        ->assertRedirect(route('login'));
});

// ── Sales user (read-only) ────────────────────────────────────────────────────

it('sales user cannot create a GRN', function () {
    $salesUser = User::factory()->create();
    $salesUser->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
    ]);

    $this->actingAs($salesUser)
        ->get(route('purchase-orders.goods-receipts.create', $this->po))
        ->assertForbidden();
});

it('sales user cannot delete a GRN', function () {
    $salesUser = User::factory()->create();
    $salesUser->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
    ]);

    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    $this->actingAs($salesUser)
        ->delete(route('purchase-orders.goods-receipts.destroy', [$this->po, $grn]))
        ->assertForbidden();
});

// ── Admin create ──────────────────────────────────────────────────────────────

it('admin can view the create GRN form for an approved PO', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::GOODS_RECEIPTS_CREATE,
    ]);

    $this->actingAs($admin)
        ->get(route('purchase-orders.goods-receipts.create', $this->po))
        ->assertOk();
});

// ── Admin store ───────────────────────────────────────────────────────────────

it('admin can store a GRN with draft status and PO qty_received unchanged', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::GOODS_RECEIPTS_CREATE,
    ]);

    $data = [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [[
            'purchase_order_line_id' => $this->poLine->id,
            'qty_received' => $this->poLine->qty_ordered,
            'notes' => null,
        ]],
    ];

    $this->actingAs($admin)
        ->post(route('purchase-orders.goods-receipts.store', $this->po), $data)
        ->assertRedirect();

    $this->assertDatabaseHas('goods_receipts', [
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft->value,
    ]);

    // PO qty_received is NOT updated until GRN is completed
    $this->assertDatabaseHas('purchase_order_lines', [
        'id' => $this->poLine->id,
        'qty_received' => 0,
    ]);
});

it('store redirects to GRN show page', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::GOODS_RECEIPTS_CREATE,
    ]);

    $data = [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [[
            'purchase_order_line_id' => $this->poLine->id,
            'qty_received' => 5,
            'notes' => null,
        ]],
    ];

    $response = $this->actingAs($admin)
        ->post(route('purchase-orders.goods-receipts.store', $this->po), $data);

    $grn = GoodsReceipt::latest()->first();

    $response->assertRedirect(route('purchase-orders.goods-receipts.show', [$this->po, $grn]));
});

it('cannot create GRN for a draft PO', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::GOODS_RECEIPTS_CREATE,
    ]);

    $draftPo = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $draftPo->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
    ]);

    $data = [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [[
            'purchase_order_line_id' => $poLine->id,
            'qty_received' => 5,
            'notes' => null,
        ]],
    ];

    $this->actingAs($admin)
        ->post(route('purchase-orders.goods-receipts.store', $draftPo), $data)
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('cannot receive more than the remaining qty', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::GOODS_RECEIPTS_CREATE,
    ]);

    $data = [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [[
            'purchase_order_line_id' => $this->poLine->id,
            'qty_received' => 999,
            'notes' => null,
        ]],
    ];

    $this->actingAs($admin)
        ->post(route('purchase-orders.goods-receipts.store', $this->po), $data)
        ->assertRedirect()
        ->assertSessionHasErrors();
});

// ── Admin edit ────────────────────────────────────────────────────────────────

it('admin can view the edit form for a draft GRN', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::GOODS_RECEIPTS_UPDATE,
    ]);

    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    $this->actingAs($admin)
        ->get(route('purchase-orders.goods-receipts.edit', [$this->po, $grn]))
        ->assertOk();
});

it('cannot edit a complete GRN', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::GOODS_RECEIPTS_UPDATE,
    ]);

    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    $this->actingAs($admin)
        ->get(route('purchase-orders.goods-receipts.edit', [$this->po, $grn]))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

// ── Admin update ──────────────────────────────────────────────────────────────

it('admin can update a draft GRN', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::GOODS_RECEIPTS_UPDATE,
    ]);

    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    $data = [
        'received_date' => now()->toDateString(),
        'notes' => 'Updated notes',
        'lines' => [[
            'purchase_order_line_id' => $this->poLine->id,
            'qty_received' => 3,
            'notes' => null,
        ]],
    ];

    $this->actingAs($admin)
        ->put(route('purchase-orders.goods-receipts.update', [$this->po, $grn]), $data)
        ->assertRedirect();

    $this->assertDatabaseHas('goods_receipts', [
        'id' => $grn->id,
        'notes' => 'Updated notes',
    ]);
});

// ── Admin complete ────────────────────────────────────────────────────────────

it('admin can complete a draft GRN', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::GOODS_RECEIPTS_UPDATE,
    ]);

    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    $this->actingAs($admin)
        ->post(route('purchase-orders.goods-receipts.complete', [$this->po, $grn]))
        ->assertRedirect();

    $this->assertDatabaseHas('goods_receipts', [
        'id' => $grn->id,
        'status' => GoodsReceiptStatus::Complete->value,
    ]);
});

it('completing a full receipt sets PO status to received', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::GOODS_RECEIPTS_UPDATE,
    ]);

    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn->id,
        'purchase_order_line_id' => $this->poLine->id,
        'qty_received' => $this->poLine->qty_ordered,
    ]);

    $this->actingAs($admin)
        ->post(route('purchase-orders.goods-receipts.complete', [$this->po, $grn]));

    // Completing a GRN moves PO to quality_check (not received).
    // received status only happens after QC is submitted via submitQc().
    $this->assertDatabaseHas('purchase_orders', [
        'id' => $this->po->id,
        'status' => PurchaseOrderStatus::QualityCheck->value,
    ]);
});

// ── Admin show ────────────────────────────────────────────────────────────────

it('admin can view the GRN show page', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
    ]);

    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
    ]);

    $this->actingAs($admin)
        ->get(route('purchase-orders.goods-receipts.show', [$this->po, $grn]))
        ->assertOk();
});

// ── Admin destroy ─────────────────────────────────────────────────────────────

it('admin can soft delete a draft GRN', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::GOODS_RECEIPTS_DELETE,
    ]);

    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    $this->actingAs($admin)
        ->delete(route('purchase-orders.goods-receipts.destroy', [$this->po, $grn]));

    $this->assertSoftDeleted('goods_receipts', ['id' => $grn->id]);
});

it('cannot delete a complete GRN', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::GOODS_RECEIPTS_DELETE,
    ]);

    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    $this->actingAs($admin)
        ->delete(route('purchase-orders.goods-receipts.destroy', [$this->po, $grn]))
        ->assertRedirect()
        ->assertSessionHasErrors();

    $this->assertDatabaseHas('goods_receipts', ['id' => $grn->id]);
});

// ── BUG-004: Edit form must load supplier ──────────────────────────────────────

it('edit form loads supplier when rendering draft GRN', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::GOODS_RECEIPTS_UPDATE,
    ]);

    $supplier = Supplier::factory()->create(['name' => 'Test Supplier Inc']);
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('purchase-orders.goods-receipts.edit', [$po, $grn]));

    $response->assertOk();
    expect($response['purchaseOrder']->supplier)->not->toBeNull();
    expect($response['purchaseOrder']->supplier->name)->toBe('Test Supplier Inc');
});

// ── BUG-006: Assign serials form must load supplier and receivedBy ────────────

it('assign-serials form loads supplier and receivedBy when rendering', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::INVENTORY_MOVEMENTS_BULK_RECEIVE,
    ]);

    $supplier = Supplier::factory()->create(['name' => 'Acme Corp']);
    $receiver = User::factory()->create(['name' => 'John Receiver']);

    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);

    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'qty_received' => 5,
    ]);

    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Complete,
        'received_by' => $receiver->id,
    ]);

    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn->id,
        'purchase_order_line_id' => $poLine->id,
        'qty_received' => 5,
        'qty_passed' => 5,
        'qty_failed' => 0,
        'qc_inspected_at' => now(),
        'qc_inspected_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('purchase-orders.goods-receipts.assignSerials', [$po, $grn]));

    $response->assertOk();
    expect($response['purchaseOrder']->supplier)->not->toBeNull();
    expect($response['purchaseOrder']->supplier->name)->toBe('Acme Corp');
    expect($response['goodsReceipt']->receivedBy)->not->toBeNull();
    expect($response['goodsReceipt']->receivedBy->name)->toBe('John Receiver');
});
