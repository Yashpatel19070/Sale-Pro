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
use Illuminate\Support\Facades\Hash;

class CustomerService
{
    /**
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
     * @param array<string, mixed> $data
     */
    public function store(array $data): Customer
    {
        return Customer::create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer;
    }

    public function changeStatus(Customer $customer, CustomerStatus $status): Customer
    {
        $customer->update(['status' => $status]);

        return $customer;
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }

    /**
     * Admin sets customer portal password directly — no current password required.
     * Use this for admin-initiated resets, not customer self-service.
     */
    public function setPassword(Customer $customer, string $password): void
    {
        $customer->update(['password' => Hash::make($password)]);
    }

    /**
     * No-op if customer is already verified.
     * Admin force-verifies email from backend.
     */
    public function verifyEmail(Customer $customer): void
    {
        if ($customer->hasVerifiedEmail()) {
            return;
        }

        $customer->markEmailAsVerified();
    }
}
```

---

## Method Summary

| Method | Input | Output | Notes |
|--------|-------|--------|-------|
| `paginate(array)` | search, status filters | `LengthAwarePaginator` | 20 per page |
| `store(array)` | validated array | `Customer` | Admin creates customer CRM record |
| `update(Customer, array)` | model + validated array | `Customer` | Admin edits customer |
| `changeStatus(Customer, CustomerStatus)` | model + enum | `Customer` | Admin changes status |
| `delete(Customer)` | model | void | Soft delete only |
| `setPassword(Customer, string)` | model + plaintext password | void | Admin resets portal password |
| `verifyEmail(Customer)` | model | void | Admin force-verifies email |

---

## Portal Methods (add to same CustomerService class)

Used by the Customer Portal — customer self-service actions.
Reference: `.claude/plans/portal-foundation/03-auth-controllers.md`

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Register a new customer from the portal.
 * Customer IS the auth model — no User row created.
 *
 * @param array{name: string, email: string, password: string, phone: string, company_name: ?string} $data
 */
public function register(array $data): Customer
{
    return Customer::create([
        'name'         => $data['name'],
        'email'        => $data['email'],
        'password'     => Hash::make($data['password']),
        'phone'        => $data['phone'],
        'company_name' => $data['company_name'] ?? null,
        'status'       => CustomerStatus::Active,
    ]);
}

/**
 * Portal customer updates own profile.
 * Does NOT update email or status — admin-only fields.
 *
 * @param array{name: string, phone: string, company_name: ?string} $data
 */
public function updateProfile(Customer $customer, array $data): Customer
{
    $customer->update([
        'name'         => $data['name'],
        'phone'        => $data['phone'],
        'company_name' => $data['company_name'] ?? null,
    ]);

    return $customer;
}

/**
 * Portal customer changes own password.
 * Verifies current password before updating.
 *
 * @throws ValidationException if current password is wrong
 */
public function changePassword(Customer $customer, string $currentPassword, string $newPassword): void
{
    if (! Hash::check($currentPassword, $customer->password)) {
        throw ValidationException::withMessages([
            'current_password' => 'Current password is incorrect.',
        ]);
    }

    $customer->update(['password' => Hash::make($newPassword)]);
}
```

---

## Portal Method Summary

| Method | Input | Output | Notes |
|--------|-------|--------|-------|
| `register(array)` | validated registration data | `Customer` | Customer self-registration — no User created |
| `updateProfile(Customer, array)` | customer + data | `Customer` | No email/status — admin-only |
| `changePassword(Customer, string, string)` | customer + passwords | void | Throws ValidationException if current wrong |

---

## Removed Methods

| Removed | Reason |
|---------|--------|
| `getByUser(User $user)` | No longer needed — `auth('customer')->user()` returns Customer directly |

---

## Rules

- `store()` creates CRM record only — no portal account created automatically
- `register()` creates portal-ready Customer (with password) — called from portal registration controller
- `setPassword()` — admin use only, no current password check
- `changePassword()` — customer use only, requires current password
- `delete()` is always soft delete — no `forceDelete()`
