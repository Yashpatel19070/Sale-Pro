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

// ── Authorization ──────────────────────────────────────────────────────────────

it('denies unauthenticated access to products index', function () {
    $this->get(route('products.index'))->assertRedirect(route('login'));
});

it('denies sales role from creating a product', function () {
    $user = User::factory()->create()->assignRole('sales');
    $this->actingAs($user)->get(route('products.create'))->assertForbidden();
});

it('allows admin to access products index', function () {
    $user = User::factory()->create()->assignRole('admin');
    $this->actingAs($user)->get(route('products.index'))->assertOk();
});

// ── Index / Filtering ──────────────────────────────────────────────────────────

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
    $admin = User::factory()->create()->assignRole('admin');
    $category = ProductCategory::factory()->create();
    Product::factory()->create(['category_id' => $category->id, 'name' => 'Cat Product']);
    Product::factory()->uncategorised()->create(['name' => 'No Cat Product']);

    $this->actingAs($admin)
        ->get(route('products.index', ['category_id' => $category->id]))
        ->assertSee('Cat Product')
        ->assertDontSee('No Cat Product');
});

// ── Create / Store ─────────────────────────────────────────────────────────────

it('admin can view create product form', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $this->actingAs($admin)->get(route('products.create'))->assertOk();
});

it('admin can create a product', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $category = ProductCategory::factory()->create();

    $this->actingAs($admin)
        ->post(route('products.store'), [
            'sku' => 'test-001',
            'name' => 'Test Product',
            'category_id' => $category->id,
            'regular_price' => 19.99,
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('products', [
        'sku' => 'TEST-001',
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

// ── Show / Edit forms ─────────────────────────────────────────────────────────

it('admin can view product detail', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertViewIs('products.show');
});

it('admin can view product edit form', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertViewIs('products.edit');
});

// ── Edit / Update ──────────────────────────────────────────────────────────────

it('admin can edit a product', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->patch(route('products.update', $product), [
            'sku' => $product->sku, 'name' => 'Updated Name', 'regular_price' => 25.00,
        ])
        ->assertRedirect(route('products.show', $product));

    $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Updated Name']);
});

it('updates and uppercases SKU when changed', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['sku' => 'ORIGINAL-SKU']);

    $this->actingAs($admin)->patch(route('products.update', $product), [
        'sku' => 'new-sku', 'name' => 'X', 'regular_price' => 10,
    ]);

    $this->assertDatabaseHas('products', ['id' => $product->id, 'sku' => 'NEW-SKU']);
});

// ── Delete ─────────────────────────────────────────────────────────────────────

it('admin can delete a product with no active listings', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->delete(route('products.destroy', $product))
        ->assertRedirect(route('products.index'));

    $this->assertSoftDeleted('products', ['id' => $product->id]);
});

it('cannot delete a product with active listings', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();
    ProductListing::factory()->forProduct($product)->public()->create();

    $this->actingAs($admin)
        ->delete(route('products.destroy', $product))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
});

// ── Toggle / Restore ───────────────────────────────────────────────────────────

it('admin can toggle product active state', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->post(route('products.toggle-active', $product))
        ->assertRedirect();

    $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
});

it('admin can restore a soft-deleted product', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();
    $product->delete();

    $this->actingAs($admin)
        ->post(route('products.restore', $product->id))
        ->assertRedirect();

    $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
});
