# Portal Foundation — Tests

**File:** `tests/Feature/Portal/Auth/PortalAuthTest.php`

---

```php
<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

// Seed staff roles for cross-role rejection tests
beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

// Helper — valid registration payload
function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name'                  => 'Jane Doe',
        'email'                 => 'jane@example.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
        'phone'                 => '555-123-4567',
        'company_name'          => null,
    ], $overrides);
}

// Helper — create a verified, active customer (ready to login)
function verifiedCustomer(): Customer
{
    return Customer::factory()->create([
        'password'          => Hash::make('password123'),
        'email_verified_at' => now(),
        'status'            => CustomerStatus::Active,
    ]);
}

// ===========================================================
// REGISTER
// ===========================================================

it('shows the register page', function () {
    $this->get(route('portal.register'))
        ->assertOk()
        ->assertViewIs('portal.auth.register');
});

it('customer can register', function () {
    Notification::fake();

    $this->post(route('portal.register.store'), registrationPayload())
        ->assertRedirect(route('portal.verification.notice'));

    $this->assertDatabaseHas('customers', ['email' => 'jane@example.com']);

    $this->assertAuthenticatedAs(
        Customer::where('email', 'jane@example.com')->first(),
        'customer'
    );
});

it('register fails with duplicate email', function () {
    Customer::factory()->create(['email' => 'jane@example.com']);

    $this->post(route('portal.register.store'), registrationPayload())
        ->assertSessionHasErrors('email');
});

it('register fails with mismatched passwords', function () {
    $this->post(route('portal.register.store'), registrationPayload([
        'password_confirmation' => 'different',
    ]))->assertSessionHasErrors('password');
});

it('register fails with missing required field', function () {
    $this->post(route('portal.register.store'), registrationPayload(['phone' => '']))
        ->assertSessionHasErrors('phone');
});

it('logged in customer cannot see register page', function () {
    $customer = verifiedCustomer();

    $this->actingAs($customer, 'customer')
        ->get(route('portal.register'))
        ->assertRedirect();
});

// ===========================================================
// LOGIN
// ===========================================================

it('shows the login page', function () {
    $this->get(route('portal.login'))
        ->assertOk()
        ->assertViewIs('portal.auth.login');
});

it('customer can login with valid credentials', function () {
    $customer = verifiedCustomer();

    $this->post(route('portal.login.store'), [
        'email'    => $customer->email,
        'password' => 'password123',
    ])->assertRedirect(route('portal.dashboard'));

    $this->assertAuthenticatedAs($customer, 'customer');
});

it('login fails with wrong password', function () {
    $customer = verifiedCustomer();

    $this->post(route('portal.login.store'), [
        'email'    => $customer->email,
        'password' => 'wrongpassword',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('customer');
});

it('admin credentials cannot login via portal', function () {
    // Staff users are in `users` table — not in `customers` table
    // customer guard checks `customers` table — staff email will simply not match
    $admin = User::factory()->create(['password' => Hash::make('password123')]);
    $admin->assignRole('admin');

    $this->post(route('portal.login.store'), [
        'email'    => $admin->email,
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('customer');
});

it('blocked customer cannot login', function () {
    $customer = Customer::factory()->create([
        'password'          => Hash::make('password123'),
        'email_verified_at' => now(),
        'status'            => CustomerStatus::Blocked,
    ]);

    $this->post(route('portal.login.store'), [
        'email'    => $customer->email,
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('customer');
});

it('inactive customer cannot login', function () {
    $customer = Customer::factory()->create([
        'password'          => Hash::make('password123'),
        'email_verified_at' => now(),
        'status'            => CustomerStatus::Inactive,
    ]);

    $this->post(route('portal.login.store'), [
        'email'    => $customer->email,
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('customer');
});

it('logged in customer cannot see login page', function () {
    $customer = verifiedCustomer();

    $this->actingAs($customer, 'customer')
        ->get(route('portal.login'))
        ->assertRedirect();
});

// ===========================================================
// LOGOUT
// ===========================================================

it('customer can logout', function () {
    $customer = verifiedCustomer();

    $this->actingAs($customer, 'customer')
        ->post(route('portal.logout'))
        ->assertRedirect(route('portal.login'));

    $this->assertGuest('customer');
});

// ===========================================================
// EMAIL VERIFICATION
// ===========================================================

it('unverified customer is redirected to verify notice', function () {
    $customer = Customer::factory()->create(['email_verified_at' => null]);

    $this->actingAs($customer, 'customer')
        ->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.verification.notice'));
});

it('customer can verify email via signed link', function () {
    Event::fake();

    $customer = Customer::factory()->create(['email_verified_at' => null]);

    $verificationUrl = URL::temporarySignedRoute(
        'portal.verification.verify',
        now()->addMinutes(60),
        ['id' => $customer->id, 'hash' => sha1($customer->email)]
    );

    $this->actingAs($customer, 'customer')
        ->get($verificationUrl)
        ->assertRedirect(route('portal.dashboard'));

    Event::assertDispatched(Verified::class);
    expect($customer->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('customer can resend verification email', function () {
    Notification::fake();

    $customer = Customer::factory()->create(['email_verified_at' => null]);

    $this->actingAs($customer, 'customer')
        ->post(route('portal.verification.send'))
        ->assertRedirect();
});

// ===========================================================
// FORGOT / RESET PASSWORD
// ===========================================================

it('shows the forgot password page', function () {
    $this->get(route('portal.password.request'))
        ->assertOk()
        ->assertViewIs('portal.auth.forgot-password');
});

it('forgot password always returns success message', function () {
    $this->post(route('portal.password.email'), ['email' => 'anyone@example.com'])
        ->assertSessionHas('status');
});

it('shows the reset password page', function () {
    $this->get(route('portal.password.reset', ['token' => 'sometoken']))
        ->assertOk()
        ->assertViewIs('portal.auth.reset-password');
});

// ===========================================================
// DASHBOARD PROTECTION
// ===========================================================

it('guest cannot access dashboard', function () {
    $this->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.login'));
});

it('admin user cannot access portal dashboard', function () {
    // Staff are authenticated via web guard — portal uses customer guard
    // actingAs($admin) authenticates web guard only, portal route requires customer guard
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin) // web guard
        ->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.login')); // not authenticated on customer guard
});

it('verified customer can access dashboard', function () {
    $customer = verifiedCustomer();

    $this->actingAs($customer, 'customer')
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertViewIs('portal.dashboard');
});
```

---

## Key Changes from Old Tests

| Before | After |
|--------|-------|
| `$this->seed(CustomerRoleSeeder::class)` | Removed — no customer role |
| `User::factory()->create()` + `assignRole('customer')` | `Customer::factory()->create()` directly |
| `Customer::factory()->create(['user_id' => $user->id])` | No `user_id` — Customer IS the auth record |
| `$this->actingAs($user)` | `$this->actingAs($customer, 'customer')` |
| `$this->assertAuthenticatedAs($user)` | `$this->assertAuthenticatedAs($customer, 'customer')` |
| `$this->assertGuest()` | `$this->assertGuest('customer')` |
| Admin test: `assertForbidden()` | `assertRedirect(route('portal.login'))` — not authenticated on customer guard |

---

## Running Tests

```bash
php artisan test --filter PortalAuthTest
```
