<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'phone' => '555-123-4567',
        'company_name' => null,
    ], $overrides);
}

function verifiedCustomer(): Customer
{
    return Customer::factory()->create([
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
        'status' => CustomerStatus::Active,
    ]);
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

    $this->assertDatabaseHas('customers', ['email' => 'jane@example.com']);
    $this->assertAuthenticated('customer');
});

it('register fails with duplicate email', function (): void {
    Customer::factory()->create(['email' => 'jane@example.com']);

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

it('logged in customer cannot see register page', function (): void {
    $customer = verifiedCustomer();

    $this->actingAs($customer, 'customer')
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
    $customer = verifiedCustomer();

    $this->post(route('portal.login.store'), [
        'email' => $customer->email,
        'password' => 'password123',
    ])->assertRedirect(route('portal.dashboard'));

    $this->assertAuthenticatedAs($customer, 'customer');
});

it('login fails with wrong password', function (): void {
    $customer = verifiedCustomer();

    $this->post(route('portal.login.store'), [
        'email' => $customer->email,
        'password' => 'wrongpassword',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('customer');
});

it('admin cannot login via portal', function (): void {
    $this->seed(RoleSeeder::class);

    $admin = User::factory()->create(['password' => Hash::make('password123')]);
    $admin->assignRole('admin');

    $this->post(route('portal.login.store'), [
        'email' => $admin->email,
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('customer');
});

it('blocked customer cannot login', function (): void {
    $customer = Customer::factory()->create([
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
        'status' => CustomerStatus::Blocked,
    ]);

    $this->post(route('portal.login.store'), [
        'email' => $customer->email,
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('customer');
});

it('inactive customer cannot login', function (): void {
    $customer = Customer::factory()->create([
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
        'status' => CustomerStatus::Inactive,
    ]);

    $this->post(route('portal.login.store'), [
        'email' => $customer->email,
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest('customer');
});

it('logged in customer cannot see login page', function (): void {
    $customer = verifiedCustomer();

    $this->actingAs($customer, 'customer')
        ->get(route('portal.login'))
        ->assertRedirect();
});

// ===========================================================
// LOGOUT
// ===========================================================

it('customer can logout', function (): void {
    $customer = verifiedCustomer();

    $this->actingAs($customer, 'customer')
        ->post(route('portal.logout'))
        ->assertRedirect(route('portal.login'));

    $this->assertGuest('customer');
});

// ===========================================================
// EMAIL VERIFICATION
// ===========================================================

it('unverified customer is redirected to verify notice', function (): void {
    $customer = Customer::factory()->unverified()->create();

    $this->actingAs($customer, 'customer')
        ->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.verification.notice'));
});

it('customer can verify email via signed link', function (): void {
    Event::fake();

    $customer = Customer::factory()->unverified()->create();

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

it('customer can resend verification email', function (): void {
    Notification::fake();

    $customer = Customer::factory()->unverified()->create();

    $this->actingAs($customer, 'customer')
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
    $this->seed(RoleSeeder::class);

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.login'));
});

it('verified customer can access dashboard', function (): void {
    $customer = verifiedCustomer();

    $this->actingAs($customer, 'customer')
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertViewIs('portal.dashboard');
});
