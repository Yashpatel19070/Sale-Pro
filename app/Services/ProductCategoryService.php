<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Collection;

class ProductCategoryService
{
    /**
     * Full tree for the admin index — roots with all children eager-loaded.
     *
     * @param  array{search?: string, active?: string}  $filters
     * @return Collection<int, ProductCategory>
     */
    public function tree(array $filters = []): Collection
    {
        $searching = isset($filters['search']) && $filters['search'] !== '';

        $all = ProductCategory::with('children.children.children')
            ->when(
                $searching,
                fn ($q) => $q->where('name', 'like', "%{$filters['search']}%")
            )
            ->when(
                isset($filters['active']) && $filters['active'] !== '',
                fn ($q) => $q->where('is_active', (bool) $filters['active'])
            )
            ->orderBy('name')
            ->get();

        // When searching, show all matches regardless of depth.
        // Without a search, only roots are returned (children render via the recursive partial).
        return $searching ? $all->values() : $all->whereNull('parent_id')->values();
    }

    /**
     * Depth-first flat list with a 'depth' attribute on each item.
     * Used to build an indented <select> dropdown.
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

        $children = [];
        $roots = [];

        foreach ($all as $cat) {
            if ($cat->parent_id === null) {
                $roots[] = $cat->id;
            } else {
                $children[$cat->parent_id][] = $cat->id;
            }
        }

        $result = [];
        $walk = function (int $id, int $depth) use (&$walk, &$result, $all, $children): void {
            $cat = $all[$id];
            $cat->depth = $depth;
            $result[] = $cat;

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

    /**
     * Active categories for simple dropdowns (id + name only).
     *
     * @return Collection<int, ProductCategory>
     */
    public function dropdown(): Collection
    {
        return ProductCategory::forDropdown()->get();
    }
}
