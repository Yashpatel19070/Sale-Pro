# Customer Module — Service

**File:** `app/Services/CustomerService.php`

The service handles all business logic. The controller calls the service — never touches the model directly.

---

## Full Service Code

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerService
{
    /**
     * Return a paginated list of customers.
     * Supports optional search (name / email / company_name) and status filter.
     *
     * @param array{search?: string, status?: string} $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Customer::query()
            ->when(
                isset($filters['search']) && $filters['search'] !== '',
                fn ($q) => $q->search($filters['search'])
            )
            ->when(
                isset($filters['status']) && $filters['status'] !== '',
                fn ($q) => $q->byStatus(CustomerStatus::from($filters['status']))
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * Create a new customer.
     *
     * @param array<string, mixed> $data — from StoreCustomerRequest::validated()
     */
    public function store(array $data): Customer
    {
        return Customer::create($data);
    }

    /**
     * Update an existing customer.
     *
     * @param array<string, mixed> $data — from UpdateCustomerRequest::validated()
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer->fresh();
    }

    /**
     * Change the status of a customer.
     */
    public function changeStatus(Customer $customer, CustomerStatus $status): Customer
    {
        $customer->update(['status' => $status->value]);

        return $customer->fresh();
    }

    /**
     * Soft-delete a customer.
     * Record is NOT permanently removed — deleted_at is set.
     */
    public function delete(Customer $customer): void
    {
        $customer->delete();
    }
}
```

---

## Method Summary

| Method | Input | Output | Notes |
|--------|-------|--------|-------|
| `paginate(array $filters)` | `search`, `status` keys | `LengthAwarePaginator` | 20 per page, preserves query string |
| `store(array $data)` | validated array | `Customer` | Calls `Customer::create()` |
| `update(Customer, array $data)` | model + validated array | `Customer` (fresh) | Returns refreshed model |
| `changeStatus(Customer, CustomerStatus)` | model + enum | `Customer` (fresh) | Stores enum value string |
| `delete(Customer)` | model | void | Soft delete only |

---

## Rules
- Never call `$request->all()` — data must come pre-validated from the controller
- `store()` and `update()` only receive `$request->validated()` output
- `delete()` is always soft delete — no `forceDelete()` anywhere in this module
- `paginate()` uses `withQueryString()` so search/filter params survive pagination links

---

## Portal Methods (add to same CustomerService class)

These methods are used by the Customer Portal Foundation.
Reference: `.claude/plans/portal-foundation/03-auth-controllers.md`

```php
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Register a new customer from the portal.
 * Creates a User account + Customer record in a single transaction.
 * Assigns the 'customer' role to the User.
 *
 * @param array{name: string, email: string, password: string, phone: string, company_name: ?string, address: string, city: string, state: string, postal_code: string, country: string} $data
 */
public function register(array $data): Customer
{
    return DB::transaction(function () use ($data) {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole('customer');

        return Customer::create([
            'user_id'      => $user->id,
            'name'         => $data['name'],
            'email'        => $data['email'],
            'phone'        => $data['phone'],
            'company_name' => $data['company_name'] ?? null,
            'address'      => $data['address'],
            'city'         => $data['city'],
            'state'        => $data['state'],
            'postal_code'  => $data['postal_code'],
            'country'      => $data['country'],
            'status'       => CustomerStatus::Active->value,
        ]);
    });
}

/**
 * Get the Customer profile linked to a User.
 * Used in portal controllers to get the current logged-in customer.
 */
public function getByUser(User $user): Customer
{
    return Customer::where('user_id', $user->id)->firstOrFail();
}
```

## Portal Method Summary

| Method | Input | Output | Notes |
|--------|-------|--------|-------|
| `register(array $data)` | validated registration data | `Customer` | Creates User + Customer in DB::transaction |
| `getByUser(User $user)` | User model | `Customer` | Throws 404 if no customer linked to user |
