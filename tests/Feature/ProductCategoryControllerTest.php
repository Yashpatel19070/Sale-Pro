<?php

declare(strict_types=1);

use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\ProductCategoryPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(ProductCategoryPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->staff = User::factory()->create();
    $this->staff->assignRole('sales');
});

// ── index ──────────────────────────────────────────────────────────────────────

it('admin can view category index', function () {
    ProductCategory::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('product-categories.index'))
        ->assertOk();
});

it('staff can view category index', function () {
    $this->actingAs($this->staff)
        ->get(route('product-categories.index'))
        ->assertOk();
});

it('guest is redirected from index', function () {
    $this->get(route('product-categories.index'))
        ->assertRedirect();
});

it('index search filter works', function () {
    ProductCategory::factory()->create(['name' => 'Electronics']);
    ProductCategory::factory()->create(['name' => 'Clothing']);

    $this->actingAs($this->admin)
        ->get(route('product-categories.index', ['search' => 'Elec']))
        ->assertOk()
        ->assertSee('Electronics')
        ->assertDontSee('Clothing');
});

// ── create ─────────────────────────────────────────────────────────────────────

it('admin can view create form', function () {
    $this->actingAs($this->admin)
        ->get(route('product-categories.create'))
        ->assertOk();
});

it('staff cannot view create form', function () {
    $this->actingAs($this->staff)
        ->get(route('product-categories.create'))
        ->assertForbidden();
});

// ── store ──────────────────────────────────────────────────────────────────────

it('admin can create a root category', function () {
    $this->actingAs($this->admin)
        ->post(route('product-categories.store'), [
            'name' => 'Electronics',
            'is_active' => '1',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('product_categories', ['name' => 'Electronics', 'parent_id' => null]);
});

it('admin can create a child category', function () {
    $parent = ProductCategory::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('product-categories.store'), [
            'name' => 'Phones',
            'parent_id' => $parent->id,
            'is_active' => '1',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('product_categories', ['name' => 'Phones', 'parent_id' => $parent->id]);
});

it('duplicate name under same parent returns validation error', function () {
    $parent = ProductCategory::factory()->create();
    ProductCategory::factory()->childOf($parent)->create(['name' => 'Phones']);

    $this->actingAs($this->admin)
        ->post(route('product-categories.store'), [
            'name' => 'Phones',
            'parent_id' => $parent->id,
        ])
        ->assertSessionHasErrors('name');
});

it('same name under different parent is allowed', function () {
    $parent1 = ProductCategory::factory()->create();
    $parent2 = ProductCategory::factory()->create();
    ProductCategory::factory()->childOf($parent1)->create(['name' => 'Phones']);

    $this->actingAs($this->admin)
        ->post(route('product-categories.store'), [
            'name' => 'Phones',
            'parent_id' => $parent2->id,
            'is_active' => '1',
        ])
        ->assertRedirect();

    expect(ProductCategory::where('name', 'Phones')->count())->toBe(2);
});

it('store requires a name', function () {
    $this->actingAs($this->admin)
        ->post(route('product-categories.store'), [])
        ->assertSessionHasErrors('name');
});

it('staff cannot store a category', function () {
    $this->actingAs($this->staff)
        ->post(route('product-categories.store'), ['name' => 'Test'])
        ->assertForbidden();
});

// ── show ───────────────────────────────────────────────────────────────────────

it('admin can view a category', function () {
    $category = ProductCategory::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('product-categories.show', $category))
        ->assertOk()
        ->assertSee($category->name);
});

it('show displays parent link when category has a parent', function () {
    $parent = ProductCategory::factory()->create(['name' => 'Electronics']);
    $category = ProductCategory::factory()->childOf($parent)->create(['name' => 'Phones']);

    $this->actingAs($this->admin)
        ->get(route('product-categories.show', $category))
        ->assertOk()
        ->assertSee('Electronics');
});

it('show lists children', function () {
    $parent = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($parent)->create(['name' => 'Child One']);

    $this->actingAs($this->admin)
        ->get(route('product-categories.show', $parent))
        ->assertOk()
        ->assertSee('Child One');
});

// ── edit ───────────────────────────────────────────────────────────────────────

it('admin can view edit form', function () {
    $category = ProductCategory::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('product-categories.edit', $category))
        ->assertOk();
});

it('staff cannot view edit form', function () {
    $category = ProductCategory::factory()->create();

    $this->actingAs($this->staff)
        ->get(route('product-categories.edit', $category))
        ->assertForbidden();
});

// ── update ─────────────────────────────────────────────────────────────────────

it('admin can update a category', function () {
    $category = ProductCategory::factory()->create(['name' => 'Old']);

    $this->actingAs($this->admin)
        ->put(route('product-categories.update', $category), [
            'name' => 'New',
            'is_active' => '1',
        ])
        ->assertRedirect(route('product-categories.show', $category));

    expect($category->fresh()->name)->toBe('New');
});

it('cannot set parent to self', function () {
    $category = ProductCategory::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('product-categories.update', $category), [
            'name' => $category->name,
            'parent_id' => $category->id,
        ])
        ->assertSessionHasErrors('parent_id');
});

it('cannot set parent to a descendant', function () {
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->create();

    $this->actingAs($this->admin)
        ->put(route('product-categories.update', $root), [
            'name' => $root->name,
            'parent_id' => $child->id,
        ])
        ->assertSessionHasErrors('parent_id');
});

it('can move category to a different parent', function () {
    $parent1 = ProductCategory::factory()->create();
    $parent2 = ProductCategory::factory()->create();
    $category = ProductCategory::factory()->childOf($parent1)->create();

    $this->actingAs($this->admin)
        ->put(route('product-categories.update', $category), [
            'name' => $category->name,
            'parent_id' => $parent2->id,
            'is_active' => '1',
        ])
        ->assertRedirect();

    expect($category->fresh()->parent_id)->toBe($parent2->id);
});

it('staff cannot update a category', function () {
    $category = ProductCategory::factory()->create();

    $this->actingAs($this->staff)
        ->put(route('product-categories.update', $category), ['name' => 'Hacked'])
        ->assertForbidden();
});

// ── destroy ────────────────────────────────────────────────────────────────────

it('admin can soft-delete a category', function () {
    $category = ProductCategory::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('product-categories.destroy', $category))
        ->assertRedirect(route('product-categories.index'));

    $this->assertSoftDeleted('product_categories', ['id' => $category->id]);
});

it('deleted category is not shown in index', function () {
    $category = ProductCategory::factory()->create(['name' => 'Gone']);
    $category->delete();

    $this->actingAs($this->admin)
        ->get(route('product-categories.index'))
        ->assertDontSee('Gone');
});

it('staff cannot delete a category', function () {
    $category = ProductCategory::factory()->create();

    $this->actingAs($this->staff)
        ->delete(route('product-categories.destroy', $category))
        ->assertForbidden();
});
