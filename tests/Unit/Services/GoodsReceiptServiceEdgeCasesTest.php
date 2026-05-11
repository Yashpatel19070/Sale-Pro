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
    $this->user = User::factory()->create();

    $this->po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $this->poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $this->po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);
});

// ── BUG-007: complete() must reject cancelled / rejected POs ──────────────────

it('complete() throws DomainException for a cancelled purchase order', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => PurchaseOrderStatus::Cancelled]);
    $grn = GoodsReceipt::factory()->create(['purchase_order_id' => $po->id, 'status' => GoodsReceiptStatus::Draft]);

    expect(fn () => $this->service->complete($grn, $po))
        ->toThrow(DomainException::class, 'cancelled');
});

it('complete() throws DomainException for a rejected purchase order', function () {
    $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id, 'status' => PurchaseOrderStatus::Rejected]);
    $grn = GoodsReceipt::factory()->create(['purchase_order_id' => $po->id, 'status' => GoodsReceiptStatus::Draft]);

    expect(fn () => $this->service->complete($grn, $po))
        ->toThrow(DomainException::class, 'rejected');
});

it('complete() succeeds for an approved PO and moves it to quality_check', function () {
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn->id,
        'purchase_order_line_id' => $this->poLine->id,
        'qty_received' => 5,
    ]);

    $result = $this->service->complete($grn, $this->po);

    expect($result->status)->toBe(GoodsReceiptStatus::Complete);
    expect($this->po->fresh()->status)->toBe(PurchaseOrderStatus::QualityCheck);
});

// ── BUG-008 (resolved by existing guard): complete GRNs cannot be edited ────────
// QC data is safe because update() already rejects Complete GRNs before any
// line deletion happens — the "Completed GRNs cannot be edited" guard fires first.

it('update() throws DomainException when GRN is already complete (protecting QC data)', function () {
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn->id,
        'purchase_order_line_id' => $this->poLine->id,
        'qty_received' => 5,
        'qty_passed' => 4,
        'qty_failed' => 1,
    ]);

    expect(fn () => $this->service->update($grn, [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [['purchase_order_line_id' => $this->poLine->id, 'qty_received' => 5]],
    ]))->toThrow(DomainException::class, 'Completed goods receipts cannot be edited');
});

it('update() replaces lines and returns updated GRN before QC is submitted', function () {
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
        'notes' => 'old notes',
    ]);

    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn->id,
        'purchase_order_line_id' => $this->poLine->id,
        'qty_received' => 5,
    ]);

    $result = $this->service->update($grn, [
        'received_date' => now()->toDateString(),
        'notes' => 'updated notes',
        'lines' => [['purchase_order_line_id' => $this->poLine->id, 'qty_received' => 3, 'notes' => null]],
    ]);

    expect($result->notes)->toBe('updated notes');
    expect((float) $result->lines->first()->qty_received)->toBe(3.0);
});

// ── BUG-009: store() must reject line IDs not belonging to the PO ─────────────

it('store() throws DomainException when a line ID does not belong to this PO', function () {
    $otherPo = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);
    $otherLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $otherPo->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
    ]);

    expect(fn () => $this->service->store($this->po, [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [['purchase_order_line_id' => $otherLine->id, 'qty_received' => 1]],
    ], $this->user))->toThrow(DomainException::class);
});

// ── BUG-011: submitQc() must block double-submission ─────────────────────────

it('submitQc() throws DomainException when lines already have QC data (double-submit guard)', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::QualityCheck,
    ]);

    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'qty_received' => 5,
    ]);

    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $po->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    // Simulate QC already submitted — qty_passed is set on the line
    $grnLine = GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn->id,
        'purchase_order_line_id' => $poLine->id,
        'qty_received' => 5,
        'qty_passed' => 5,
        'qty_failed' => 0,
        'qc_inspected_at' => now(),
        'qc_inspected_by' => $this->user->id,
    ]);

    expect(fn () => $this->service->submitQc($grn, [
        'lines' => [[
            'goods_receipt_line_id' => $grnLine->id,
            'qty_passed' => 5,
            'qty_failed' => 0,
        ]],
    ], $this->user))->toThrow(DomainException::class, 'QC has already been submitted');
});

// ── BUG-013: updatePoStatus() must not overwrite terminal PO statuses ─────────

it('updatePoStatus() does not overwrite PartiallyReceived status', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::PartiallyReceived,
    ]);

    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 10,
        'qty_received' => 5,
    ]);

    $this->service->updatePoStatus($po);

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived);
});

it('updatePoStatus() does not overwrite Received status', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
    ]);

    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'qty_received' => 5,
    ]);

    $this->service->updatePoStatus($po);

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Received);
});

it('updatePoStatus() does not overwrite Cancelled status', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Cancelled,
    ]);

    $this->service->updatePoStatus($po);

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Cancelled);
});

it('updatePoStatus() sets QualityCheck when PO is in Approved status and lines are received', function () {
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'qty_received' => 5,
    ]);

    $this->service->updatePoStatus($po);

    expect($po->fresh()->status)->toBe(PurchaseOrderStatus::QualityCheck);
});
