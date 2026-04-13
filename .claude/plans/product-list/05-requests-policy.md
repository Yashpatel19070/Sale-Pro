# ProductList Module — Requests & Policy

## StoreProductListingRequest
`app/Http/Requests/ProductListing/StoreProductListingRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\ProductListing;

use App\Enums\ListingVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ProductListing::class);
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'title'      => ['required', 'string', 'max:200'],
            'visibility' => ['required', 'string', Rule::in(array_column(ListingVisibility::cases(), 'value'))],
            'is_active'  => ['nullable', 'boolean'],
        ];
    }
}
```

---

## UpdateProductListingRequest
`app/Http/Requests/ProductListing/UpdateProductListingRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\ProductListing;

use App\Enums\ListingVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product_listing'));
    }

    public function rules(): array
    {
        // product_id intentionally absent — immutable after creation
        return [
            'title'      => ['required', 'string', 'max:200'],
            'visibility' => ['required', 'string', Rule::in(array_column(ListingVisibility::cases(), 'value'))],
            'is_active'  => ['nullable', 'boolean'],
        ];
    }
}
```

---

## ProductListingPolicy
`app/Policies/ProductListingPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ProductListing;
use App\Models\User;

class ProductListingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PRODUCT_LISTINGS_VIEW_ANY);
    }

    public function view(User $user, ProductListing $listing): bool
    {
        return $user->can(Permission::PRODUCT_LISTINGS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PRODUCT_LISTINGS_CREATE);
    }

    public function update(User $user, ProductListing $listing): bool
    {
        return $user->can(Permission::PRODUCT_LISTINGS_EDIT);
    }

    public function delete(User $user, ProductListing $listing): bool
    {
        return $user->can(Permission::PRODUCT_LISTINGS_DELETE);
    }

    public function restore(User $user): bool
    {
        return $user->can(Permission::PRODUCT_LISTINGS_RESTORE);
    }
}
```

---

## Permission Constants (add to `app/Enums/Permission.php`)

```php
// Product Listings
const PRODUCT_LISTINGS_VIEW_ANY = 'product-listings.view-any';
const PRODUCT_LISTINGS_VIEW     = 'product-listings.view';
const PRODUCT_LISTINGS_CREATE   = 'product-listings.create';
const PRODUCT_LISTINGS_EDIT     = 'product-listings.edit';
const PRODUCT_LISTINGS_DELETE   = 'product-listings.delete';
const PRODUCT_LISTINGS_RESTORE  = 'product-listings.restore';
```

---

## Register Policy (in `AppServiceProvider::boot()`)

```php
Gate::policy(ProductListing::class, ProductListingPolicy::class);
```

## Checklist
- [ ] `StoreProductListingRequest` — product_id required + exists; title + visibility only
- [ ] `UpdateProductListingRequest` — product_id **absent** (immutable); title + visibility only
- [ ] No price, stock, or attribute fields in any request
- [ ] Policy registered in AppServiceProvider
- [ ] 6 permission constants added to Permission enum (no ADJUST_STOCK)
