<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\CustomerRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CustomerRoleSeeder::class);
    $this->seed(RoleSeeder::class);
});

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'phone' => '555-123-4567',
        'company_name' => null,
        'address' => '123 Main St',
        'city' => 'Springfield',
        'state' => 'IL',
        'postal_code' => '62701',
        'country' => 'USA',
    ], $overrides);
}

function verifiedCustomer(): User
{
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
    ]);
    $user->assignRole('customer');
    Customer::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'status' => CustomerStatus::Active,
    ]);

    return $user;
}

// ===========================================================
// REGISTER
// ===========================================================

it('shows the register page', function (): void {
    $this->get(route('portal.register'))
        ->assertOk()
        ->assertViewIs('portal.auth.register');
});

it('customer can register', function (): void {
    Notification::fake();

    $this->post(route('portal.register.store'), registrationPayload())
        ->assertRedirect(route('portal.verification.notice'));

    $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    $this->assertDatabaseHas('customers', ['email' => 'jane@example.com']);

    $user = User::where('email', 'jane@example.com')->first();
    expect($user->hasRole('customer'))->toBeTrue();
    $this->assertAuthenticated();
});

it('register fails with duplicate email', function (): void {
    User::factory()->create(['email' => 'jane@example.com']);

    $this->post(route('portal.register.store'), registrationPayload())
        ->assertSessionHasErrors('email');
});

it('register fails with mismatched passwords', function (): void {
    $this->post(route('portal.register.store'), registrationPayload([
        'password_confirmation' => 'different',
    ]))->assertSessionHasErrors('password');
});

it('register fails with missing required field', function (): void {
    $this->post(route('portal.register.store'), registrationPayload(['phone' => '']))
        ->assertSessionHasErrors('phone');
});

it('logged in user cannot see register page', function (): void {
    $user = verifiedCustomer();

    $this->actingAs($user)
        ->get(route('portal.register'))
        ->assertRedirect();
});

// ===========================================================
// LOGIN
// ===========================================================

it('shows the login page', function (): void {
    $this->get(route('portal.login'))
        ->assertOk()
        ->assertViewIs('portal.auth.login');
});

it('customer can login with valid credentials', function (): void {
    $user = verifiedCustomer();

    $this->post(route('portal.login.store'), [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertRedirect(route('portal.dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('login fails with wrong password', function (): void {
    $user = verifiedCustomer();

    $this->post(route('portal.login.store'), [
        'email' => $user->email,
        'password' => 'wrongpassword',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('admin cannot login via portal', function (): void {
    $admin = User::factory()->create(['password' => Hash::make('password123')]);
    $admin->assignRole('admin');

    $this->post(route('portal.login.store'), [
        'email' => $admin->email,
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('blocked customer cannot login', function (): void {
    $user = User::factory()->create(['password' => Hash::make('password123'), 'email_verified_at' => now()]);
    $user->assignRole('customer');
    Customer::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'status' => CustomerStatus::Blocked,
    ]);

    $this->post(route('portal.login.store'), [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('inactive customer cannot login', function (): void {
    $user = User::factory()->create(['password' => Hash::make('password123'), 'email_verified_at' => now()]);
    $user->assignRole('customer');
    Customer::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'status' => CustomerStatus::Inactive,
    ]);

    $this->post(route('portal.login.store'), [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logged in user cannot see login page', function (): void {
    $user = verifiedCustomer();

    $this->actingAs($user)
        ->get(route('portal.login'))
        ->assertRedirect();
});

// ===========================================================
// LOGOUT
// ===========================================================

it('customer can logout', function (): void {
    $user = verifiedCustomer();

    $this->actingAs($user)
        ->post(route('portal.logout'))
        ->assertRedirect(route('portal.login'));

    $this->assertGuest();
});

// ===========================================================
// EMAIL VERIFICATION
// ===========================================================

it('unverified customer is redirected to verify notice', function (): void {
    $user = User::factory()->create(['email_verified_at' => null]);
    $user->assignRole('customer');
    Customer::factory()->create(['user_id' => $user->id, 'email' => $user->email]);

    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.verification.notice'));
});

it('customer can verify email via signed link', function (): void {
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

it('customer can resend verification email', function (): void {
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

it('shows the forgot password page', function (): void {
    $this->get(route('portal.password.request'))
        ->assertOk()
        ->assertViewIs('portal.auth.forgot-password');
});

it('forgot password always returns success message', function (): void {
    $this->post(route('portal.password.email'), ['email' => 'anyone@example.com'])
        ->assertSessionHas('status');
});

it('shows the reset password page', function (): void {
    $this->get(route('portal.password.reset', ['token' => 'sometoken']))
        ->assertOk()
        ->assertViewIs('portal.auth.reset-password');
});

// ===========================================================
// DASHBOARD PROTECTION
// ===========================================================

it('guest cannot access dashboard', function (): void {
    $this->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.login'));
});

it('admin cannot access portal dashboard', function (): void {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('portal.dashboard'))
        ->assertForbidden();
});

it('verified customer can access dashboard', function (): void {
    $user = verifiedCustomer();

    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertViewIs('portal.dashboard');
});
