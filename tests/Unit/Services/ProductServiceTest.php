<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductListing;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ProductService;
});

it('creates a product and uppercases sku', function () {
    $product = $this->service->create([
        'sku' => 'abc-001', 'name' => 'Test', 'regular_price' => 10.00,
    ]);

    expect($product->sku)->toBe('ABC-001')
        ->and($product->name)->toBe('Test');
});

it('updates sku and uppercases it', function () {
    $product = Product::factory()->create(['sku' => 'OLD-SKU']);

    $this->service->update($product, ['sku' => 'new-sku', 'name' => 'New', 'regular_price' => 5]);

    expect($product->fresh()->sku)->toBe('NEW-SKU');
});

it('throws when deleting product with active listings', function () {
    $product = Product::factory()->create();
    ProductListing::factory()->forProduct($product)->public()->create();

    expect(fn () => $this->service->delete($product))
        ->toThrow(RuntimeException::class);
});

it('soft-deletes inactive listings when deleting product', function () {
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->forProduct($product)->create(['is_active' => false]);

    $this->service->delete($product);

    $this->assertSoftDeleted('product_listings', ['id' => $listing->id]);
    $this->assertSoftDeleted('products', ['id' => $product->id]);
});

it('toggleActive flips is_active', function () {
    $product = Product::factory()->create(['is_active' => true]);

    $result = $this->service->toggleActive($product);
    expect($result->is_active)->toBeFalse();

    $result = $this->service->toggleActive($result);
    expect($result->is_active)->toBeTrue();
});

it('restore undeletes a soft-deleted product', function () {
    $product = Product::factory()->create();
    $product->delete();

    $trashed = Product::onlyTrashed()->findOrFail($product->id);
    $restored = $this->service->restore($trashed);
    expect($restored->deleted_at)->toBeNull();
});

it('dropdown returns only active products', function () {
    Product::factory()->count(3)->create(['is_active' => true]);
    Product::factory()->count(2)->inactive()->create();

    $results = $this->service->dropdown();
    expect($results)->toHaveCount(3);
});

it('currentPrice returns sale_price when set', function () {
    $product = Product::factory()->onSale(9.99)->create(['regular_price' => 19.99]);
    expect($product->currentPrice())->toBe('9.99');
});

it('currentPrice returns regular_price when no sale', function () {
    $product = Product::factory()->create(['regular_price' => 19.99, 'sale_price' => null]);
    expect($product->currentPrice())->toBe('19.99');
});
