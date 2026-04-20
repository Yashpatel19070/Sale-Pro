<?php

declare(strict_types=1);

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
use App\Services\PurchaseOrderService;
use Database\Seeders\PurchaseOrderPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RoleSeeder::class, PurchaseOrderPermissionSeeder::class]);

    $this->service = app(PurchaseOrderService::class);
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
    $this->user = User::factory()->create();
    $this->superAdmin = User::factory()->create()->assignRole('super-admin');
    $this->manager = User::factory()->create()->assignRole('manager');
});

// ── list() ────────────────────────────────────────────────────────────────────

it('list() returns paginated results', function () {
    PurchaseOrder::factory()->count(30)->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->user->id,
    ]);

    $result = $this->service->list();

    expect($result->total())->toBe(30);
    expect($result->perPage())->toBe(25);
});

it('list() filters by po_number', function () {
    PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id, 'po_number' => 'PO-2026-0001']);
    PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id, 'po_number' => 'PO-2026-0002']);

    $result = $this->service->list(['search' => '0001']);

    expect($result->total())->toBe(1);
    expect($result->first()->po_number)->toBe('PO-2026-0001');
});

it('list() filters by status', function () {
    PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);
    PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);

    $result = $this->service->list(['status' => 'draft']);

    expect($result->total())->toBe(1);
    expect($result->first()->status)->toBe(PoStatus::Draft);
});

it('list() filters by supplier_id', function () {
    $s2 = Supplier::factory()->create();
    PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);
    PurchaseOrder::factory()->create(['supplier_id' => $s2->id, 'created_by_user_id' => $this->user->id]);

    $result = $this->service->list(['supplier_id' => $this->supplier->id]);

    expect($result->total())->toBe(1);
    expect($result->first()->supplier_id)->toBe($this->supplier->id);
});

it('list() filters by type', function () {
    PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);
    PurchaseOrder::factory()->returnType()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);

    $result = $this->service->list(['type' => 'return']);

    expect($result->total())->toBe(1);
    expect($result->first()->type)->toBe(PoType::Return);
});

it('list() ignores empty string filters', function () {
    PurchaseOrder::factory()->count(3)->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);

    $result = $this->service->list(['search' => '', 'status' => '', 'type' => '']);

    expect($result->total())->toBe(3);
});

// ── create() ──────────────────────────────────────────────────────────────────

it('create() persists PO and lines in transaction', function () {
    $po = $this->service->create([
        'supplier_id' => $this->supplier->id,
        'lines' => [
            ['product_id' => $this->product->id, 'qty_ordered' => 2, 'unit_price' => '15.00'],
        ],
    ], $this->user);

    expect(PurchaseOrder::count())->toBe(1);
    expect(PoLine::count())->toBe(1);
    expect($po->lines->first()->qty_ordered)->toBe(2);
});

it('create() generates PO number PO-YYYY-0001 for first PO', function () {
    $po = $this->service->create([
        'supplier_id' => $this->supplier->id,
        'lines' => [
            ['product_id' => $this->product->id, 'qty_ordered' => 1, 'unit_price' => '10.00'],
        ],
    ], $this->user);

    expect($po->po_number)->toBe('PO-'.now()->year.'-0001');
});

it('create() generates sequential PO numbers within year', function () {
    PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);

    $po = $this->service->create([
        'supplier_id' => $this->supplier->id,
        'lines' => [
            ['product_id' => $this->product->id, 'qty_ordered' => 1, 'unit_price' => '10.00'],
        ],
    ], $this->user);

    expect($po->po_number)->toBe('PO-'.now()->year.'-0002');
});

it('create() sets status draft', function () {
    $po = $this->service->create([
        'supplier_id' => $this->supplier->id,
        'lines' => [
            ['product_id' => $this->product->id, 'qty_ordered' => 1, 'unit_price' => '10.00'],
        ],
    ], $this->user);

    expect($po->status)->toBe(PoStatus::Draft);
});

it('create() sets created_by_user_id', function () {
    $po = $this->service->create([
        'supplier_id' => $this->supplier->id,
        'lines' => [
            ['product_id' => $this->product->id, 'qty_ordered' => 1, 'unit_price' => '10.00'],
        ],
    ], $this->user);

    expect($po->created_by_user_id)->toBe($this->user->id);
});

it('create() stores skip_tech and skip_qa flags', function () {
    $po = $this->service->create([
        'supplier_id' => $this->supplier->id,
        'skip_tech' => true,
        'skip_qa' => true,
        'lines' => [
            ['product_id' => $this->product->id, 'qty_ordered' => 1, 'unit_price' => '10.00'],
        ],
    ], $this->user);

    expect($po->skip_tech)->toBeTrue();
    expect($po->skip_qa)->toBeTrue();
});

// ── update() ──────────────────────────────────────────────────────────────────

it('update() replaces all lines when lines key provided', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);
    PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id]);

    $p2 = Product::factory()->create();
    $this->service->update($po, [
        'supplier_id' => $this->supplier->id,
        'lines' => [
            ['product_id' => $p2->id, 'qty_ordered' => 5, 'unit_price' => '99.00'],
        ],
    ]);

    expect($po->fresh()->lines()->count())->toBe(1);
    expect($po->fresh()->lines()->first()->product_id)->toBe($p2->id);
});

it('update() updates supplier and notes', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);
    $s2 = Supplier::factory()->create();

    $this->service->update($po, ['supplier_id' => $s2->id, 'notes' => 'Updated note']);

    $fresh = $po->fresh();
    expect($fresh->supplier_id)->toBe($s2->id);
    expect($fresh->notes)->toBe('Updated note');
});

it('update() throws DomainException when PO is not draft', function () {
    $po = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);

    expect(fn () => $this->service->update($po, ['supplier_id' => $this->supplier->id]))
        ->toThrow(DomainException::class);
});

// ── confirm() ─────────────────────────────────────────────────────────────────

it('confirm() sets status open and confirmed_at', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);
    PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id]);

    $this->service->confirm($po);

    $fresh = $po->fresh();
    expect($fresh->status)->toBe(PoStatus::Open);
    expect($fresh->confirmed_at)->not->toBeNull();
});

it('confirm() throws DomainException when not draft', function () {
    $po = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);

    expect(fn () => $this->service->confirm($po))->toThrow(DomainException::class);
});

it('confirm() throws DomainException when no lines', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);

    expect(fn () => $this->service->confirm($po))->toThrow(DomainException::class);
});

// ── cancel() ──────────────────────────────────────────────────────────────────

it('cancel() sets status cancelled and cancelled_at on draft', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);

    $this->service->cancel($po, 'No longer needed for this order');

    $fresh = $po->fresh();
    expect($fresh->status)->toBe(PoStatus::Cancelled);
    expect($fresh->cancelled_at)->not->toBeNull();
});

it('cancel() sets status cancelled on open PO with no received units', function () {
    $po = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);
    PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id, 'qty_received' => 0]);

    $this->service->cancel($po, 'Cancelling open order cleanly');

    expect($po->fresh()->status)->toBe(PoStatus::Cancelled);
});

it('cancel() persists cancel_notes on the PO record', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);

    $this->service->cancel($po, 'These are the cancellation notes');

    expect($po->fresh()->cancel_notes)->toBe('These are the cancellation notes');
});

it('cancel() throws DomainException when units received', function () {
    $po = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);
    PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id, 'qty_received' => 1]);

    expect(fn () => $this->service->cancel($po, 'Cannot cancel with received units'))->toThrow(DomainException::class);
});

it('cancel() throws DomainException when status is partial or closed', function () {
    $po = PurchaseOrder::factory()->partial()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);

    expect(fn () => $this->service->cancel($po, 'Cannot cancel partial order'))->toThrow(DomainException::class);
});

// ── reopen() ──────────────────────────────────────────────────────────────────

it('reopen() sets status open and increments reopen_count', function () {
    $po = PurchaseOrder::factory()->closed()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id, 'reopen_count' => 0]);

    $this->service->reopen($po, $this->manager);

    $fresh = $po->fresh();
    expect($fresh->status)->toBe(PoStatus::Open);
    expect($fresh->reopen_count)->toBe(1);
    expect($fresh->reopened_at)->not->toBeNull();
});

it('reopen() succeeds for manager on 1st reopen (count=0)', function () {
    $po = PurchaseOrder::factory()->closed()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id, 'reopen_count' => 0]);

    $this->service->reopen($po, $this->manager);

    expect($po->fresh()->reopen_count)->toBe(1);
});

it('reopen() succeeds for manager on 2nd reopen (count=1)', function () {
    $po = PurchaseOrder::factory()->closed()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id, 'reopen_count' => 1]);

    $this->service->reopen($po, $this->manager);

    expect($po->fresh()->reopen_count)->toBe(2);
});

it('reopen() throws DomainException for manager on 3rd reopen (count=2)', function () {
    $po = PurchaseOrder::factory()->closed()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id, 'reopen_count' => 2]);

    expect(fn () => $this->service->reopen($po, $this->manager))->toThrow(DomainException::class);
});

it('reopen() succeeds for super-admin on 3rd reopen (count=2)', function () {
    $po = PurchaseOrder::factory()->closed()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id, 'reopen_count' => 2]);

    $this->service->reopen($po, $this->superAdmin);

    expect($po->fresh()->reopen_count)->toBe(3);
});

it('reopen() throws DomainException when unit is on shelf', function () {
    $po = PurchaseOrder::factory()->closed()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);
    PoUnitJob::factory()->create([
        'purchase_order_id' => $po->id,
        'current_stage' => PipelineStage::Shelf->value,
        'status' => UnitJobStatus::Passed->value,
    ]);

    expect(fn () => $this->service->reopen($po, $this->manager))->toThrow(DomainException::class);
});

it('reopen() does not block reopen when shelf job exists but status is not passed', function () {
    $po = PurchaseOrder::factory()->closed()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);
    PoUnitJob::factory()->create([
        'purchase_order_id' => $po->id,
        'current_stage' => PipelineStage::Shelf->value,
        'status' => UnitJobStatus::Failed->value,
    ]);

    $this->service->reopen($po, $this->manager);

    expect($po->fresh()->status)->toBe(PoStatus::Open);
});

it('reopen() throws DomainException when PO is not closed', function () {
    $po = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);

    expect(fn () => $this->service->reopen($po, $this->manager))->toThrow(DomainException::class);
});

// ── incrementReceived() ───────────────────────────────────────────────────────

it('incrementReceived() adds 1 to qty_received', function () {
    $po = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);
    $line = PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id, 'qty_ordered' => 3, 'qty_received' => 0]);

    $this->service->incrementReceived($line);

    expect($line->fresh()->qty_received)->toBe(1);
});

it('incrementReceived() sets PO status to partial when was open', function () {
    $po = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);
    $line = PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id, 'qty_ordered' => 3, 'qty_received' => 0]);

    $this->service->incrementReceived($line);

    expect($po->fresh()->status)->toBe(PoStatus::Partial);
});

it('incrementReceived() throws DomainException when line already fulfilled', function () {
    $po = PurchaseOrder::factory()->partial()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);
    $line = PoLine::factory()->fulfilled()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id, 'qty_ordered' => 2]);

    expect(fn () => $this->service->incrementReceived($line))->toThrow(DomainException::class);
});

// ── checkAndClose() ───────────────────────────────────────────────────────────

it('checkAndClose() closes PO when all lines fulfilled and all jobs terminal', function () {
    $po = PurchaseOrder::factory()->partial()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);
    PoLine::factory()->fulfilled()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id, 'qty_ordered' => 2]);
    PoUnitJob::factory()->create([
        'purchase_order_id' => $po->id,
        'current_stage' => PipelineStage::Shelf->value,
        'status' => UnitJobStatus::Passed->value,
    ]);

    $this->service->checkAndClose($po);

    $fresh = $po->fresh();
    expect($fresh->status)->toBe(PoStatus::Closed);
    expect($fresh->closed_at)->not->toBeNull();
});

it('checkAndClose() does not close when lines not fulfilled', function () {
    $po = PurchaseOrder::factory()->partial()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);
    PoLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id, 'qty_ordered' => 5, 'qty_received' => 2]);

    $this->service->checkAndClose($po);

    expect($po->fresh()->status)->toBe(PoStatus::Partial);
});

it('checkAndClose() does not close when jobs not terminal', function () {
    $po = PurchaseOrder::factory()->partial()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);
    PoLine::factory()->fulfilled()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id, 'qty_ordered' => 2]);
    PoUnitJob::factory()->create([
        'purchase_order_id' => $po->id,
        'current_stage' => PipelineStage::Tech->value,
        'status' => UnitJobStatus::Pending->value,
    ]);

    $this->service->checkAndClose($po);

    expect($po->fresh()->status)->toBe(PoStatus::Partial);
});

it('checkAndClose() sets closed_at timestamp', function () {
    $po = PurchaseOrder::factory()->partial()->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);
    PoLine::factory()->fulfilled()->create(['purchase_order_id' => $po->id, 'product_id' => $this->product->id, 'qty_ordered' => 1]);
    PoUnitJob::factory()->create([
        'purchase_order_id' => $po->id,
        'current_stage' => PipelineStage::Shelf->value,
        'status' => UnitJobStatus::Passed->value,
    ]);

    $this->service->checkAndClose($po);

    expect($po->fresh()->closed_at)->not->toBeNull();
});

// ── generatePoNumber() ────────────────────────────────────────────────────────

it('generatePoNumber() returns PO-YYYY-0001 when no POs exist', function () {
    $number = $this->service->generatePoNumber();

    expect($number)->toBe('PO-'.now()->year.'-0001');
});

it('generatePoNumber() increments within year', function () {
    PurchaseOrder::factory()->count(3)->create(['supplier_id' => $this->supplier->id, 'created_by_user_id' => $this->user->id]);

    $number = $this->service->generatePoNumber();

    expect($number)->toBe('PO-'.now()->year.'-0004');
});

it('generatePoNumber() resets at year boundary', function () {
    PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'created_by_user_id' => $this->user->id,
        'created_at' => now()->subYear(),
    ]);

    $number = $this->service->generatePoNumber();

    expect($number)->toBe('PO-'.now()->year.'-0001');
});
