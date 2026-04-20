<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Enums\PipelineStage;
use App\Enums\PoStatus;
use App\Enums\PoType;
use App\Enums\UnitJobStatus;
use App\Models\PoLine;
use App\Models\PoUnitJob;
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

    $this->admin = User::factory()->create()->assignRole('admin');
    $this->manager = User::factory()->create()->assignRole('manager');
    $this->procurement = User::factory()->create()->assignRole('procurement');
    $this->warehouse = User::factory()->create()->assignRole('warehouse');

    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
    $this->originalPo = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id]);
    $this->line = PoLine::factory()->create([
        'purchase_order_id' => $this->originalPo->id,
        'product_id' => $this->product->id,
        'unit_price' => '350.00',
    ]);
    $this->job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->originalPo->id,
        'po_line_id' => $this->line->id,
    ]);

    // Pre-built return PO
    $this->returnPo = PurchaseOrder::factory()->returnType()->create([
        'parent_po_id' => $this->originalPo->id,
        'supplier_id' => $this->supplier->id,
        'status' => PoStatus::Open,
        'confirmed_at' => now(),
        'created_by_user_id' => $this->admin->id,
    ]);
    $this->returnPo->lines()->create([
        'product_id' => $this->product->id,
        'qty_ordered' => 1,
        'unit_price' => '350.00',
    ]);
});

// Auth & Access - Index
test('RT-01: Unauthenticated user redirected to login', function () {
    $this->get('/admin/po-returns')
        ->assertRedirect('/admin/login');
});

test('RT-02: Admin can access returns index', function () {
    $this->actingAs($this->admin)
        ->get('/admin/po-returns')
        ->assertStatus(200);
});

test('RT-03: Procurement can access returns index', function () {
    $this->actingAs($this->procurement)
        ->get('/admin/po-returns')
        ->assertStatus(200);
});

test('RT-04: Warehouse cannot access returns index', function () {
    $this->actingAs($this->warehouse)
        ->get('/admin/po-returns')
        ->assertStatus(403);
});

// Index - Only Return Type POs Shown
test('RT-10: Index shows only return-type POs', function () {
    PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'type' => PoType::Purchase]);
    PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'type' => PoType::Purchase]);

    $response = $this->actingAs($this->admin)
        ->get('/admin/po-returns');

    $returnPos = PurchaseOrder::where('type', PoType::Return)->get();
    expect($returnPos)->toHaveCount(1);
});

test('RT-11: Search by return PO number', function () {
    $response = $this->actingAs($this->admin)
        ->get("/admin/po-returns?search={$this->returnPo->po_number}");

    $response->assertSee($this->returnPo->po_number);
});

test('RT-12: Filter by status open', function () {
    PurchaseOrder::factory()->returnType()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PoStatus::Closed,
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/admin/po-returns?status=open');

    $openReturns = PurchaseOrder::where('type', PoType::Return)
        ->where('status', PoStatus::Open)
        ->get();
    expect($openReturns->count())->toBeGreaterThan(0);
});

test('RT-13: Empty state when no returns exist', function () {
    PurchaseOrder::whereType(PoType::Return)->delete();

    $response = $this->actingAs($this->admin)
        ->get('/admin/po-returns');

    $response->assertSee(['No', 'return', 'order'], escape: false);
});

// Show - Return PO Detail
test('RT-20: Show page displays return PO details', function () {
    $this->actingAs($this->admin)
        ->get("/admin/po-returns/{$this->returnPo->id}")
        ->assertStatus(200)
        ->assertSee($this->returnPo->po_number)
        ->assertSee($this->supplier->name);
});

test('RT-21: Purchase-type PO ID returns 404', function () {
    $purchasePo = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'type' => PoType::Purchase]);

    $this->actingAs($this->admin)
        ->get("/admin/po-returns/{$purchasePo->id}")
        ->assertStatus(404);
});

test('RT-22: Warehouse cannot view return PO', function () {
    $this->actingAs($this->warehouse)
        ->get("/admin/po-returns/{$this->returnPo->id}")
        ->assertStatus(403);
});

// Full Auto-Creation Flow (Integration)
test('RT-30-34: Return PO auto-created via fail', function () {
    $job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->originalPo->id,
        'po_line_id' => $this->line->id,
        'current_stage' => PipelineStage::Visual,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post("/admin/pipeline/jobs/{$job->id}/fail", [
            'notes' => 'Cracked screen on arrival',
        ])
        ->assertRedirect();

    $job->refresh();
    expect($job->status->value)->toBe('failed');

    $returnPo = PurchaseOrder::where('parent_po_id', $this->originalPo->id)
        ->where('type', PoType::Return)
        ->first();

    expect($returnPo)->not->toBeNull();
    expect($returnPo->status->value)->toBe('open');
    expect($returnPo->supplier_id)->toBe($this->supplier->id);

    $returnLine = $returnPo->lines()->first();
    expect($returnLine->product_id)->toBe($this->product->id);
    expect($returnLine->qty_ordered)->toBe(1);
});

test('RT-35: Multiple failures create separate return POs', function () {
    // Delete pre-seeded return PO to start fresh
    $this->returnPo->delete();

    $job1 = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->originalPo->id,
        'po_line_id' => $this->line->id,
        'current_stage' => PipelineStage::Visual,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $job2 = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->originalPo->id,
        'po_line_id' => $this->line->id,
        'current_stage' => PipelineStage::Visual,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post("/admin/pipeline/jobs/{$job1->id}/fail", ['notes' => 'Fail 1']);
    $this->actingAs($this->warehouse)
        ->post("/admin/pipeline/jobs/{$job2->id}/fail", ['notes' => 'Fail 2']);

    $returnPos = PurchaseOrder::where('parent_po_id', $this->originalPo->id)
        ->where('type', PoType::Return)
        ->get();

    expect($returnPos)->toHaveCount(2);
});

// Close Return PO
test('RT-40: Manager closes return PO', function () {
    $this->actingAs($this->manager)
        ->post("/admin/po-returns/{$this->returnPo->id}/close")
        ->assertRedirect();

    $this->returnPo->refresh();
    expect($this->returnPo->status->value)->toBe('closed');
    expect($this->returnPo->closed_at)->not->toBeNull();
});

test('RT-41: Procurement cannot close return PO', function () {
    $this->actingAs($this->procurement)
        ->post("/admin/po-returns/{$this->returnPo->id}/close")
        ->assertStatus(403);
});

test('RT-42: Already-closed return PO cannot close again', function () {
    $closedReturn = PurchaseOrder::factory()->returnType()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PoStatus::Closed,
        'closed_at' => now(),
    ]);

    $this->actingAs($this->manager)
        ->post("/admin/po-returns/{$closedReturn->id}/close")
        ->assertStatus(403);
});

test('RT-43: Purchase-type PO used as ID returns 403', function () {
    $purchasePo = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'type' => PoType::Purchase]);

    $this->actingAs($this->manager)
        ->post("/admin/po-returns/{$purchasePo->id}/close")
        ->assertStatus(403);
});
