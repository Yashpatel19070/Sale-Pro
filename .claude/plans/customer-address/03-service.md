# Customer Address Module — Service

**File:** `app/Services/CustomerAddressService.php`

Handles all business logic for customer addresses. Controller calls service — never touches model directly.

---

## Full Service Code

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CustomerAddressService
{
    public function list(Customer $customer): Collection
    {
        return $customer->addresses()
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();
    }

    public function store(Customer $customer, array $data): CustomerAddress
    {
        return $customer->addresses()->create($data);
    }

    public function update(CustomerAddress $address, array $data): CustomerAddress
    {
        $address->update($data);

        return $address->fresh();
    }

    public function setDefault(CustomerAddress $address): CustomerAddress
    {
        return DB::transaction(function () use ($address) {
            CustomerAddress::where('customer_id', $address->customer_id)
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);

            $address->update(['is_default' => true]);

            return $address->fresh();
        });
    }

    public function delete(CustomerAddress $address): void
    {
        if ($address->is_default) {
            throw new \RuntimeException('Cannot delete the default address.');
        }

        $address->delete();
    }
}
```

---

## Method Summary

| Method | Args | Returns | Transaction | Notes |
|--------|------|---------|-------------|-------|
| `list(Customer)` | Customer | `Collection<CustomerAddress>` | No | Default first, then by label |
| `store(Customer, array)` | Customer + validated data | `CustomerAddress` | No | `is_default` not accepted — set via `setDefault` only |
| `update(CustomerAddress, array)` | Address + validated data | `CustomerAddress` (fresh) | No | `is_default` not accepted — set via `setDefault` only |
| `setDefault(CustomerAddress)` | Address | `CustomerAddress` (fresh) | Yes | Unsets all others, sets this one |
| `delete(CustomerAddress)` | Address | void | No | Throws `RuntimeException` if address is default |

---

## Business Rules

### `is_default` invariant
Only one address per customer may have `is_default = true`. Only `setDefault()` changes it — store/update never touch it.

| Operation | `is_default` handling |
|-----------|----------------------|
| `store()` | Never sets `is_default` — new address always created with DB default (`false`) |
| `update()` | Never changes `is_default` — field not in validated data |
| `setDefault()` | Unsets all others unconditionally, sets this one |
| `delete()` | Throws `RuntimeException('Cannot delete the default address.')` if `is_default = true` — caller must reassign default first |

### Why `DB::transaction` on setDefault only
`setDefault` writes to multiple rows. `store` and `update` are single-row writes — no transaction needed.

### Why no `paginate()`
Addresses per customer are few (< 20). Return full collection — no pagination needed.

### Why `$customer->addresses()->create($data)` in store
Relationship method sets `customer_id` automatically. `customer_id` is not in `$fillable` — cannot be mass-assigned.
