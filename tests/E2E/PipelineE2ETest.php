<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Enums\PipelineStage;
use App\Enums\UnitJobStatus;
use App\Models\InventoryLocation;
use App\Models\InventorySerial;
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
    $this->procurement = User::factory()->create()->assignRole('procurement');
    $this->warehouse = User::factory()->create()->assignRole('warehouse');
    $this->tech = User::factory()->create()->assignRole('tech');
    $this->qa = User::factory()->create()->assignRole('qa');

    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
    $this->location = InventoryLocation::factory()->create();
    $this->po = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id]);
    $this->line = PoLine::factory()->create([
        'purchase_order_id' => $this->po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 3,
    ]);
});

// Auth & Queue Access
test('PL-01: Unauthenticated user redirected to login', function () {
    $this->get('/admin/pipeline')
        ->assertRedirect('/admin/login');
});

test('PL-02: Admin sees all pending jobs', function () {
    $this->actingAs($this->admin)
        ->get('/admin/pipeline')
        ->assertStatus(200);
});

test('PL-03: Warehouse sees visual/serial_assign/shelf jobs', function () {
    $this->actingAs($this->warehouse)
        ->get('/admin/pipeline')
        ->assertStatus(200);
});

test('PL-04: Tech sees tech-stage jobs', function () {
    $this->actingAs($this->tech)
        ->get('/admin/pipeline')
        ->assertStatus(200);
});

test('PL-05: QA sees qa-stage jobs', function () {
    $this->actingAs($this->qa)
        ->get('/admin/pipeline')
        ->assertStatus(200);
});

test('PL-06: Procurement sees empty queue', function () {
    $this->actingAs($this->procurement)
        ->get('/admin/pipeline')
        ->assertStatus(200);
});

// Full Happy Path - No Skip Flags
test('PL-10: Create receive job at visual stage', function () {
    $this->actingAs($this->procurement)
        ->post('/admin/pipeline/jobs', [
            'po_line_id' => $this->line->id,
        ])
        ->assertRedirect();

    $job = PoUnitJob::latest()->first();
    expect($job->current_stage->value)->toBe(PipelineStage::Visual->value);
    expect($job->status->value)->toBe('pending');

    $this->line->refresh();
    expect($this->line->qty_received)->toBe(1);

    $this->po->refresh();
    expect($this->po->status->value)->toBe('partial');
});

test('PL-11: Claim visual job', function () {
    $job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'current_stage' => PipelineStage::Visual,
        'status' => UnitJobStatus::Pending,
    ]);

    $this->actingAs($this->warehouse)
        ->post("/admin/pipeline/jobs/{$job->id}/start")
        ->assertRedirect();

    $job->refresh();
    expect($job->status->value)->toBe('in_progress');
    expect($job->assigned_to_user_id)->toBe($this->warehouse->id);
});

test('PL-12: Pass visual stage to serial_assign', function () {
    $job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'current_stage' => PipelineStage::Visual,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post("/admin/pipeline/jobs/{$job->id}/pass")
        ->assertRedirect();

    $job->refresh();
    expect($job->current_stage->value)->toBe(PipelineStage::SerialAssign->value);
    expect($job->status->value)->toBe('pending');
    expect($job->assigned_to_user_id)->toBeNull();
});

test('PL-14: Pass serial_assign with serial number', function () {
    $job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'current_stage' => PipelineStage::SerialAssign,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post("/admin/pipeline/jobs/{$job->id}/pass", [
            'serial_number' => 'SN-ABC-001',
        ])
        ->assertRedirect();

    $job->refresh();
    expect($job->current_stage->value)->toBe(PipelineStage::Tech->value);
    expect($job->pending_serial_number)->toBe('SN-ABC-001');
});

test('PL-20: Pass shelf stage with inventory location', function () {
    $job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'current_stage' => PipelineStage::Shelf,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
        'pending_serial_number' => 'SN-ABC-001',
    ]);

    $this->actingAs($this->warehouse)
        ->post("/admin/pipeline/jobs/{$job->id}/pass", [
            'inventory_location_id' => $this->location->id,
        ])
        ->assertRedirect();

    $job->refresh();
    expect($job->status->value)->toBe('passed');
    expect($job->inventory_serial_id)->not->toBeNull();

    $serial = InventorySerial::where('serial_number', 'SN-ABC-001')->first();
    expect($serial)->not->toBeNull();
});

// Skip Flags
test('PL-30-32: Skip tech stage path', function () {
    $po = PurchaseOrder::factory()->open()->create([
        'supplier_id' => $this->supplier->id,
        'skip_tech' => true,
    ]);
    $line = PoLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
    ]);

    $this->actingAs($this->procurement)
        ->post('/admin/pipeline/jobs', ['po_line_id' => $line->id]);

    $job = PoUnitJob::latest()->first();

    // Pass visual
    $this->actingAs($this->warehouse)->post("/admin/pipeline/jobs/{$job->id}/start");
    $this->actingAs($this->warehouse)->post("/admin/pipeline/jobs/{$job->id}/pass");

    // At serial_assign, pass directly to qa (tech skipped)
    $job->refresh();
    $this->actingAs($this->warehouse)->post("/admin/pipeline/jobs/{$job->id}/start");
    $this->actingAs($this->warehouse)->post("/admin/pipeline/jobs/{$job->id}/pass", [
        'serial_number' => 'SN-SKIP-001',
    ]);

    $job->refresh();
    expect($job->current_stage->value)->toBe(PipelineStage::Qa->value);
});

test('PL-35: Skip QA stage path', function () {
    $po = PurchaseOrder::factory()->open()->create([
        'supplier_id' => $this->supplier->id,
        'skip_qa' => true,
    ]);
    $line = PoLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
    ]);

    $job = PoUnitJob::factory()->create([
        'purchase_order_id' => $po->id,
        'po_line_id' => $line->id,
        'current_stage' => PipelineStage::Tech,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->tech->id,
    ]);

    $this->actingAs($this->tech)
        ->post("/admin/pipeline/jobs/{$job->id}/pass")
        ->assertRedirect();

    $job->refresh();
    expect($job->current_stage->value)->toBe(PipelineStage::Shelf->value);
});

// Fail Flow
test('PL-40: Fail job creates return PO', function () {
    $job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'current_stage' => PipelineStage::Tech,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->tech->id,
    ]);

    $this->actingAs($this->tech)
        ->post("/admin/pipeline/jobs/{$job->id}/fail", [
            'notes' => 'Broken screen',
        ])
        ->assertRedirect();

    $job->refresh();
    expect($job->status->value)->toBe('failed');

    $returnPo = PurchaseOrder::where('parent_po_id', $this->po->id)->first();
    expect($returnPo)->not->toBeNull();
});

// Serial Number - Race Condition Guard
test('PL-50: Duplicate serial in inventory rejects', function () {
    InventorySerial::factory()->create([
        'serial_number' => 'DUP-001',
        'product_id' => $this->product->id,
    ]);

    $job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'current_stage' => PipelineStage::SerialAssign,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post("/admin/pipeline/jobs/{$job->id}/pass", [
            'serial_number' => 'DUP-001',
        ])
        ->assertSessionHasErrors('serial_number');
});

test('PL-51: Duplicate pending serial rejects', function () {
    $job1 = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'pending_serial_number' => 'DUP-002',
    ]);

    $job2 = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'current_stage' => PipelineStage::SerialAssign,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post("/admin/pipeline/jobs/{$job2->id}/pass", [
            'serial_number' => 'DUP-002',
        ])
        ->assertSessionHasErrors('serial_number');
});

// Authorization Edge Cases
test('PL-60: QA cannot claim visual-stage job', function () {
    $job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'current_stage' => PipelineStage::Visual,
        'status' => UnitJobStatus::Pending,
    ]);

    $this->actingAs($this->qa)
        ->post("/admin/pipeline/jobs/{$job->id}/start")
        ->assertStatus(403);
});

test('PL-61: Tech cannot pass QA-stage job', function () {
    $job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'current_stage' => PipelineStage::Qa,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->qa->id,
    ]);

    $this->actingAs($this->tech)
        ->post("/admin/pipeline/jobs/{$job->id}/pass")
        ->assertStatus(403);
});

test('PL-62: Admin cannot claim stage job', function () {
    $job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'current_stage' => PipelineStage::Tech,
        'status' => UnitJobStatus::Pending,
    ]);

    $this->actingAs($this->admin)
        ->post("/admin/pipeline/jobs/{$job->id}/start")
        ->assertStatus(403);
});

test('PL-63: Different worker cannot pass claimed job', function () {
    $warehouse2 = User::factory()->create()->assignRole('warehouse');

    $job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'current_stage' => PipelineStage::Visual,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($warehouse2)
        ->post("/admin/pipeline/jobs/{$job->id}/pass")
        ->assertStatus(403);
});

test('PL-64: Unclaimed job cannot be failed', function () {
    $job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'current_stage' => PipelineStage::Visual,
        'status' => UnitJobStatus::Pending,
    ]);

    $this->actingAs($this->warehouse)
        ->post("/admin/pipeline/jobs/{$job->id}/fail", [
            'notes' => 'Broken',
        ])
        ->assertStatus(403);
});

// Fail Validation
test('PL-70: Fail requires notes', function () {
    $job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'current_stage' => PipelineStage::Visual,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post("/admin/pipeline/jobs/{$job->id}/fail", [])
        ->assertSessionHasErrors('notes');
});

test('PL-71: Empty notes validation', function () {
    $job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'current_stage' => PipelineStage::Visual,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post("/admin/pipeline/jobs/{$job->id}/fail", [
            'notes' => '',
        ])
        ->assertSessionHasErrors('notes');
});
