# Customer Module — Tests

Two test files: Feature (controller) and Unit (service).
All tests use Pest. Use `RefreshDatabase` trait.

---

## Customer Factory

**File:** `database/factories/CustomerFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'         => fake()->name(),
            'email'        => fake()->unique()->safeEmail(),
            'phone'        => fake()->numerify('###-###-####'),
            'company_name' => fake()->optional()->company(),
            'address'      => fake()->streetAddress(),
            'city'         => fake()->city(),
            'state'        => fake()->state(),
            'postal_code'  => fake()->postcode(),
            'country'      => fake()->country(),
            'status'       => CustomerStatus::Active->value,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => CustomerStatus::Inactive->value]);
    }

    public function blocked(): static
    {
        return $this->state(['status' => CustomerStatus::Blocked->value]);
    }
}
```

---

## 1. Feature Test — CustomerControllerTest

**File:** `tests/Feature/CustomerControllerTest.php`

```php
<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Seed permissions and roles before each test
// RefreshDatabase wipes the DB — permissions must be re-created each time
beforeEach(function () {
    $this->seed(\Database\Seeders\CustomerPermissionSeeder::class);
});

// --- Helpers ---
// Create an Admin user with full customer permissions
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

// Create a Staff user with view-only permissions
function staffUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        'customers.viewAny',
        'customers.view',
    ]);
    return $user;
}

// Valid payload for creating/updating a customer
function customerPayload(array $overrides = []): array
{
    return array_merge([
        'name'         => 'Jane Doe',
        'email'        => 'jane@example.com',
        'phone'        => '555-123-4567',
        'company_name' => null,
        'address'      => '123 Main St',
        'city'         => 'Springfield',
        'state'        => 'IL',
        'postal_code'  => '62701',
        'country'      => 'USA',
        'status'       => 'active',
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
    Customer::factory()->create(['status' => CustomerStatus::Active]);
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
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

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
```

---

## 2. Unit Test — CustomerServiceTest

**File:** `tests/Unit/CustomerServiceTest.php`

```php
<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CustomerService();
});

it('paginate returns a paginator', function () {
    Customer::factory()->count(5)->create();

    $result = $this->service->paginate([]);

    expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
    expect($result->total())->toBe(5);
});

it('paginate filters by search', function () {
    Customer::factory()->create(['name' => 'Alice Smith']);
    Customer::factory()->create(['name' => 'Bob Jones']);

    $result = $this->service->paginate(['search' => 'Alice']);

    expect($result->total())->toBe(1);
    expect($result->first()->name)->toBe('Alice Smith');
});

it('paginate filters by status', function () {
    Customer::factory()->create(['status' => CustomerStatus::Active]);
    Customer::factory()->inactive()->create();

    $result = $this->service->paginate(['status' => 'active']);

    expect($result->total())->toBe(1);
    expect($result->first()->status)->toBe(CustomerStatus::Active);
});

it('store creates a customer', function () {
    $data = [
        'name'         => 'Jane Doe',
        'email'        => 'jane@example.com',
        'phone'        => '555-123-4567',
        'company_name' => null,
        'address'      => '123 Main St',
        'city'         => 'Springfield',
        'state'        => 'IL',
        'postal_code'  => '62701',
        'country'      => 'USA',
        'status'       => 'active',
    ];

    $customer = $this->service->store($data);

    expect($customer)->toBeInstanceOf(Customer::class);
    expect($customer->email)->toBe('jane@example.com');
    $this->assertDatabaseHas('customers', ['email' => 'jane@example.com']);
});

it('update modifies a customer', function () {
    $customer = Customer::factory()->create(['name' => 'Old Name']);

    $result = $this->service->update($customer, ['name' => 'New Name']);

    expect($result->name)->toBe('New Name');
    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'New Name']);
});

it('changeStatus updates the status', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active]);

    $result = $this->service->changeStatus($customer, CustomerStatus::Blocked);

    expect($result->status)->toBe(CustomerStatus::Blocked);
    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'status' => 'blocked']);
});

it('delete soft deletes a customer', function () {
    $customer = Customer::factory()->create();

    $this->service->delete($customer);

    $this->assertSoftDeleted('customers', ['id' => $customer->id]);
});
```

---

## Running Tests
```bash
php artisan test --filter CustomerControllerTest
php artisan test --filter CustomerServiceTest
```

Or run all:
```bash
php artisan test
```
