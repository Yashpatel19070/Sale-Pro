# ProductCategory Module — Tests

## Files
- `tests/Feature/ProductCategoryControllerTest.php`
- `tests/Unit/Services/ProductCategoryServiceTest.php`

---

## Feature Test: ProductCategoryControllerTest

Setup: `beforeEach` seeds `ProductCategoryPermissionSeeder` + creates admin user with `admin` role.

### index
- [ ] Admin can view category tree (200)
- [ ] Staff can view category tree (200)
- [ ] Guest redirected to login
- [ ] Search filter returns matching categories only

### create
- [ ] Admin can view create form (200)
- [ ] Staff gets 403

### store
- [ ] Admin can create root category (parent_id = null) → redirects to show
- [ ] Admin can create child category (parent_id set) → redirects to show
- [ ] Duplicate name under same parent returns validation error
- [ ] Same name under different parent is allowed
- [ ] Missing name returns validation error
- [ ] Invalid parent_id (deleted category) returns validation error
- [ ] Staff gets 403

### show
- [ ] Admin can view category (200)
- [ ] Staff can view category (200)
- [ ] Parent breadcrumb visible when category has a parent
- [ ] Children listed on show page

### edit
- [ ] Admin can view edit form (200)
- [ ] Edit dropdown excludes self
- [ ] Edit dropdown excludes descendants (no circular reference option shown)
- [ ] Staff gets 403

### update
- [ ] Admin can update name, description, is_active, parent_id
- [ ] Cannot set parent_id to self (validation error)
- [ ] Cannot set parent_id to a descendant (validation error)
- [ ] Can move category to a different parent
- [ ] Can promote category to root (parent_id = null)
- [ ] Staff gets 403

### destroy
- [ ] Admin can soft-delete a root category
- [ ] Admin can soft-delete a child category
- [ ] Soft-deleted category not shown in index
- [ ] Children of deleted parent keep their parent_id (not orphaned by cascade)
- [ ] Staff gets 403

---

## Unit Test: ProductCategoryServiceTest

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\ProductCategory;
use App\Services\ProductCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ProductCategoryService();
});

// tree()
it('returns only root categories in tree', function () {
    $root  = ProductCategory::factory()->create();
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

// flatTree()
it('returns flat tree in depth-first order with depth attribute', function () {
    $root  = ProductCategory::factory()->create(['name' => 'A Root']);
    $child = ProductCategory::factory()->childOf($root)->create(['name' => 'B Child']);
    $grand = ProductCategory::factory()->childOf($child)->create(['name' => 'C Grandchild']);

    $flat = $this->service->flatTree();

    expect($flat[0]->name)->toBe('A Root');
    expect($flat[0]->depth)->toBe(0);
    expect($flat[1]->name)->toBe('B Child');
    expect($flat[1]->depth)->toBe(1);
    expect($flat[2]->name)->toBe('C Grandchild');
    expect($flat[2]->depth)->toBe(2);
});

it('flatTree excludes inactive categories', function () {
    ProductCategory::factory()->create(['is_active' => true]);
    ProductCategory::factory()->inactive()->create();

    $flat = $this->service->flatTree();
    expect($flat)->toHaveCount(1);
});

// create()
it('creates a root category', function () {
    $category = $this->service->create(['name' => 'Electronics', 'is_active' => true]);
    expect($category->parent_id)->toBeNull();
    $this->assertDatabaseHas('product_categories', ['name' => 'Electronics']);
});

it('creates a child category', function () {
    $parent   = ProductCategory::factory()->create();
    $child    = $this->service->create(['name' => 'Phones', 'parent_id' => $parent->id, 'is_active' => true]);
    expect($child->parent_id)->toBe($parent->id);
});

// update()
it('updates a category', function () {
    $category = ProductCategory::factory()->create(['name' => 'Old']);
    $updated  = $this->service->update($category, ['name' => 'New']);
    expect($updated->name)->toBe('New');
    $this->assertDatabaseHas('product_categories', ['name' => 'New']);
});

it('can move category to a new parent', function () {
    $parent   = ProductCategory::factory()->create();
    $category = ProductCategory::factory()->create(['parent_id' => null]);
    $this->service->update($category, ['parent_id' => $parent->id]);
    expect($category->fresh()->parent_id)->toBe($parent->id);
});

it('can promote category to root', function () {
    $parent   = ProductCategory::factory()->create();
    $category = ProductCategory::factory()->childOf($parent)->create();
    $this->service->update($category, ['parent_id' => null]);
    expect($category->fresh()->parent_id)->toBeNull();
});

// delete()
it('soft deletes a category', function () {
    $category = ProductCategory::factory()->create();
    $this->service->delete($category);
    $this->assertSoftDeleted('product_categories', ['id' => $category->id]);
});

// dropdown()
it('returns only active categories for dropdown', function () {
    ProductCategory::factory()->count(3)->create(['is_active' => true]);
    ProductCategory::factory()->inactive()->create();

    $result = $this->service->dropdown();
    expect($result)->toHaveCount(3);
});

// descendantIds()
it('collects all descendant ids', function () {
    $root  = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->create();
    $grand = ProductCategory::factory()->childOf($child)->create();

    $root->load('children.children');
    $ids = $root->descendantIds();

    expect($ids)->toContain($child->id);
    expect($ids)->toContain($grand->id);
    expect($ids)->not->toContain($root->id);
});
```

## Checklist
- [ ] `ProductCategoryControllerTest` seeds `ProductCategoryPermissionSeeder` in `beforeEach`
- [ ] Circular reference prevention tested (self + descendant as parent)
- [ ] Tree structure verified in unit tests (depth-first order + depth attribute)
- [ ] `childOf()` factory state used in tests
- [ ] Soft delete verified with `assertSoftDeleted`
- [ ] `php artisan test --filter=ProductCategory` — all pass
