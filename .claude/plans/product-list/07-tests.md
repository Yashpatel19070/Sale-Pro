# ProductList Module — Tests

## Feature Test
`tests/Feature/ProductListingControllerTest.php`

```php
<?php

declare(strict_types=1);

use App\Enums\ListingVisibility;
use App\Models\Product;
use App\Models\ProductListing;
use App\Models\User;
use Database\Seeders\RoleSeeder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(\Database\Seeders\ProductListingPermissionSeeder::class);
});

// ── Authorization ──────────────────────────────────────────────────────────

it('denies unauthenticated access', function () {
    $this->get(route('product-listings.index'))->assertRedirect(route('login'));
});

it('denies staff from creating a listing', function () {
    $user = User::factory()->create()->assignRole('staff');
    $this->actingAs($user)->get(route('product-listings.create'))->assertForbidden();
});

it('allows staff to view listings', function () {
    $user = User::factory()->create()->assignRole('staff');
    $this->actingAs($user)->get(route('product-listings.index'))->assertOk();
});

// ── Index / Filtering ──────────────────────────────────────────────────────

it('lists all listings paginated', function () {
    $admin = User::factory()->create()->assignRole('admin');
    ProductListing::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('product-listings.index'))
        ->assertOk()
        ->assertViewIs('product_listings.index')
        ->assertViewHas('listings');
});

it('filters listings by product', function () {
    $admin   = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->forProduct($product)->create(['title' => 'Target Listing']);
    ProductListing::factory()->create(['title' => 'Other Listing']);

    $this->actingAs($admin)
        ->get(route('product-listings.index', ['product_id' => $product->id]))
        ->assertSee('Target Listing')
        ->assertDontSee('Other Listing');
});

it('filters listings by visibility', function () {
    $admin = User::factory()->create()->assignRole('admin');
    ProductListing::factory()->public()->create(['title' => 'Public Listing']);
    ProductListing::factory()->create(['title' => 'Draft Listing', 'visibility' => ListingVisibility::Draft->value]);

    $this->actingAs($admin)
        ->get(route('product-listings.index', ['visibility' => 'public']))
        ->assertSee('Public Listing')
        ->assertDontSee('Draft Listing');
});

// ── Create / Store ─────────────────────────────────────────────────────────

it('admin can create a listing', function () {
    $admin   = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['sku' => 'PROD-001']);

    $this->actingAs($admin)
        ->post(route('product-listings.store'), [
            'product_id' => $product->id,
            'title'      => 'Standard',
            'visibility' => 'draft',
            'is_active'  => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('product_listings', ['title' => 'Standard', 'product_id' => $product->id]);
});

// Slug generation tests → see product-slug/05-tests.md

it('validates required fields on store', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('product-listings.store'), [])
        ->assertSessionHasErrors(['product_id', 'title', 'visibility']);
});

// ── Edit / Update ──────────────────────────────────────────────────────────

it('admin can update a listing title and visibility', function () {
    $admin   = User::factory()->create()->assignRole('admin');
    $listing = ProductListing::factory()->create(['title' => 'Old Title']);

    $this->actingAs($admin)
        ->patch(route('product-listings.update', $listing), [
            'title' => 'New Title', 'visibility' => 'public',
        ])
        ->assertRedirect(route('product-listings.show', $listing));

    $this->assertDatabaseHas('product_listings', ['id' => $listing->id, 'title' => 'New Title']);
});

// Slug regen + redirect tests → see product-slug/05-tests.md

// ── Delete / Restore ───────────────────────────────────────────────────────

it('admin can delete a listing', function () {
    $admin   = User::factory()->create()->assignRole('admin');
    $listing = ProductListing::factory()->create();

    $this->actingAs($admin)
        ->delete(route('product-listings.destroy', $listing))
        ->assertRedirect(route('product-listings.index'));

    $this->assertSoftDeleted('product_listings', ['id' => $listing->id]);
});

it('admin can restore a deleted listing', function () {
    $admin   = User::factory()->create()->assignRole('admin');
    $listing = ProductListing::factory()->create();
    $listing->delete();

    $this->actingAs($admin)
        ->post(route('product-listings.restore', $listing->id))
        ->assertRedirect();

    $this->assertDatabaseHas('product_listings', ['id' => $listing->id, 'deleted_at' => null]);
});

// ── Toggle Visibility ──────────────────────────────────────────────────────

it('toggleVisibility cycles public and draft', function () {
    $admin   = User::factory()->create()->assignRole('admin');
    $listing = ProductListing::factory()->public()->create();

    $this->actingAs($admin)
        ->post(route('product-listings.toggle-visibility', $listing))
        ->assertRedirect();

    $this->assertDatabaseHas('product_listings', ['id' => $listing->id, 'visibility' => 'draft']);
});
```

---

## Unit Test
`tests/Unit/Services/ProductListingServiceTest.php`

```php
<?php

declare(strict_types=1);

use App\Enums\ListingVisibility;
use App\Models\Product;
use App\Models\ProductListing;
use App\Services\ProductListingService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ProductListingService();
});

// Slug unit tests (create, collision, title regen, redirect records, SKU regen)
// → see product-slug/05-tests.md

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
```

## Checklist
- [ ] Feature: auth gates — staff can view but not create/edit/delete
- [ ] Feature: filter by product_id and visibility
- [ ] Feature: create validates product_id, title, visibility
- [ ] Feature: delete + restore
- [ ] Feature: toggleVisibility cycles public ↔ draft
- [ ] Unit: toggleVisibility cycles correctly
- [ ] Slug tests (generate, collision, regen, redirect, SKU regen, portal routes) → `product-slug/05-tests.md`
- [ ] No adjustStock tests — stock not tracked on listings
