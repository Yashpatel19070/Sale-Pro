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
use App\Services\PipelineService;
use Database\Seeders\PipelinePermissionSeeder;
use Database\Seeders\PurchaseOrderPermissionSeeder;
use Database\Seeders\SupplierPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        SupplierPermissionSeeder::class,
        PurchaseOrderPermissionSeeder::class,
        PipelinePermissionSeeder::class,
    ]);

    $this->service = app(PipelineService::class);
    $this->user = User::factory()->create();
    $this->supplier = Supplier::factory()->create();
    $this->location = InventoryLocation::factory()->create();
    $this->product = Product::factory()->create();
    $this->po = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id]);
    $this->line = PoLine::factory()->create([
        'purchase_order_id' => $this->po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
    ]);
});

// ── createJob() ───────────────────────────────────────────────────────────────

test('createJob() creates PoUnitJob at receive stage', function () {
    $job = $this->service->createJob($this->line, $this->user);
    expect($job)->toBeInstanceOf(PoUnitJob::class);
    $this->assertDatabaseHas('po_unit_jobs', ['po_line_id' => $this->line->id]);
});

test('createJob() writes receive pass event', function () {
    $job = $this->service->createJob($this->line, $this->user);
    $this->assertDatabaseHas('po_unit_events', [
        'po_unit_job_id' => $job->id,
        'stage' => 'receive',
        'action' => 'pass',
    ]);
});

test('createJob() increments line qty_received', function () {
    $this->service->createJob($this->line, $this->user);
    expect($this->line->fresh()->qty_received)->toBe(1);
});

test('createJob() advances job to visual stage', function () {
    $job = $this->service->createJob($this->line, $this->user);
    expect($job->fresh()->current_stage)->toBe(PipelineStage::Visual);
    expect($job->fresh()->status)->toBe(UnitJobStatus::Pending);
});

test('createJob() sets PO status to partial', function () {
    $this->service->createJob($this->line, $this->user);
    expect($this->po->fresh()->status->value)->toBe('partial');
});

test('createJob() throws DomainException when PO is not open', function () {
    $cancelledPo = PurchaseOrder::factory()->cancelled()->create(['supplier_id' => $this->supplier->id]);
    $cancelledLine = PoLine::factory()->create(['purchase_order_id' => $cancelledPo->id, 'product_id' => $this->product->id]);

    expect(fn () => $this->service->createJob($cancelledLine, $this->user))
        ->toThrow(DomainException::class);
});

test('createJob() throws DomainException when line is fully received', function () {
    $fullLine = PoLine::factory()->create([
        'purchase_order_id' => $this->po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 1,
        'qty_received' => 1,
    ]);

    expect(fn () => $this->service->createJob($fullLine, $this->user))
        ->toThrow(DomainException::class);
});

// ── start() ───────────────────────────────────────────────────────────────────

test('start() sets status to in_progress', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Pending,
    ]);

    $this->service->start($job, $this->user);
    expect($job->fresh()->status)->toBe(UnitJobStatus::InProgress);
});

test('start() assigns user to assigned_to_user_id', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Pending,
    ]);

    $this->service->start($job, $this->user);
    expect($job->fresh()->assigned_to_user_id)->toBe($this->user->id);
});

test('start() writes start event', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Pending,
    ]);

    $this->service->start($job, $this->user);

    $this->assertDatabaseHas('po_unit_events', [
        'po_unit_job_id' => $job->id,
        'action' => 'start',
        'user_id' => $this->user->id,
    ]);
});

test('start() throws DomainException when job is already in_progress', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
    ]);

    expect(fn () => $this->service->start($job, $this->user))
        ->toThrow(DomainException::class);
});

test('start() throws DomainException when job is terminal', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Failed,
    ]);

    expect(fn () => $this->service->start($job, $this->user))
        ->toThrow(DomainException::class);
});

// ── pass() — stage progression ────────────────────────────────────────────────

test('pass() at visual advances to serial_assign', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
    ]);

    $this->service->pass($job, $this->user);
    expect($job->fresh()->current_stage)->toBe(PipelineStage::SerialAssign);
});

test('pass() at serial_assign stores serial in pending_serial_number', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::SerialAssign)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
    ]);

    $this->service->pass($job, $this->user, ['serial_number' => 'sn-abc123']);
    expect($job->fresh()->pending_serial_number)->toBe('SN-ABC123');
});

test('pass() at serial_assign advances to tech', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::SerialAssign)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
    ]);

    $this->service->pass($job, $this->user, ['serial_number' => 'SN-T001']);
    expect($job->fresh()->current_stage)->toBe(PipelineStage::Tech);
});

test('pass() at tech advances to qa', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Tech)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
    ]);

    $this->service->pass($job, $this->user);
    expect($job->fresh()->current_stage)->toBe(PipelineStage::Qa);
});

test('pass() at qa advances to shelf', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Qa)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
    ]);

    $this->service->pass($job, $this->user);
    expect($job->fresh()->current_stage)->toBe(PipelineStage::Shelf);
});

test('pass() writes pass event at each stage', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
    ]);

    $this->service->pass($job, $this->user);

    $this->assertDatabaseHas('po_unit_events', [
        'po_unit_job_id' => $job->id,
        'stage' => 'visual',
        'action' => 'pass',
    ]);
});

test('pass() throws DomainException when job is pending (not yet claimed)', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Pending,
    ]);

    expect(fn () => $this->service->pass($job, $this->user))
        ->toThrow(DomainException::class);
});

test('pass() throws DomainException when called by a different user than assigned', function () {
    $other = User::factory()->create();
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
    ]);

    expect(fn () => $this->service->pass($job, $other))
        ->toThrow(DomainException::class);
});

// ── pass() — skip flags ───────────────────────────────────────────────────────

test('pass() skips tech and writes skip event when skip_tech=true', function () {
    $po = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id, 'skip_tech' => true]);
    $line = PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id]);
    $job = PoUnitJob::factory()->atStage(PipelineStage::SerialAssign)->create([
        'purchase_order_id' => $po->id,
        'po_line_id' => $line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
    ]);

    $this->service->pass($job, $this->user, ['serial_number' => 'SN-SKIPTECH']);

    expect($job->fresh()->current_stage)->toBe(PipelineStage::Qa);
    $this->assertDatabaseHas('po_unit_events', ['po_unit_job_id' => $job->id, 'stage' => 'tech', 'action' => 'skip']);
});

test('pass() skips qa and writes skip event when skip_qa=true', function () {
    $po = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id, 'skip_qa' => true]);
    $line = PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id]);
    $job = PoUnitJob::factory()->atStage(PipelineStage::Tech)->create([
        'purchase_order_id' => $po->id,
        'po_line_id' => $line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
    ]);

    $this->service->pass($job, $this->user);

    expect($job->fresh()->current_stage)->toBe(PipelineStage::Shelf);
    $this->assertDatabaseHas('po_unit_events', ['po_unit_job_id' => $job->id, 'stage' => 'qa', 'action' => 'skip']);
});

test('pass() skips both tech and qa directly to shelf when both flags set', function () {
    $po = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id, 'skip_tech' => true, 'skip_qa' => true]);
    $line = PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id]);
    $job = PoUnitJob::factory()->atStage(PipelineStage::SerialAssign)->create([
        'purchase_order_id' => $po->id,
        'po_line_id' => $line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
    ]);

    $this->service->pass($job, $this->user, ['serial_number' => 'SN-SKIPBOTH']);

    expect($job->fresh()->current_stage)->toBe(PipelineStage::Shelf);
});

// ── pass() — shelf stage ──────────────────────────────────────────────────────

test('pass() at shelf creates InventorySerial', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Shelf)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
        'pending_serial_number' => 'SN-SHELF01',
    ]);

    $this->service->pass($job, $this->user, ['inventory_location_id' => $this->location->id]);

    $this->assertDatabaseHas('inventory_serials', ['serial_number' => 'SN-SHELF01']);
});

test('pass() at shelf creates InventoryMovement of type receive', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Shelf)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
        'pending_serial_number' => 'SN-SHELF02',
    ]);

    $this->service->pass($job, $this->user, ['inventory_location_id' => $this->location->id]);

    $serial = InventorySerial::where('serial_number', 'SN-SHELF02')->first();
    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $serial->id,
        'type' => 'receive',
    ]);
});

test('pass() at shelf links serial to job via inventory_serial_id', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Shelf)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
        'pending_serial_number' => 'SN-SHELF03',
    ]);

    $this->service->pass($job, $this->user, ['inventory_location_id' => $this->location->id]);

    expect($job->fresh()->inventory_serial_id)->not->toBeNull();
});

test('pass() at shelf marks job status as passed', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Shelf)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
        'pending_serial_number' => 'SN-SHELF04',
    ]);

    $this->service->pass($job, $this->user, ['inventory_location_id' => $this->location->id]);

    expect($job->fresh()->status)->toBe(UnitJobStatus::Passed);
});

// ── fail() ────────────────────────────────────────────────────────────────────

test('fail() marks job status as failed', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
    ]);

    $this->service->fail($job, $this->user, 'Cracked housing.');

    expect($job->fresh()->status)->toBe(UnitJobStatus::Failed);
});

test('fail() writes fail event', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
    ]);

    $this->service->fail($job, $this->user, 'Cracked housing.');

    $this->assertDatabaseHas('po_unit_events', [
        'po_unit_job_id' => $job->id,
        'action' => 'fail',
        'user_id' => $this->user->id,
    ]);
});

test('fail() throws DomainException when job is pending (not yet claimed)', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Pending,
    ]);

    expect(fn () => $this->service->fail($job, $this->user, 'notes'))
        ->toThrow(DomainException::class);
});

test('fail() throws DomainException when job is already terminal', function () {
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Failed,
    ]);

    expect(fn () => $this->service->fail($job, $this->user, 'notes'))
        ->toThrow(DomainException::class);
});

test('fail() throws DomainException when called by a different user than assigned', function () {
    $other = User::factory()->create();
    $job = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::InProgress,
        'assigned_to_user_id' => $this->user->id,
    ]);

    expect(fn () => $this->service->fail($job, $other, 'notes'))
        ->toThrow(DomainException::class);
});

// ── queue() ───────────────────────────────────────────────────────────────────

test('queue() returns active jobs at specified stages', function () {
    $visual = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Pending,
    ]);

    $result = $this->service->queue(['stages' => [PipelineStage::Visual]]);
    expect($result->total())->toBe(1);
    expect($result->items()[0]->id)->toBe($visual->id);
});

test('queue() excludes terminal jobs', function () {
    PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Failed,
    ]);

    $result = $this->service->queue(['stages' => [PipelineStage::Visual]]);
    expect($result->total())->toBe(0);
});

test('queue() returns oldest first', function () {
    $first = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Pending,
        'created_at' => now()->subHour(),
    ]);
    $second = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Pending,
        'created_at' => now(),
    ]);

    $result = $this->service->queue(['stages' => [PipelineStage::Visual]]);
    expect($result->items()[0]->id)->toBe($first->id);
});

test('queue() filters by purchase_order_id', function () {
    $otherPo = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id]);
    $otherLine = PoLine::factory()->create(['purchase_order_id' => $otherPo->id, 'product_id' => $this->product->id]);

    PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
        'status' => UnitJobStatus::Pending,
    ]);
    $target = PoUnitJob::factory()->atStage(PipelineStage::Visual)->create([
        'purchase_order_id' => $otherPo->id,
        'po_line_id' => $otherLine->id,
        'status' => UnitJobStatus::Pending,
    ]);

    $result = $this->service->queue(['stages' => [PipelineStage::Visual], 'purchase_order_id' => $otherPo->id]);
    expect($result->total())->toBe(1);
    expect($result->items()[0]->id)->toBe($target->id);
});
