<?php

declare(strict_types=1);

use App\Enums\ListingVisibility;
use App\Models\Product;
use App\Models\ProductListing;
use App\Models\ProductListingSlugRedirect;
use App\Services\ProductListingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ProductListingService;
});

it('creates a listing and generates slug from sku + title', function () {
    $product = Product::factory()->create(['sku' => 'TSHIRT-001']);

    $listing = $this->service->create([
        'product_id' => $product->id,
        'title' => 'Blue / M',
        'visibility' => ListingVisibility::Draft->value,
        'is_active' => true,
    ]);

    expect($listing->slug)->toBe('tshirt-001-blue-m');
    expect($listing->title)->toBe('Blue / M');
});

it('appends suffix on slug collision', function () {
    $product = Product::factory()->create(['sku' => 'PROD-001']);

    $this->service->create([
        'product_id' => $product->id,
        'title' => 'Standard',
        'visibility' => ListingVisibility::Draft->value,
        'is_active' => true,
    ]);

    $second = $this->service->create([
        'product_id' => $product->id,
        'title' => 'Standard',
        'visibility' => ListingVisibility::Draft->value,
        'is_active' => true,
    ]);

    expect($second->slug)->toBe('prod-001-standard-1');
});

it('regenerates slug on title change and saves old slug as redirect', function () {
    $product = Product::factory()->create(['sku' => 'PROD-001']);
    $listing = $this->service->create([
        'product_id' => $product->id,
        'title' => 'Original',
        'visibility' => ListingVisibility::Draft->value,
        'is_active' => true,
    ]);

    $oldSlug = $listing->slug;

    $this->service->update($listing, ['title' => 'Renamed', 'visibility' => ListingVisibility::Draft->value]);

    $listing->refresh();
    expect($listing->slug)->not->toBe($oldSlug);
    expect(ProductListingSlugRedirect::where('old_slug', $oldSlug)->exists())->toBeTrue();
});

it('does not regenerate slug when title unchanged', function () {
    $product = Product::factory()->create(['sku' => 'PROD-001']);
    $listing = $this->service->create([
        'product_id' => $product->id,
        'title' => 'Stable',
        'visibility' => ListingVisibility::Draft->value,
        'is_active' => true,
    ]);

    $originalSlug = $listing->slug;

    $this->service->update($listing, ['title' => 'Stable', 'visibility' => ListingVisibility::Public->value]);

    $listing->refresh();
    expect($listing->slug)->toBe($originalSlug);
    expect(ProductListingSlugRedirect::count())->toBe(0);
});

it('toggleVisibility switches public to draft', function () {
    $listing = ProductListing::factory()->public()->create();

    $result = $this->service->toggleVisibility($listing);

    expect($result->visibility)->toBe(ListingVisibility::Draft);
});

it('toggleVisibility switches draft to public', function () {
    $listing = ProductListing::factory()->create(['visibility' => ListingVisibility::Draft->value]);

    $result = $this->service->toggleVisibility($listing);

    expect($result->visibility)->toBe(ListingVisibility::Public);
});

it('soft deletes a listing', function () {
    $listing = ProductListing::factory()->create();

    $this->service->delete($listing);

    $this->assertSoftDeleted('product_listings', ['id' => $listing->id]);
});

it('restores a soft-deleted listing', function () {
    $listing = ProductListing::factory()->create();
    $listing->delete();

    $this->service->restore($listing);

    $this->assertDatabaseHas('product_listings', ['id' => $listing->id, 'deleted_at' => null]);
});

it('strips product_id on update — immutable after creation', function () {
    $product = Product::factory()->create(['sku' => 'PROD-001']);
    $other = Product::factory()->create(['sku' => 'OTHER-001']);
    $listing = $this->service->create([
        'product_id' => $product->id,
        'title' => 'Original',
        'visibility' => ListingVisibility::Draft->value,
        'is_active' => true,
    ]);

    $this->service->update($listing, [
        'product_id' => $other->id,
        'title' => 'Original',
        'visibility' => ListingVisibility::Public->value,
    ]);

    $listing->refresh();
    expect($listing->product_id)->toBe($product->id);
});
