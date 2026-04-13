# Product Module — Tests

## Feature Test
`tests/Feature/ProductControllerTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\RoleSeeder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(\Database\Seeders\ProductPermissionSeeder::class);
});

// ── Authorization ──────────────────────────────────────────────────────────

it('denies unauthenticated access to products index', function () {
    $this->get(route('products.index'))->assertRedirect(route('login'));
});

it('denies staff role from creating a product', function () {
    $user = User::factory()->create()->assignRole('staff');
    $this->actingAs($user)->get(route('products.create'))->assertForbidden();
});

it('allows admin to access products index', function () {
    $user = User::factory()->create()->assignRole('admin');
    $this->actingAs($user)->get(route('products.index'))->assertOk();
});

// ── Index / Filtering ──────────────────────────────────────────────────────

it('lists products paginated', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Product::factory()->count(5)->create();

    $this->actingAs($admin)
        ->get(route('products.index'))
        ->assertOk()
        ->assertViewIs('products.index')
        ->assertViewHas('products');
});

it('filters products by search term', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Product::factory()->create(['name' => 'Alpha Widget', 'sku' => 'AW-001']);
    Product::factory()->create(['name' => 'Beta Gadget', 'sku' => 'BG-001']);

    $this->actingAs($admin)
        ->get(route('products.index', ['search' => 'Alpha']))
        ->assertSee('Alpha Widget')
        ->assertDontSee('Beta Gadget');
});

it('filters products by category', function () {
    $admin    = User::factory()->create()->assignRole('admin');
    $category = ProductCategory::factory()->create();
    Product::factory()->create(['category_id' => $category->id, 'name' => 'Cat Product']);
    Product::factory()->uncategorised()->create(['name' => 'No Cat Product']);

    $this->actingAs($admin)
        ->get(route('products.index', ['category_id' => $category->id]))
        ->assertSee('Cat Product')
        ->assertDontSee('No Cat Product');
});

// ── Create / Store ─────────────────────────────────────────────────────────

it('admin can view create product form', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $this->actingAs($admin)->get(route('products.create'))->assertOk();
});

it('admin can create a product', function () {
    $admin    = User::factory()->create()->assignRole('admin');
    $category = ProductCategory::factory()->create();

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'sku'           => 'test-001',
            'name'          => 'Test Product',
            'category_id'   => $category->id,
            'regular_price' => 19.99,
            'is_active'     => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('products', [
        'sku'  => 'TEST-001', // uppercased
        'name' => 'Test Product',
    ]);
});

it('uppercases the SKU on store', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)->post(route('products.store'), [
        'sku' => 'abc-123', 'name' => 'X', 'regular_price' => 10,
    ]);

    $this->assertDatabaseHas('products', ['sku' => 'ABC-123']);
});

it('validates required fields on store', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('products.store'), [])
        ->assertSessionHasErrors(['sku', 'name', 'regular_price']);
});

it('validates sale_price must be less than regular_price', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'sku' => 'X-001', 'name' => 'X', 'regular_price' => 10, 'sale_price' => 15,
        ])
        ->assertSessionHasErrors(['sale_price']);
});

it('validates SKU uniqueness', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Product::factory()->create(['sku' => 'DUP-001']);

    $this->actingAs($admin)
        ->post(route('products.store'), ['sku' => 'DUP-001', 'name' => 'X', 'regular_price' => 10])
        ->assertSessionHasErrors(['sku']);
});

// ── Edit / Update ──────────────────────────────────────────────────────────

it('admin can view product detail', function () {
    $admin   = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertViewIs('products.show');
});

it('admin can view product edit form', function () {
    $admin   = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertViewIs('products.edit');
});

it('admin can edit a product', function () {
    $admin   = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->patch(route('products.update', $product), [
            'sku' => $product->sku, 'name' => 'Updated Name', 'regular_price' => 25.00,
        ])
        ->assertRedirect(route('products.show', $product));

    $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Updated Name']);
});

it('updates and uppercases SKU when changed', function () {
    $admin   = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['sku' => 'ORIGINAL-SKU']);

    $this->actingAs($admin)->patch(route('products.update', $product), [
        'sku' => 'new-sku', 'name' => 'X', 'regular_price' => 10,
    ]);

    $this->assertDatabaseHas('products', ['id' => $product->id, 'sku' => 'NEW-SKU']);
});

// ── Delete ─────────────────────────────────────────────────────────────────

it('admin can delete a product with no active listings', function () {
    $admin   = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->delete(route('products.destroy', $product))
        ->assertRedirect(route('products.index'));

    $this->assertSoftDeleted('products', ['id' => $product->id]);
});

it('cannot delete a product with active listings', function () {
    $admin   = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();
    \App\Models\ProductListing::factory()->forProduct($product)->public()->create();

    $this->actingAs($admin)
        ->delete(route('products.destroy', $product))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
});

// ── Toggle / Restore ───────────────────────────────────────────────────────

it('admin can toggle product active state', function () {
    $admin   = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->post(route('products.toggle-active', $product))
        ->assertRedirect();

    $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
});

it('admin can restore a soft-deleted product', function () {
    $admin   = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();
    $product->delete();

    $this->actingAs($admin)
        ->post(route('products.restore', $product->id))
        ->assertRedirect();

    $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
});
```

---

## Unit Test
`tests/Unit/Services/ProductServiceTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Product;
use App\Services\ProductService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ProductService();
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
    \App\Models\ProductListing::factory()->forProduct($product)->public()->create();

    expect(fn () => $this->service->delete($product))
        ->toThrow(\RuntimeException::class);
});

it('soft-deletes inactive listings when deleting product', function () {
    $product = Product::factory()->create();
    $listing = \App\Models\ProductListing::factory()->forProduct($product)->create(['is_active' => false]);

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

    $trashed  = Product::onlyTrashed()->findOrFail($product->id);
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
```

## Checklist
- [ ] Feature test: auth gates (unauthenticated redirect, role-based forbidden)
- [ ] Feature test: index with search + category filter
- [ ] Feature test: show GET — assertOk + assertViewIs('products.show')
- [ ] Feature test: edit GET — assertOk + assertViewIs('products.edit')
- [ ] Feature test: create + SKU uppercase; `regular_price` used (not `base_price`)
- [ ] Feature test: `sale_price` must be less than `regular_price`
- [ ] Feature test: SKU uniqueness validation
- [ ] Feature test: update uppercases changed SKU
- [ ] Feature test: delete blocked by active listings
- [ ] Feature test: toggleActive flips state
- [ ] Feature test: restore works
- [ ] Unit test: service::create uppercases SKU
- [ ] Unit test: service::update uppercases changed SKU
- [ ] Unit test: service::delete throws on active listings
- [ ] Unit test: service::delete cascades inactive listings
- [ ] Unit test: restore accepts Product model (resolved via withTrashed route binding)
- [ ] Unit test: `currentPrice()` returns sale_price when set
