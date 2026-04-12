# ProductCategory Module — Model

## File
`app/Models/ProductCategory.php`

## Full Implementation

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCategory extends Model
{
    /** @use HasFactory<ProductCategoryFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'parent_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForDropdown(Builder $query): Builder
    {
        return $query->active()
            ->orderBy('name')
            ->select(['id', 'parent_id', 'name']);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Collect all descendant IDs (children, grandchildren, etc.).
     * Used to prevent assigning a category as its own ancestor.
     *
     * @return array<int>
     */
    public function descendantIds(): array
    {
        $ids = [];

        foreach ($this->children as $child) {
            $ids[] = $child->id;
            array_push($ids, ...$child->descendantIds());
        }

        return $ids;
    }
}
```

## Why `descendantIds()` on the Model
When building the parent dropdown for edit, we exclude the category itself AND all
its descendants — otherwise you could create a circular reference (A → B → A).
`descendantIds()` walks the already-loaded `children` relation recursively in PHP.
Efficient enough for typical category counts (< 500). No extra queries if
`children.children` is eager-loaded before calling it.

---

## Factory
`database/factories/ProductCategoryFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'parent_id'   => null,
            'name'        => $this->faker->unique()->words(2, true),
            'description' => $this->faker->optional()->sentence(),
            'is_active'   => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function childOf(ProductCategory $parent): static
    {
        return $this->state(['parent_id' => $parent->id]);
    }
}
```

## Checklist
- [ ] `parent_id` in `$fillable`
- [ ] `parent()` BelongsTo — self-referencing with explicit FK `parent_id`
- [ ] `children()` HasMany — self-referencing with explicit FK `parent_id`
- [ ] `scopeRoots()` — whereNull('parent_id')
- [ ] `scopeActive()` — where is_active = true
- [ ] `scopeForDropdown()` — active + ordered + selects id, parent_id, name
- [ ] `descendantIds()` — recursive walk of loaded children
- [ ] Factory has `childOf(ProductCategory $parent)` state
- [ ] Factory `parent_id` defaults to null
