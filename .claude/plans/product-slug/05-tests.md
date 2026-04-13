# Product Slug Feature — Tests

## Dependencies

- **Requires:** All previous files in this plan
- **Requires:** `ProductListingFactory` with `forProduct()` state (in `product-list/02-model.md`)
- **Adds to:** `tests/Unit/Services/ProductListingServiceTest.php` — slug unit tests
- **Adds to:** `tests/Feature/ProductListingControllerTest.php` — portal route tests

> Full CRUD controller + service tests are in `product-list/07-tests.md`.
> That file has the complete test file headers (`declare(strict_types=1)`, imports,
> `uses(RefreshDatabase::class)`, `beforeEach` seeding roles + permissions).
> The tests below are added to those existing files — not standalone files.
> This file only covers slug-specific tests.

---

## Unit Tests — Slug Behaviour

`tests/Unit/Services/ProductListingServiceTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductListing;
use App\Models\ProductListingSlugRedirect;
use App\Services\ProductListingService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ProductListingService();
});

// ── create() — slug generation ─────────────────────────────────────────────

it('generates slug from product sku and title on create', function () {
    $product = Product::factory()->create(['sku' => 'TSHIRT-001']);

    $listing = $this->service->create([
        'product_id' => $product->id,
        'title'      => 'Blue / M',
        'visibility' => 'draft',
    ]);

    expect($listing->slug)->toBe('tshirt-001-blue-m');
});

it('appends numeric suffix on slug collision', function () {
    $product = Product::factory()->create(['sku' => 'TSHIRT-001']);

    $this->service->create(['product_id' => $product->id, 'title' => 'Blue / M', 'visibility' => 'draft']);
    $second = $this->service->create(['product_id' => $product->id, 'title' => 'Blue / M', 'visibility' => 'draft']);

    expect($second->slug)->toBe('tshirt-001-blue-m-2');
});

// ── update() — slug regen + redirect ──────────────────────────────────────

it('regenerates slug when title changes', function () {
    $product = Product::factory()->create(['sku' => 'TSHIRT-001']);
    $listing = $this->service->create(['product_id' => $product->id, 'title' => 'Blue / M', 'visibility' => 'draft']);

    $updated = $this->service->update($listing, ['title' => 'Blue / Medium', 'visibility' => 'draft']);

    expect($updated->slug)->toBe('tshirt-001-blue-medium');
});

it('saves old slug to redirects table when title changes', function () {
    $product = Product::factory()->create(['sku' => 'TSHIRT-001']);
    $listing = $this->service->create(['product_id' => $product->id, 'title' => 'Blue / M', 'visibility' => 'draft']);

    $this->service->update($listing, ['title' => 'Blue / Medium', 'visibility' => 'draft']);

    $this->assertDatabaseHas('product_listing_slug_redirects', [
        'old_slug'   => 'tshirt-001-blue-m',
        'listing_id' => $listing->id,
    ]);
});

it('does not create redirect when title is unchanged', function () {
    $product = Product::factory()->create(['sku' => 'TSHIRT-001']);
    $listing = $this->service->create(['product_id' => $product->id, 'title' => 'Blue / M', 'visibility' => 'draft']);

    $this->service->update($listing, ['title' => 'Blue / M', 'visibility' => 'public']);

    expect(ProductListingSlugRedirect::count())->toBe(0);
});

it('does not create duplicate redirect on repeated title change to same value', function () {
    $product = Product::factory()->create(['sku' => 'TSHIRT-001']);
    $listing = $this->service->create(['product_id' => $product->id, 'title' => 'Blue / M', 'visibility' => 'draft']);

    $this->service->update($listing->fresh(), ['title' => 'Blue / Medium', 'visibility' => 'draft']);
    $this->service->update($listing->fresh(), ['title' => 'Blue / M',      'visibility' => 'draft']);

    expect(ProductListingSlugRedirect::where('old_slug', 'tshirt-001-blue-m')->count())->toBe(1);
});

// ── regenerateSlugsForProduct() — SKU change ──────────────────────────────

it('regenerates all listing slugs when product sku changes', function () {
    $product = Product::factory()->create(['sku' => 'TSHIRT-001']);
    $service = new ProductListingService();

    $listing = $service->create(['product_id' => $product->id, 'title' => 'Blue / M', 'visibility' => 'draft']);
    expect($listing->slug)->toBe('tshirt-001-blue-m');

    $product->update(['sku' => 'TSHIRT-XL-001']);
    $service->regenerateSlugsForProduct($product->fresh());

    expect($listing->fresh()->slug)->toBe('tshirt-xl-001-blue-m');
});

it('saves redirect records for all listings when sku changes', function () {
    $product  = Product::factory()->create(['sku' => 'TSHIRT-001']);
    $service  = new ProductListingService();
    $listing  = $service->create(['product_id' => $product->id, 'title' => 'Blue / M', 'visibility' => 'draft']);

    $product->update(['sku' => 'TSHIRT-XL-001']);
    $service->regenerateSlugsForProduct($product->fresh());

    $this->assertDatabaseHas('product_listing_slug_redirects', [
        'old_slug'   => 'tshirt-001-blue-m',
        'listing_id' => $listing->id,
    ]);
});

it('skips trashed listings during sku regen', function () {
    $product = Product::factory()->create(['sku' => 'TSHIRT-001']);
    $service = new ProductListingService();
    $listing = $service->create(['product_id' => $product->id, 'title' => 'Blue / M', 'visibility' => 'draft']);

    $listing->delete(); // soft delete

    $product->update(['sku' => 'TSHIRT-XL-001']);
    $service->regenerateSlugsForProduct($product->fresh());

    // Trashed listing slug should NOT be updated
    expect($listing->fresh()->slug)->toBe('tshirt-001-blue-m');
    expect(ProductListingSlugRedirect::count())->toBe(0);
});
```

---

## Feature Tests — Portal Redirect Route

`tests/Feature/ProductListingControllerTest.php`

> Add these tests to the existing file. The file header, imports, `uses(RefreshDatabase::class)`,
> and `beforeEach` seeding are already defined in `product-list/07-tests.md`.

```php
// ── portal slug route ──────────────────────────────────────────────────────

it('portal route serves listing for current slug', function () {
    $product = Product::factory()->create(['sku' => 'WIDGET-001']);
    $service = app(\App\Services\ProductListingService::class);

    $listing = $service->create([
        'product_id' => $product->id,
        'title'      => 'Standard',
        'visibility' => 'public',
        'is_active'  => true,
    ]);

    $this->get(route('portal.shop.listing', $listing->slug))
        ->assertRedirect(route('product-listings.show', $listing));
});

it('portal route 301s an old slug to the current slug', function () {
    $product = Product::factory()->create(['sku' => 'WIDGET-001']);
    $service = app(\App\Services\ProductListingService::class);

    $listing = $service->create([
        'product_id' => $product->id,
        'title'      => 'Standard',
        'visibility' => 'public',
        'is_active'  => true,
    ]);

    $oldSlug = $listing->slug; // "widget-001-standard"
    $service->update($listing, ['title' => 'Standard Edition', 'visibility' => 'public']);

    $this->get(route('portal.shop.listing', $oldSlug))
        ->assertRedirectContains('widget-001-standard-edition')
        ->assertStatus(301);
});

it('portal route returns 404 for unknown slug', function () {
    $this->get(route('portal.shop.listing', 'does-not-exist'))
        ->assertNotFound();
});

it('portal route returns 404 for draft listing', function () {
    $product = Product::factory()->create(['sku' => 'WIDGET-001']);
    $service = app(\App\Services\ProductListingService::class);

    $listing = $service->create([
        'product_id' => $product->id,
        'title'      => 'Draft Listing',
        'visibility' => 'draft',
        'is_active'  => true,
    ]);

    $this->get(route('portal.shop.listing', $listing->slug))
        ->assertNotFound();
});

it('portal route returns 404 for inactive listing', function () {
    $product = Product::factory()->create(['sku' => 'WIDGET-001']);
    $service = app(\App\Services\ProductListingService::class);

    $listing = $service->create([
        'product_id' => $product->id,
        'title'      => 'Inactive',
        'visibility' => 'public',
        'is_active'  => false,
    ]);

    $this->get(route('portal.shop.listing', $listing->slug))
        ->assertNotFound();
});

it('portal route returns 404 when listing is soft deleted', function () {
    $product = Product::factory()->create(['sku' => 'WIDGET-001']);
    $service = app(\App\Services\ProductListingService::class);

    $listing = $service->create([
        'product_id' => $product->id,
        'title'      => 'Active',
        'visibility' => 'public',
        'is_active'  => true,
    ]);

    $oldSlug = $listing->slug;
    $service->update($listing, ['title' => 'Updated', 'visibility' => 'public']);
    $service->delete($listing->fresh());

    // Redirect record exists but listing is soft-deleted — should 404
    $this->get(route('portal.shop.listing', $oldSlug))
        ->assertNotFound();
});
```

---

## Checklist

- [ ] Unit: slug generated as `sku-title` (lowercased, hyphenated)
- [ ] Unit: collision → suffix `-2` appended
- [ ] Unit: title change → new slug + redirect record
- [ ] Unit: title unchanged → no redirect created
- [ ] Unit: `firstOrCreate` — no duplicate redirects
- [ ] Unit: SKU change → all listing slugs regenerated
- [ ] Unit: SKU change → redirect records created per listing
- [ ] Unit: trashed listings skipped during SKU regen
- [ ] Feature: portal current slug → redirect to show
- [ ] Feature: portal old slug → 301 to current slug
- [ ] Feature: portal unknown slug → 404
- [ ] Feature: portal draft listing → 404
- [ ] Feature: portal inactive listing → 404
- [ ] Feature: portal soft-deleted listing → 404
