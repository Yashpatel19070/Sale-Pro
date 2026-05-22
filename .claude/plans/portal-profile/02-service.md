# Customer Portal — Service Methods

Do NOT create a new service. Add these methods to the existing `CustomerService`.
File: `app/Services/CustomerService.php`

---

## Methods to Add / Update

```php
use Illuminate\Support\Facades\Hash;
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
 * Does NOT allow email or status changes — admin-only fields.
 * No user sync needed — Customer is its own auth record.
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
 * Requires current password — use CustomerService::setPassword() for admin resets.
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

## How to Get Current Customer in Portal Controllers

No `getByUser()` method. Use the guard directly:

```php
// In portal controllers:
/** @var \App\Models\Customer $customer */
$customer = auth('customer')->user();

// Or via request:
$customer = $request->user('customer');
```

---

## Method Summary

| Method | Input | Output | Notes |
|--------|-------|--------|-------|
| `register(array)` | validated registration data | `Customer` | No User created — Customer IS the auth model |
| `updateProfile(Customer, array)` | customer + data | `Customer` | name/phone/company only — no email/status |
| `changePassword(Customer, string, string)` | customer + passwords | void | Throws ValidationException if current wrong |

---

## Rules

- `register()` — no `DB::transaction` needed (single table write)
- `updateProfile()` — no user sync (no linked User model)
- `changePassword()` — takes `Customer`, not `User`
- `getByUser()` is deleted — use `auth('customer')->user()` everywhere
