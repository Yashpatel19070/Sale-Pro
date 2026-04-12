# ProductCategory Module — Service

## File
`app/Services/ProductCategoryService.php`

## Full Implementation

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Collection;

class ProductCategoryService
{
    /**
     * Return all root categories with all descendants eager-loaded.
     * Used for the admin tree view — no pagination (categories are few).
     *
     * @param  array{search?: string, active?: string}  $filters
     * @return Collection<int, ProductCategory>
     */
    public function tree(array $filters = []): Collection
    {
        $allCategories = ProductCategory::with('children')
            ->when(
                isset($filters['search']) && $filters['search'] !== '',
                fn ($q) => $q->where('name', 'like', "%{$filters['search']}%")
            )
            ->when(
                isset($filters['active']) && $filters['active'] !== '',
                fn ($q) => $q->where('is_active', (bool) $filters['active'])
            )
            ->orderBy('name')
            ->get();

        // Build tree in PHP — no recursive SQL needed
        return $this->buildTree($allCategories);
    }

    /**
     * Flat list of all categories for dropdowns.
     * Returns indented labels so the hierarchy is visible in <select>.
     *
     * @return Collection<int, ProductCategory>
     */
    public function dropdown(): Collection
    {
        return ProductCategory::forDropdown()->get();
    }

    /**
     * Flat ordered list for building an indented dropdown.
     * Returns items in depth-first order with a `depth` attribute set.
     *
     * @return array<int, ProductCategory>
     */
    public function flatTree(): array
    {
        $all = ProductCategory::active()
            ->orderBy('name')
            ->select(['id', 'parent_id', 'name'])
            ->get()
            ->keyBy('id');

        // Build adjacency list
        $children = [];
        $roots    = [];

        foreach ($all as $cat) {
            if ($cat->parent_id === null) {
                $roots[] = $cat->id;
            } else {
                $children[$cat->parent_id][] = $cat->id;
            }
        }

        // Depth-first walk
        $result = [];
        $walk   = function (int $id, int $depth) use (&$walk, &$result, $all, $children): void {
            $cat        = $all[$id];
            $cat->depth = $depth;
            $result[]   = $cat;

            foreach ($children[$id] ?? [] as $childId) {
                $walk($childId, $depth + 1);
            }
        };

        foreach ($roots as $rootId) {
            $walk($rootId, 0);
        }

        return $result;
    }

    /**
     * @param  array{parent_id?: int|null, name: string, description?: string, is_active?: bool}  $data
     */
    public function create(array $data): ProductCategory
    {
        return ProductCategory::create($data);
    }

    /**
     * @param  array{parent_id?: int|null, name?: string, description?: string, is_active?: bool}  $data
     */
    public function update(ProductCategory $category, array $data): ProductCategory
    {
        $category->update($data);

        return $category;
    }

    public function delete(ProductCategory $category): void
    {
        $category->delete();
    }

    // ── Private ────────────────────────────────────────────────────────────

    /**
     * Given a flat collection with 'children' relation loaded,
     * return only root items (parent_id = null).
     * Children are already nested via the 'children' relation.
     *
     * @param  Collection<int, ProductCategory>  $all
     * @return Collection<int, ProductCategory>
     */
    private function buildTree(Collection $all): Collection
    {
        return $all->whereNull('parent_id')->values();
    }
}
```

## Method Summary

| Method | Purpose |
|--------|---------|
| `tree(array $filters)` | Full tree for admin index — roots with all children eager-loaded |
| `dropdown()` | Active categories keyed by id for simple selects |
| `flatTree()` | Depth-first flat list with `depth` attribute — for indented `<select>` |
| `create(array $data)` | Create a new category |
| `update($category, array $data)` | Update and return |
| `delete($category)` | Soft delete |

## How tree() Works
1. Load ALL categories matching filters with `with('children')` — one query with a self-join
2. `with('children')` only loads direct children (one level)
3. Blade recursive partial handles deeper nesting by calling `children` on each child
4. `buildTree()` returns only root items — children are accessible via `$cat->children`

## How flatTree() Works
Used for the parent `<select>` dropdown in the form:
1. Load all active categories in one query (no eager load needed)
2. Build adjacency map in PHP (`$children` array keyed by parent_id)
3. Depth-first walk sets `$cat->depth` attribute
4. Result is flat array in tree order — Blade uses `str_repeat('— ', $cat->depth)` for indentation

## Checklist
- [ ] `tree()` uses `with('children')` not lazy-loading
- [ ] `buildTree()` filters roots with `whereNull('parent_id')`
- [ ] `flatTree()` returns items in depth-first order with `depth` attribute
- [ ] `flatTree()` only loads active categories (used in form dropdowns)
- [ ] `create()` includes `parent_id` in data passthrough
- [ ] `update()` includes `parent_id` in data passthrough
- [ ] `delete()` is soft delete only
