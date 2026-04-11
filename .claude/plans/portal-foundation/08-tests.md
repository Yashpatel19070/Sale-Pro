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

// Seed roles before each test — RefreshDatabase wipes them
// customer role needed for portal, admin/staff roles needed for cross-role tests
beforeEach(function () {
    $this->seed(\Database\Seeders\CustomerRoleSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class); // seeds admin, staff roles — verify seeder name matches project
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
        'address'               => '123 Main St',
        'city'                  => 'Springfield',
        'state'                 => 'IL',
        'postal_code'           => '62701',
        'country'               => 'USA',
    ], $overrides);
}

// Helper — create a verified customer user
function verifiedCustomer(): User
{
    $user = User::factory()->create([
        'password'          => Hash::make('password123'),
        'email_verified_at' => now(),
    ]);
    $user->assignRole('customer');
    Customer::factory()->create([
        'user_id' => $user->id,
        'email'   => $user->email,
        'status'  => CustomerStatus::Active,
    ]);
    return $user;
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

    $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    $this->assertDatabaseHas('customers', ['email' => 'jane@example.com']);

    $user = User::where('email', 'jane@example.com')->first();
    expect($user->hasRole('customer'))->toBeTrue();
    $this->assertAuthenticated();
});

it('register fails with duplicate email', function () {
    User::factory()->create(['email' => 'jane@example.com']);

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

it('logged in user cannot see register page', function () {
    $user = verifiedCustomer();

    $this->actingAs($user)
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
    $user = verifiedCustomer();

    $this->post(route('portal.login.store'), [
        'email'    => $user->email,
        'password' => 'password123',
    ])->assertRedirect(route('portal.dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('login fails with wrong password', function () {
    $user = verifiedCustomer();

    $this->post(route('portal.login.store'), [
        'email'    => $user->email,
        'password' => 'wrongpassword',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('admin cannot login via portal', function () {
    $admin = User::factory()->create(['password' => Hash::make('password123')]);
    $admin->assignRole('admin');

    $this->post(route('portal.login.store'), [
        'email'    => $admin->email,
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('blocked customer cannot login', function () {
    $user = User::factory()->create(['password' => Hash::make('password123'), 'email_verified_at' => now()]);
    $user->assignRole('customer');
    Customer::factory()->create([
        'user_id' => $user->id,
        'email'   => $user->email,
        'status'  => CustomerStatus::Blocked,
    ]);

    $this->post(route('portal.login.store'), [
        'email'    => $user->email,
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('inactive customer cannot login', function () {
    $user = User::factory()->create(['password' => Hash::make('password123'), 'email_verified_at' => now()]);
    $user->assignRole('customer');
    Customer::factory()->create([
        'user_id' => $user->id,
        'email'   => $user->email,
        'status'  => CustomerStatus::Inactive,
    ]);

    $this->post(route('portal.login.store'), [
        'email'    => $user->email,
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logged in user cannot see login page', function () {
    $user = verifiedCustomer();

    $this->actingAs($user)
        ->get(route('portal.login'))
        ->assertRedirect();
});

// ===========================================================
// LOGOUT
// ===========================================================

it('customer can logout', function () {
    $user = verifiedCustomer();

    $this->actingAs($user)
        ->post(route('portal.logout'))
        ->assertRedirect(route('portal.login'));

    $this->assertGuest();
});

// ===========================================================
// EMAIL VERIFICATION
// ===========================================================

it('unverified customer is redirected to verify notice', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    $user->assignRole('customer');
    Customer::factory()->create(['user_id' => $user->id, 'email' => $user->email]);

    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.verification.notice'));
});

it('customer can verify email via signed link', function () {
    Event::fake();

    $user = User::factory()->create(['email_verified_at' => null]);
    $user->assignRole('customer');
    Customer::factory()->create(['user_id' => $user->id, 'email' => $user->email]);

    $verificationUrl = URL::temporarySignedRoute(
        'portal.verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)
        ->get($verificationUrl)
        ->assertRedirect(route('portal.dashboard'));

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('customer can resend verification email', function () {
    Notification::fake();

    $user = User::factory()->create(['email_verified_at' => null]);
    $user->assignRole('customer');
    Customer::factory()->create(['user_id' => $user->id, 'email' => $user->email]);

    $this->actingAs($user)
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

it('admin cannot access portal dashboard', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('portal.dashboard'))
        ->assertForbidden();
});

it('verified customer can access dashboard', function () {
    $user = verifiedCustomer();

    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertViewIs('portal.dashboard');
});
```

---

## Running Tests
```bash
php artisan test --filter PortalAuthTest
```
