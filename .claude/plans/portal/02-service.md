# Customer Portal — Service Methods

Do NOT create a new service. Add these methods to the existing `CustomerService`.
File: `app/Services/CustomerService.php`

---

## New Methods to Add

```php
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Register a new customer.
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
 * Used in portal controllers to get the current customer.
 */
public function getByUser(User $user): Customer
{
    return Customer::where('user_id', $user->id)->firstOrFail();
}

/**
 * Update the customer's own profile (portal use only).
 * Does NOT allow status change — status is admin-only.
 *
 * @param array{name: string, phone: string, company_name: ?string, address: string, city: string, state: string, postal_code: string, country: string} $data
 */
public function updateProfile(Customer $customer, array $data): Customer
{
    $customer->update([
        'name'         => $data['name'],
        'phone'        => $data['phone'],
        'company_name' => $data['company_name'] ?? null,
        'address'      => $data['address'],
        'city'         => $data['city'],
        'state'        => $data['state'],
        'postal_code'  => $data['postal_code'],
        'country'      => $data['country'],
    ]);

    // Also sync name on the User account
    $customer->user->update(['name' => $data['name']]);

    return $customer->fresh();
}

/**
 * Change the customer's password.
 * Verifies current password before updating.
 * Returns false if current password is wrong.
 */
public function changePassword(User $user, string $currentPassword, string $newPassword): bool
{
    if (! Hash::check($currentPassword, $user->password)) {
        return false;
    }

    $user->update(['password' => Hash::make($newPassword)]);

    return true;
}
```

---

## Method Summary

| Method | Input | Output | Notes |
|--------|-------|--------|-------|
| `register(array)` | validated registration data | `Customer` | Creates User + Customer in transaction |
| `getByUser(User)` | User model | `Customer` | Gets customer linked to logged-in user |
| `updateProfile(Customer, array)` | customer + validated data | `Customer` | No status field — portal cannot change status |
| `changePassword(User, string, string)` | user + current + new password | `bool` | Returns false if current password wrong |

---

## Rules
- `register()` uses `DB::transaction` — both User and Customer must be created or neither
- `updateProfile()` does NOT include `status` or `email` — customer cannot change their own email or status
- `changePassword()` returns `bool` — controller checks return value and shows error if false
- `getByUser()` uses `firstOrFail()` — throws 404 if no customer profile found (should never happen for valid users)
