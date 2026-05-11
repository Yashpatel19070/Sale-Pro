<?php

declare(strict_types=1);

use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\GoodsReceiptService;
use Database\Seeders\PurchaseOrderPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PurchaseOrderPermissionSeeder::class);

    $this->service = app(GoodsReceiptService::class);
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
    $this->inspector = User::factory()->create();

    $this->po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::QualityCheck,
    ]);

    $this->poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $this->po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 10,
    ]);

    $this->grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    $this->grnLine = GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $this->grn->id,
        'purchase_order_line_id' => $this->poLine->id,
        'qty_received' => 10,
    ]);
});

// ── submitQc: Happy Path ──────────────────────────────────────────────────────

it('submitQc() sets qty_passed on GRN lines', function () {
    $this->service->submitQc($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'qty_passed' => 8,
            'qty_failed' => 2,
        ]],
    ], $this->inspector);

    $this->assertDatabaseHas('goods_receipt_lines', [
        'id' => $this->grnLine->id,
        'qty_passed' => 8,
    ]);
});

it('submitQc() sets qty_failed on GRN lines', function () {
    $this->service->submitQc($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'qty_passed' => 7,
            'qty_failed' => 3,
        ]],
    ], $this->inspector);

    $this->assertDatabaseHas('goods_receipt_lines', [
        'id' => $this->grnLine->id,
        'qty_failed' => 3,
    ]);
});

it('submitQc() sets qc_inspected_at on GRN lines', function () {
    $this->service->submitQc($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'qty_passed' => 10,
            'qty_failed' => 0,
        ]],
    ], $this->inspector);

    $line = GoodsReceiptLine::find($this->grnLine->id);
    expect($line->qc_inspected_at)->not->toBeNull();
});

it('submitQc() sets qc_inspected_by to inspector user ID', function () {
    $this->service->submitQc($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'qty_passed' => 10,
            'qty_failed' => 0,
        ]],
    ], $this->inspector);

    $this->assertDatabaseHas('goods_receipt_lines', [
        'id' => $this->grnLine->id,
        'qc_inspected_by' => $this->inspector->id,
    ]);
});

it('submitQc() returns fresh GoodsReceipt with lines loaded', function () {
    $result = $this->service->submitQc($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'qty_passed' => 10,
            'qty_failed' => 0,
        ]],
    ], $this->inspector);

    expect($result)->toBeInstanceOf(GoodsReceipt::class);
    expect($result->lines)->toHaveCount(1);
});

it('submitQc() calls PurchaseOrderService::passQualityCheck', function () {
    $this->service->submitQc($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'qty_passed' => 10,
            'qty_failed' => 0,
        ]],
    ], $this->inspector);

    // passQualityCheck should transition PO from quality_check to received (full qty) or partially_received
    $po = $this->po->fresh();
    expect(in_array($po->status, [
        PurchaseOrderStatus::Received,
        PurchaseOrderStatus::PartiallyReceived,
    ], true))->toBeTrue();
});

it('submitQc() with multiple lines all pass', function () {
    $poLine2 = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $this->po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'qty_received' => 5,
    ]);

    $grnLine2 = GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $this->grn->id,
        'purchase_order_line_id' => $poLine2->id,
        'qty_received' => 5,
    ]);

    $this->service->submitQc($this->grn, [
        'lines' => [
            [
                'goods_receipt_line_id' => $this->grnLine->id,
                'qty_passed' => 10,
                'qty_failed' => 0,
            ],
            [
                'goods_receipt_line_id' => $grnLine2->id,
                'qty_passed' => 5,
                'qty_failed' => 0,
            ],
        ],
    ], $this->inspector);

    $this->assertDatabaseHas('goods_receipt_lines', [
        'id' => $this->grnLine->id,
        'qty_passed' => 10,
    ]);

    $this->assertDatabaseHas('goods_receipt_lines', [
        'id' => $grnLine2->id,
        'qty_passed' => 5,
    ]);
});

// ── submitQc: Validation Failures ─────────────────────────────────────────────

it('submitQc() throws DomainException when GRN status is not complete', function () {
    $draftGrn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    $draftGrnLine = GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $draftGrn->id,
        'purchase_order_line_id' => $this->poLine->id,
        'qty_received' => 5,
    ]);

    expect(fn () => $this->service->submitQc($draftGrn, [
        'lines' => [[
            'goods_receipt_line_id' => $draftGrnLine->id,
            'qty_passed' => 5,
            'qty_failed' => 0,
        ]],
    ], $this->inspector))->toThrow(DomainException::class);
});

it('submitQc() throws DomainException when PO status is not quality_check', function () {
    $receivedPo = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);

    $receivedPoLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $receivedPo->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'qty_received' => 5,
    ]);

    $receivedGrn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $receivedPo->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    $receivedGrnLine = GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $receivedGrn->id,
        'purchase_order_line_id' => $receivedPoLine->id,
        'qty_received' => 5,
    ]);

    expect(fn () => $this->service->submitQc($receivedGrn, [
        'lines' => [[
            'goods_receipt_line_id' => $receivedGrnLine->id,
            'qty_passed' => 5,
            'qty_failed' => 0,
        ]],
    ], $this->inspector))->toThrow(DomainException::class);
});

it('submitQc() throws DomainException when QC already submitted', function () {
    // Pre-submit QC on the line
    $this->grnLine->update([
        'qty_passed' => 5,
        'qty_failed' => 5,
        'qc_inspected_at' => now(),
        'qc_inspected_by' => $this->inspector->id,
    ]);

    expect(fn () => $this->service->submitQc($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'qty_passed' => 8,
            'qty_failed' => 2,
        ]],
    ], $this->inspector))->toThrow(DomainException::class);
});

it('submitQc() throws DomainException when qty_passed + qty_failed !== qty_received', function () {
    expect(fn () => $this->service->submitQc($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'qty_passed' => 5,
            'qty_failed' => 3,
        ]],
    ], $this->inspector))->toThrow(DomainException::class);
});

it('submitQc() throws DomainException when qty_passed + qty_failed > qty_received', function () {
    expect(fn () => $this->service->submitQc($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'qty_passed' => 7,
            'qty_failed' => 5,
        ]],
    ], $this->inspector))->toThrow(DomainException::class);
});

it('submitQc() throws DomainException for invalid goods_receipt_line_id', function () {
    expect(fn () => $this->service->submitQc($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => 999,
            'qty_passed' => 10,
            'qty_failed' => 0,
        ]],
    ], $this->inspector))->toThrow(DomainException::class);
});

// ── submitQc: Transaction Safety (TOCTOU) ──────────────────────────────────────

it('submitQc() rolls back all changes if validation fails mid-transaction', function () {
    $poLine2 = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $this->po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'qty_received' => 5,
    ]);

    $grnLine2 = GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $this->grn->id,
        'purchase_order_line_id' => $poLine2->id,
        'qty_received' => 5,
    ]);

    try {
        $this->service->submitQc($this->grn, [
            'lines' => [
                [
                    'goods_receipt_line_id' => $this->grnLine->id,
                    'qty_passed' => 10,
                    'qty_failed' => 0,
                ],
                [
                    'goods_receipt_line_id' => $grnLine2->id,
                    'qty_passed' => 3,
                    'qty_failed' => 1, // 3+1=4 ≠ 5, triggers DomainException
                ],
            ],
        ], $this->inspector);
    } catch (DomainException) {
        // Expected
    }

    // First line should NOT have been updated (transaction rolled back)
    $this->assertDatabaseHas('goods_receipt_lines', [
        'id' => $this->grnLine->id,
        'qty_passed' => null, // Not updated
    ]);
});
