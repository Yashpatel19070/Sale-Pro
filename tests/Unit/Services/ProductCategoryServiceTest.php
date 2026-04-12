<?php

declare(strict_types=1);

use App\Models\ProductCategory;
use App\Services\ProductCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ProductCategoryService;
});

// ── tree() ─────────────────────────────────────────────────────────────────────

it('returns only root categories in tree', function () {
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->create();

    $tree = $this->service->tree();

    expect($tree)->toHaveCount(1);
    expect($tree->first()->id)->toBe($root->id);
    expect($tree->first()->children->first()->id)->toBe($child->id);
});

it('tree filters by search', function () {
    ProductCategory::factory()->create(['name' => 'Electronics']);
    ProductCategory::factory()->create(['name' => 'Clothing']);

    $tree = $this->service->tree(['search' => 'Elec']);

    expect($tree)->toHaveCount(1);
    expect($tree->first()->name)->toBe('Electronics');
});

it('tree filters by active status', function () {
    ProductCategory::factory()->create(['is_active' => true]);
    ProductCategory::factory()->inactive()->create();

    $active = $this->service->tree(['active' => '1']);
    $inactive = $this->service->tree(['active' => '0']);

    expect($active)->toHaveCount(1);
    expect($inactive)->toHaveCount(1);
});

// ── flatTree() ─────────────────────────────────────────────────────────────────

it('returns flat tree in depth-first order with depth attribute', function () {
    $root = ProductCategory::factory()->create(['name' => 'A Root']);
    $child = ProductCategory::factory()->childOf($root)->create(['name' => 'B Child']);
    $grand = ProductCategory::factory()->childOf($child)->create(['name' => 'C Grand']);

    $flat = $this->service->flatTree();

    expect($flat[0]->name)->toBe('A Root');
    expect($flat[0]->depth)->toBe(0);
    expect($flat[1]->name)->toBe('B Child');
    expect($flat[1]->depth)->toBe(1);
    expect($flat[2]->name)->toBe('C Grand');
    expect($flat[2]->depth)->toBe(2);
});

it('flatTree excludes inactive categories', function () {
    ProductCategory::factory()->create(['is_active' => true]);
    ProductCategory::factory()->inactive()->create();

    expect($this->service->flatTree())->toHaveCount(1);
});

// ── create() ───────────────────────────────────────────────────────────────────

it('creates a root category', function () {
    $category = $this->service->create(['name' => 'Electronics', 'is_active' => true]);

    expect($category->parent_id)->toBeNull();
    expect($category->name)->toBe('Electronics');
    $this->assertDatabaseHas('product_categories', ['name' => 'Electronics', 'parent_id' => null]);
});

it('creates a child category', function () {
    $parent = ProductCategory::factory()->create();
    $child = $this->service->create(['name' => 'Phones', 'parent_id' => $parent->id, 'is_active' => true]);

    expect($child->parent_id)->toBe($parent->id);
});

// ── update() ───────────────────────────────────────────────────────────────────

it('updates a category name', function () {
    $category = ProductCategory::factory()->create(['name' => 'Old']);
    $updated = $this->service->update($category, ['name' => 'New']);

    expect($updated->name)->toBe('New');
    $this->assertDatabaseHas('product_categories', ['name' => 'New']);
});

it('can move category to a new parent', function () {
    $parent = ProductCategory::factory()->create();
    $category = ProductCategory::factory()->create(['parent_id' => null]);

    $this->service->update($category, ['parent_id' => $parent->id]);

    expect($category->fresh()->parent_id)->toBe($parent->id);
});

it('can promote category to root', function () {
    $parent = ProductCategory::factory()->create();
    $category = ProductCategory::factory()->childOf($parent)->create();

    $this->service->update($category, ['parent_id' => null]);

    expect($category->fresh()->parent_id)->toBeNull();
});

// ── delete() ───────────────────────────────────────────────────────────────────

it('soft deletes a category', function () {
    $category = ProductCategory::factory()->create();
    $this->service->delete($category);

    $this->assertSoftDeleted('product_categories', ['id' => $category->id]);
});

// ── dropdown() ─────────────────────────────────────────────────────────────────

it('dropdown returns only active categories', function () {
    ProductCategory::factory()->count(3)->create(['is_active' => true]);
    ProductCategory::factory()->inactive()->create();

    expect($this->service->dropdown())->toHaveCount(3);
});

// ── descendantIds() ────────────────────────────────────────────────────────────

it('collects all descendant ids recursively', function () {
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->create();
    $grand = ProductCategory::factory()->childOf($child)->create();

    $root->load('children.children');
    $ids = $root->descendantIds();

    expect($ids)->toContain($child->id);
    expect($ids)->toContain($grand->id);
    expect($ids)->not->toContain($root->id);
});
