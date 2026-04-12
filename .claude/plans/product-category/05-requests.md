# ProductCategory Module — FormRequests

## Files
- `app/Http/Requests/ProductCategory/StoreProductCategoryRequest.php`
- `app/Http/Requests/ProductCategory/UpdateProductCategoryRequest.php`

---

## StoreProductCategoryRequest

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\ProductCategory;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ProductCategory::class);
    }

    public function rules(): array
    {
        return [
            'parent_id'   => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')->whereNull('deleted_at'),
            ],
            'name'        => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_categories', 'name')
                    ->where('parent_id', $this->input('parent_id'))
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }
}
```

---

## UpdateProductCategoryRequest

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\ProductCategory;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('productCategory'));
    }

    public function rules(): array
    {
        /** @var ProductCategory $category */
        $category = $this->route('productCategory');

        // Prevent assigning self or any descendant as parent
        $forbiddenIds = array_merge(
            [$category->id],
            $category->load('children.children.children')->descendantIds()
        );

        return [
            'parent_id'   => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')->whereNull('deleted_at'),
                Rule::notIn($forbiddenIds),
            ],
            'name'        => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_categories', 'name')
                    ->where('parent_id', $this->input('parent_id'))
                    ->ignore($category->id)
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }
}
```

## Validation Details

### `parent_id`
- `nullable` — null means root category
- `exists` in `product_categories` (soft-delete aware via `whereNull('deleted_at')`)
- On update: `Rule::notIn($forbiddenIds)` prevents:
  - Assigning the category as its own parent
  - Assigning any descendant as parent (circular reference)

### `name` uniqueness
- Unique **within the same parent** — `->where('parent_id', $this->input('parent_id'))`
- Soft-delete aware — `->whereNull('deleted_at')`
- On update — `->ignore($category->id)` allows saving same name

### Circular reference prevention (Update only)
```php
$forbiddenIds = array_merge([$category->id], $category->descendantIds());
```
Loads descendants up to 3 levels deep before calling `descendantIds()`.
Adjust `load('children.children.children')` depth if needed.

## Checklist
- [ ] `parent_id` is nullable + exists check with soft-delete awareness
- [ ] `name` unique scoped to same `parent_id`
- [ ] Update request forbids self + descendants as parent
- [ ] `is_active` is `nullable|boolean` (unchecked checkbox = null = false)
- [ ] Both requests have `authorize()` checking the correct policy gate
