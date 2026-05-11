<?php

declare(strict_types=1);

use App\Enums\GoodsReceiptStatus;
use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PurchaseOrderPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function qcAdminUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::GOODS_RECEIPTS_UPDATE,
        Permission::INVENTORY_MOVEMENTS_BULK_RECEIVE,
    ]);

    return $user;
}

function qcRestrictedUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
    ]);

    return $user;
}

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PurchaseOrderPermissionSeeder::class);
    $this->seed(InventoryMovementPermissionSeeder::class);

    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
    $this->location = InventoryLocation::factory()->create();

    $this->po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::QualityCheck,
    ]);

    $this->poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $this->po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 10,
    ]);

    $this->grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    $this->grnLine = GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $this->grn->id,
        'purchase_order_line_id' => $this->poLine->id,
        'qty_received' => 10,
    ]);
});

// ── submitQc: Authorization ───────────────────────────────────────────────────

it('guest is redirected to login on submitQc', function () {
    $this->post(route('purchase-orders.goods-receipts.submitQc', [$this->po, $this->grn]))
        ->assertRedirect(route('login'));
});

it('user without permission cannot submitQc (403)', function () {
    $this->actingAs(qcRestrictedUser())
        ->post(route('purchase-orders.goods-receipts.submitQc', [$this->po, $this->grn]), [
            'lines' => [[
                'goods_receipt_line_id' => $this->grnLine->id,
                'qty_passed' => 10,
                'qty_failed' => 0,
            ]],
        ])
        ->assertForbidden();
});

// ── submitQc: Happy Path ──────────────────────────────────────────────────────

it('authorized user can submitQc with valid data', function () {
    $this->actingAs(qcAdminUser())
        ->post(route('purchase-orders.goods-receipts.submitQc', [$this->po, $this->grn]), [
            'lines' => [[
                'goods_receipt_line_id' => $this->grnLine->id,
                'qty_passed' => 8,
                'qty_failed' => 2,
            ]],
        ])
        ->assertRedirect(route('purchase-orders.goods-receipts.show', [$this->po, $this->grn]))
        ->assertSessionHas('success', 'QC submitted. Assign serials below.');
});

it('submitQc sets qty_passed and qty_failed on GRN lines', function () {
    $this->actingAs(qcAdminUser())
        ->post(route('purchase-orders.goods-receipts.submitQc', [$this->po, $this->grn]), [
            'lines' => [[
                'goods_receipt_line_id' => $this->grnLine->id,
                'qty_passed' => 7,
                'qty_failed' => 3,
            ]],
        ]);

    $this->assertDatabaseHas('goods_receipt_lines', [
        'id' => $this->grnLine->id,
        'qty_passed' => 7,
        'qty_failed' => 3,
    ]);
});

it('submitQc sets qc_inspected_by to current user', function () {
    $inspector = qcAdminUser();

    $this->actingAs($inspector)
        ->post(route('purchase-orders.goods-receipts.submitQc', [$this->po, $this->grn]), [
            'lines' => [[
                'goods_receipt_line_id' => $this->grnLine->id,
                'qty_passed' => 10,
                'qty_failed' => 0,
            ]],
        ]);

    $this->assertDatabaseHas('goods_receipt_lines', [
        'id' => $this->grnLine->id,
        'qc_inspected_by' => $inspector->id,
    ]);
});

it('submitQc sets qc_inspected_at timestamp', function () {
    $this->actingAs(qcAdminUser())
        ->post(route('purchase-orders.goods-receipts.submitQc', [$this->po, $this->grn]), [
            'lines' => [[
                'goods_receipt_line_id' => $this->grnLine->id,
                'qty_passed' => 10,
                'qty_failed' => 0,
            ]],
        ]);

    $this->assertDatabaseHas('goods_receipt_lines', [
        'id' => $this->grnLine->id,
    ]);

    $line = GoodsReceiptLine::find($this->grnLine->id);
    expect($line->qc_inspected_at)->not->toBeNull();
});

// ── submitQc: Validation Failures ─────────────────────────────────────────────

it('submitQc fails when qty_passed + qty_failed !== qty_received', function () {
    $this->actingAs(qcAdminUser())
        ->post(route('purchase-orders.goods-receipts.submitQc', [$this->po, $this->grn]), [
            'lines' => [[
                'goods_receipt_line_id' => $this->grnLine->id,
                'qty_passed' => 5,
                'qty_failed' => 3,
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('lines.0.qty_failed');
});

it('submitQc fails when GRN is not complete', function () {
    $draftGrn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $draftGrn->id,
        'purchase_order_line_id' => $this->poLine->id,
        'qty_received' => 5,
    ]);

    $this->actingAs(qcAdminUser())
        ->post(route('purchase-orders.goods-receipts.submitQc', [$this->po, $draftGrn]), [
            'lines' => [[
                'goods_receipt_line_id' => $draftGrn->lines()->first()->id,
                'qty_passed' => 5,
                'qty_failed' => 0,
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('error');
});

it('submitQc fails when PO is not in quality_check status', function () {
    $receivedPo = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);

    $receivedPoLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $receivedPo->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'qty_received' => 5,
    ]);

    $receivedGrn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $receivedPo->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    $receivedGrnLine = GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $receivedGrn->id,
        'purchase_order_line_id' => $receivedPoLine->id,
        'qty_received' => 5,
    ]);

    $this->actingAs(qcAdminUser())
        ->post(route('purchase-orders.goods-receipts.submitQc', [$receivedPo, $receivedGrn]), [
            'lines' => [[
                'goods_receipt_line_id' => $receivedGrnLine->id,
                'qty_passed' => 5,
                'qty_failed' => 0,
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('error');
});

it('submitQc fails when QC already submitted', function () {
    // First submission
    $this->grnLine->update([
        'qty_passed' => 8,
        'qty_failed' => 2,
        'qc_inspected_at' => now(),
        'qc_inspected_by' => qcAdminUser()->id,
    ]);

    // Try second submission
    $this->actingAs(qcAdminUser())
        ->post(route('purchase-orders.goods-receipts.submitQc', [$this->po, $this->grn]), [
            'lines' => [[
                'goods_receipt_line_id' => $this->grnLine->id,
                'qty_passed' => 8,
                'qty_failed' => 2,
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('error');
});

// ── assignSerials: Authorization ──────────────────────────────────────────────

it('guest is redirected to login on assignSerials GET', function () {
    $this->get(route('purchase-orders.goods-receipts.assignSerials', [$this->po, $this->grn]))
        ->assertRedirect(route('login'));
});

it('user without bulk-receive permission cannot view assignSerials (403)', function () {
    $this->actingAs(qcRestrictedUser())
        ->get(route('purchase-orders.goods-receipts.assignSerials', [$this->po, $this->grn]))
        ->assertForbidden();
});

// ── assignSerials: Happy Path ─────────────────────────────────────────────────

it('authorized user can view assignSerials form (200)', function () {
    $this->po->update(['status' => PurchaseOrderStatus::Received]);
    $this->grnLine->update([
        'qty_passed' => 10,
        'qty_failed' => 0,
        'qc_inspected_at' => now(),
        'qc_inspected_by' => qcAdminUser()->id,
    ]);

    $this->actingAs(qcAdminUser())
        ->get(route('purchase-orders.goods-receipts.assignSerials', [$this->po, $this->grn]))
        ->assertOk()
        ->assertViewHas('purchaseOrder')
        ->assertViewHas('goodsReceipt')
        ->assertViewHas('locations');
});

it('assignSerials shows GRN lines with QC data', function () {
    $this->po->update(['status' => PurchaseOrderStatus::Received]);
    $this->grnLine->update([
        'qty_passed' => 8,
        'qty_failed' => 2,
        'qc_inspected_at' => now(),
        'qc_inspected_by' => qcAdminUser()->id,
    ]);

    $response = $this->actingAs(qcAdminUser())
        ->get(route('purchase-orders.goods-receipts.assignSerials', [$this->po, $this->grn]));

    $response->assertOk();
    expect($response['goodsReceipt']->lines)->toHaveCount(1);
});

// ── assignSerials: Validation Failures ────────────────────────────────────────

it('assignSerials fails when GRN is not complete', function () {
    $draftGrn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $draftGrn->id,
        'purchase_order_line_id' => $this->poLine->id,
        'qty_received' => 5,
    ]);

    $this->actingAs(qcAdminUser())
        ->get(route('purchase-orders.goods-receipts.assignSerials', [$this->po, $draftGrn]))
        ->assertRedirect()
        ->assertSessionHasErrors('error');
});

it('assignSerials fails when PO status is not partially_received or received', function () {
    $approvedPo = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $approvedPoLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $approvedPo->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'qty_received' => 0,
    ]);

    $approvedGrn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $approvedPo->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $approvedGrn->id,
        'purchase_order_line_id' => $approvedPoLine->id,
        'qty_received' => 5,
    ]);

    $this->actingAs(qcAdminUser())
        ->get(route('purchase-orders.goods-receipts.assignSerials', [$approvedPo, $approvedGrn]))
        ->assertRedirect()
        ->assertSessionHasErrors('error');
});

// ── storeSerials: Authorization ───────────────────────────────────────────────

it('guest is redirected to login on storeSerials POST', function () {
    $this->post(route('purchase-orders.goods-receipts.storeSerials', [$this->po, $this->grn]), [])
        ->assertRedirect(route('login'));
});

it('user without bulk-receive permission cannot storeSerials (403)', function () {
    $this->grnLine->update([
        'qty_passed' => 10,
        'qty_failed' => 0,
    ]);

    $this->actingAs(qcRestrictedUser())
        ->post(route('purchase-orders.goods-receipts.storeSerials', [$this->po, $this->grn]), [
            'lines' => [[
                'goods_receipt_line_id' => $this->grnLine->id,
                'inventory_location_id' => $this->location->id,
                'purchase_price' => 100.00,
            ]],
        ])
        ->assertForbidden();
});

// ── storeSerials: Happy Path ──────────────────────────────────────────────────

it('authorized user can storeSerials with valid data', function () {
    $this->po->update(['status' => PurchaseOrderStatus::Received]);
    $this->grnLine->update([
        'qty_passed' => 10,
        'qty_failed' => 0,
        'qc_inspected_at' => now(),
        'qc_inspected_by' => qcAdminUser()->id,
    ]);

    $this->actingAs(qcAdminUser())
        ->post(route('purchase-orders.goods-receipts.storeSerials', [$this->po, $this->grn]), [
            'lines' => [[
                'goods_receipt_line_id' => $this->grnLine->id,
                'inventory_location_id' => $this->location->id,
                'purchase_price' => 100.00,
            ]],
        ])
        ->assertRedirect(route('inventory-movements.bulk-receive-print'))
        ->assertSessionHas('success');
});

it('storeSerials creates InventorySerial records for qty_passed', function () {
    $this->po->update(['status' => PurchaseOrderStatus::Received]);
    $this->grnLine->update([
        'qty_passed' => 5,
        'qty_failed' => 5,
        'qc_inspected_at' => now(),
        'qc_inspected_by' => qcAdminUser()->id,
    ]);

    $this->actingAs(qcAdminUser())
        ->post(route('purchase-orders.goods-receipts.storeSerials', [$this->po, $this->grn]), [
            'lines' => [[
                'goods_receipt_line_id' => $this->grnLine->id,
                'inventory_location_id' => $this->location->id,
                'purchase_price' => 100.00,
            ]],
        ]);

    $this->assertDatabaseCount('inventory_serials', 5);
});

it('storeSerials creates InventoryMovement records with goods_receipt_id', function () {
    $this->po->update(['status' => PurchaseOrderStatus::Received]);
    $this->grnLine->update([
        'qty_passed' => 3,
        'qty_failed' => 7,
        'qc_inspected_at' => now(),
        'qc_inspected_by' => qcAdminUser()->id,
    ]);

    $this->actingAs(qcAdminUser())
        ->post(route('purchase-orders.goods-receipts.storeSerials', [$this->po, $this->grn]), [
            'lines' => [[
                'goods_receipt_line_id' => $this->grnLine->id,
                'inventory_location_id' => $this->location->id,
                'purchase_price' => 100.00,
            ]],
        ]);

    $this->assertDatabaseHas('inventory_movements', [
        'goods_receipt_id' => $this->grn->id,
    ]);
});

it('storeSerials skips lines with qty_passed = 0', function () {
    $this->po->update(['status' => PurchaseOrderStatus::Received]);
    $this->grnLine->update([
        'qty_passed' => 0,
        'qty_failed' => 10,
        'qc_inspected_at' => now(),
        'qc_inspected_by' => qcAdminUser()->id,
    ]);

    $this->actingAs(qcAdminUser())
        ->post(route('purchase-orders.goods-receipts.storeSerials', [$this->po, $this->grn]), [
            'lines' => [[
                'goods_receipt_line_id' => $this->grnLine->id,
                'inventory_location_id' => $this->location->id,
                'purchase_price' => 100.00,
            ]],
        ]);

    $this->assertDatabaseCount('inventory_serials', 0);
});

it('storeSerials fails when QC not submitted', function () {
    // GRN line has no qty_passed
    $this->actingAs(qcAdminUser())
        ->post(route('purchase-orders.goods-receipts.storeSerials', [$this->po, $this->grn]), [
            'lines' => [[
                'goods_receipt_line_id' => $this->grnLine->id,
                'inventory_location_id' => $this->location->id,
                'purchase_price' => 100.00,
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('error');
});

it('storeSerials fails when PO status is not partially_received or received', function () {
    $approvedPo = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $approvedPoLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $approvedPo->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'qty_received' => 0,
    ]);

    $approvedGrn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $approvedPo->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    $approvedGrnLine = GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $approvedGrn->id,
        'purchase_order_line_id' => $approvedPoLine->id,
        'qty_received' => 5,
        'qty_passed' => 5,
        'qty_failed' => 0,
        'qc_inspected_at' => now(),
        'qc_inspected_by' => qcAdminUser()->id,
    ]);

    $this->actingAs(qcAdminUser())
        ->post(route('purchase-orders.goods-receipts.storeSerials', [$approvedPo, $approvedGrn]), [
            'lines' => [[
                'goods_receipt_line_id' => $approvedGrnLine->id,
                'inventory_location_id' => $this->location->id,
                'purchase_price' => 100.00,
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('error');
});
