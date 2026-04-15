<?php

declare(strict_types=1);

use App\Enums\SerialStatus;
use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function inStock(array $attrs = []): InventorySerial
{
    return InventorySerial::factory()->create(array_merge(
        ['status' => SerialStatus::InStock],
        $attrs,
    ));
}

function inventoryService(): InventoryService
{
    return new InventoryService;
}

// ── overview() ────────────────────────────────────────────────────────────────

it('overview returns empty collection when no in_stock serials exist', function () {
    expect(inventoryService()->overview())->toBeEmpty();
});

it('overview groups in_stock serials by product_id', function () {
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    inStock(['product_id' => $product1->id, 'inventory_location_id' => $location->id]);
    inStock(['product_id' => $product1->id, 'inventory_location_id' => $location->id]);
    inStock(['product_id' => $product2->id, 'inventory_location_id' => $location->id]);

    $result = inventoryService()->overview();

    expect($result)->toHaveCount(2)
        ->and($result->get($product1->id)->count())->toBe(2)
        ->and($result->get($product2->id)->count())->toBe(1);
});

it('overview excludes sold serials', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id]);
    InventorySerial::factory()->create([
        'product_id' => $product->id,
        'inventory_location_id' => $location->id,
        'status' => SerialStatus::Sold,
    ]);

    expect(inventoryService()->overview()->get($product->id)->count())->toBe(1);
});

it('overview excludes damaged and missing serials', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    InventorySerial::factory()->create([
        'product_id' => $product->id, 'inventory_location_id' => $location->id,
        'status' => SerialStatus::Damaged,
    ]);
    InventorySerial::factory()->create([
        'product_id' => $product->id, 'inventory_location_id' => $location->id,
        'status' => SerialStatus::Missing,
    ]);

    expect(inventoryService()->overview())->toBeEmpty();
});

it('overview eager loads the product relation', function () {
    $product = Product::factory()->create(['sku' => 'TEST-SKU']);
    $location = InventoryLocation::factory()->create();

    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id]);

    $serial = inventoryService()->overview()->first()->first();

    expect($serial->relationLoaded('product'))->toBeTrue()
        ->and($serial->product->sku)->toBe('TEST-SKU');
});

// ── stockBySku() ──────────────────────────────────────────────────────────────

it('stockBySku returns empty collection when product has no in_stock serials', function () {
    $product = Product::factory()->create();

    expect(inventoryService()->stockBySku($product))->toBeEmpty();
});

it('stockBySku groups in_stock serials by inventory_location_id', function () {
    $product = Product::factory()->create();
    $locationA = InventoryLocation::factory()->create();
    $locationB = InventoryLocation::factory()->create();

    inStock(['product_id' => $product->id, 'inventory_location_id' => $locationA->id]);
    inStock(['product_id' => $product->id, 'inventory_location_id' => $locationA->id]);
    inStock(['product_id' => $product->id, 'inventory_location_id' => $locationB->id]);

    $result = inventoryService()->stockBySku($product);

    expect($result)->toHaveCount(2)
        ->and($result->get($locationA->id)->count())->toBe(2)
        ->and($result->get($locationB->id)->count())->toBe(1);
});

it('stockBySku excludes serials from other products', function () {
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    inStock(['product_id' => $productA->id, 'inventory_location_id' => $location->id]);
    inStock(['product_id' => $productB->id, 'inventory_location_id' => $location->id]);

    expect(inventoryService()->stockBySku($productA)->flatten()->count())->toBe(1);
});

it('stockBySku excludes non-in_stock serials', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id]);
    InventorySerial::factory()->create([
        'product_id' => $product->id,
        'inventory_location_id' => $location->id,
        'status' => SerialStatus::Sold,
    ]);

    expect(inventoryService()->stockBySku($product)->get($location->id)->count())->toBe(1);
});

it('stockBySku eager loads the location relation', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create(['code' => 'ZONE-X']);

    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id]);

    $serial = inventoryService()->stockBySku($product)->first()->first();

    expect($serial->relationLoaded('location'))->toBeTrue()
        ->and($serial->location->code)->toBe('ZONE-X');
});

// ── stockBySkuAtLocation() ────────────────────────────────────────────────────

it('stockBySkuAtLocation returns empty collection when no in_stock serials match', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    expect(inventoryService()->stockBySkuAtLocation($product, $location))->toBeEmpty();
});

it('stockBySkuAtLocation returns in_stock serials for the given product and location', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'SN-001']);
    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'SN-002']);

    expect(inventoryService()->stockBySkuAtLocation($product, $location)->count())->toBe(2);
});

it('stockBySkuAtLocation excludes serials from other products', function () {
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    inStock(['product_id' => $productA->id, 'inventory_location_id' => $location->id]);
    inStock(['product_id' => $productB->id, 'inventory_location_id' => $location->id]);

    expect(inventoryService()->stockBySkuAtLocation($productA, $location)->count())->toBe(1);
});

it('stockBySkuAtLocation excludes serials from other locations', function () {
    $product = Product::factory()->create();
    $locationA = InventoryLocation::factory()->create();
    $locationB = InventoryLocation::factory()->create();

    inStock(['product_id' => $product->id, 'inventory_location_id' => $locationA->id]);
    inStock(['product_id' => $product->id, 'inventory_location_id' => $locationB->id]);

    expect(inventoryService()->stockBySkuAtLocation($product, $locationA)->count())->toBe(1);
});

it('stockBySkuAtLocation excludes non-in_stock serials', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'LIVE-SN']);
    InventorySerial::factory()->create([
        'product_id' => $product->id,
        'inventory_location_id' => $location->id,
        'serial_number' => 'SOLD-SN',
        'status' => SerialStatus::Sold,
    ]);

    $result = inventoryService()->stockBySkuAtLocation($product, $location);

    expect($result->count())->toBe(1)
        ->and($result->first()->serial_number)->toBe('LIVE-SN');
});

it('stockBySkuAtLocation orders results by serial_number', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'ZZZ-003']);
    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'AAA-001']);

    $result = inventoryService()->stockBySkuAtLocation($product, $location);

    expect($result->first()->serial_number)->toBe('AAA-001')
        ->and($result->last()->serial_number)->toBe('ZZZ-003');
});

it('stockBySkuAtLocation eager loads product and location relations', function () {
    $product = Product::factory()->create(['sku' => 'EAGER-SKU']);
    $location = InventoryLocation::factory()->create(['code' => 'EAGER-LOC']);

    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id]);

    $serial = inventoryService()->stockBySkuAtLocation($product, $location)->first();

    expect($serial->relationLoaded('product'))->toBeTrue()
        ->and($serial->product->sku)->toBe('EAGER-SKU')
        ->and($serial->relationLoaded('location'))->toBeTrue()
        ->and($serial->location->code)->toBe('EAGER-LOC');
});
