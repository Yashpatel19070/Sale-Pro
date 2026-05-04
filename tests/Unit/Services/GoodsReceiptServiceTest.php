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

    $this->service = new GoodsReceiptService;
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

// ── store() ───────────────────────────────────────────────────────────────────

it('store() creates a GRN with grn_number matching GRN-year-XXXX and status draft', function () {
    $data = [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [[
            'purchase_order_line_id' => $this->poLine->id,
            'qty_received' => 5,
            'notes' => null,
        ]],
    ];

    $grn = $this->service->store($this->po, $data, $this->user);

    expect($grn->grn_number)->toMatch('/^GRN-\d{4}-\d{4}$/');
    expect($grn->status)->toBe(GoodsReceiptStatus::Draft);
});

it('store() creates GRN lines', function () {
    $data = [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [[
            'purchase_order_line_id' => $this->poLine->id,
            'qty_received' => 5,
            'notes' => null,
        ]],
    ];

    $grn = $this->service->store($this->po, $data, $this->user);

    expect($grn->lines()->count())->toBe(1);
});

it('store() does NOT update qty_received on PO lines', function () {
    $data = [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [[
            'purchase_order_line_id' => $this->poLine->id,
            'qty_received' => 10,
            'notes' => null,
        ]],
    ];

    $this->service->store($this->po, $data, $this->user);

    $this->assertDatabaseHas('purchase_order_lines', [
        'id' => $this->poLine->id,
        'qty_received' => 0,
    ]);
});

it('store() throws DomainException when PO status is draft', function () {
    $draftPo = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $draftPo->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
    ]);

    $data = [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [[
            'purchase_order_line_id' => $poLine->id,
            'qty_received' => 5,
            'notes' => null,
        ]],
    ];

    expect(fn () => $this->service->store($draftPo, $data, $this->user))
        ->toThrow(DomainException::class);
});

it('store() throws DomainException when qty_received exceeds remaining', function () {
    $data = [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [[
            'purchase_order_line_id' => $this->poLine->id,
            'qty_received' => 999,
            'notes' => null,
        ]],
    ];

    expect(fn () => $this->service->store($this->po, $data, $this->user))
        ->toThrow(DomainException::class);
});

// ── update() ──────────────────────────────────────────────────────────────────

it('update() replaces GRN lines', function () {
    $data = [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [[
            'purchase_order_line_id' => $this->poLine->id,
            'qty_received' => 3,
            'notes' => null,
        ]],
    ];

    $grn = $this->service->store($this->po, $data, $this->user);
    $oldLineId = $grn->lines()->first()->id;

    $updateData = [
        'received_date' => now()->toDateString(),
        'notes' => 'Updated',
        'lines' => [[
            'purchase_order_line_id' => $this->poLine->id,
            'qty_received' => 5,
            'notes' => null,
        ]],
    ];

    $updated = $this->service->update($grn, $updateData);

    $this->assertDatabaseMissing('goods_receipt_lines', ['id' => $oldLineId]);
    expect($updated->lines()->count())->toBe(1);
    $this->assertDatabaseHas('goods_receipt_lines', [
        'goods_receipt_id' => $grn->id,
        'qty_received' => 5,
    ]);
});

it('update() throws DomainException when status is complete', function () {
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    $updateData = [
        'received_date' => now()->toDateString(),
        'notes' => null,
        'lines' => [[
            'purchase_order_line_id' => $this->poLine->id,
            'qty_received' => 1,
            'notes' => null,
        ]],
    ];

    expect(fn () => $this->service->update($grn, $updateData))
        ->toThrow(DomainException::class);
});

// ── complete() ────────────────────────────────────────────────────────────────

it('complete() updates GRN status to complete', function () {
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn->id,
        'purchase_order_line_id' => $this->poLine->id,
        'qty_received' => 5,
    ]);

    $result = $this->service->complete($grn);

    expect($result->status)->toBe(GoodsReceiptStatus::Complete);
});

it('complete() increments qty_received on PO lines', function () {
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn->id,
        'purchase_order_line_id' => $this->poLine->id,
        'qty_received' => 5,
    ]);

    $this->service->complete($grn);

    $this->assertDatabaseHas('purchase_order_lines', [
        'id' => $this->poLine->id,
        'qty_received' => 5,
    ]);
});

it('complete() does not count draft GRN qty — only completed GRNs are counted', function () {
    // Draft GRN 1 — will be completed
    $grn1 = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn1->id,
        'purchase_order_line_id' => $this->poLine->id,
        'qty_received' => 4,
    ]);

    // Draft GRN 2 — stays draft
    $grn2 = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn2->id,
        'purchase_order_line_id' => $this->poLine->id,
        'qty_received' => 6,
    ]);

    // Only complete grn1
    $this->service->complete($grn1);

    // qty_received should only reflect the completed GRN (4), not grn2 (6)
    $this->assertDatabaseHas('purchase_order_lines', [
        'id' => $this->poLine->id,
        'qty_received' => 4,
    ]);
});

it('complete() sets PO status to partially_received for a partial delivery', function () {
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn->id,
        'purchase_order_line_id' => $this->poLine->id,
        'qty_received' => 5, // 5 of 10
    ]);

    $this->service->complete($grn);

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $this->po->id,
        'status' => PurchaseOrderStatus::PartiallyReceived->value,
    ]);
});

it('complete() sets PO status to received for a full delivery', function () {
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $grn->id,
        'purchase_order_line_id' => $this->poLine->id,
        'qty_received' => 10, // 10 of 10
    ]);

    $this->service->complete($grn);

    $this->assertDatabaseHas('purchase_orders', [
        'id' => $this->po->id,
        'status' => PurchaseOrderStatus::Received->value,
    ]);
});

// ── delete() ──────────────────────────────────────────────────────────────────

it('delete() soft deletes a draft GRN', function () {
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Draft,
    ]);

    $this->service->delete($grn);

    $this->assertSoftDeleted('goods_receipts', ['id' => $grn->id]);
});

it('delete() throws DomainException when status is complete', function () {
    $grn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $this->po->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    expect(fn () => $this->service->delete($grn))
        ->toThrow(DomainException::class);
});
