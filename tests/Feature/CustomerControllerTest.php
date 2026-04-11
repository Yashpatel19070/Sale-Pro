<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\CustomerPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(CustomerPermissionSeeder::class);
});

function adminUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        'customers.viewAny',
        'customers.view',
        'customers.create',
        'customers.update',
        'customers.delete',
        'customers.changeStatus',
    ]);

    return $user;
}

function staffUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        'customers.viewAny',
        'customers.view',
    ]);

    return $user;
}

function customerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '555-123-4567',
        'company_name' => null,
        'address' => '123 Main St',
        'city' => 'Springfield',
        'state' => 'IL',
        'postal_code' => '62701',
        'country' => 'USA',
        'status' => 'active',
    ], $overrides);
}

// ===========================================================
// INDEX
// ===========================================================

it('admin can list customers', function () {
    $admin = adminUser();
    Customer::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertViewIs('customers.index')
        ->assertViewHas('customers');
});

it('staff can list customers', function () {
    $staff = staffUser();

    $this->actingAs($staff)
        ->get(route('customers.index'))
        ->assertOk();
});

it('guest is redirected to login from index', function () {
    $this->get(route('customers.index'))
        ->assertRedirect(route('login'));
});

it('index filters by search term', function () {
    $admin = adminUser();
    $match = Customer::factory()->create(['name' => 'Alice Smith']);
    Customer::factory()->create(['name' => 'Bob Jones']);

    $this->actingAs($admin)
        ->get(route('customers.index', ['search' => 'Alice']))
        ->assertOk()
        ->assertSee('Alice Smith')
        ->assertDontSee('Bob Jones');
});

it('index filters by status', function () {
    $admin = adminUser();
    Customer::factory()->create(['status' => CustomerStatus::Active->value]);
    Customer::factory()->inactive()->create();

    $this->actingAs($admin)
        ->get(route('customers.index', ['status' => 'active']))
        ->assertOk();
});

// ===========================================================
// SHOW
// ===========================================================

it('admin can view a customer', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->get(route('customers.show', $customer))
        ->assertOk()
        ->assertViewIs('customers.show')
        ->assertViewHas('customer');
});

it('staff can view a customer', function () {
    $staff = staffUser();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)
        ->get(route('customers.show', $customer))
        ->assertOk();
});

// ===========================================================
// CREATE
// ===========================================================

it('admin can see create customer form', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('customers.create'))
        ->assertOk()
        ->assertViewIs('customers.create');
});

it('staff cannot see create customer form', function () {
    $staff = staffUser();

    $this->actingAs($staff)
        ->get(route('customers.create'))
        ->assertForbidden();
});

// ===========================================================
// STORE
// ===========================================================

it('admin can create a customer', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('customers.store'), customerPayload())
        ->assertRedirect(route('customers.index'));

    $this->assertDatabaseHas('customers', ['email' => 'jane@example.com']);
});

it('store fails with missing required field', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('customers.store'), customerPayload(['name' => '']))
        ->assertSessionHasErrors('name');
});

it('store fails with duplicate email', function () {
    $admin = adminUser();
    Customer::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($admin)
        ->post(route('customers.store'), customerPayload(['email' => 'jane@example.com']))
        ->assertSessionHasErrors('email');
});

it('store fails with invalid status', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('customers.store'), customerPayload(['status' => 'invalid']))
        ->assertSessionHasErrors('status');
});

it('staff cannot create a customer', function () {
    $staff = staffUser();

    $this->actingAs($staff)
        ->post(route('customers.store'), customerPayload())
        ->assertForbidden();
});

it('company_name is optional on store', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('customers.store'), customerPayload(['company_name' => null]))
        ->assertRedirect(route('customers.index'));

    $this->assertDatabaseHas('customers', ['company_name' => null]);
});

// ===========================================================
// EDIT
// ===========================================================

it('admin can see edit form', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->get(route('customers.edit', $customer))
        ->assertOk()
        ->assertViewIs('customers.edit');
});

it('staff cannot see edit form', function () {
    $staff = staffUser();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)
        ->get(route('customers.edit', $customer))
        ->assertForbidden();
});

// ===========================================================
// UPDATE
// ===========================================================

it('admin can update a customer', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->put(route('customers.update', $customer), customerPayload(['name' => 'Updated Name']))
        ->assertRedirect(route('customers.show', $customer));

    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Updated Name']);
});

it('update allows same email on same customer', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($admin)
        ->put(route('customers.update', $customer), customerPayload(['email' => 'jane@example.com']))
        ->assertRedirect(route('customers.show', $customer));
});

it('staff cannot update a customer', function () {
    $staff = staffUser();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)
        ->put(route('customers.update', $customer), customerPayload())
        ->assertForbidden();
});

// ===========================================================
// DESTROY
// ===========================================================

it('admin can delete a customer', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect(route('customers.index'));

    $this->assertSoftDeleted('customers', ['id' => $customer->id]);
});

it('staff cannot delete a customer', function () {
    $staff = staffUser();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)
        ->delete(route('customers.destroy', $customer))
        ->assertForbidden();
});

// ===========================================================
// CHANGE STATUS
// ===========================================================

it('admin can change customer status', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active->value]);

    $this->actingAs($admin)
        ->patch(route('customers.changeStatus', $customer), ['status' => 'inactive'])
        ->assertRedirect();

    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'status' => 'inactive']);
});

it('changeStatus fails with invalid status', function () {
    $admin = adminUser();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->patch(route('customers.changeStatus', $customer), ['status' => 'unknown'])
        ->assertSessionHasErrors('status');
});

it('staff cannot change customer status', function () {
    $staff = staffUser();
    $customer = Customer::factory()->create();

    $this->actingAs($staff)
        ->patch(route('customers.changeStatus', $customer), ['status' => 'inactive'])
        ->assertForbidden();
});
