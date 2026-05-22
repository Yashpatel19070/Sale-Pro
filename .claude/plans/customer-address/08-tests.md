# Customer Address Module — Tests

Two test files: Feature (controller) and Unit (service). All tests use Pest + `RefreshDatabase`.

---

## Factory

**File:** `database/factories/CustomerAddressFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerAddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id'   => Customer::factory(),
            'label'         => fake()->randomElement(['Home', 'Work', 'Billing', 'Other']),
            'first_name'    => fake()->firstName(),
            'last_name'     => fake()->lastName(),
            'email'         => fake()->optional()->safeEmail(),
            'phone'         => fake()->optional()->numerify('###-###-####'),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => fake()->optional()->secondaryAddress(),
            'city'          => fake()->city(),
            'state'         => fake()->stateAbbr(),
            'postal_code'   => fake()->postcode(),
            'country'       => 'US',
            'is_default'    => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
```

---

## 1. Feature Test — CustomerAddressControllerTest

**File:** `tests/Feature/CustomerAddressControllerTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // RoleSeeder must run first — creates roles that module seeder assigns permissions to
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\CustomerAddressPermissionSeeder::class);
});

// --- Helpers ---

function adminUser(): User
{
    return User::factory()->create()->tap(fn ($u) => $u->assignRole('admin'));
}

function managerUser(): User
{
    return User::factory()->create()->tap(fn ($u) => $u->assignRole('manager'));
}

function salesUser(): User
{
    return User::factory()->create()->tap(fn ($u) => $u->assignRole('sales'));
}

function addressPayload(array $overrides = []): array
{
    return array_merge([
        'label'         => 'Home',
        'first_name'    => 'Jane',
        'last_name'     => 'Doe',
        'email'         => null,
        'phone'         => null,
        'address_line1' => '123 Main St',
        'address_line2' => null,
        'city'          => 'Austin',
        'state'         => 'TX',
        'postal_code'   => '78701',
        'country'       => 'US',
        'is_default'    => false,
    ], $overrides);
}

// ===========================================================
// INDEX
// ===========================================================

it('admin can list addresses for a customer', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create();
    CustomerAddress::factory()->count(2)->create(['customer_id' => $customer->id]);

    $this->actingAs($admin)
        ->get(route('customer-addresses.index', $customer))
        ->assertOk()
        ->assertViewIs('customer-addresses.index')
        ->assertViewHas('addresses');
});

it('sales can list addresses for a customer', function () {
    $sales    = salesUser();
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

// ===========================================================
// CREATE
// ===========================================================

it('admin can see create address form', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->get(route('customer-addresses.create', $customer))
        ->assertOk()
        ->assertViewIs('customer-addresses.create');
});

it('sales cannot see create address form', function () {
    $sales    = salesUser();
    $customer = Customer::factory()->create();

    $this->actingAs($sales)
        ->get(route('customer-addresses.create', $customer))
        ->assertForbidden();
});

// ===========================================================
// STORE
// ===========================================================

it('admin can create an address', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->post(route('customer-addresses.store', $customer), addressPayload())
        ->assertRedirect(route('customer-addresses.index', $customer));

    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $customer->id,
        'label'       => 'Home',
    ]);
});

it('store fails with missing required field', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->post(route('customer-addresses.store', $customer), addressPayload(['label' => '']))
        ->assertSessionHasErrors('label');
});

it('store fails with invalid country length', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->post(route('customer-addresses.store', $customer), addressPayload(['country' => 'USA']))
        ->assertSessionHasErrors('country');
});

it('store with is_default=true unsets other defaults', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create();
    $existing = CustomerAddress::factory()->default()->create(['customer_id' => $customer->id]);

    $this->actingAs($admin)
        ->post(route('customer-addresses.store', $customer), addressPayload(['is_default' => true]))
        ->assertRedirect();

    $this->assertDatabaseHas('customer_addresses', ['id' => $existing->id, 'is_default' => false]);
    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $customer->id,
        'label'       => 'Home',
        'is_default'  => true,
    ]);
});

it('sales cannot create an address', function () {
    $sales    = salesUser();
    $customer = Customer::factory()->create();

    $this->actingAs($sales)
        ->post(route('customer-addresses.store', $customer), addressPayload())
        ->assertForbidden();
});

// ===========================================================
// EDIT
// ===========================================================

it('admin can see edit form', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create();
    $address  = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($admin)
        ->get(route('customer-addresses.edit', [$customer, $address]))
        ->assertOk()
        ->assertViewIs('customer-addresses.edit');
});

it('sales cannot see edit form', function () {
    $sales    = salesUser();
    $customer = Customer::factory()->create();
    $address  = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($sales)
        ->get(route('customer-addresses.edit', [$customer, $address]))
        ->assertForbidden();
});

it('cannot edit an address belonging to a different customer', function () {
    $admin      = adminUser();
    $customer1  = Customer::factory()->create();
    $customer2  = Customer::factory()->create();
    $address    = CustomerAddress::factory()->create(['customer_id' => $customer2->id]);

    $this->actingAs($admin)
        ->get(route('customer-addresses.edit', [$customer1, $address]))
        ->assertForbidden();
});

// ===========================================================
// UPDATE
// ===========================================================

it('admin can update an address', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create();
    $address  = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($admin)
        ->put(route('customer-addresses.update', [$customer, $address]), addressPayload(['label' => 'Work']))
        ->assertRedirect(route('customer-addresses.index', $customer));

    $this->assertDatabaseHas('customer_addresses', ['id' => $address->id, 'label' => 'Work']);
});

it('update with is_default=true unsets other defaults', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create();
    $existing = CustomerAddress::factory()->default()->create(['customer_id' => $customer->id]);
    $address  = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($admin)
        ->put(
            route('customer-addresses.update', [$customer, $address]),
            addressPayload(['is_default' => true])
        )
        ->assertRedirect();

    $this->assertDatabaseHas('customer_addresses', ['id' => $existing->id, 'is_default' => false]);
    $this->assertDatabaseHas('customer_addresses', ['id' => $address->id, 'is_default' => true]);
});

it('sales cannot update an address', function () {
    $sales    = salesUser();
    $customer = Customer::factory()->create();
    $address  = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($sales)
        ->put(route('customer-addresses.update', [$customer, $address]), addressPayload())
        ->assertForbidden();
});

// ===========================================================
// DESTROY
// ===========================================================

it('admin can delete an address', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create();
    $address  = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($admin)
        ->delete(route('customer-addresses.destroy', [$customer, $address]))
        ->assertRedirect(route('customer-addresses.index', $customer));

    $this->assertSoftDeleted('customer_addresses', ['id' => $address->id]);
});

it('manager cannot delete an address', function () {
    $manager  = managerUser();
    $customer = Customer::factory()->create();
    $address  = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($manager)
        ->delete(route('customer-addresses.destroy', [$customer, $address]))
        ->assertForbidden();
});

it('sales cannot delete an address', function () {
    $sales    = salesUser();
    $customer = Customer::factory()->create();
    $address  = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($sales)
        ->delete(route('customer-addresses.destroy', [$customer, $address]))
        ->assertForbidden();
});

// ===========================================================
// SET DEFAULT
// ===========================================================

it('admin can set an address as default', function () {
    $admin    = adminUser();
    $customer = Customer::factory()->create();
    $old      = CustomerAddress::factory()->default()->create(['customer_id' => $customer->id]);
    $new      = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($admin)
        ->patch(route('customer-addresses.setDefault', [$customer, $new]))
        ->assertRedirect(route('customer-addresses.index', $customer));

    $this->assertDatabaseHas('customer_addresses', ['id' => $old->id, 'is_default' => false]);
    $this->assertDatabaseHas('customer_addresses', ['id' => $new->id, 'is_default' => true]);
});

it('sales cannot set default', function () {
    $sales    = salesUser();
    $customer = Customer::factory()->create();
    $address  = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($sales)
        ->patch(route('customer-addresses.setDefault', [$customer, $address]))
        ->assertForbidden();
});
```

---

## 2. Unit Test — CustomerAddressServiceTest

**File:** `tests/Unit/CustomerAddressServiceTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\CustomerAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service  = app(CustomerAddressService::class);
    $this->customer = Customer::factory()->create();
});

it('list returns all addresses ordered default first', function () {
    CustomerAddress::factory()->create(['customer_id' => $this->customer->id, 'label' => 'Work', 'is_default' => false]);
    CustomerAddress::factory()->default()->create(['customer_id' => $this->customer->id, 'label' => 'Home']);

    $result = $this->service->list($this->customer);

    expect($result)->toHaveCount(2);
    expect($result->first()->label)->toBe('Home');
    expect($result->first()->is_default)->toBeTrue();
});

it('list returns empty collection when no addresses', function () {
    $result = $this->service->list($this->customer);

    expect($result)->toBeEmpty();
});

it('store creates an address for a customer', function () {
    $data = [
        'label'         => 'Home',
        'first_name'    => 'Jane',
        'last_name'     => 'Doe',
        'email'         => null,
        'phone'         => null,
        'address_line1' => '123 Main St',
        'address_line2' => null,
        'city'          => 'Austin',
        'state'         => 'TX',
        'postal_code'   => '78701',
        'country'       => 'US',
        'is_default'    => false,
    ];

    $address = $this->service->store($this->customer, $data);

    expect($address)->toBeInstanceOf(CustomerAddress::class);
    expect($address->customer_id)->toBe($this->customer->id);
    expect($address->label)->toBe('Home');
    $this->assertDatabaseHas('customer_addresses', ['id' => $address->id]);
});

it('store with is_default=true unsets existing default', function () {
    $existing = CustomerAddress::factory()->default()->create(['customer_id' => $this->customer->id]);

    $this->service->store($this->customer, [
        'label' => 'Work', 'first_name' => 'J', 'last_name' => 'D',
        'email' => null, 'phone' => null,
        'address_line1' => '1 Work St', 'address_line2' => null,
        'city' => 'Dallas', 'state' => 'TX', 'postal_code' => '75201', 'country' => 'US',
        'is_default' => true,
    ]);

    $this->assertDatabaseHas('customer_addresses', ['id' => $existing->id, 'is_default' => false]);
});

it('update modifies address fields', function () {
    $address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id, 'label' => 'Old']);

    $result = $this->service->update($address, ['label' => 'New'] + [
        'first_name' => 'J', 'last_name' => 'D', 'email' => null, 'phone' => null,
        'address_line1' => '1 St', 'address_line2' => null,
        'city' => 'C', 'state' => 'TX', 'postal_code' => '00000', 'country' => 'US',
        'is_default' => false,
    ]);

    expect($result->label)->toBe('New');
    $this->assertDatabaseHas('customer_addresses', ['id' => $address->id, 'label' => 'New']);
});

it('update promoting to default unsets existing default', function () {
    $existing = CustomerAddress::factory()->default()->create(['customer_id' => $this->customer->id]);
    $address  = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);

    $this->service->update($address, [
        'label' => 'X', 'first_name' => 'J', 'last_name' => 'D', 'email' => null, 'phone' => null,
        'address_line1' => '1 St', 'address_line2' => null,
        'city' => 'C', 'state' => 'TX', 'postal_code' => '00000', 'country' => 'US',
        'is_default' => true,
    ]);

    $this->assertDatabaseHas('customer_addresses', ['id' => $existing->id, 'is_default' => false]);
    $this->assertDatabaseHas('customer_addresses', ['id' => $address->id, 'is_default' => true]);
});

it('setDefault sets one address as default and unsets all others', function () {
    $a = CustomerAddress::factory()->default()->create(['customer_id' => $this->customer->id]);
    $b = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);
    $c = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);

    $result = $this->service->setDefault($b);

    expect($result->is_default)->toBeTrue();
    $this->assertDatabaseHas('customer_addresses', ['id' => $a->id, 'is_default' => false]);
    $this->assertDatabaseHas('customer_addresses', ['id' => $b->id, 'is_default' => true]);
    $this->assertDatabaseHas('customer_addresses', ['id' => $c->id, 'is_default' => false]);
});

it('delete soft deletes an address', function () {
    $address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);

    $this->service->delete($address);

    $this->assertSoftDeleted('customer_addresses', ['id' => $address->id]);
});

it('delete does not force-delete when address is default', function () {
    $address = CustomerAddress::factory()->default()->create(['customer_id' => $this->customer->id]);

    $this->service->delete($address);

    $this->assertSoftDeleted('customer_addresses', ['id' => $address->id]);
    // No exception — soft delete only. No auto-reassignment.
});
```

---

## Running Tests

```bash
php artisan test --filter CustomerAddressControllerTest
php artisan test --filter CustomerAddressServiceTest

# Or run both together:
php artisan test --filter CustomerAddress
```
