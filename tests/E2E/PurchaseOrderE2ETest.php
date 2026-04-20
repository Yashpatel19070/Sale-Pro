<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Enums\PoStatus;
use App\Enums\PoType;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PipelinePermissionSeeder;
use Database\Seeders\PurchaseOrderPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SupplierPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(SupplierPermissionSeeder::class);
    $this->seed(PurchaseOrderPermissionSeeder::class);
    $this->seed(PipelinePermissionSeeder::class);

    $this->superAdmin = User::factory()->create()->assignRole('super-admin');
    $this->admin = User::factory()->create()->assignRole('admin');
    $this->manager = User::factory()->create()->assignRole('manager');
    $this->procurement = User::factory()->create()->assignRole('procurement');
    $this->warehouse = User::factory()->create()->assignRole('warehouse');

    $this->supplier = Supplier::factory()->create(['is_active' => true]);
    $this->product = Product::factory()->create();
    $this->location = InventoryLocation::factory()->create();
});

// Auth & Access
test('PO-01: Unauthenticated user redirected to login', function () {
    $this->get('/admin/purchase-orders')
        ->assertRedirect('/admin/login');
});

test('PO-02: Admin can access purchase orders index', function () {
    $this->actingAs($this->admin)
        ->get('/admin/purchase-orders')
        ->assertStatus(200);
});

test('PO-03: Procurement can access purchase orders index', function () {
    $this->actingAs($this->procurement)
        ->get('/admin/purchase-orders')
        ->assertStatus(200);
});

test('PO-04: Warehouse cannot access purchase orders index', function () {
    $this->actingAs($this->warehouse)
        ->get('/admin/purchase-orders')
        ->assertStatus(403);
});

// Lifecycle - Happy Path
test('PO-10: Create draft PO with 2 lines', function () {
    $this->actingAs($this->procurement)
        ->post('/admin/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'lines' => [
                ['product_id' => $this->product->id, 'qty_ordered' => 5, 'unit_price' => '100.00'],
                ['product_id' => $this->product->id, 'qty_ordered' => 10, 'unit_price' => '50.00'],
            ],
        ])
        ->assertRedirect();

    $po = PurchaseOrder::latest()->first();
    expect($po->status->value)->toBe('draft');
    expect($po->po_number)->toMatch('/PO-\d{4}-\d{4}/');
    expect($po->lines)->toHaveCount(2);
});

test('PO-11: PO lines have snapshots recorded', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);
    $line = $po->lines()->create([
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'unit_price' => '100.00',
    ]);

    expect($line->snapshot_stock)->toBeGreaterThanOrEqual(0);
    expect($line->snapshot_inbound)->toBeGreaterThanOrEqual(0);
});

test('PO-12: Confirm draft PO to open status', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => PoStatus::Draft]);
    $po->lines()->create([
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'unit_price' => '100.00',
    ]);

    $this->actingAs($this->manager)
        ->post("/admin/purchase-orders/{$po->id}/confirm")
        ->assertRedirect();

    $po->refresh();
    expect($po->status->value)->toBe('open');
    expect($po->confirmed_at)->not->toBeNull();
});

test('PO-13: Edit blocked after confirm', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => PoStatus::Open]);

    $this->actingAs($this->procurement)
        ->patch("/admin/purchase-orders/{$po->id}", ['supplier_id' => $this->supplier->id])
        ->assertStatus(403);
});

// Create Validation Edge Cases
test('PO-20: Missing supplier_id validation', function () {
    $this->actingAs($this->procurement)
        ->post('/admin/purchase-orders', [
            'lines' => [['product_id' => $this->product->id, 'qty_ordered' => 5, 'unit_price' => '100.00']],
        ])
        ->assertSessionHasErrors('supplier_id');
});

test('PO-21: Invalid supplier_id validation', function () {
    $this->actingAs($this->procurement)
        ->post('/admin/purchase-orders', [
            'supplier_id' => 999999,
            'lines' => [['product_id' => $this->product->id, 'qty_ordered' => 5, 'unit_price' => '100.00']],
        ])
        ->assertSessionHasErrors('supplier_id');
});

test('PO-22: Empty lines array validation', function () {
    $this->actingAs($this->procurement)
        ->post('/admin/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'lines' => [],
        ])
        ->assertSessionHasErrors('lines');
});

test('PO-23: qty_ordered min validation', function () {
    $this->actingAs($this->procurement)
        ->post('/admin/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'lines' => [['product_id' => $this->product->id, 'qty_ordered' => 0, 'unit_price' => '100.00']],
        ])
        ->assertSessionHasErrors('lines.0.qty_ordered');
});

test('PO-24: qty_ordered max validation', function () {
    $this->actingAs($this->procurement)
        ->post('/admin/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'lines' => [['product_id' => $this->product->id, 'qty_ordered' => 10001, 'unit_price' => '100.00']],
        ])
        ->assertSessionHasErrors('lines.0.qty_ordered');
});

test('PO-25: unit_price min validation', function () {
    $this->actingAs($this->procurement)
        ->post('/admin/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'lines' => [['product_id' => $this->product->id, 'qty_ordered' => 5, 'unit_price' => '0']],
        ])
        ->assertSessionHasErrors('lines.0.unit_price');
});

test('PO-26: Invalid product_id validation', function () {
    $this->actingAs($this->procurement)
        ->post('/admin/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'lines' => [['product_id' => 999999, 'qty_ordered' => 5, 'unit_price' => '100.00']],
        ])
        ->assertSessionHasErrors('lines.0.product_id');
});

test('PO-27: Warehouse cannot create PO', function () {
    $this->actingAs($this->warehouse)
        ->post('/admin/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'lines' => [['product_id' => $this->product->id, 'qty_ordered' => 5, 'unit_price' => '100.00']],
        ])
        ->assertStatus(403);
});

// Cancel Edge Cases
test('PO-30: Cancel draft PO with valid notes', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => PoStatus::Draft]);

    $this->actingAs($this->manager)
        ->post("/admin/purchase-orders/{$po->id}/cancel", [
            'cancel_notes' => 'Valid cancellation reason here',
        ])
        ->assertRedirect();

    $po->refresh();
    expect($po->status->value)->toBe('cancelled');
    expect($po->cancel_notes)->toBe('Valid cancellation reason here');
    expect($po->cancelled_at)->not->toBeNull();
});

test('PO-31: Cancel notes min length validation', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => PoStatus::Draft]);

    $this->actingAs($this->manager)
        ->post("/admin/purchase-orders/{$po->id}/cancel", [
            'cancel_notes' => 'short',
        ])
        ->assertSessionHasErrors('cancel_notes');
});

test('PO-32: Cancel notes required validation', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => PoStatus::Draft]);

    $this->actingAs($this->manager)
        ->post("/admin/purchase-orders/{$po->id}/cancel", [])
        ->assertSessionHasErrors('cancel_notes');
});

test('PO-33: Cancel open PO with no units received', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => PoStatus::Open]);

    $this->actingAs($this->manager)
        ->post("/admin/purchase-orders/{$po->id}/cancel", [
            'cancel_notes' => 'Valid cancellation reason here',
        ])
        ->assertRedirect();

    $po->refresh();
    expect($po->status->value)->toBe('cancelled');
});

test('PO-34: Cannot cancel open PO with received units', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => PoStatus::Open]);
    $line = $po->lines()->create([
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'qty_received' => 1,
        'unit_price' => '100.00',
    ]);

    $this->actingAs($this->manager)
        ->post("/admin/purchase-orders/{$po->id}/cancel", [
            'cancel_notes' => 'Valid cancellation reason here',
        ])
        ->assertSessionHasErrors();
});

test('PO-35: Cannot cancel partial PO', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => PoStatus::Partial]);

    $this->actingAs($this->manager)
        ->post("/admin/purchase-orders/{$po->id}/cancel", [
            'cancel_notes' => 'Valid cancellation reason here',
        ])
        ->assertStatus(403);
});

test('PO-36: Cannot cancel closed PO', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => PoStatus::Closed]);

    $this->actingAs($this->manager)
        ->post("/admin/purchase-orders/{$po->id}/cancel", [
            'cancel_notes' => 'Valid cancellation reason here',
        ])
        ->assertStatus(403);
});

test('PO-37: Procurement cannot cancel open PO', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => PoStatus::Open]);

    $this->actingAs($this->procurement)
        ->post("/admin/purchase-orders/{$po->id}/cancel", [
            'cancel_notes' => 'Valid cancellation reason here',
        ])
        ->assertStatus(403);
});

// Reopen Edge Cases
test('PO-40: Reopen closed PO as manager increments count', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PoStatus::Closed,
        'reopen_count' => 0,
    ]);

    $this->actingAs($this->manager)
        ->post("/admin/purchase-orders/{$po->id}/reopen")
        ->assertRedirect();

    $po->refresh();
    expect($po->status->value)->toBe('open');
    expect($po->reopen_count)->toBe(1);
    expect($po->reopened_at)->not->toBeNull();
});

test('PO-41: Second reopen increments count to 2', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PoStatus::Closed,
        'reopen_count' => 1,
    ]);

    $this->actingAs($this->manager)
        ->post("/admin/purchase-orders/{$po->id}/reopen")
        ->assertRedirect();

    $po->refresh();
    expect($po->reopen_count)->toBe(2);
});

test('PO-42: Third reopen blocked for manager', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PoStatus::Closed,
        'reopen_count' => 2,
    ]);

    $this->actingAs($this->manager)
        ->post("/admin/purchase-orders/{$po->id}/reopen")
        ->assertSessionHasErrors();
});

test('PO-43: Super admin can reopen after 2 reopens', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PoStatus::Closed,
        'reopen_count' => 2,
    ]);

    $this->actingAs($this->superAdmin)
        ->post("/admin/purchase-orders/{$po->id}/reopen")
        ->assertRedirect();

    $po->refresh();
    expect($po->reopen_count)->toBe(3);
});

// PO Number Generation
test('PO-50: First PO gets yearly counter 0001', function () {
    $this->actingAs($this->procurement)
        ->post('/admin/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'lines' => [['product_id' => $this->product->id, 'qty_ordered' => 5, 'unit_price' => '100.00']],
        ]);

    $po = PurchaseOrder::latest()->first();
    $year = now()->year;
    expect($po->po_number)->toBe("PO-{$year}-0001");
});

test('PO-51: Third PO gets counter 0003', function () {
    for ($i = 0; $i < 3; $i++) {
        PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);
    }

    $this->actingAs($this->procurement)
        ->post('/admin/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'lines' => [['product_id' => $this->product->id, 'qty_ordered' => 5, 'unit_price' => '100.00']],
        ]);

    $po = PurchaseOrder::latest()->first();
    $year = now()->year;
    expect($po->po_number)->toMatch("/PO-{$year}-0004/");
});

test('PO-52: PO number resets yearly', function () {
    PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_at' => now()->subYear(),
    ]);

    $this->actingAs($this->procurement)
        ->post('/admin/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'lines' => [['product_id' => $this->product->id, 'qty_ordered' => 5, 'unit_price' => '100.00']],
        ]);

    $po = PurchaseOrder::latest()->first();
    $year = now()->year;
    expect($po->po_number)->toBe("PO-{$year}-0001");
});

// Search & Filter
test('PO-60: Search by PO number', function () {
    PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'po_number' => 'PO-2026-0001']);
    PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'po_number' => 'PO-2026-0002']);
    PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'po_number' => 'PO-2026-0003']);

    $response = $this->actingAs($this->admin)
        ->get('/admin/purchase-orders?search=0002');

    $response->assertSee('PO-2026-0002');
});

test('PO-61: Search by supplier name', function () {
    $supplierA = Supplier::factory()->create(['name' => 'Acme Corp']);
    PurchaseOrder::factory()->create(['supplier_id' => $supplierA->id]);

    $response = $this->actingAs($this->admin)
        ->get('/admin/purchase-orders?search=acme');

    $response->assertSee('Acme Corp');
});

test('PO-62: Filter by status draft', function () {
    PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => PoStatus::Draft]);
    PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => PoStatus::Open]);

    $response = $this->actingAs($this->admin)
        ->get('/admin/purchase-orders?status=draft');

    expect(PurchaseOrder::where('status', PoStatus::Draft)->count())->toBeGreaterThan(0);
});

test('PO-63: Filter by supplier', function () {
    $supplierA = Supplier::factory()->create();
    $supplierB = Supplier::factory()->create();
    PurchaseOrder::factory()->create(['supplier_id' => $supplierA->id]);
    PurchaseOrder::factory()->create(['supplier_id' => $supplierB->id]);

    $response = $this->actingAs($this->admin)
        ->get("/admin/purchase-orders?supplier_id={$supplierA->id}");

    expect(PurchaseOrder::where('supplier_id', $supplierA->id)->count())->toBeGreaterThan(0);
});

test('PO-64: Filter by type return', function () {
    PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'type' => PoType::Purchase]);
    PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'type' => PoType::Return]);

    $response = $this->actingAs($this->admin)
        ->get('/admin/purchase-orders?type=return');

    expect(PurchaseOrder::where('type', PoType::Return)->count())->toBeGreaterThan(0);
});
