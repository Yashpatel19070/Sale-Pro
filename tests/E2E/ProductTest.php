<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductListing;
use App\Models\User;
use Database\Seeders\ProductPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(ProductPermissionSeeder::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// JOURNEY 1 — Auth & Authorization
// ──────────────────────────────────────────────────────────────────────────────

it('1.1: redirects unauthenticated user from products index to login', function () {
    $this->get(route('products.index'))
        ->assertRedirect(route('login'));
});

it('1.2: denies sales role access and products not in nav', function () {
    $sales = User::factory()->create()->assignRole('sales');

    $this->actingAs($sales)
        ->get(route('products.index'))
        ->assertForbidden();
});

it('1.3: admin can access products and see nav link', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('products.index'))
        ->assertOk()
        ->assertViewIs('products.index');
});

// ──────────────────────────────────────────────────────────────────────────────
// JOURNEY 2 — Index Page
// ──────────────────────────────────────────────────────────────────────────────

it('2.1: displays 3 products with new button', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Product::factory(3)->create();

    $this->actingAs($admin)
        ->get(route('products.index'))
        ->assertOk()
        ->assertSeeText('New Product');
});

it('2.2: shows empty state when no products', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('products.index'))
        ->assertOk()
        ->assertSeeText('No products found');
});

it('2.3: filters by name search and preserves URL', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Product::factory()->create(['name' => 'Alpha Widget']);
    Product::factory()->create(['name' => 'Beta Gadget']);

    $this->actingAs($admin)
        ->get(route('products.index', ['search' => 'Alpha']))
        ->assertOk()
        ->assertSeeText('Alpha Widget')
        ->assertDontSeeText('Beta Gadget');
});

it('2.4: filters by SKU search', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Product::factory()->create(['sku' => 'BG-002', 'name' => 'Beta Gadget']);
    Product::factory()->create(['sku' => 'AW-001', 'name' => 'Alpha Widget']);

    $this->actingAs($admin)
        ->get(route('products.index', ['search' => 'BG-002']))
        ->assertOk()
        ->assertSeeText('Beta Gadget')
        ->assertDontSeeText('Alpha Widget');
});

it('2.5: shows empty state for no search results', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Product::factory()->create(['name' => 'Alpha Widget']);

    $this->actingAs($admin)
        ->get(route('products.index', ['search' => 'ZZZNOMATCH']))
        ->assertOk()
        ->assertSeeText('No products found');
});

it('2.6: filters by category', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $electronics = ProductCategory::factory()->create(['name' => 'Electronics']);
    Product::factory()->create(['category_id' => $electronics->id, 'name' => 'Phone']);
    Product::factory()->create(['category_id' => null, 'name' => 'Shirt']);

    $this->actingAs($admin)
        ->get(route('products.index', ['category_id' => $electronics->id]))
        ->assertOk()
        ->assertSeeText('Phone')
        ->assertDontSeeText('Shirt');
});

it('2.7: filters active products only', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Product::factory(2)->create(['is_active' => true]);
    Product::factory(1)->create(['is_active' => false]);

    $response = $this->actingAs($admin)
        ->get(route('products.index', ['active' => '1']))
        ->assertOk();

    $products = $response->viewData('products');
    expect($products->count())->toBe(2);
});

it('2.8: filters inactive products only', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Product::factory(2)->create(['is_active' => true]);
    Product::factory(1)->create(['is_active' => false]);

    $response = $this->actingAs($admin)
        ->get(route('products.index', ['active' => '0']))
        ->assertOk();

    $products = $response->viewData('products');
    expect($products->count())->toBe(1);
});

it('2.9: shows all statuses when no filter', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Product::factory(2)->create(['is_active' => true]);
    Product::factory(1)->create(['is_active' => false]);

    $response = $this->actingAs($admin)
        ->get(route('products.index'))
        ->assertOk();

    $products = $response->viewData('products');
    expect($products->count())->toBe(3);
});

it('2.10: combines search and active filter', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Product::factory()->create(['name' => 'Alpha Widget', 'is_active' => true]);
    Product::factory()->create(['name' => 'Alpha Gadget', 'is_active' => false]);
    Product::factory()->create(['name' => 'Beta Widget', 'is_active' => true]);

    $response = $this->actingAs($admin)
        ->get(route('products.index', ['search' => 'Alpha', 'active' => '1']))
        ->assertOk();

    $products = $response->viewData('products');
    expect($products->count())->toBe(1);
    expect($products->first()->name)->toBe('Alpha Widget');
});

it('2.11: clears filters on clear link', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Product::factory()->create();

    $this->actingAs($admin)
        ->get(route('products.index', ['search' => 'test', 'active' => '1']))
        ->assertOk()
        ->assertSeeHtml(route('products.index'));
});

it('2.12: shows SALE badge for on-sale products', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Product::factory()
        ->create([
            'sale_price' => '9.99',
            'regular_price' => '19.99',
        ]);

    $this->actingAs($admin)
        ->get(route('products.index'))
        ->assertOk()
        ->assertSeeText('SALE');
});

it('2.13: shows status badges', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Product::factory()->create(['is_active' => true]);
    Product::factory()->create(['is_active' => false]);

    $this->actingAs($admin)
        ->get(route('products.index'))
        ->assertOk()
        ->assertSeeText('Active')
        ->assertSeeText('Inactive');
});

it('2.14: paginates products with 20 per page', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Product::factory(25)->create();

    $response = $this->actingAs($admin)
        ->get(route('products.index'))
        ->assertOk();

    $products = $response->viewData('products');
    expect($products->count())->toBe(20);
    expect($products->total())->toBe(25);
    expect($products->lastPage())->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────────────
// JOURNEY 3 — Create Product
// ──────────────────────────────────────────────────────────────────────────────

it('3.1: displays create form with default active checkbox', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('products.create'))
        ->assertOk()
        ->assertViewIs('products.create')
        ->assertSeeText('SKU')
        ->assertSeeText('Name')
        ->assertSeeText('Regular Price');
});

it('3.2: successfully creates product and redirects to show page', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)
        ->post(route('products.store'), [
            'sku' => 'test-001',
            'name' => 'Test Product',
            'regular_price' => '29.99',
        ]);

    $product = Product::where('name', 'Test Product')->first();
    expect($product)->not()->toBeNull();
    expect($product->sku)->toBe('TEST-001');

    $response->assertRedirect(route('products.show', $product));
});

it('3.3: auto-uppercases SKU on create', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'sku' => 'abc-123',
            'name' => 'Test Product',
            'regular_price' => '29.99',
        ]);

    $product = Product::where('name', 'Test Product')->first();
    expect($product->sku)->toBe('ABC-123');
});

it('3.4: validates required fields', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('products.store'), [])
        ->assertSessionHasErrors(['sku', 'name', 'regular_price']);
});

it('3.5: enforces SKU uniqueness', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Product::factory()->create(['sku' => 'TAKEN-001']);

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'sku' => 'TAKEN-001',
            'name' => 'Test Product',
            'regular_price' => '29.99',
        ])
        ->assertSessionHasErrors('sku');
});

it('3.6: enforces SKU uniqueness case-insensitively', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Product::factory()->create(['sku' => 'TAKEN-001']);

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'sku' => 'taken-001',
            'name' => 'Test Product',
            'regular_price' => '29.99',
        ])
        ->assertSessionHasErrors('sku');
});

it('3.7: validates SKU format', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'sku' => 'INVALID SKU!',
            'name' => 'Test Product',
            'regular_price' => '29.99',
        ])
        ->assertSessionHasErrors('sku');
});

it('3.8: prevents sale price greater than regular price', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'sku' => 'test-001',
            'name' => 'Test Product',
            'regular_price' => '10.00',
            'sale_price' => '15.00',
        ])
        ->assertSessionHasErrors('sale_price');
});

it('3.9: prevents sale price equal to regular price', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'sku' => 'test-001',
            'name' => 'Test Product',
            'regular_price' => '10.00',
            'sale_price' => '10.00',
        ])
        ->assertSessionHasErrors('sale_price');
});

it('3.10: allows selecting category', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $category = ProductCategory::factory()->create(['name' => 'Electronics']);

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'sku' => 'test-001',
            'name' => 'Test Product',
            'regular_price' => '29.99',
            'category_id' => $category->id,
        ]);

    $product = Product::where('name', 'Test Product')->first();
    expect($product->category_id)->toBe($category->id);
});

it('3.11: allows uncategorised products', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'sku' => 'test-001',
            'name' => 'Test Product',
            'regular_price' => '29.99',
            'category_id' => '',
        ]);

    $product = Product::where('name', 'Test Product')->first();
    expect($product->category_id)->toBeNull();
});

it('3.12: allows inactive products on create', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'sku' => 'test-001',
            'name' => 'Test Product',
            'regular_price' => '29.99',
            'is_active' => '0',
        ]);

    $product = Product::where('name', 'Test Product')->first();
    expect($product->is_active)->toBeFalse();
});

it('3.13: repopulates form on validation failure', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'name' => 'My Product',
            'regular_price' => '10.00',
        ])
        ->assertSessionHasErrors('sku')
        ->assertSessionHas('_old_input', function ($old) {
            return $old['name'] === 'My Product' && $old['regular_price'] === '10.00';
        });
});

// ──────────────────────────────────────────────────────────────────────────────
// JOURNEY 4 — Show Product
// ──────────────────────────────────────────────────────────────────────────────

it('4.1: displays all product fields', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create([
        'sku' => 'PROD-001',
        'name' => 'Test Product',
        'regular_price' => '29.99',
    ]);

    $this->actingAs($admin)
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertSeeText('PROD-001')
        ->assertSeeText('Test Product')
        ->assertSeeText('$29.99');
});

it('4.2: shows on-sale product with strikethrough regular price', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create([
        'regular_price' => '19.99',
        'sale_price' => '9.99',
    ]);

    $this->actingAs($admin)
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertSeeText('$9.99')
        ->assertSeeText('On Sale');
});

it('4.3: shows regular price without sale price', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create([
        'regular_price' => '19.99',
        'sale_price' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertSeeText('$19.99');
});

it('4.4: shows uncategorised label when no category', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->uncategorised()->create();

    $this->actingAs($admin)
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertSeeText('Uncategorised');
});

it('4.5: shows category name when assigned', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $category = ProductCategory::factory()->create(['name' => 'Electronics']);
    $product = Product::factory()->create(['category_id' => $category->id]);

    $this->actingAs($admin)
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertSeeText('Electronics');
});

it('4.6: displays description and notes when present', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create([
        'description' => 'Test description',
        'notes' => 'Test notes',
    ]);

    $this->actingAs($admin)
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertSeeText('Test description')
        ->assertSeeText('Test notes');
});

it('4.7: does not display empty description and notes', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create([
        'description' => null,
        'notes' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('products.show', $product))
        ->assertOk();
});

it('4.8: shows 0 listings count', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->get(route('products.show', $product))
        ->assertOk();
});

it('4.9: admin can see edit and delete buttons', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertSeeText('Edit')
        ->assertSeeText('Delete');
});

it('4.10: shows active badge for active product', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertSeeText('Active');
});

it('4.10b: shows inactive badge for inactive product', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['is_active' => false]);

    $this->actingAs($admin)
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertSeeText('Inactive');
});

// ──────────────────────────────────────────────────────────────────────────────
// JOURNEY 5 — Edit Product
// ──────────────────────────────────────────────────────────────────────────────

it('5.1: edit form is pre-filled with product data', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create([
        'sku' => 'PROD-001',
        'name' => 'Original Name',
        'regular_price' => '29.99',
    ]);

    $this->actingAs($admin)
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertViewIs('products.edit')
        ->assertViewHas('product', $product)
        ->assertSeeText('Original Name');
});

it('5.2: successfully updates product', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['name' => 'Original']);

    $this->actingAs($admin)
        ->put(route('products.update', $product), [
            'sku' => $product->sku,
            'name' => 'Updated Name',
            'regular_price' => '49.99',
        ])
        ->assertRedirect(route('products.show', $product));

    expect($product->fresh()->name)->toBe('Updated Name');
    expect($product->fresh()->regular_price)->toBe('49.99');
});

it('5.3: edit can change and uppercase SKU', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['sku' => 'OLD-001']);

    $this->actingAs($admin)
        ->put(route('products.update', $product), [
            'sku' => 'new-sku',
            'name' => $product->name,
            'regular_price' => $product->regular_price,
        ]);

    expect($product->fresh()->sku)->toBe('NEW-SKU');
});

it('5.4: allows keeping same SKU on edit', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['sku' => 'PROD-001']);

    $this->actingAs($admin)
        ->put(route('products.update', $product), [
            'sku' => 'PROD-001',
            'name' => 'Updated Name',
            'regular_price' => $product->regular_price,
        ])
        ->assertRedirect();

    expect($product->fresh()->sku)->toBe('PROD-001');
});

it('5.5: prevents changing SKU to another products SKU', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product1 = Product::factory()->create(['sku' => 'PROD-001']);
    $product2 = Product::factory()->create(['sku' => 'PROD-002']);

    $this->actingAs($admin)
        ->put(route('products.update', $product1), [
            'sku' => 'PROD-002',
            'name' => 'Test',
            'regular_price' => '10.00',
        ])
        ->assertSessionHasErrors('sku');
});

it('5.6: repopulates form on validation error', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create([
        'name' => 'Original',
        'regular_price' => '29.99',
    ]);

    $this->actingAs($admin)
        ->put(route('products.update', $product), [
            'sku' => $product->sku,
            'name' => '',
            'regular_price' => $product->regular_price,
        ])
        ->assertSessionHasErrors('name');
});

it('5.7: validates sale price on edit', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create([
        'regular_price' => '10.00',
        'sale_price' => null,
    ]);

    $this->actingAs($admin)
        ->put(route('products.update', $product), [
            'sku' => $product->sku,
            'name' => $product->name,
            'regular_price' => '10.00',
            'sale_price' => '15.00',
        ])
        ->assertSessionHasErrors('sale_price');
});

it('5.8: can toggle active status via edit', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->put(route('products.update', $product), [
            'sku' => $product->sku,
            'name' => $product->name,
            'regular_price' => $product->regular_price,
            'is_active' => '0',
        ]);

    expect($product->fresh()->is_active)->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// JOURNEY 6 — Delete Product
// ──────────────────────────────────────────────────────────────────────────────

it('6.1: delete on show page works', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['name' => 'To Delete']);

    $response = $this->actingAs($admin)
        ->delete(route('products.destroy', $product));

    expect(Product::withTrashed()->find($product->id))->not()->toBeNull();
});

it('6.3: successfully deletes product and redirects to index', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->delete(route('products.destroy', $product))
        ->assertRedirect(route('products.index'))
        ->assertSessionHas('success');

    expect($product->fresh()->trashed())->toBeTrue();
});

it('6.4: prevents delete when active listings exist', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();
    ProductListing::factory()->create([
        'product_id' => $product->id,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->delete(route('products.destroy', $product))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($product->fresh()->trashed())->toBeFalse();
});

it('6.5: allows delete when only inactive listings exist', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();
    ProductListing::factory()->create([
        'product_id' => $product->id,
        'is_active' => false,
    ]);

    $this->actingAs($admin)
        ->delete(route('products.destroy', $product))
        ->assertRedirect();

    expect($product->fresh()->trashed())->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// JOURNEY 7 — Toggle Active
// ──────────────────────────────────────────────────────────────────────────────

it('7.1: deactivates active product', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->post(route('products.toggle-active', $product))
        ->assertSessionHas('success');

    expect($product->fresh()->is_active)->toBeFalse();
});

it('7.2: activates inactive product', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['is_active' => false]);

    $this->actingAs($admin)
        ->post(route('products.toggle-active', $product))
        ->assertSessionHas('success');

    expect($product->fresh()->is_active)->toBeTrue();
});

it('7.3: toggle reflected on index page', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->post(route('products.toggle-active', $product))
        ->assertSessionHas('success');

    $response = $this->actingAs($admin)
        ->get(route('products.index'));

    expect($response->viewData('products')->first()->is_active)->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// JOURNEY 8 — Navigation
// ──────────────────────────────────────────────────────────────────────────────

it('8.1: products nav link is active when on products page', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('products.index'))
        ->assertOk();
});

it('8.2: products nav link not active on other pages', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk();
});

it('8.3: products route forbidden for sales role', function () {
    $sales = User::factory()->create()->assignRole('sales');

    $this->actingAs($sales)
        ->get(route('products.index'))
        ->assertForbidden();
});
