# Customer Portal — Profile Tests

**File:** `tests/Feature/Portal/ProfileControllerTest.php`

Auth tests (register, login, logout, email verify, forgot/reset password) belong in
`tests/Feature/Portal/Auth/PortalAuthTest.php` — see `portal-foundation/08-tests.md`.

This file covers ProfileController only.

---

## Structure

Two `describe` blocks — scoped `beforeEach` means guest tests run unauthenticated cleanly.

```php
<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);
```

---

## Authenticated Tests

```php
describe('authenticated customer', function () {

    beforeEach(function () {
        $this->customer = Customer::factory()->create([
            'password'          => Hash::make('password123'),
            'email_verified_at' => now(),
            'status'            => CustomerStatus::Active,
        ]);

        $this->actingAs($this->customer, 'customer');
    });

    // --- Profile View ---

    it('can view their profile', function () {
        $this->get(route('portal.profile.show'))
            ->assertOk()
            ->assertViewIs('portal.profile.show')
            ->assertViewHas('customer');
    });

    it('can see the edit profile form', function () {
        $this->get(route('portal.profile.edit'))
            ->assertOk()
            ->assertViewIs('portal.profile.edit');
    });

    // --- Profile Update ---

    it('can update their profile', function () {
        $this->put(route('portal.profile.update'), [
            'name'         => 'Updated Name',
            'phone'        => '999-888-7777',
            'company_name' => 'ACME Corp',
        ])->assertRedirect(route('portal.profile.show'));

        $this->assertDatabaseHas('customers', [
            'id'   => $this->customer->id,
            'name' => 'Updated Name',
        ]);
    });

    it('profile update saves phone correctly', function () {
        $this->put(route('portal.profile.update'), [
            'name'  => 'Jane',
            'phone' => '555-000-0000',
        ])->assertRedirect(route('portal.profile.show'));

        $this->assertDatabaseHas('customers', [
            'id'    => $this->customer->id,
            'phone' => '555-000-0000',
        ]);
    });

    it('profile update does not change email', function () {
        $originalEmail = $this->customer->email;

        $this->put(route('portal.profile.update'), [
            'name'  => 'Jane',
            'email' => 'newemail@example.com', // ignored — not in FormRequest rules
            'phone' => '555-000-0000',
        ]);

        $this->assertDatabaseHas('customers', [
            'id'    => $this->customer->id,
            'email' => $originalEmail,
        ]);
    });

    it('profile update fails with missing name', function () {
        $this->put(route('portal.profile.update'), [
            'name'  => '',
            'phone' => '555-000-0000',
        ])->assertSessionHasErrors('name');
    });

    it('profile update fails with missing phone', function () {
        $this->put(route('portal.profile.update'), [
            'name'  => 'Jane',
            'phone' => '',
        ])->assertSessionHasErrors('phone');
    });

    // --- Change Password ---

    it('can see the change password form', function () {
        $this->get(route('portal.profile.password'))
            ->assertOk()
            ->assertViewIs('portal.profile.password');
    });

    it('can change their password', function () {
        $this->put(route('portal.profile.password.update'), [
            'current_password'      => 'password123',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertRedirect(route('portal.profile.show'));

        expect(Hash::check('newpassword123', $this->customer->fresh()->password))->toBeTrue();
    });

    it('change password fails with wrong current password', function () {
        $this->put(route('portal.profile.password.update'), [
            'current_password'      => 'wrongpassword',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertSessionHasErrors('current_password');
    });

    it('change password fails with mismatched confirmation', function () {
        $this->put(route('portal.profile.password.update'), [
            'current_password'      => 'password123',
            'password'              => 'newpassword123',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');
    });

});
```

---

## Guest Access Tests

Separate `describe` — no `beforeEach` `actingAs`, so requests are unauthenticated.

```php
describe('guest access', function () {

    it('is redirected from profile show to portal login', function () {
        $this->get(route('portal.profile.show'))
            ->assertRedirect(route('portal.login'));
    });

    it('is redirected from profile edit to portal login', function () {
        $this->get(route('portal.profile.edit'))
            ->assertRedirect(route('portal.login'));
    });

    it('is redirected from change password form to portal login', function () {
        $this->get(route('portal.profile.password'))
            ->assertRedirect(route('portal.login'));
    });

    it('put to profile update is redirected to portal login', function () {
        $this->put(route('portal.profile.update'), [
            'name'  => 'Hacker',
            'phone' => '000-000-0000',
        ])->assertRedirect(route('portal.login'));
    });

    it('put to password update is redirected to portal login', function () {
        $this->put(route('portal.profile.password.update'), [
            'current_password'      => 'anything',
            'password'              => 'anything123',
            'password_confirmation' => 'anything123',
        ])->assertRedirect(route('portal.login'));
    });

});
```

---

## Running Tests

```bash
php artisan test --filter ProfileControllerTest
```

---

## Notes

- Two `describe` blocks — `beforeEach` is scoped to its describe block in Pest, so guest tests run without auth
- `email_verified_at` set in factory — customer must be verified to reach profile routes
- `changePassword()` throws `ValidationException` → Laravel auto-converts to redirect with errors on `current_password`
- No `User` created anywhere — Customer IS the auth model
- Admin accessing portal routes → redirected to `portal.login` (not 403) — guard enforces this at middleware level
