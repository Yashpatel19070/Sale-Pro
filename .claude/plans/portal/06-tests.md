# Customer Portal — Tests

**File:** `tests/Feature/Portal/ProfileControllerTest.php`

---

## Setup Helper

```php
<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// Create a fully registered customer (User + Customer linked)
function portalCustomer(array $customerOverrides = []): array
{
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
    ]);
    $user->assignRole('customer');

    $customer = Customer::factory()->create(array_merge([
        'user_id' => $user->id,
        'email'   => $user->email,
        'status'  => CustomerStatus::Active,
    ], $customerOverrides));

    return [$user, $customer];
}
```

---

## Registration Tests

```php
it('customer can see the register form', function () {
    $this->get(route('portal.register'))
        ->assertOk()
        ->assertViewIs('portal.auth.register');
});

it('customer can register a new account', function () {
    $this->post(route('portal.register.store'), [
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
    ])->assertRedirect(route('portal.dashboard'));

    $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    $this->assertDatabaseHas('customers', ['email' => 'jane@example.com']);

    $user = User::where('email', 'jane@example.com')->first();
    expect($user->hasRole('customer'))->toBeTrue();
    expect($user->customer)->not->toBeNull();
});

it('register fails with duplicate email', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $this->post(route('portal.register.store'), [
        'name'                  => 'Jane Doe',
        'email'                 => 'jane@example.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
        'phone'                 => '555-123-4567',
        'address'               => '123 Main St',
        'city'                  => 'Springfield',
        'state'                 => 'IL',
        'postal_code'           => '62701',
        'country'               => 'USA',
    ])->assertSessionHasErrors('email');
});

it('register fails with mismatched passwords', function () {
    $this->post(route('portal.register.store'), [
        'name'                  => 'Jane Doe',
        'email'                 => 'jane@example.com',
        'password'              => 'password123',
        'password_confirmation' => 'different',
        'phone'                 => '555-123-4567',
        'address'               => '123 Main St',
        'city'                  => 'Springfield',
        'state'                 => 'IL',
        'postal_code'           => '62701',
        'country'               => 'USA',
    ])->assertSessionHasErrors('password');
});
```

---

## Login / Logout Tests

```php
it('customer can see the login form', function () {
    $this->get(route('portal.login'))
        ->assertOk()
        ->assertViewIs('portal.auth.login');
});

it('customer can login with valid credentials', function () {
    [$user] = portalCustomer();

    $this->post(route('portal.login.store'), [
        'email'    => $user->email,
        'password' => 'password123',
    ])->assertRedirect(route('portal.dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('login fails with wrong password', function () {
    [$user] = portalCustomer();

    $this->post(route('portal.login.store'), [
        'email'    => $user->email,
        'password' => 'wrongpassword',
    ])->assertSessionHasErrors('email');
});

it('admin cannot login via portal', function () {
    $admin = User::factory()->create(['password' => Hash::make('password123')]);
    $admin->assignRole('admin');

    $this->post(route('portal.login.store'), [
        'email'    => $admin->email,
        'password' => 'password123',
    ])->assertSessionHasErrors('email');
});

it('customer can logout', function () {
    [$user] = portalCustomer();

    $this->actingAs($user)
        ->post(route('portal.logout'))
        ->assertRedirect(route('portal.login'));

    $this->assertGuest();
});
```

---

## Dashboard Tests

```php
it('customer can see dashboard', function () {
    [$user, $customer] = portalCustomer();

    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertViewIs('portal.dashboard')
        ->assertViewHas('customer');
});

it('guest is redirected from dashboard', function () {
    $this->get(route('portal.dashboard'))
        ->assertRedirect();
});

it('admin cannot access portal dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('portal.dashboard'))
        ->assertForbidden();
});
```

---

## Profile Tests

```php
it('customer can view their profile', function () {
    [$user, $customer] = portalCustomer();

    $this->actingAs($user)
        ->get(route('portal.profile.show'))
        ->assertOk()
        ->assertViewIs('portal.profile.show')
        ->assertViewHas('customer');
});

it('customer can see edit profile form', function () {
    [$user, $customer] = portalCustomer();

    $this->actingAs($user)
        ->get(route('portal.profile.edit'))
        ->assertOk()
        ->assertViewIs('portal.profile.edit');
});

it('customer can update their profile', function () {
    [$user, $customer] = portalCustomer();

    $this->actingAs($user)
        ->put(route('portal.profile.update'), [
            'name'         => 'Updated Name',
            'phone'        => '999-888-7777',
            'company_name' => 'ACME Corp',
            'address'      => '456 New St',
            'city'         => 'Chicago',
            'state'        => 'IL',
            'postal_code'  => '60601',
            'country'      => 'USA',
        ])->assertRedirect(route('portal.profile.show'));

    $this->assertDatabaseHas('customers', [
        'id'   => $customer->id,
        'name' => 'Updated Name',
    ]);
});

it('profile update fails with missing required field', function () {
    [$user] = portalCustomer();

    $this->actingAs($user)
        ->put(route('portal.profile.update'), [
            'name' => '', // missing
        ])->assertSessionHasErrors('name');
});

it('customer cannot update email via profile', function () {
    [$user, $customer] = portalCustomer();
    $originalEmail = $customer->email;

    $this->actingAs($user)
        ->put(route('portal.profile.update'), [
            'name'         => 'Jane',
            'email'        => 'newemail@example.com', // should be ignored
            'phone'        => '555-000-0000',
            'address'      => '123 Main St',
            'city'         => 'Springfield',
            'state'        => 'IL',
            'postal_code'  => '62701',
            'country'      => 'USA',
        ]);

    $this->assertDatabaseHas('customers', [
        'id'    => $customer->id,
        'email' => $originalEmail, // unchanged
    ]);
});
```

---

## Change Password Tests

```php
it('customer can see change password form', function () {
    [$user] = portalCustomer();

    $this->actingAs($user)
        ->get(route('portal.profile.password'))
        ->assertOk()
        ->assertViewIs('portal.profile.password');
});

it('customer can change their password', function () {
    [$user] = portalCustomer();

    $this->actingAs($user)
        ->put(route('portal.profile.password.update'), [
            'current_password'      => 'password123',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertRedirect(route('portal.profile.show'));

    expect(Hash::check('newpassword123', $user->fresh()->password))->toBeTrue();
});

it('change password fails with wrong current password', function () {
    [$user] = portalCustomer();

    $this->actingAs($user)
        ->put(route('portal.profile.password.update'), [
            'current_password'      => 'wrongpassword',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertSessionHasErrors('current_password');
});

it('change password fails with mismatched confirmation', function () {
    [$user] = portalCustomer();

    $this->actingAs($user)
        ->put(route('portal.profile.password.update'), [
            'current_password'      => 'password123',
            'password'              => 'newpassword123',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');
});
```

---

## Running Tests
```bash
php artisan test --filter ProfileControllerTest
```
