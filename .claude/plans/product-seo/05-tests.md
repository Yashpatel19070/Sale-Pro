# ProductSEO Module — Tests

## Feature Tests

File: `tests/Feature/ProductListingSeoTest.php`

> Add as a new test file. Uses the same pattern as `ProductListingControllerTest.php`.

```php
<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductListing;
use App\Models\User;

// ─── Meta Tags ───────────────────────────────────────────────────────────────

it('renders custom meta_title on listing show page', function (): void {
    $listing = ProductListing::factory()
        ->for(Product::factory())
        ->create([
            'visibility'       => 'public',
            'is_active'        => true,
            'meta_title'       => 'Custom SEO Title',
            'meta_description' => 'Custom SEO description.',
        ]);

    $this->get(route('portal.shop.listing', $listing->slug))
        ->assertOk()
        ->assertSee('<title>Custom SEO Title</title>', false)
        ->assertSee('name="description" content="Custom SEO description."', false);
});

it('falls back to listing title when meta_title is null', function (): void {
    $listing = ProductListing::factory()
        ->for(Product::factory())
        ->create([
            'visibility' => 'public',
            'is_active'  => true,
            'meta_title' => null,
        ]);

    $this->get(route('portal.shop.listing', $listing->slug))
        ->assertOk()
        ->assertSee('<title>' . $listing->title . '</title>', false);
});

it('renders canonical url tag on listing show page', function (): void {
    $listing = ProductListing::factory()
        ->for(Product::factory())
        ->create(['visibility' => 'public', 'is_active' => true]);

    $this->get(route('portal.shop.listing', $listing->slug))
        ->assertOk()
        ->assertSee('rel="canonical"', false);
});

it('renders json-ld product schema on listing show page', function (): void {
    $listing = ProductListing::factory()
        ->for(Product::factory())
        ->create(['visibility' => 'public', 'is_active' => true]);

    $this->get(route('portal.shop.listing', $listing->slug))
        ->assertOk()
        ->assertSee('"@type":"Product"', false)
        ->assertSee('"sku":"' . $listing->product->sku . '"', false);
});

// ─── Sitemap ─────────────────────────────────────────────────────────────────

it('sitemap contains public active listings', function (): void {
    $listing = ProductListing::factory()
        ->for(Product::factory())
        ->create(['visibility' => 'public', 'is_active' => true]);

    $this->get(route('portal.sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee($listing->slug);
});

it('sitemap excludes draft listings', function (): void {
    $listing = ProductListing::factory()
        ->for(Product::factory())
        ->create(['visibility' => 'draft', 'is_active' => true]);

    $this->get(route('portal.sitemap'))
        ->assertOk()
        ->assertDontSee($listing->slug);
});

it('sitemap excludes private listings', function (): void {
    $listing = ProductListing::factory()
        ->for(Product::factory())
        ->create(['visibility' => 'private', 'is_active' => true]);

    $this->get(route('portal.sitemap'))
        ->assertOk()
        ->assertDontSee($listing->slug);
});

it('sitemap excludes inactive listings', function (): void {
    $listing = ProductListing::factory()
        ->for(Product::factory())
        ->create(['visibility' => 'public', 'is_active' => false]);

    $this->get(route('portal.sitemap'))
        ->assertOk()
        ->assertDontSee($listing->slug);
});

it('sitemap excludes soft-deleted listings', function (): void {
    $listing = ProductListing::factory()
        ->for(Product::factory())
        ->create(['visibility' => 'public', 'is_active' => true]);

    $listing->delete();

    $this->get(route('portal.sitemap'))
        ->assertOk()
        ->assertDontSee($listing->slug);
});

it('sitemap is accessible without authentication', function (): void {
    $this->get(route('portal.sitemap'))
        ->assertOk();
});
```

---

## Unit Tests

Add to `tests/Unit/Services/ProductListingServiceTest.php`:

> Test via the feature test HTTP layer — artesaos/seotools facades set view state, not return values, so asserting HTML output is more reliable than inspecting facade internals.

```php
it('setSeoForListing uses custom meta_title on show page', function (): void {
    $listing = ProductListing::factory()
        ->for(Product::factory())
        ->create([
            'visibility' => 'public',
            'is_active'  => true,
            'meta_title' => 'My Custom Title',
        ]);

    $this->get(route('portal.shop.listing', $listing->slug))
        ->assertOk()
        ->assertSee('<title>My Custom Title</title>', false);
});

it('setSeoForListing falls back to listing title when meta_title is null', function (): void {
    $listing = ProductListing::factory()
        ->for(Product::factory())
        ->create([
            'visibility' => 'public',
            'is_active'  => true,
            'meta_title' => null,
            'title'      => 'Fallback Title',
        ]);

    $this->get(route('portal.shop.listing', $listing->slug))
        ->assertOk()
        ->assertSee('<title>Fallback Title</title>', false);
});
```

---

## Checklist
- [ ] `ProductListingSeoTest.php` created
- [ ] Meta tag tests: custom title, fallback title, canonical, JSON-LD
- [ ] Sitemap tests: public listings included, draft/private/inactive/deleted excluded
- [ ] Sitemap unauthenticated access test
- [ ] Unit tests for `setSeoForListing()` fallback behaviour
- [ ] All tests pass: `php artisan test --filter=Seo`
