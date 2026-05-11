<?php

declare(strict_types=1);

use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryMovementService;
use Database\Seeders\PurchaseOrderPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PurchaseOrderPermissionSeeder::class);

    $this->service = app(InventoryMovementService::class);
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
    $this->location = InventoryLocation::factory()->create();
    $this->user = User::factory()->create();

    $this->po = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Received,
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
        'qty_passed' => 8,
        'qty_failed' => 2,
    ]);
});

// ── bulkReceiveFromGrn: Happy Path ────────────────────────────────────────────

it('bulkReceiveFromGrn() creates InventorySerial for each qty_passed unit', function () {
    $serials = $this->service->bulkReceiveFromGrn($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'inventory_location_id' => $this->location->id,
            'purchase_price' => 100.00,
        ]],
    ], $this->user);

    expect($serials)->toHaveCount(8);
    expect($serials->every(fn ($s) => $s instanceof InventorySerial))->toBeTrue();
});

it('bulkReceiveFromGrn() creates InventoryMovement for each serial with goods_receipt_id', function () {
    $this->service->bulkReceiveFromGrn($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'inventory_location_id' => $this->location->id,
            'purchase_price' => 100.00,
        ]],
    ], $this->user);

    $movements = InventoryMovement::where('goods_receipt_id', $this->grn->id)->get();
    expect($movements)->toHaveCount(8);
    expect($movements->every(fn ($m) => $m->goods_receipt_id === $this->grn->id))->toBeTrue();
});

it('bulkReceiveFromGrn() skips lines with qty_passed = 0', function () {
    $this->grnLine->update([
        'qty_passed' => 0,
        'qty_failed' => 10,
    ]);

    $serials = $this->service->bulkReceiveFromGrn($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'inventory_location_id' => $this->location->id,
            'purchase_price' => 100.00,
        ]],
    ], $this->user);

    expect($serials)->toHaveCount(0);
    $this->assertDatabaseCount('inventory_serials', 0);
});

it('bulkReceiveFromGrn() returns Collection of InventorySerial', function () {
    $result = $this->service->bulkReceiveFromGrn($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'inventory_location_id' => $this->location->id,
            'purchase_price' => 100.00,
        ]],
    ], $this->user);

    expect($result)->toBeInstanceOf(Collection::class);
});

it('bulkReceiveFromGrn() with multiple lines', function () {
    $product2 = Product::factory()->create();

    $poLine2 = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $this->po->id,
        'product_id' => $product2->id,
        'qty_ordered' => 5,
        'qty_received' => 5,
    ]);

    $grnLine2 = GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $this->grn->id,
        'purchase_order_line_id' => $poLine2->id,
        'qty_received' => 5,
        'qty_passed' => 3,
        'qty_failed' => 2,
    ]);

    $serials = $this->service->bulkReceiveFromGrn($this->grn, [
        'lines' => [
            [
                'goods_receipt_line_id' => $this->grnLine->id,
                'inventory_location_id' => $this->location->id,
                'purchase_price' => 100.00,
            ],
            [
                'goods_receipt_line_id' => $grnLine2->id,
                'inventory_location_id' => $this->location->id,
                'purchase_price' => 50.00,
            ],
        ],
    ], $this->user);

    expect($serials)->toHaveCount(11); // 8 + 3
});

it('bulkReceiveFromGrn() creates serials in correct location', function () {
    $location2 = InventoryLocation::factory()->create();

    $serials = $this->service->bulkReceiveFromGrn($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'inventory_location_id' => $location2->id,
            'purchase_price' => 100.00,
        ]],
    ], $this->user);

    foreach ($serials as $serial) {
        $this->assertDatabaseHas('inventory_serials', [
            'id' => $serial->id,
            'inventory_location_id' => $location2->id,
        ]);
    }
});

it('bulkReceiveFromGrn() sets purchase_price on movements', function () {
    $this->service->bulkReceiveFromGrn($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'inventory_location_id' => $this->location->id,
            'purchase_price' => 123.45,
        ]],
    ], $this->user);

    $movements = InventoryMovement::where('goods_receipt_id', $this->grn->id)->get();
    expect($movements->every(fn ($m) => (float) $m->purchase_price === 123.45))->toBeTrue();
});

// ── bulkReceiveFromGrn: Validation Failures ───────────────────────────────────

it('bulkReceiveFromGrn() throws DomainException when QC not submitted on any line', function () {
    $this->grnLine->update([
        'qty_passed' => null,
        'qty_failed' => null,
    ]);

    expect(fn () => $this->service->bulkReceiveFromGrn($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'inventory_location_id' => $this->location->id,
            'purchase_price' => 100.00,
        ]],
    ], $this->user))->toThrow(DomainException::class);
});

it('bulkReceiveFromGrn() throws DomainException when PO status is not partially_received or received', function () {
    $approvedPo = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Approved,
    ]);

    $approvedPoLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $approvedPo->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'qty_received' => 5,
    ]);

    $approvedGrn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $approvedPo->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    $approvedGrnLine = GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $approvedGrn->id,
        'purchase_order_line_id' => $approvedPoLine->id,
        'qty_received' => 5,
        'qty_passed' => 5,
        'qty_failed' => 0,
    ]);

    expect(fn () => $this->service->bulkReceiveFromGrn($approvedGrn, [
        'lines' => [[
            'goods_receipt_line_id' => $approvedGrnLine->id,
            'inventory_location_id' => $this->location->id,
            'purchase_price' => 100.00,
        ]],
    ], $this->user))->toThrow(DomainException::class);
});

it('bulkReceiveFromGrn() throws DomainException when PO status is draft', function () {
    $draftPo = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $draftPoLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $draftPo->id,
        'product_id' => $this->product->id,
        'qty_ordered' => 5,
        'qty_received' => 0,
    ]);

    $draftGrn = GoodsReceipt::factory()->create([
        'purchase_order_id' => $draftPo->id,
        'status' => GoodsReceiptStatus::Complete,
    ]);

    $draftGrnLine = GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $draftGrn->id,
        'purchase_order_line_id' => $draftPoLine->id,
        'qty_received' => 5,
        'qty_passed' => 5,
        'qty_failed' => 0,
    ]);

    expect(fn () => $this->service->bulkReceiveFromGrn($draftGrn, [
        'lines' => [[
            'goods_receipt_line_id' => $draftGrnLine->id,
            'inventory_location_id' => $this->location->id,
            'purchase_price' => 100.00,
        ]],
    ], $this->user))->toThrow(DomainException::class);
});

it('bulkReceiveFromGrn() throws DomainException when GRN line not found', function () {
    expect(fn () => $this->service->bulkReceiveFromGrn($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => 999,
            'inventory_location_id' => $this->location->id,
            'purchase_price' => 100.00,
        ]],
    ], $this->user))->toThrow(DomainException::class);
});

// ── bulkReceiveFromGrn: Serial Details ────────────────────────────────────────

it('bulkReceiveFromGrn() creates serials with correct serial_number format', function () {
    $serials = $this->service->bulkReceiveFromGrn($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'inventory_location_id' => $this->location->id,
            'purchase_price' => 100.00,
        ]],
    ], $this->user);

    expect($serials->every(fn ($s) => preg_match('/^SN-\d{4}-\d{6}$/', $s->serial_number)))->toBeTrue();
});

it('bulkReceiveFromGrn() creates serials with correct product_id', function () {
    $serials = $this->service->bulkReceiveFromGrn($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'inventory_location_id' => $this->location->id,
            'purchase_price' => 100.00,
        ]],
    ], $this->user);

    foreach ($serials as $serial) {
        $this->assertDatabaseHas('inventory_serials', [
            'id' => $serial->id,
            'product_id' => $this->product->id,
        ]);
    }
});

it('bulkReceiveFromGrn() creates serials with InStock status', function () {
    $serials = $this->service->bulkReceiveFromGrn($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'inventory_location_id' => $this->location->id,
            'purchase_price' => 100.00,
        ]],
    ], $this->user);

    foreach ($serials as $serial) {
        $this->assertDatabaseHas('inventory_serials', [
            'id' => $serial->id,
            'status' => 'in_stock',
        ]);
    }
});

it('bulkReceiveFromGrn() creates movements with correct type', function () {
    $this->service->bulkReceiveFromGrn($this->grn, [
        'lines' => [[
            'goods_receipt_line_id' => $this->grnLine->id,
            'inventory_location_id' => $this->location->id,
            'purchase_price' => 100.00,
        ]],
    ], $this->user);

    $movements = InventoryMovement::where('goods_receipt_id', $this->grn->id)->get();
    expect($movements->every(fn ($m) => $m->type->value === 'receive'))->toBeTrue();
});

// ── bulkReceiveFromGrn: Transaction Safety ────────────────────────────────────

it('bulkReceiveFromGrn() wraps all operations in a transaction', function () {
    $product2 = Product::factory()->create();

    $poLine2 = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $this->po->id,
        'product_id' => $product2->id,
        'qty_ordered' => 5,
        'qty_received' => 5,
    ]);

    $grnLine2 = GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $this->grn->id,
        'purchase_order_line_id' => $poLine2->id,
        'qty_received' => 5,
        'qty_passed' => 3,
        'qty_failed' => 2,
    ]);

    try {
        $this->service->bulkReceiveFromGrn($this->grn, [
            'lines' => [
                [
                    'goods_receipt_line_id' => $this->grnLine->id,
                    'inventory_location_id' => $this->location->id,
                    'purchase_price' => 100.00,
                ],
                [
                    'goods_receipt_line_id' => 999, // Invalid line — causes exception
                    'inventory_location_id' => $this->location->id,
                    'purchase_price' => 50.00,
                ],
            ],
        ], $this->user);
    } catch (DomainException) {
        // Expected
    }

    // No serials should have been created if transaction rolled back
    // (But actually, first line is processed before second line fails)
    // So let's verify by checking the line ID is found
    $this->assertDatabaseCount('inventory_serials', 0);
});

// ── bulkReceiveFromGrn: Edge Cases ────────────────────────────────────────────

it('bulkReceiveFromGrn() handles qty_passed = 0 on some lines but not all', function () {
    $product2 = Product::factory()->create();

    $poLine2 = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $this->po->id,
        'product_id' => $product2->id,
        'qty_ordered' => 5,
        'qty_received' => 5,
    ]);

    $grnLine2 = GoodsReceiptLine::factory()->create([
        'goods_receipt_id' => $this->grn->id,
        'purchase_order_line_id' => $poLine2->id,
        'qty_received' => 5,
        'qty_passed' => 0, // No passed units
        'qty_failed' => 5,
    ]);

    $serials = $this->service->bulkReceiveFromGrn($this->grn, [
        'lines' => [
            [
                'goods_receipt_line_id' => $this->grnLine->id,
                'inventory_location_id' => $this->location->id,
                'purchase_price' => 100.00,
            ],
            [
                'goods_receipt_line_id' => $grnLine2->id,
                'inventory_location_id' => $this->location->id,
                'purchase_price' => 50.00,
            ],
        ],
    ], $this->user);

    expect($serials)->toHaveCount(8); // Only from first line
});
