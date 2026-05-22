<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use Database\Seeders\CustomerAddressPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CustomerAddressPermissionSeeder::class);

    $this->addressAdminUser = function () {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    };
    $this->addressManagerUser = function () {
        $u = User::factory()->create();
        $u->assignRole('manager');

        return $u;
    };
    $this->addressSalesUser = function () {
        $u = User::factory()->create();
        $u->assignRole('sales');

        return $u;
    };
    $this->addressPayload = fn (array $overrides = []) => array_merge([
        'label' => 'Home',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => null,
        'phone' => null,
        'address_line1' => '123 Main St',
        'address_line2' => null,
        'city' => 'Austin',
        'state' => 'TX',
        'postal_code' => '78701',
        'country' => 'US',
    ], $overrides);
});

it('admin can list addresses for a customer', function () {
    $admin = ($this->addressAdminUser)();
    $customer = Customer::factory()->create();
    CustomerAddress::factory()->count(2)->create(['customer_id' => $customer->id]);

    $this->actingAs($admin)
        ->get(route('customer-addresses.index', $customer))
        ->assertOk()
        ->assertViewIs('customer-addresses.index')
        ->assertViewHas('addresses');
});

it('sales can list addresses for a customer', function () {
    $sales = ($this->addressSalesUser)();
    $customer = Customer::factory()->create();

    $this->actingAs($sales)
        ->get(route('customer-addresses.index', $customer))
        ->assertOk();
});

it('guest is redirected to login from index', function () {
    $customer = Customer::factory()->create();

    $this->get(route('customer-addresses.index', $customer))
        ->assertRedirect(route('login'));
});

it('admin can see create address form', function () {
    $admin = ($this->addressAdminUser)();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->get(route('customer-addresses.create', $customer))
        ->assertOk()
        ->assertViewIs('customer-addresses.create');
});

it('sales cannot see create address form', function () {
    $sales = ($this->addressSalesUser)();
    $customer = Customer::factory()->create();

    $this->actingAs($sales)
        ->get(route('customer-addresses.create', $customer))
        ->assertForbidden();
});

it('admin can create an address', function () {
    $admin = ($this->addressAdminUser)();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->post(route('customer-addresses.store', $customer), ($this->addressPayload)())
        ->assertRedirect(route('customer-addresses.index', $customer));

    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $customer->id,
        'label' => 'Home',
    ]);
});

it('store fails with missing required field', function () {
    $admin = ($this->addressAdminUser)();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->post(route('customer-addresses.store', $customer), ($this->addressPayload)(['label' => '']))
        ->assertSessionHasErrors('label');
});

it('store fails with invalid country length', function () {
    $admin = ($this->addressAdminUser)();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->post(route('customer-addresses.store', $customer), ($this->addressPayload)(['country' => 'USA']))
        ->assertSessionHasErrors('country');
});

it('sales cannot create an address', function () {
    $sales = ($this->addressSalesUser)();
    $customer = Customer::factory()->create();

    $this->actingAs($sales)
        ->post(route('customer-addresses.store', $customer), ($this->addressPayload)())
        ->assertForbidden();
});

it('admin can see edit form', function () {
    $admin = ($this->addressAdminUser)();
    $customer = Customer::factory()->create();
    $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($admin)
        ->get(route('customer-addresses.edit', [$customer, $address]))
        ->assertOk()
        ->assertViewIs('customer-addresses.edit');
});

it('sales cannot see edit form', function () {
    $sales = ($this->addressSalesUser)();
    $customer = Customer::factory()->create();
    $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($sales)
        ->get(route('customer-addresses.edit', [$customer, $address]))
        ->assertForbidden();
});

it('cannot edit an address belonging to a different customer', function () {
    $admin = ($this->addressAdminUser)();
    $customer1 = Customer::factory()->create();
    $customer2 = Customer::factory()->create();
    $address = CustomerAddress::factory()->create(['customer_id' => $customer2->id]);

    $this->actingAs($admin)
        ->get(route('customer-addresses.edit', [$customer1, $address]))
        ->assertNotFound();
});

it('admin can update an address', function () {
    $admin = ($this->addressAdminUser)();
    $customer = Customer::factory()->create();
    $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($admin)
        ->put(route('customer-addresses.update', [$customer, $address]), ($this->addressPayload)(['label' => 'Work']))
        ->assertRedirect(route('customer-addresses.index', $customer));

    $this->assertDatabaseHas('customer_addresses', ['id' => $address->id, 'label' => 'Work']);
});

it('sales cannot update an address', function () {
    $sales = ($this->addressSalesUser)();
    $customer = Customer::factory()->create();
    $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($sales)
        ->put(route('customer-addresses.update', [$customer, $address]), ($this->addressPayload)())
        ->assertForbidden();
});

it('admin can delete an address', function () {
    $admin = ($this->addressAdminUser)();
    $customer = Customer::factory()->create();
    $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($admin)
        ->delete(route('customer-addresses.destroy', [$customer, $address]))
        ->assertRedirect(route('customer-addresses.index', $customer));

    $this->assertSoftDeleted('customer_addresses', ['id' => $address->id]);
});

it('manager cannot delete an address', function () {
    $manager = ($this->addressManagerUser)();
    $customer = Customer::factory()->create();
    $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($manager)
        ->delete(route('customer-addresses.destroy', [$customer, $address]))
        ->assertForbidden();
});

it('sales cannot delete an address', function () {
    $sales = ($this->addressSalesUser)();
    $customer = Customer::factory()->create();
    $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($sales)
        ->delete(route('customer-addresses.destroy', [$customer, $address]))
        ->assertForbidden();
});

it('admin can set an address as default', function () {
    $admin = ($this->addressAdminUser)();
    $customer = Customer::factory()->create();
    $old = CustomerAddress::factory()->default()->create(['customer_id' => $customer->id]);
    $new = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($admin)
        ->patch(route('customer-addresses.setDefault', [$customer, $new]))
        ->assertRedirect(route('customer-addresses.index', $customer));

    $this->assertDatabaseHas('customer_addresses', ['id' => $old->id, 'is_default' => false]);
    $this->assertDatabaseHas('customer_addresses', ['id' => $new->id, 'is_default' => true]);
});

it('sales cannot set default', function () {
    $sales = ($this->addressSalesUser)();
    $customer = Customer::factory()->create();
    $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($sales)
        ->patch(route('customer-addresses.setDefault', [$customer, $address]))
        ->assertForbidden();
});
