<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PurchaseOrderPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PurchaseOrderPermissionSeeder::class);
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
});

// ── Guest redirects ───────────────────────────────────────────────────────────

it('guest is redirected to login on index', function () {
    $this->get(route('purchase-orders.index'))
        ->assertRedirect(route('login'));
});

it('guest is redirected to login on create', function () {
    $this->get(route('purchase-orders.create'))
        ->assertRedirect(route('login'));
});

it('guest is redirected to login on store', function () {
    $this->post(route('purchase-orders.store'), [])
        ->assertRedirect(route('login'));
});

// ── Sales user (read-only) ────────────────────────────────────────────────────

it('sales user can view the purchase orders index', function () {
    $this->actingAs(poSalesUser())
        ->get(route('purchase-orders.index'))
        ->assertOk();
});

it('sales user cannot access create form', function () {
    $this->actingAs(poSalesUser())
        ->get(route('purchase-orders.create'))
        ->assertForbidden();
});

it('sales user cannot store a purchase order', function () {
    $this->actingAs(poSalesUser())
        ->post(route('purchase-orders.store'), [])
        ->assertForbidden();
});

it('sales user cannot delete a purchase order', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);

    $this->actingAs(poSalesUser())
        ->delete(route('purchase-orders.destroy', $po))
        ->assertForbidden();
});

// ── Admin index ───────────────────────────────────────────────────────────────

it('admin can view the purchase orders index', function () {
    $this->actingAs(poAdminUser())
        ->get(route('purchase-orders.index'))
        ->assertOk()
        ->assertViewHas('pos');
});

// ── Admin create ──────────────────────────────────────────────────────────────

it('admin can view the create form', function () {
    $this->actingAs(poAdminUser())
        ->get(route('purchase-orders.create'))
        ->assertOk();
});

// ── Admin store ───────────────────────────────────────────────────────────────

it('admin can create a purchase order with valid data', function () {
    $data = [
        'supplier_id' => $this->supplier->id,
        'expected_delivery_date' => now()->addDays(7)->toDateString(),
        'notes' => null,
        'lines' => [[
            'product_id' => $this->product->id,
            'description' => 'Widget',
            'qty_ordered' => 5,
            'unit_cost' => 10.00,
            'tax_rate' => 10,
        ]],
    ];

    $this->actingAs(poAdminUser())
        ->post(route('purchase-orders.store'), $data)
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', ['supplier_id' => $this->supplier->id]);
});

it('store redirects to show after creating a purchase order', function () {
    $data = [
        'supplier_id' => $this->supplier->id,
        'expected_delivery_date' => now()->addDays(7)->toDateString(),
        'notes' => null,
        'lines' => [[
            'product_id' => $this->product->id,
            'description' => 'Widget',
            'qty_ordered' => 5,
            'unit_cost' => 10.00,
            'tax_rate' => 10,
        ]],
    ];

    $response = $this->actingAs(poAdminUser())
        ->post(route('purchase-orders.store'), $data);

    $po = PurchaseOrder::latest()->first();

    $response->assertRedirect(route('purchase-orders.show', $po));
});

it('store generates po_number matching PO-year-XXXX format', function () {
    $data = [
        'supplier_id' => $this->supplier->id,
        'expected_delivery_date' => null,
        'notes' => null,
        'lines' => [[
            'product_id' => $this->product->id,
            'description' => 'Widget',
            'qty_ordered' => 1,
            'unit_cost' => 5.00,
            'tax_rate' => 0,
        ]],
    ];

    $this->actingAs(poAdminUser())
        ->post(route('purchase-orders.store'), $data);

    $po = PurchaseOrder::latest()->first();

    expect($po->po_number)->toMatch('/^PO-\d{4}-\d{4}$/');
});

it('store fails validation when supplier_id is missing', function () {
    $data = [
        'expected_delivery_date' => null,
        'notes' => null,
        'lines' => [[
            'product_id' => $this->product->id,
            'description' => 'Widget',
            'qty_ordered' => 1,
            'unit_cost' => 5.00,
            'tax_rate' => 0,
        ]],
    ];

    $this->actingAs(poAdminUser())
        ->post(route('purchase-orders.store'), $data)
        ->assertSessionHasErrors('supplier_id');
});

it('store fails validation when lines array is empty', function () {
    $data = [
        'supplier_id' => $this->supplier->id,
        'expected_delivery_date' => null,
        'notes' => null,
        'lines' => [],
    ];

    $this->actingAs(poAdminUser())
        ->post(route('purchase-orders.store'), $data)
        ->assertSessionHasErrors('lines');
});

// ── Admin show ────────────────────────────────────────────────────────────────

it('admin can view the show page', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);

    $this->actingAs(poAdminUser())
        ->get(route('purchase-orders.show', $po))
        ->assertOk();
});

// ── Admin edit ────────────────────────────────────────────────────────────────

it('admin can view the edit form for a draft purchase order', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $this->actingAs(poAdminUser())
        ->get(route('purchase-orders.edit', $po))
        ->assertOk();
});

it('admin cannot edit an approved purchase order', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $this->actingAs(poAdminUser())
        ->get(route('purchase-orders.edit', $po))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

// ── Admin update ──────────────────────────────────────────────────────────────

it('admin can update a draft purchase order', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $newSupplier = Supplier::factory()->create();

    $data = [
        'supplier_id' => $newSupplier->id,
        'expected_delivery_date' => now()->addDays(14)->toDateString(),
        'notes' => 'Updated notes',
        'lines' => [[
            'product_id' => $this->product->id,
            'description' => 'Updated Widget',
            'qty_ordered' => 3,
            'unit_cost' => 20.00,
            'tax_rate' => 5,
            'qty_on_hand_snapshot' => 0,
        ]],
    ];

    $this->actingAs(poAdminUser())
        ->put(route('purchase-orders.update', $po), $data)
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'supplier_id' => $newSupplier->id,
    ]);
});

// ── Admin submit ──────────────────────────────────────────────────────────────

it('admin can submit a draft purchase order', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $this->actingAs(poAdminUser())
        ->post(route('purchase-orders.submit', $po));

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::PendingApproval->value,
    ]);
});

it('admin cannot submit an approved purchase order', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $this->actingAs(poAdminUser())
        ->post(route('purchase-orders.submit', $po))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

// ── Admin approve ─────────────────────────────────────────────────────────────

it('admin can approve a pending approval purchase order', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::PendingApproval,
    ]);

    $admin = poAdminUser();

    $this->actingAs($admin)
        ->post(route('purchase-orders.approve', $po));

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Approved->value,
        'approved_by' => $admin->id,
    ]);
});

it('admin cannot approve a draft purchase order', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $this->actingAs(poAdminUser())
        ->post(route('purchase-orders.approve', $po))
        ->assertRedirect()
        ->assertSessionHasErrors();
});

// ── Admin reject ──────────────────────────────────────────────────────────────

it('admin can reject a pending approval purchase order', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::PendingApproval,
    ]);

    $this->actingAs(poAdminUser())
        ->post(route('purchase-orders.reject', $po), ['rejection_reason' => 'Over budget']);

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Rejected->value,
    ]);
});

// ── Admin cancel ──────────────────────────────────────────────────────────────

it('admin can cancel an approved purchase order', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $this->actingAs(poAdminUser())
        ->post(route('purchase-orders.cancel', $po));

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Cancelled->value,
    ]);
});

// ── Admin destroy / restore ───────────────────────────────────────────────────

it('admin can soft delete a purchase order', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);

    $this->actingAs(poAdminUser())
        ->delete(route('purchase-orders.destroy', $po));

    $this->assertSoftDeleted('purchase_orders', ['id' => $po->id]);
});

it('admin can restore a soft-deleted purchase order', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);
    $po->delete();

    $this->actingAs(poAdminUser())
        ->post(route('purchase-orders.restore', $po));

    $this->assertNotSoftDeleted('purchase_orders', ['id' => $po->id]);
});

// ── Print ─────────────────────────────────────────────────────────────────────

it('admin can view the print page', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);

    $this->actingAs(poAdminUser())
        ->get(route('purchase-orders.print', $po))
        ->assertOk();
});

it('sales user can view the print page', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);

    $this->actingAs(poSalesUser())
        ->get(route('purchase-orders.print', $po))
        ->assertOk();
});

// ── Quality Check ─────────────────────────────────────────────────────────────

it('admin can pass quality check on a purchase order', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::QualityCheck,
    ]);

    $this->actingAs(poAdminUser())
        ->post(route('purchase-orders.qualityCheck', $po), ['qc_notes' => 'All items passed inspection'])
        ->assertRedirect(route('purchase-orders.show', $po));

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Received->value,
        'qc_notes' => 'All items passed inspection',
    ]);
});

it('admin can pass quality check without qc_notes', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::QualityCheck,
    ]);

    $this->actingAs(poAdminUser())
        ->post(route('purchase-orders.qualityCheck', $po), [])
        ->assertRedirect(route('purchase-orders.show', $po));

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Received->value,
    ]);
});

it('quality check returns success flash message', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::QualityCheck,
    ]);

    $this->actingAs(poAdminUser())
        ->post(route('purchase-orders.qualityCheck', $po), [])
        ->assertSessionHas('success', 'Quality check passed. Purchase order marked as received.');
});

it('manager can pass quality check', function () {
    $user = User::factory()->create();
    $user->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::PURCHASE_ORDERS_QUALITY_CHECK,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
    ]);

    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::QualityCheck,
    ]);

    $this->actingAs($user)
        ->post(route('purchase-orders.qualityCheck', $po), [])
        ->assertRedirect(route('purchase-orders.show', $po));
});

it('sales user cannot pass quality check', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::QualityCheck,
    ]);

    $this->actingAs(poSalesUser())
        ->post(route('purchase-orders.qualityCheck', $po), [])
        ->assertForbidden();
});

it('quality check fails when purchase order status is not quality_check', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $this->actingAs(poAdminUser())
        ->post(route('purchase-orders.qualityCheck', $po), [])
        ->assertRedirect(route('purchase-orders.show', $po))
        ->assertSessionHasErrors('error');
});

it('quality check fails when purchase order status is received', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);

    $this->actingAs(poAdminUser())
        ->post(route('purchase-orders.qualityCheck', $po), [])
        ->assertRedirect(route('purchase-orders.show', $po))
        ->assertSessionHasErrors('error');
});

it('quality check fails when purchase order status is partially_received', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::PartiallyReceived,
    ]);

    $this->actingAs(poAdminUser())
        ->post(route('purchase-orders.qualityCheck', $po), [])
        ->assertRedirect(route('purchase-orders.show', $po))
        ->assertSessionHasErrors('error');
});
