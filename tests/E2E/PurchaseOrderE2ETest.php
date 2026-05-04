<?php

declare(strict_types=1);

/**
 * Purchase Order E2E Test Suite
 *
 * Covers HTTP journeys for the full procurement module:
 *   PO-01..29  — Purchase Order lifecycle, auth, CRUD, state machine
 *   GRN-01..21 — Goods Receipt lifecycle, auth, CRUD, completion side-effects
 *   INV-01..17 — Invoice lifecycle, auth, CRUD, PO closure side-effects
 *   J-01..05   — Cross-module journeys
 */

use App\Enums\GoodsReceiptStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PurchaseOrderPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────────────────────────────────────
// SETUP
// ──────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PurchaseOrderPermissionSeeder::class);

    $this->admin = User::factory()->create()->assignRole('admin');
    $this->sales = User::factory()->create()->assignRole('sales');
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
});

function poPayload(int $supplierId, int $productId): array
{
    return [
        'supplier_id' => $supplierId,
        'expected_delivery_date' => now()->addDays(14)->toDateString(),
        'notes' => null,
        'lines' => [[
            'product_id' => $productId,
            'description' => 'E2E Widget',
            'qty_ordered' => 10,
            'unit_cost' => 25.00,
            'tax_rate' => 10,
        ]],
    ];
}

function grnPayload(int $poLineId, int|float|string $qty = 10): array
{
    return [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [[
            'purchase_order_line_id' => $poLineId,
            'qty_received' => $qty,
            'notes' => null,
        ]],
    ];
}

function invoicePayload(string $suffix = ''): array
{
    return [
        'invoice_number' => 'INV-E2E-'.($suffix ?: uniqid()),
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'amount' => 275.00,
        'notes' => null,
    ];
}

// ==============================================================================
// AUTH & ACCESS (PO-01 to PO-05)
// ==============================================================================

it('[PO-01] guest GET index redirects to login', function () {
    $this->get(route('purchase-orders.index'))
        ->assertRedirect(route('login'));
});

it('[PO-02] guest GET create redirects to login', function () {
    $this->get(route('purchase-orders.create'))
        ->assertRedirect(route('login'));
});

it('[PO-03] sales GET index returns 200', function () {
    $this->actingAs($this->sales)
        ->get(route('purchase-orders.index'))
        ->assertOk();
});

it('[PO-04] sales GET create is forbidden', function () {
    $this->actingAs($this->sales)
        ->get(route('purchase-orders.create'))
        ->assertForbidden();
});

it('[PO-05] sales POST store is forbidden', function () {
    $this->actingAs($this->sales)
        ->post(route('purchase-orders.store'), poPayload($this->supplier->id, $this->product->id))
        ->assertForbidden();
});

// ==============================================================================
// CREATE / STORE (PO-06 to PO-09)
// ==============================================================================

it('[PO-06] admin GET create returns 200', function () {
    $this->actingAs($this->admin)
        ->get(route('purchase-orders.create'))
        ->assertOk();
});

it('[PO-07] admin POST valid payload creates draft PO with PO- prefixed number', function () {
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.store'), poPayload($this->supplier->id, $this->product->id))
        ->assertRedirect();

    $po = PurchaseOrder::latest()->first();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Draft->value,
    ]);

    expect($po->po_number)->toStartWith('PO-');
});

it('[PO-08] admin POST with empty lines array returns validation errors', function () {
    $payload = poPayload($this->supplier->id, $this->product->id);
    $payload['lines'] = [];

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.store'), $payload)
        ->assertSessionHasErrors();
});

it('[PO-09] admin POST with invalid supplier_id returns validation errors', function () {
    $payload = poPayload(9999, $this->product->id);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.store'), $payload)
        ->assertSessionHasErrors();
});

// ==============================================================================
// EDIT / UPDATE (PO-10 to PO-13)
// ==============================================================================

it('[PO-10] admin GET edit on draft PO returns 200', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $this->actingAs($this->admin)
        ->get(route('purchase-orders.edit', $po))
        ->assertOk();
});

it('[PO-11] admin GET edit on submitted PO redirects with errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::PendingApproval,
    ]);

    $this->actingAs($this->admin)
        ->get(route('purchase-orders.edit', $po))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('[PO-12] admin PUT valid update on draft PO persists notes change', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);
    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'qty_received' => 0,
    ]);

    $payload = [
        'supplier_id' => $this->supplier->id,
        'expected_delivery_date' => now()->addDays(7)->toDateString(),
        'notes' => 'Updated via E2E',
        'lines' => [[
            'product_id' => $this->product->id,
            'description' => 'E2E Widget',
            'qty_ordered' => 5,
            'unit_cost' => 25.00,
            'tax_rate' => 10,
            'qty_on_hand_snapshot' => 0,
        ]],
    ];

    $this->actingAs($this->admin)
        ->put(route('purchase-orders.update', $po), $payload)
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'notes' => 'Updated via E2E',
    ]);
});

it('[PO-13] admin PUT on approved PO redirects with errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'qty_received' => 0,
    ]);

    $payload = [
        'supplier_id' => $this->supplier->id,
        'expected_delivery_date' => now()->addDays(7)->toDateString(),
        'notes' => 'Should not save',
        'lines' => [[
            'product_id' => $this->product->id,
            'description' => 'E2E Widget',
            'qty_ordered' => 5,
            'unit_cost' => 25.00,
            'tax_rate' => 10,
        ]],
    ];

    $this->actingAs($this->admin)
        ->put(route('purchase-orders.update', $po), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors();
});

// ==============================================================================
// STATUS TRANSITIONS (PO-14 to PO-23)
// ==============================================================================

it('[PO-14] admin POST submit on draft PO transitions to pending_approval', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.submit', $po))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::PendingApproval->value,
    ]);
});

it('[PO-15] admin POST submit on rejected PO resubmits to pending_approval', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Rejected,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.submit', $po))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::PendingApproval->value,
    ]);
});

it('[PO-16] admin POST submit on already-pending PO redirects with errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::PendingApproval,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.submit', $po))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('[PO-17] admin POST approve on pending PO sets approved status, approved_by, and approved_at', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::PendingApproval,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.approve', $po))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Approved->value,
        'approved_by' => $this->admin->id,
    ]);

    expect(PurchaseOrder::find($po->id)->approved_at)->not->toBeNull();
});

it('[PO-18] admin POST reject on pending PO with rejection_reason sets rejected status and persists reason', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::PendingApproval,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.reject', $po), ['rejection_reason' => 'Too expensive'])
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Rejected->value,
        'rejection_reason' => 'Too expensive',
    ]);
});

it('[PO-19] admin POST approve on draft PO redirects with errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.approve', $po))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('[PO-20] admin POST on-the-way on approved PO transitions to on_the_way', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.markOnTheWay', $po))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::OnTheWay->value,
    ]);
});

it('[PO-21] admin POST cancel on approved PO transitions to cancelled', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.cancel', $po))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Cancelled->value,
    ]);
});

it('[PO-22] admin POST cancel on already-cancelled PO redirects with errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Cancelled,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.cancel', $po))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('[PO-23] manager with submit permission can submit a draft PO', function () {
    $manager = User::factory()->create()->assignRole('manager');

    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $this->actingAs($manager)
        ->post(route('purchase-orders.submit', $po))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::PendingApproval->value,
    ]);
});

// ==============================================================================
// DELETE / RESTORE (PO-24 to PO-26)
// ==============================================================================

it('[PO-24] admin DELETE draft PO soft-deletes the record', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('purchase-orders.destroy', $po))
        ->assertRedirect();

    $this->assertSoftDeleted('purchase_orders', ['id' => $po->id]);
});

it('[PO-25] admin POST restore on deleted PO clears deleted_at', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);
    $po->delete();

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.restore', $po->id))
        ->assertRedirect();

    $restored = PurchaseOrder::withTrashed()->find($po->id);
    expect($restored->deleted_at)->toBeNull();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'deleted_at' => null,
    ]);
});

it('[PO-26] sales DELETE is forbidden', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $this->actingAs($this->sales)
        ->delete(route('purchase-orders.destroy', $po))
        ->assertForbidden();

    $this->assertDatabaseHas('purchase_orders', ['id' => $po->id, 'deleted_at' => null]);
});

// ==============================================================================
// SHOW / PRINT (PO-27 to PO-29)
// ==============================================================================

it('[PO-27] admin GET show returns 200', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('purchase-orders.show', $po))
        ->assertOk();
});

it('[PO-28] admin GET print returns 200', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('purchase-orders.print', $po))
        ->assertOk();
});

it('[PO-29] sales GET show returns 200', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
    ]);

    $this->actingAs($this->sales)
        ->get(route('purchase-orders.show', $po))
        ->assertOk();
});
// ==============================================================================
// GRN — GOODS RECEIPTS
// ==============================================================================

// ── Auth & Access (GRN-01 to GRN-04) ─────────────────────────────────────────

it('[GRN-01] guest GET create GRN → redirect to login', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $this->get(route('purchase-orders.goods-receipts.create', $po))
        ->assertRedirect(route('login'));
});

it('[GRN-02] sales GET create GRN → 403 Forbidden', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $this->actingAs($this->sales)
        ->get(route('purchase-orders.goods-receipts.create', $po))
        ->assertForbidden();
});

it('[GRN-03] sales GET show GRN → 200 OK (view permission)', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    $this->actingAs($this->sales)
        ->get(route('purchase-orders.goods-receipts.show', [$po, $grn]))
        ->assertOk();
});

it('[GRN-04] sales PUT update GRN → 403 Forbidden', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    $this->actingAs($this->sales)
        ->put(route('purchase-orders.goods-receipts.update', [$po, $grn]), grnPayload($poLine->id, 5))
        ->assertForbidden();
});

// ── Create / Store (GRN-05 to GRN-10) ────────────────────────────────────────

it('[GRN-05] admin GET create GRN for approved PO → 200 OK', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $this->actingAs($this->admin)
        ->get(route('purchase-orders.goods-receipts.create', $po))
        ->assertOk();
});

it('[GRN-06] admin POST valid GRN for approved PO → draft GRN created, PO line qty_received unchanged', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.store', $po), grnPayload($poLine->id, 5))
        ->assertRedirect();

    $this->assertDatabaseHas('goods_receipts', [
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft->value,
    ]);

    // qty_received on PO line must remain 0 until GRN is completed
    $this->assertDatabaseHas('purchase_order_lines', [
        'id' => $poLine->id,
        'qty_received' => 0,
    ]);
});

it('[GRN-07] admin POST valid GRN for on_the_way PO → success (allowed status)', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::OnTheWay,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.store', $po), grnPayload($poLine->id, 5))
        ->assertRedirect();

    $this->assertDatabaseHas('goods_receipts', [
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft->value,
    ]);
});

it('[GRN-08] admin POST GRN for draft PO → redirect with validation errors', function () {
    $draftPo = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $draftPo->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.store', $draftPo), grnPayload($poLine->id, 5))
        ->assertRedirect()
        ->assertSessionHasErrors();

    $this->assertDatabaseMissing('goods_receipts', [
        'purchase_order_id' => $draftPo->id,
    ]);
});

it('[GRN-09] admin POST GRN with qty_received > qty_ordered → redirect with validation errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.store', $po), grnPayload($poLine->id, 999))
        ->assertRedirect()
        ->assertSessionHasErrors();

    $this->assertDatabaseMissing('goods_receipts', [
        'purchase_order_id' => $po->id,
    ]);
});

it('[GRN-10] admin POST GRN with no lines → redirect with validation errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $payload = [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [],
    ];

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.store', $po), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors();

    $this->assertDatabaseMissing('goods_receipts', [
        'purchase_order_id' => $po->id,
    ]);
});

// ── Edit / Update (GRN-11 to GRN-15) ─────────────────────────────────────────

it('[GRN-11] admin GET edit on draft GRN → 200 OK', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    $this->actingAs($this->admin)
        ->get(route('purchase-orders.goods-receipts.edit', [$po, $grn]))
        ->assertOk();
});

it('[GRN-12] admin GET edit on complete GRN → redirect with errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    $this->actingAs($this->admin)
        ->get(route('purchase-orders.goods-receipts.edit', [$po, $grn]))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('[GRN-13] admin PUT update on draft GRN with notes → redirect and notes persisted', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    $payload = [
        'received_date' => now()->toDateString(),
        'notes' => 'Updated E2E',
        'lines' => [[
            'purchase_order_line_id' => $poLine->id,
            'qty_received' => 5,
            'notes' => null,
        ]],
    ];

    $this->actingAs($this->admin)
        ->put(route('purchase-orders.goods-receipts.update', [$po, $grn]), $payload)
        ->assertRedirect();

    $this->assertDatabaseHas('goods_receipts', [
        'id' => $grn->id,
        'notes' => 'Updated E2E',
    ]);
});

it('[GRN-14] admin PUT with qty > remaining → redirect with validation errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    $payload = [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [[
            'purchase_order_line_id' => $poLine->id,
            'qty_received' => 999,
            'notes' => null,
        ]],
    ];

    $this->actingAs($this->admin)
        ->put(route('purchase-orders.goods-receipts.update', [$po, $grn]), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('[GRN-15] sales PUT update GRN → 403 Forbidden', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    $this->actingAs($this->sales)
        ->put(route('purchase-orders.goods-receipts.update', [$po, $grn]), grnPayload($poLine->id, 5))
        ->assertForbidden();
});

// ── Complete (GRN-16 to GRN-18) ───────────────────────────────────────────────

it('[GRN-16] admin POST complete on draft GRN with full qty → GRN complete, PO received, line qty_received updated', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);
    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn->id,
        'purchase_order_line_id' => $poLine->id,
        'qty_received' => 10, // full quantity
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.complete', [$po, $grn]))
        ->assertRedirect();

    $this->assertDatabaseHas('goods_receipts', [
        'id' => $grn->id,
        'status' => GoodsReceiptStatus::Complete->value,
    ]);
    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Received->value,
    ]);
    $this->assertDatabaseHas('purchase_order_lines', [
        'id' => $poLine->id,
        'qty_received' => 10,
    ]);
});

it('[GRN-17] admin POST complete on draft GRN with partial qty (5 of 10) → GRN complete, PO partially_received', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);
    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn->id,
        'purchase_order_line_id' => $poLine->id,
        'qty_received' => 5, // partial quantity
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.complete', [$po, $grn]))
        ->assertRedirect();

    $this->assertDatabaseHas('goods_receipts', [
        'id' => $grn->id,
        'status' => GoodsReceiptStatus::Complete->value,
    ]);
    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::PartiallyReceived->value,
    ]);
});

it('[GRN-18] admin POST complete on already-complete GRN → redirect with errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.complete', [$po, $grn]))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

// ── Delete (GRN-19 to GRN-21) ─────────────────────────────────────────────────

it('[GRN-19] admin DELETE draft GRN → soft-deleted', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('purchase-orders.goods-receipts.destroy', [$po, $grn]))
        ->assertRedirect();

    $this->assertSoftDeleted('goods_receipts', ['id' => $grn->id]);
});

it('[GRN-20] admin DELETE complete GRN → redirect with errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('purchase-orders.goods-receipts.destroy', [$po, $grn]))
        ->assertRedirect()
        ->assertSessionHasErrors();

    $this->assertDatabaseHas('goods_receipts', ['id' => $grn->id, 'deleted_at' => null]);
});

it('[GRN-21] sales DELETE draft GRN → 403 Forbidden', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    $this->actingAs($this->sales)
        ->delete(route('purchase-orders.goods-receipts.destroy', [$po, $grn]))
        ->assertForbidden();

    $this->assertDatabaseHas('goods_receipts', ['id' => $grn->id, 'deleted_at' => null]);
});
// ==============================================================================
// INVOICES
// ==============================================================================

// ── Auth & Access (INV-01 to INV-03) ─────────────────────────────────────────

it('[INV-01] guest GET invoice create → redirect to login', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);

    $this->get(route('purchase-orders.invoices.create', $po))
        ->assertRedirect(route('login'));
});

it('[INV-02] sales GET invoice create → 403 Forbidden', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);

    $this->actingAs($this->sales)
        ->get(route('purchase-orders.invoices.create', $po))
        ->assertForbidden();
});

it('[INV-03] sales GET invoice show → 200 OK (view permission)', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);
    $invoice = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Pending,
    ]);

    $this->actingAs($this->sales)
        ->get(route('purchase-orders.invoices.show', [$po, $invoice]))
        ->assertOk();
});

// ── Create / Store (INV-04 to INV-07) ────────────────────────────────────────

it('[INV-04] admin POST valid invoice for received PO → invoice status=pending; PO status=invoiced', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);
    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.store', $po), invoicePayload())
        ->assertRedirect();

    $invoice = Invoice::latest()->first();

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Pending->value,
    ]);

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Invoiced->value,
    ]);
});

it('[INV-05] admin POST invoice for approved PO → invoice created (approved PO allowed)', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.store', $po), invoicePayload())
        ->assertRedirect();

    $this->assertDatabaseHas('invoices', [
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Pending->value,
    ]);
});

it('[INV-06] admin POST invoice for draft PO → redirected with validation errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);
    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.store', $po), invoicePayload())
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('[INV-07] admin POST duplicate invoice_number → validation errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);
    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
    ]);

    Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'invoice_number' => 'INV-DUP-001',
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.store', $po), [
            'invoice_number' => 'INV-DUP-001',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'amount' => 275.00,
            'notes' => null,
        ])
        ->assertSessionHasErrors();
});

// ── Approve (INV-08 to INV-10) ────────────────────────────────────────────────

it('[INV-08] admin POST approve on pending invoice → status=approved; approved_by set; approved_at set', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Invoiced,
    ]);
    $invoice = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Pending,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.approve', [$po, $invoice]))
        ->assertRedirect();

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'status' => InvoiceStatus::Approved->value,
        'approved_by' => $this->admin->id,
    ]);

    expect(Invoice::find($invoice->id)->approved_at)->not->toBeNull();
});

it('[INV-09] admin POST approve on paid invoice → redirected with validation errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Closed,
    ]);
    $invoice = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Paid,
        'paid_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.approve', [$po, $invoice]))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('[INV-10] sales POST approve → 403 Forbidden', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Invoiced,
    ]);
    $invoice = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Pending,
    ]);

    $this->actingAs($this->sales)
        ->post(route('purchase-orders.invoices.approve', [$po, $invoice]))
        ->assertForbidden();
});

// ── Mark Paid (INV-11 to INV-14) ─────────────────────────────────────────────

it('[INV-11] admin POST mark-paid on approved invoice → status=paid; paid_at is not null', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Invoiced,
    ]);
    $invoice = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Approved,
        'approved_by' => $this->admin->id,
        'approved_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.markPaid', [$po, $invoice]))
        ->assertRedirect();

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'status' => InvoiceStatus::Paid->value,
    ]);

    expect(Invoice::find($invoice->id)->paid_at)->not->toBeNull();
});

it('[INV-12] admin POST mark-paid on pending invoice → redirected with validation errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Invoiced,
    ]);
    $invoice = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Pending,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.markPaid', [$po, $invoice]))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('[INV-13] admin POST mark-paid on last unpaid invoice → PO status=closed', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Invoiced,
    ]);
    $invoice = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Approved,
        'approved_by' => $this->admin->id,
        'approved_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.markPaid', [$po, $invoice]))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Closed->value,
    ]);
});

it('[INV-14] admin POST mark-paid one of two invoices (other still pending) → PO NOT closed', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Invoiced,
    ]);

    $invoiceA = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'invoice_number' => 'INV-A',
        'status' => InvoiceStatus::Approved,
        'approved_by' => $this->admin->id,
        'approved_at' => now(),
    ]);

    // Second invoice is still pending — not approved or paid
    Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'invoice_number' => 'INV-B',
        'status' => InvoiceStatus::Pending,
    ]);

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.markPaid', [$po, $invoiceA]))
        ->assertRedirect();

    // PO must NOT be closed while INV-B is still pending
    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Invoiced->value,
    ]);
});

// ── Delete (INV-15 to INV-17) ─────────────────────────────────────────────────

it('[INV-15] admin DELETE pending invoice → soft-deleted record', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Invoiced,
    ]);
    $invoice = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Pending,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('purchase-orders.invoices.destroy', [$po, $invoice]))
        ->assertRedirect();

    $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
});

it('[INV-16] admin DELETE paid invoice → redirected with validation errors', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Closed,
    ]);
    $invoice = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Paid,
        'paid_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->delete(route('purchase-orders.invoices.destroy', [$po, $invoice]))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

it('[INV-17] sales DELETE invoice → 403 Forbidden', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Invoiced,
    ]);
    $invoice = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Pending,
    ]);

    $this->actingAs($this->sales)
        ->delete(route('purchase-orders.invoices.destroy', [$po, $invoice]))
        ->assertForbidden();

    $this->assertDatabaseMissing('invoices', [
        'id' => $invoice->id,
        'deleted_at' => null === false,
    ]);
    $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'deleted_at' => null]);
});

// ==============================================================================
// JOURNEY TESTS
// ==============================================================================

// ── J-01: Full procurement pipeline ──────────────────────────────────────────

it('[J-01] full procurement pipeline: draft → submit → approve → GRN → complete → invoice → approve → pay → PO closed', function () {
    // 1. POST purchase-orders.store → draft PO
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'expected_delivery_date' => now()->addDays(14)->toDateString(),
            'notes' => null,
            'lines' => [[
                'product_id' => $this->product->id,
                'description' => 'J-01 Widget',
                'qty_ordered' => 10,
                'unit_cost' => 25.00,
                'tax_rate' => 10,
            ]],
        ])
        ->assertRedirect();

    $po = PurchaseOrder::latest()->first();
    expect($po)->not->toBeNull();

    // 2. POST purchase-orders.submit
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.submit', $po))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::PendingApproval->value,
    ]);

    // 3. POST purchase-orders.approve
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.approve', $po))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Approved->value,
    ]);

    // Resolve the PO line for GRN payload
    $poLine = $po->lines()->first();
    expect($poLine)->not->toBeNull();

    // 4. POST purchase-orders.goods-receipts.store (full qty)
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.store', $po), [
            'received_date' => now()->toDateString(),
            'notes' => null,
            'lines' => [[
                'purchase_order_line_id' => $poLine->id,
                'qty_received' => 10,
                'notes' => null,
            ]],
        ])
        ->assertRedirect();

    $grn = GoodsReceipt::latest()->first();
    expect($grn)->not->toBeNull();

    // 5. POST purchase-orders.goods-receipts.complete
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.complete', [$po, $grn]))
        ->assertRedirect();

    // 6. Assert PO status=received
    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Received->value,
    ]);

    // 7. POST purchase-orders.invoices.store
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.store', $po), invoicePayload('INV-J01-001'))
        ->assertRedirect();

    // PO transitions to invoiced after invoice store on received PO
    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Invoiced->value,
    ]);

    $invoice = Invoice::latest()->first();
    expect($invoice)->not->toBeNull();

    // 8. POST purchase-orders.invoices.approve
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.approve', [$po, $invoice]))
        ->assertRedirect();

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'status' => InvoiceStatus::Approved->value,
    ]);

    // 9. POST purchase-orders.invoices.markPaid
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.markPaid', [$po, $invoice]))
        ->assertRedirect();

    // 10. Assert PO status=closed
    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Closed->value,
    ]);
});

// ── J-02: Partial then full receive ──────────────────────────────────────────

it('[J-02] partial then full receive: first GRN sets partially_received, second sets received', function () {
    // Create an approved PO with one line (qty=10)
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);

    // 1. First GRN with qty=4 → complete → PO=partially_received
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.store', $po), [
            'received_date' => now()->toDateString(),
            'notes' => null,
            'lines' => [[
                'purchase_order_line_id' => $poLine->id,
                'qty_received' => 4,
                'notes' => null,
            ]],
        ])
        ->assertRedirect();

    $grn1 = GoodsReceipt::latest('id')->first();

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.complete', [$po, $grn1]))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::PartiallyReceived->value,
    ]);

    // 2. Second GRN with qty=6 → complete → PO=received
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.store', $po), [
            'received_date' => now()->toDateString(),
            'notes' => null,
            'lines' => [[
                'purchase_order_line_id' => $poLine->id,
                'qty_received' => 6,
                'notes' => null,
            ]],
        ])
        ->assertRedirect();

    $grn2 = GoodsReceipt::latest('id')->first();

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.goods-receipts.complete', [$po, $grn2]))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Received->value,
    ]);
});

// ── J-03: Reject and resubmit ─────────────────────────────────────────────────

it('[J-03] reject and resubmit: draft → pending → rejected → resubmit → pending → approved', function () {
    // 1. Create draft PO
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    // 2. POST submit → pending_approval
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.submit', $po))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::PendingApproval->value,
    ]);

    // 3. POST reject with reason
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.reject', $po), [
            'rejection_reason' => 'Price too high — please negotiate',
        ])
        ->assertRedirect();

    // 4. Assert status=rejected + reason saved
    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Rejected->value,
        'rejection_reason' => 'Price too high — please negotiate',
    ]);

    // 5. POST submit again → pending_approval (resubmit)
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.submit', $po))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::PendingApproval->value,
    ]);

    // 6. POST approve → approved
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.approve', $po))
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Approved->value,
    ]);
});

// ── J-04: Multiple invoices, PO closes only when all paid ────────────────────

it('[J-04] multiple invoices: PO closes only when ALL invoices are paid', function () {
    // 1. Create received PO
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);
    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
    ]);

    // 2. POST invoice INV-A → pending
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.store', $po), invoicePayload('INV-J04-A'))
        ->assertRedirect();

    $invoiceA = Invoice::latest('id')->first();
    expect($invoiceA)->not->toBeNull();

    // PO transitions to invoiced on first invoice store against received PO
    $po->refresh();

    // 3. POST invoice INV-B → pending (second invoice allowed on invoiced PO)
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.store', $po), invoicePayload('INV-J04-B'))
        ->assertRedirect();

    $invoiceB = Invoice::latest('id')->first();
    expect($invoiceB)->not->toBeNull();

    // 4. Approve + pay INV-A
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.approve', [$po, $invoiceA]))
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.markPaid', [$po, $invoiceA]))
        ->assertRedirect();

    // 5. Assert PO NOT closed (INV-B still pending)
    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Invoiced->value,
    ]);

    // 6. Approve + pay INV-B
    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.approve', [$po, $invoiceB]))
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('purchase-orders.invoices.markPaid', [$po, $invoiceB]))
        ->assertRedirect();

    // 7. Assert PO status=closed
    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Closed->value,
    ]);
});

// ── J-05: Sales read-only boundary ───────────────────────────────────────────

it('[J-05] sales user has read-only access: can view, cannot write', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);
    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);
    $invoice = Invoice::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => InvoiceStatus::Pending,
    ]);

    // — Reads that must succeed (200) —

    $this->actingAs($this->sales)
        ->get(route('purchase-orders.index'))
        ->assertOk();

    $this->actingAs($this->sales)
        ->get(route('purchase-orders.show', $po))
        ->assertOk();

    $this->actingAs($this->sales)
        ->get(route('purchase-orders.goods-receipts.show', [$po, $grn]))
        ->assertOk();

    $this->actingAs($this->sales)
        ->get(route('purchase-orders.invoices.show', [$po, $invoice]))
        ->assertOk();

    // — Writes that must be forbidden (403) —

    $this->actingAs($this->sales)
        ->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'description' => 'J-05 Widget',
                'qty_ordered' => 1,
                'unit_cost' => 10.00,
                'tax_rate' => 0,
            ]],
        ])
        ->assertForbidden();

    $this->actingAs($this->sales)
        ->post(route('purchase-orders.submit', $po))
        ->assertForbidden();

    $this->actingAs($this->sales)
        ->post(route('purchase-orders.goods-receipts.store', $po), [
            'received_date' => now()->toDateString(),
            'notes' => null,
            'lines' => [[
                'purchase_order_line_id' => $poLine->id,
                'qty_received' => 1,
                'notes' => null,
            ]],
        ])
        ->assertForbidden();

    $this->actingAs($this->sales)
        ->post(route('purchase-orders.invoices.store', $po), invoicePayload('INV-J05-001'))
        ->assertForbidden();
});
