<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
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
    $this->seed(RoleSeeder::class);
    $this->seed(PurchaseOrderPermissionSeeder::class);

    $this->service = new PurchaseOrderService;
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
    $this->user = User::factory()->create();
});

// ── store() ───────────────────────────────────────────────────────────────────

it('store() creates a PO with po_number matching PO-year-XXXX format', function () {
    $data = [
        'supplier_id' => $this->supplier->id,
        'expected_delivery_date' => null,
        'notes' => null,
        'lines' => [[
            'product_id' => $this->product->id,
            'description' => 'Widget',
            'qty_ordered' => 2,
            'unit_cost' => 10.00,
            'tax_rate' => 10,
        ]],
    ];

    $po = $this->service->store($data, $this->user);

    expect($po->po_number)->toMatch('/^PO-\d{4}-\d{4}$/');
});

it('store() creates the correct number of lines', function () {
    $product2 = Product::factory()->create();

    $data = [
        'supplier_id' => $this->supplier->id,
        'expected_delivery_date' => null,
        'notes' => null,
        'lines' => [
            [
                'product_id' => $this->product->id,
                'description' => 'Widget A',
                'qty_ordered' => 2,
                'unit_cost' => 10.00,
                'tax_rate' => 0,
            ],
            [
                'product_id' => $product2->id,
                'description' => 'Widget B',
                'qty_ordered' => 3,
                'unit_cost' => 20.00,
                'tax_rate' => 0,
            ],
        ],
    ];

    $po = $this->service->store($data, $this->user);

    expect($po->lines()->count())->toBe(2);
});

it('store() calculates subtotal correctly', function () {
    $data = [
        'supplier_id' => $this->supplier->id,
        'expected_delivery_date' => null,
        'notes' => null,
        'lines' => [[
            'product_id' => $this->product->id,
            'description' => 'Widget',
            'qty_ordered' => 2,
            'unit_cost' => 10.00,
            'tax_rate' => 10,
        ]],
    ];

    $po = $this->service->store($data, $this->user);

    // subtotal = qty * unit_cost = 2 * 10 = 20
    expect((float) $po->subtotal)->toBe(20.0);
});

it('store() calculates tax_total correctly', function () {
    $data = [
        'supplier_id' => $this->supplier->id,
        'expected_delivery_date' => null,
        'notes' => null,
        'lines' => [[
            'product_id' => $this->product->id,
            'description' => 'Widget',
            'qty_ordered' => 2,
            'unit_cost' => 10.00,
            'tax_rate' => 10,
        ]],
    ];

    $po = $this->service->store($data, $this->user);

    // tax_total = qty * unit_cost * tax_rate/100 = 2 * 10 * 0.10 = 2
    expect((float) $po->tax_total)->toBe(2.0);
});

it('store() calculates grand_total as subtotal plus tax_total', function () {
    $data = [
        'supplier_id' => $this->supplier->id,
        'expected_delivery_date' => null,
        'notes' => null,
        'lines' => [[
            'product_id' => $this->product->id,
            'description' => 'Widget',
            'qty_ordered' => 2,
            'unit_cost' => 10.00,
            'tax_rate' => 10,
        ]],
    ];

    $po = $this->service->store($data, $this->user);

    // grand_total = subtotal + tax_total = 20 + 2 = 22
    expect((float) $po->grand_total)->toBe(22.0);
});

// ── update() ──────────────────────────────────────────────────────────────────

it('update() replaces old lines with new lines', function () {
    $data = [
        'supplier_id' => $this->supplier->id,
        'expected_delivery_date' => null,
        'notes' => null,
        'lines' => [[
            'product_id' => $this->product->id,
            'description' => 'Old Widget',
            'qty_ordered' => 1,
            'unit_cost' => 5.00,
            'tax_rate' => 0,
        ]],
    ];

    $po = $this->service->store($data, $this->user);
    $oldLineId = $po->lines()->first()->id;

    $product2 = Product::factory()->create();

    $updateData = [
        'supplier_id' => $this->supplier->id,
        'expected_delivery_date' => null,
        'notes' => null,
        'lines' => [[
            'product_id' => $product2->id,
            'description' => 'New Widget',
            'qty_ordered' => 3,
            'unit_cost' => 15.00,
            'tax_rate' => 0,
            'qty_on_hand_snapshot' => 0,
        ]],
    ];

    $updated = $this->service->update($po, $updateData);

    $this->assertDatabaseMissing('purchase_order_lines', ['id' => $oldLineId]);
    expect($updated->lines()->count())->toBe(1);
    $this->assertDatabaseHas('purchase_order_lines', [
        'purchase_order_id' => $po->id,
        'description' => 'New Widget',
    ]);
});

it('update() throws DomainException when status is approved', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $updateData = [
        'supplier_id' => $this->supplier->id,
        'expected_delivery_date' => null,
        'notes' => null,
        'lines' => [[
            'product_id' => $this->product->id,
            'description' => 'Widget',
            'qty_ordered' => 1,
            'unit_cost' => 5.00,
            'tax_rate' => 0,
            'qty_on_hand_snapshot' => 0,
        ]],
    ];

    expect(fn () => $this->service->update($po, $updateData))
        ->toThrow(DomainException::class);
});

// ── submit() ──────────────────────────────────────────────────────────────────

it('submit() changes status to pending_approval', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $result = $this->service->submit($po);

    expect($result->status)->toBe(PurchaseOrderStatus::PendingApproval);
    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::PendingApproval->value,
    ]);
});

it('submit() throws DomainException when status is approved', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    expect(fn () => $this->service->submit($po))
        ->toThrow(DomainException::class);
});

// ── approve() ─────────────────────────────────────────────────────────────────

it('approve() sets approved_by and approved_at', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::PendingApproval,
    ]);

    $result = $this->service->approve($po, $this->user);

    expect($result->status)->toBe(PurchaseOrderStatus::Approved);
    expect($result->approved_by)->toBe($this->user->id);
    expect($result->approved_at)->not->toBeNull();
});

it('approve() throws DomainException when status is not pending_approval', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    expect(fn () => $this->service->approve($po, $this->user))
        ->toThrow(DomainException::class);
});

// ── reject() ──────────────────────────────────────────────────────────────────

it('reject() sets rejection_reason and status to rejected', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::PendingApproval,
    ]);

    $result = $this->service->reject($po, 'Over budget');

    expect($result->status)->toBe(PurchaseOrderStatus::Rejected);
    expect($result->rejection_reason)->toBe('Over budget');
});

it('reject() throws DomainException when status is not pending_approval', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    expect(fn () => $this->service->reject($po, 'reason'))
        ->toThrow(DomainException::class);
});

// ── cancel() ──────────────────────────────────────────────────────────────────

it('cancel() changes status to cancelled', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $result = $this->service->cancel($po);

    expect($result->status)->toBe(PurchaseOrderStatus::Cancelled);
});

it('cancel() throws DomainException when status is closed', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Closed,
    ]);

    expect(fn () => $this->service->cancel($po))
        ->toThrow(DomainException::class);
});

// ── generatePoNumber() ────────────────────────────────────────────────────────

it('generatePoNumber() returns PO-year-0001 when no existing POs', function () {
    $year = now()->year;

    $number = $this->service->generatePoNumber();

    expect($number)->toBe("PO-{$year}-0001");
});

it('generatePoNumber() increments when POs already exist', function () {
    $year = now()->year;

    PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'po_number' => "PO-{$year}-0001",
    ]);

    $number = $this->service->generatePoNumber();

    expect($number)->toBe("PO-{$year}-0002");
});

// ── passQualityCheck() ────────────────────────────────────────────────────────

it('passQualityCheck() sets status to received', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::QualityCheck,
        'qc_notes' => null,
    ]);

    $result = $this->service->passQualityCheck($po, null);

    expect($result->status)->toBe(PurchaseOrderStatus::Received);
    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'status' => PurchaseOrderStatus::Received->value,
    ]);
});

it('passQualityCheck() saves qc_notes when provided', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::QualityCheck,
        'qc_notes' => null,
    ]);

    $notes = 'All items inspected and approved. No defects found.';

    $result = $this->service->passQualityCheck($po, $notes);

    expect($result->qc_notes)->toBe($notes);
    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'qc_notes' => $notes,
    ]);
});

it('passQualityCheck() with null notes does not fail', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::QualityCheck,
        'qc_notes' => null,
    ]);

    $result = $this->service->passQualityCheck($po, null);

    expect($result->qc_notes)->toBeNull();
    $this->assertDatabaseHas('purchase_orders', [
        'id' => $po->id,
        'qc_notes' => null,
    ]);
});

it('passQualityCheck() throws DomainException when status is draft', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    expect(fn () => $this->service->passQualityCheck($po, null))
        ->toThrow(DomainException::class);
});

it('passQualityCheck() throws DomainException when status is approved', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    expect(fn () => $this->service->passQualityCheck($po, null))
        ->toThrow(DomainException::class);
});

it('passQualityCheck() throws DomainException when status is received', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);

    expect(fn () => $this->service->passQualityCheck($po, null))
        ->toThrow(DomainException::class);
});

it('passQualityCheck() throws DomainException when status is partially_received', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::PartiallyReceived,
    ]);

    expect(fn () => $this->service->passQualityCheck($po, null))
        ->toThrow(DomainException::class);
});
