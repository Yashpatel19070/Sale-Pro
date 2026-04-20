<?php

declare(strict_types=1);

use App\Enums\PoStatus;
use App\Enums\PoType;
use App\Models\PoLine;
use App\Models\PoUnitJob;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PoReturnService;
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

    $this->service = app(PoReturnService::class);
    $this->user = User::factory()->create();
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
    $this->po = PurchaseOrder::factory()->open()->create(['supplier_id' => $this->supplier->id]);
    $this->line = PoLine::factory()->create([
        'purchase_order_id' => $this->po->id,
        'product_id' => $this->product->id,
        'unit_price' => '350.00',
        'qty_ordered' => 5,
    ]);
    $this->job = PoUnitJob::factory()->create([
        'purchase_order_id' => $this->po->id,
        'po_line_id' => $this->line->id,
    ]);
});

// ── createForFailedUnit() ────────────────────────────────────────────────────

it('createForFailedUnit() creates a return PO with type=return', function () {
    $returnPo = $this->service->createForFailedUnit($this->job, $this->user);

    expect($returnPo->type)->toBe(PoType::Return);
});

it('createForFailedUnit() sets parent_po_id to original PO', function () {
    $returnPo = $this->service->createForFailedUnit($this->job, $this->user);

    expect($returnPo->parent_po_id)->toBe($this->po->id);
});

it('createForFailedUnit() copies supplier from original PO', function () {
    $returnPo = $this->service->createForFailedUnit($this->job, $this->user);

    expect($returnPo->supplier_id)->toBe($this->supplier->id);
});

it('createForFailedUnit() sets status to open', function () {
    $returnPo = $this->service->createForFailedUnit($this->job, $this->user);

    expect($returnPo->status)->toBe(PoStatus::Open);
});

it('createForFailedUnit() sets confirmed_at', function () {
    $returnPo = $this->service->createForFailedUnit($this->job, $this->user);

    expect($returnPo->confirmed_at)->not->toBeNull();
});

it('createForFailedUnit() creates one PO line with qty_ordered=1', function () {
    $returnPo = $this->service->createForFailedUnit($this->job, $this->user);

    expect($returnPo->lines)->toHaveCount(1);
    expect($returnPo->lines->first()->qty_ordered)->toBe(1);
});

it('createForFailedUnit() copies product_id from the failed job\'s line', function () {
    $returnPo = $this->service->createForFailedUnit($this->job, $this->user);

    expect($returnPo->lines->first()->product_id)->toBe($this->product->id);
});

it('createForFailedUnit() copies unit_price from the failed job\'s line', function () {
    $returnPo = $this->service->createForFailedUnit($this->job, $this->user);

    expect((string) $returnPo->lines->first()->unit_price)->toBe('350.00');
});

it('createForFailedUnit() sets notes with job id and stage', function () {
    $returnPo = $this->service->createForFailedUnit($this->job, $this->user);

    expect($returnPo->notes)->toContain("job #{$this->job->id}");
    expect($returnPo->notes)->toContain($this->job->current_stage->value);
});

it('createForFailedUnit() sets created_by_user_id to the user who triggered the failure', function () {
    $returnPo = $this->service->createForFailedUnit($this->job, $this->user);

    expect($returnPo->created_by_user_id)->toBe($this->user->id);
});

it('createForFailedUnit() generates a PO number', function () {
    $returnPo = $this->service->createForFailedUnit($this->job, $this->user);

    expect($returnPo->po_number)->toStartWith('PO-'.now()->year.'-');
});

// ── list() ───────────────────────────────────────────────────────────────────

it('list() returns only type=return POs', function () {
    PurchaseOrder::factory()->returnType()->create([
        'supplier_id' => $this->supplier->id,
        'parent_po_id' => $this->po->id,
    ]);

    $results = $this->service->list();

    expect($results->total())->toBe(1);
    expect($results->items()[0]->type)->toBe(PoType::Return);
});

it('list() paginates results', function () {
    PurchaseOrder::factory()->returnType()->count(30)->create([
        'supplier_id' => $this->supplier->id,
        'parent_po_id' => $this->po->id,
    ]);

    $results = $this->service->list();

    expect($results->perPage())->toBe(25);
    expect($results->total())->toBe(30);
});

it('list() filters by search', function () {
    PurchaseOrder::factory()->returnType()->create([
        'po_number' => 'PO-SEARCH-1111',
        'supplier_id' => $this->supplier->id,
        'parent_po_id' => $this->po->id,
    ]);
    PurchaseOrder::factory()->returnType()->create([
        'po_number' => 'PO-OTHER-2222',
        'supplier_id' => $this->supplier->id,
        'parent_po_id' => $this->po->id,
    ]);

    $results = $this->service->list(['search' => 'SEARCH']);

    expect($results->total())->toBe(1);
    expect($results->items()[0]->po_number)->toBe('PO-SEARCH-1111');
});

it('list() filters by status', function () {
    PurchaseOrder::factory()->returnType()->create([
        'supplier_id' => $this->supplier->id,
        'parent_po_id' => $this->po->id,
        'status' => PoStatus::Open,
    ]);
    PurchaseOrder::factory()->returnType()->closed()->create([
        'supplier_id' => $this->supplier->id,
        'parent_po_id' => $this->po->id,
    ]);

    $results = $this->service->list(['status' => 'closed']);

    expect($results->total())->toBe(1);
    expect($results->items()[0]->status)->toBe(PoStatus::Closed);
});

// ── close() ──────────────────────────────────────────────────────────────────

it('close() sets status to closed', function () {
    $returnPo = PurchaseOrder::factory()->returnType()->create([
        'supplier_id' => $this->supplier->id,
        'parent_po_id' => $this->po->id,
        'status' => PoStatus::Open,
    ]);

    $result = $this->service->close($returnPo);

    expect($result->status)->toBe(PoStatus::Closed);
});

it('close() sets closed_at timestamp', function () {
    $returnPo = PurchaseOrder::factory()->returnType()->create([
        'supplier_id' => $this->supplier->id,
        'parent_po_id' => $this->po->id,
        'status' => PoStatus::Open,
    ]);

    $result = $this->service->close($returnPo);

    expect($result->closed_at)->not->toBeNull();
});

it('close() throws DomainException when PO is not type=return', function () {
    expect(fn () => $this->service->close($this->po))
        ->toThrow(DomainException::class, 'Only return POs can be closed via this method.');
});

it('close() throws DomainException when return PO is already closed', function () {
    $closedReturn = PurchaseOrder::factory()->returnType()->closed()->create([
        'supplier_id' => $this->supplier->id,
        'parent_po_id' => $this->po->id,
    ]);

    expect(fn () => $this->service->close($closedReturn))
        ->toThrow(DomainException::class, 'Return PO is already closed.');
});

// ── generateReturnPoNumber() ─────────────────────────────────────────────────

it('generateReturnPoNumber() returns PO-YYYY-0001 when no POs exist', function () {
    // Delete the PO created in beforeEach
    PurchaseOrder::query()->delete();

    $number = $this->service->generateReturnPoNumber();

    expect($number)->toBe('PO-'.now()->year.'-0001');
});

it('generateReturnPoNumber() increments correctly when POs exist (including purchase type)', function () {
    // $this->po + orphan PO from PoUnitJobFactory::definition() eager create = 2 POs, next = 3
    $number = $this->service->generateReturnPoNumber();

    expect($number)->toBe('PO-'.now()->year.'-0003');
});
