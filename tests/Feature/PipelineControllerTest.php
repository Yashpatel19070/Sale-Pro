<?php

declare(strict_types=1);

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
use Database\Seeders\SupplierPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        SupplierPermissionSeeder::class,
        PurchaseOrderPermissionSeeder::class,
        PipelinePermissionSeeder::class,
    ]);

    $this->admin = User::factory()->create()->assignRole('admin');
    $this->procurement = User::factory()->create()->assignRole('procurement');
    $this->warehouse = User::factory()->create()->assignRole('warehouse');
    $this->tech = User::factory()->create()->assignRole('tech');
    $this->qa = User::factory()->create()->assignRole('qa');

    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
    $this->po = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id]);
    $this->line = PoLine::factory()->create([
        'purchase_order_id' => $this->po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
    ]);
});

// ── Queue ─────────────────────────────────────────────────────────────────────

test('queue returns 200 for warehouse', function () {
    $this->actingAs($this->warehouse)->get('/admin/pipeline')->assertOk();
});

test('queue returns 200 for tech', function () {
    $this->actingAs($this->tech)->get('/admin/pipeline')->assertOk();
});

test('queue returns 200 for qa', function () {
    $this->actingAs($this->qa)->get('/admin/pipeline')->assertOk();
});

test('queue returns 200 for admin showing all stages', function () {
    $this->actingAs($this->admin)->get('/admin/pipeline')->assertOk();
});

test('queue only shows pending jobs', function () {
    $pending = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Pending,
    ]);
    $claimed = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
    ]);

    $response = $this->actingAs($this->admin)->get('/admin/pipeline')->assertOk();
    $response->assertSee('/admin/pipeline/jobs/'.$pending->id);
    $response->assertDontSee('/admin/pipeline/jobs/'.$claimed->id);
});

test('queue filters by purchase_order_id', function () {
    $otherPo = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id]);
    $otherLine = PoLine::factory()->create(['purchase_order_id' => $otherPo->id, 'product_id' => $this->product->id]);
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $otherPo->id,
        'po_line_id' => $otherLine->id,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/pipeline?purchase_order_id='.$otherPo->id)
        ->assertOk()
        ->assertSee($job->id);
});

// ── Show ──────────────────────────────────────────────────────────────────────

test('show returns 200 for admin', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
    ]);

    $this->actingAs($this->admin)->get('/admin/pipeline/jobs/'.$job->id)->assertOk();
});

test('show returns 200 for warehouse on visual-stage job', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
    ]);

    $this->actingAs($this->warehouse)->get('/admin/pipeline/jobs/'.$job->id)->assertOk();
});

test('show returns 200 for tech — has pipeline.viewAny', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
    ]);

    $this->actingAs($this->tech)->get('/admin/pipeline/jobs/'.$job->id)->assertOk();
});

test('show returns 403 for sales — no pipeline permissions', function () {
    Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
    $sales = User::factory()->create()->assignRole('sales');
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
    ]);

    $this->actingAs($sales)->get('/admin/pipeline/jobs/'.$job->id)->assertForbidden();
});

// ── Start ─────────────────────────────────────────────────────────────────────

test('start sets job status to in_progress', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Pending,
    ]);

    $this->actingAs($this->warehouse)
        ->post('/admin/pipeline/jobs/'.$job->id.'/start')
        ->assertRedirect('/admin/pipeline/jobs/'.$job->id);

    expect($job->fresh()->status)->toBe(UnitJobStatus::InProgress);
});

test('start assigns authenticated user to job', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Pending,
    ]);

    $this->actingAs($this->warehouse)
        ->post('/admin/pipeline/jobs/'.$job->id.'/start');

    expect($job->fresh()->assigned_to_user_id)->toBe($this->warehouse->id);
});

test('start writes start event to po_unit_events', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Pending,
    ]);

    $this->actingAs($this->warehouse)->post('/admin/pipeline/jobs/'.$job->id.'/start');

    $this->assertDatabaseHas('po_unit_events', [
        'po_unit_job_id' => $job->id,
        'action' => 'start',
        'user_id' => $this->warehouse->id,
    ]);
});

test('start returns error when job is already in_progress', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $other = User::factory()->create()->assignRole('warehouse');

    $this->actingAs($other)
        ->post('/admin/pipeline/jobs/'.$job->id.'/start')
        ->assertForbidden();
});

test('start returns 403 for wrong-stage user', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Pending,
    ]);

    $this->actingAs($this->qa)
        ->post('/admin/pipeline/jobs/'.$job->id.'/start')
        ->assertForbidden();
});

test('start returns 403 for admin — has viewAny but not stage permissions', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Pending,
    ]);

    $this->actingAs($this->admin)
        ->post('/admin/pipeline/jobs/'.$job->id.'/start')
        ->assertForbidden();
});

// ── Store (createJob) ─────────────────────────────────────────────────────────

test('store creates unit job for open PO line', function () {
    $this->actingAs($this->procurement)
        ->post('/admin/pipeline/jobs', ['po_line_id' => $this->line->id])
        ->assertRedirect();

    $this->assertDatabaseHas('po_unit_jobs', ['po_line_id' => $this->line->id]);
});

test('store writes receive event', function () {
    $this->actingAs($this->procurement)
        ->post('/admin/pipeline/jobs', ['po_line_id' => $this->line->id]);

    $job = PoUnitJob::where('po_line_id', $this->line->id)->first();

    $this->assertDatabaseHas('po_unit_events', [
        'po_unit_job_id' => $job->id,
        'stage' => 'receive',
        'action' => 'pass',
    ]);
});

test('store increments qty_received on PO line', function () {
    $this->actingAs($this->procurement)
        ->post('/admin/pipeline/jobs', ['po_line_id' => $this->line->id]);

    expect($this->line->fresh()->qty_received)->toBe(1);
});

test('store advances job to visual stage', function () {
    $this->actingAs($this->procurement)
        ->post('/admin/pipeline/jobs', ['po_line_id' => $this->line->id]);

    $job = PoUnitJob::where('po_line_id', $this->line->id)->first();
    expect($job->current_stage)->toBe(PipelineStage::Visual);
});

test('store returns error when PO is not open', function () {
    $cancelledPo = PurchaseOrder::factory()->cancelled()->create(['supplier_id' => $this->supplier->id]);
    $cancelledLine = PoLine::factory()->create(['purchase_order_id' => $cancelledPo->id, 'product_id' => $this->product->id]);

    $this->actingAs($this->procurement)
        ->post('/admin/pipeline/jobs', ['po_line_id' => $cancelledLine->id])
        ->assertRedirect()
        ->assertSessionHasErrors('po_line_id');
});

test('store returns error when line is fulfilled', function () {
    $fulfilledLine = PoLine::factory()->create([
        'purchase_order_id' => $this->po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 1,
        'qty_received' => 1,
    ]);

    $this->actingAs($this->procurement)
        ->post('/admin/pipeline/jobs', ['po_line_id' => $fulfilledLine->id])
        ->assertRedirect()
        ->assertSessionHasErrors('job');
});

test('store returns 403 for warehouse — not procurement', function () {
    $this->actingAs($this->warehouse)
        ->post('/admin/pipeline/jobs', ['po_line_id' => $this->line->id])
        ->assertForbidden();
});

// ── Pass ──────────────────────────────────────────────────────────────────────

test('pass at visual stage advances to serial_assign', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post('/admin/pipeline/jobs/'.$job->id.'/pass')
        ->assertRedirect(route('pipeline.queue'));

    expect($job->fresh()->current_stage)->toBe(PipelineStage::SerialAssign);
});

test('pass at serial_assign requires serial_number', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::SerialAssign)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post('/admin/pipeline/jobs/'.$job->id.'/pass', [])
        ->assertSessionHasErrors('serial_number');
});

test('pass at serial_assign rejects serial already in inventory_serials', function () {
    $location = InventoryLocation::factory()->create();
    $existing = InventorySerial::factory()->create([
        'serial_number' => 'DUPE123',
        'product_id' => $this->product->id,
        'inventory_location_id' => $location->id,
    ]);

    $job = PoUnitJob::factory()->atStage(PipelineStage::SerialAssign)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post('/admin/pipeline/jobs/'.$job->id.'/pass', ['serial_number' => 'DUPE123'])
        ->assertSessionHasErrors('serial_number');
});

test('pass at serial_assign advances to tech', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::SerialAssign)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post('/admin/pipeline/jobs/'.$job->id.'/pass', ['serial_number' => 'SN-NEWONE']);

    expect($job->fresh()->current_stage)->toBe(PipelineStage::Tech);
});

test('pass at tech advances to qa', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Tech)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->tech->id,
    ]);

    $this->actingAs($this->tech)
        ->post('/admin/pipeline/jobs/'.$job->id.'/pass');

    expect($job->fresh()->current_stage)->toBe(PipelineStage::Qa);
});

test('pass at qa advances to shelf', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Qa)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->qa->id,
    ]);

    $this->actingAs($this->qa)
        ->post('/admin/pipeline/jobs/'.$job->id.'/pass');

    expect($job->fresh()->current_stage)->toBe(PipelineStage::Shelf);
});

test('pass at shelf creates InventorySerial and marks job passed', function () {
    $location = InventoryLocation::factory()->create();
    $job = PoUnitJob::factory()->atStage(PipelineStage::Shelf)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
        'pending_serial_number' => 'SN-SHELF01',
    ]);

    $this->actingAs($this->warehouse)
        ->post('/admin/pipeline/jobs/'.$job->id.'/pass', [
            'inventory_location_id' => $location->id,
        ]);

    $this->assertDatabaseHas('inventory_serials', ['serial_number' => 'SN-SHELF01']);
    expect($job->fresh()->status)->toBe(UnitJobStatus::Passed);
    expect($job->fresh()->inventory_serial_id)->not->toBeNull();
});

test('pass returns 403 for wrong-stage user', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->qa)
        ->post('/admin/pipeline/jobs/'.$job->id.'/pass')
        ->assertForbidden();
});

// ── Pass with Skip Flags ──────────────────────────────────────────────────────

test('pass skips tech stage when skip_tech=true on PO', function () {
    $po = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id, 'skip_tech' => true]);
    $line = PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id]);
    $job = PoUnitJob::factory()->atStage(PipelineStage::SerialAssign)->create([
        'purchase_order_id' => $po->id,
        'po_line_id' => $line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post('/admin/pipeline/jobs/'.$job->id.'/pass', ['serial_number' => 'SN-SKIPTECH']);

    expect($job->fresh()->current_stage)->toBe(PipelineStage::Qa);

    $this->assertDatabaseHas('po_unit_events', [
        'po_unit_job_id' => $job->id,
        'stage' => 'tech',
        'action' => 'skip',
    ]);
});

test('pass skips qa stage when skip_qa=true on PO', function () {
    $po = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id, 'skip_qa' => true]);
    $line = PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id]);
    $job = PoUnitJob::factory()->atStage(PipelineStage::Tech)->create([
        'purchase_order_id' => $po->id,
        'po_line_id' => $line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->tech->id,
    ]);

    $this->actingAs($this->tech)
        ->post('/admin/pipeline/jobs/'.$job->id.'/pass');

    expect($job->fresh()->current_stage)->toBe(PipelineStage::Shelf);

    $this->assertDatabaseHas('po_unit_events', [
        'po_unit_job_id' => $job->id,
        'stage' => 'qa',
        'action' => 'skip',
    ]);
});

test('pass skips both tech and qa when both flags set', function () {
    $po = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id, 'skip_tech' => true, 'skip_qa' => true]);
    $line = PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id]);
    $job = PoUnitJob::factory()->atStage(PipelineStage::SerialAssign)->create([
        'purchase_order_id' => $po->id,
        'po_line_id' => $line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post('/admin/pipeline/jobs/'.$job->id.'/pass', ['serial_number' => 'SN-SKIPBOTH']);

    expect($job->fresh()->current_stage)->toBe(PipelineStage::Shelf);
});

// ── Fail ──────────────────────────────────────────────────────────────────────

test('fail marks job as failed', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post('/admin/pipeline/jobs/'.$job->id.'/fail', ['notes' => 'Broken screen.'])
        ->assertRedirect(route('pipeline.queue'));

    expect($job->fresh()->status)->toBe(UnitJobStatus::Failed);
});

test('fail writes fail event to po_unit_events', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post('/admin/pipeline/jobs/'.$job->id.'/fail', ['notes' => 'Broken screen.']);

    $this->assertDatabaseHas('po_unit_events', [
        'po_unit_job_id' => $job->id,
        'action' => 'fail',
        'user_id' => $this->warehouse->id,
    ]);
});

test('fail requires notes', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->warehouse)
        ->post('/admin/pipeline/jobs/'.$job->id.'/fail', ['notes' => ''])
        ->assertSessionHasErrors('notes');
});

test('fail returns 403 for wrong-stage user', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->warehouse->id,
    ]);

    $this->actingAs($this->qa)
        ->post('/admin/pipeline/jobs/'.$job->id.'/fail', ['notes' => 'x'])
        ->assertForbidden();
});
