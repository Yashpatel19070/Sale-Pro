# Product Module — Requests & Policy

## StoreProductRequest
`app/Http/Requests/Product/StoreProductRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // controller calls $this->authorize()
    }

    public function rules(): array
    {
        return [
            'category_id'    => ['nullable', 'integer', 'exists:product_categories,id'],
            'sku'            => ['required', 'string', 'max:64', 'unique:products,sku', 'regex:/^[A-Za-z0-9\-\.]+$/'],
            'name'           => ['required', 'string', 'max:200'],
            'description'    => ['nullable', 'string', 'max:5000'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'regular_price'  => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'sale_price'     => ['nullable', 'numeric', 'min:0', 'max:9999999.99', 'lt:regular_price'],
            'notes'          => ['nullable', 'string', 'max:500'],
            'is_active'      => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.regex'        => 'SKU may only contain letters, numbers, hyphens, and dots.',
            'sale_price.lt'    => 'Sale price must be less than the regular price.',
        ];
    }
}
```

---

## UpdateProductRequest
`app/Http/Requests/Product/UpdateProductRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id'    => ['nullable', 'integer', 'exists:product_categories,id'],
            // SKU is editable. Rule::unique()->ignore() allows saving the same SKU
            // while still blocking another product from taking it.
            // Changing SKU auto-regenerates slugs for all non-trashed listings.
            'sku'            => ['required', 'string', 'max:64',
                                 Rule::unique('products', 'sku')->ignore($this->product),
                                 'regex:/^[A-Za-z0-9\-\.]+$/'],
            'name'           => ['required', 'string', 'max:200'],
            'description'    => ['nullable', 'string', 'max:5000'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'regular_price'  => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'sale_price'     => ['nullable', 'numeric', 'min:0', 'max:9999999.99', 'lt:regular_price'],
            'notes'          => ['nullable', 'string', 'max:500'],
            'is_active'      => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.regex'     => 'SKU may only contain letters, numbers, hyphens, and dots.',
            'sale_price.lt' => 'Sale price must be less than the regular price.',
        ];
    }
}
```

---

## ProductPolicy
`app/Policies/ProductPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PRODUCTS_VIEW_ANY);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can(Permission::PRODUCTS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PRODUCTS_CREATE);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can(Permission::PRODUCTS_EDIT);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can(Permission::PRODUCTS_DELETE);
    }

    public function restore(User $user): bool
    {
        return $user->can(Permission::PRODUCTS_RESTORE);
    }
}
```

---

## Permission Constants (add to `app/Enums/Permission.php`)

```php
// Products
const PRODUCTS_VIEW_ANY = 'products.view-any';
const PRODUCTS_VIEW     = 'products.view';
const PRODUCTS_CREATE   = 'products.create';
const PRODUCTS_EDIT     = 'products.edit';
const PRODUCTS_DELETE   = 'products.delete';
const PRODUCTS_RESTORE  = 'products.restore';
```

---

## Register Policy (in `AppServiceProvider::boot()`)

```php
Gate::policy(Product::class, ProductPolicy::class);
```

## Checklist
- [ ] `StoreProductRequest` — SKU required + regex + unique
- [ ] `UpdateProductRequest` — SKU present with `Rule::unique()->ignore($this->product)`
- [ ] `UpdateProductRequest` — SKU regex matches store request
- [ ] `category_id` — nullable + exists in both requests
- [ ] `regular_price` — required numeric
- [ ] `purchase_price` — nullable numeric (internal)
- [ ] `sale_price` — nullable, `lt:regular_price`
- [ ] Policy registered in AppServiceProvider
- [ ] 6 permission constants added to Permission enum
