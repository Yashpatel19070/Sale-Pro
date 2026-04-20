# Supplier Module — Tests

Two test files: Feature (controller) and Unit (service).
All tests use Pest. Use `RefreshDatabase` trait.

---

## 1. Feature Test — SupplierControllerTest

**File:** `tests/Feature/SupplierControllerTest.php`

```php
<?php

declare(strict_types=1);

use App\Enums\SupplierStatus;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\SupplierPermissionSeeder::class);
});

// --- Helpers ---

function supplierAdminUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        'suppliers.viewAny',
        'suppliers.view',
        'suppliers.create',
        'suppliers.update',
        'suppliers.delete',
        'suppliers.changeStatus',
    ]);
    return $user;
}

function supplierSalesUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        'suppliers.viewAny',
        'suppliers.view',
    ]);
    return $user;
}

function supplierPayload(array $overrides = []): array
{
    return array_merge([
        'name'          => 'Acme Corp',
        'contact_name'  => 'John Smith',
        'email'         => 'acme@example.com',
        'phone'         => '555-123-4567',
        'address'       => null,
        'city'          => null,
        'state'         => null,
        'postal_code'   => null,
        'country'       => null,
        'payment_terms' => 'Net 30',
        'notes'         => null,
        'status'        => 'active',
    ], $overrides);
}

// ===========================================================
// INDEX
// ===========================================================

it('admin can list suppliers', function () {
    $admin = supplierAdminUser();
    Supplier::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('suppliers.index'))
        ->assertOk()
        ->assertViewIs('suppliers.index')
        ->assertViewHas('suppliers');
});

it('sales user can list suppliers', function () {
    $sales = supplierSalesUser();

    $this->actingAs($sales)
        ->get(route('suppliers.index'))
        ->assertOk();
});

it('guest is redirected to login from index', function () {
    $this->get(route('suppliers.index'))
        ->assertRedirect(route('login'));
});

it('index filters by search term', function () {
    $admin = supplierAdminUser();
    Supplier::factory()->create(['name' => 'Alpha Supplies']);
    Supplier::factory()->create(['name' => 'Beta Corp']);

    $this->actingAs($admin)
        ->get(route('suppliers.index', ['search' => 'Alpha']))
        ->assertOk()
        ->assertSee('Alpha Supplies')
        ->assertDontSee('Beta Corp');
});

it('index filters by status', function () {
    $admin = supplierAdminUser();
    Supplier::factory()->create(['status' => SupplierStatus::Active->value]);
    Supplier::factory()->inactive()->create();

    $this->actingAs($admin)
        ->get(route('suppliers.index', ['status' => 'active']))
        ->assertOk();
});

// ===========================================================
// SHOW
// ===========================================================

it('admin can view a supplier', function () {
    $admin = supplierAdminUser();
    $supplier = Supplier::factory()->create();

    $this->actingAs($admin)
        ->get(route('suppliers.show', $supplier))
        ->assertOk()
        ->assertViewIs('suppliers.show')
        ->assertViewHas('supplier');
});

it('sales user can view a supplier', function () {
    $sales = supplierSalesUser();
    $supplier = Supplier::factory()->create();

    $this->actingAs($sales)
        ->get(route('suppliers.show', $supplier))
        ->assertOk();
});

// ===========================================================
// CREATE
// ===========================================================

it('admin can see create supplier form', function () {
    $admin = supplierAdminUser();

    $this->actingAs($admin)
        ->get(route('suppliers.create'))
        ->assertOk()
        ->assertViewIs('suppliers.create');
});

it('sales user cannot see create supplier form', function () {
    $sales = supplierSalesUser();

    $this->actingAs($sales)
        ->get(route('suppliers.create'))
        ->assertForbidden();
});

// ===========================================================
// STORE
// ===========================================================

it('admin can create a supplier', function () {
    $admin = supplierAdminUser();

    $this->actingAs($admin)
        ->post(route('suppliers.store'), supplierPayload())
        ->assertRedirect(route('suppliers.index'));

    $this->assertDatabaseHas('suppliers', ['email' => 'acme@example.com']);
});

it('store fails with missing name', function () {
    $admin = supplierAdminUser();

    $this->actingAs($admin)
        ->post(route('suppliers.store'), supplierPayload(['name' => '']))
        ->assertSessionHasErrors('name');
});

it('store fails with missing email', function () {
    $admin = supplierAdminUser();

    $this->actingAs($admin)
        ->post(route('suppliers.store'), supplierPayload(['email' => '']))
        ->assertSessionHasErrors('email');
});

it('store fails with duplicate email', function () {
    $admin = supplierAdminUser();
    Supplier::factory()->create(['email' => 'acme@example.com']);

    $this->actingAs($admin)
        ->post(route('suppliers.store'), supplierPayload(['email' => 'acme@example.com']))
        ->assertSessionHasErrors('email');
});

it('store fails with invalid status', function () {
    $admin = supplierAdminUser();

    $this->actingAs($admin)
        ->post(route('suppliers.store'), supplierPayload(['status' => 'blocked']))
        ->assertSessionHasErrors('status');
});

it('sales user cannot create a supplier', function () {
    $sales = supplierSalesUser();

    $this->actingAs($sales)
        ->post(route('suppliers.store'), supplierPayload())
        ->assertForbidden();
});

it('nullable fields are optional on store', function () {
    $admin = supplierAdminUser();

    $this->actingAs($admin)
        ->post(route('suppliers.store'), supplierPayload([
            'contact_name'  => null,
            'address'       => null,
            'payment_terms' => null,
            'notes'         => null,
        ]))
        ->assertRedirect(route('suppliers.index'));
});

// ===========================================================
// EDIT
// ===========================================================

it('admin can see edit form', function () {
    $admin = supplierAdminUser();
    $supplier = Supplier::factory()->create();

    $this->actingAs($admin)
        ->get(route('suppliers.edit', $supplier))
        ->assertOk()
        ->assertViewIs('suppliers.edit');
});

it('sales user cannot see edit form', function () {
    $sales = supplierSalesUser();
    $supplier = Supplier::factory()->create();

    $this->actingAs($sales)
        ->get(route('suppliers.edit', $supplier))
        ->assertForbidden();
});

// ===========================================================
// UPDATE
// ===========================================================

it('admin can update a supplier', function () {
    $admin = supplierAdminUser();
    $supplier = Supplier::factory()->create();

    $this->actingAs($admin)
        ->put(route('suppliers.update', $supplier), supplierPayload(['name' => 'Updated Name']))
        ->assertRedirect(route('suppliers.show', $supplier));

    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'Updated Name']);
});

it('update allows same email on same supplier', function () {
    $admin = supplierAdminUser();
    $supplier = Supplier::factory()->create(['email' => 'acme@example.com']);

    $this->actingAs($admin)
        ->put(route('suppliers.update', $supplier), supplierPayload(['email' => 'acme@example.com']))
        ->assertRedirect(route('suppliers.show', $supplier));
});

it('sales user cannot update a supplier', function () {
    $sales = supplierSalesUser();
    $supplier = Supplier::factory()->create();

    $this->actingAs($sales)
        ->put(route('suppliers.update', $supplier), supplierPayload())
        ->assertForbidden();
});

// ===========================================================
// DESTROY
// ===========================================================

it('admin can delete a supplier', function () {
    $admin = supplierAdminUser();
    $supplier = Supplier::factory()->create();

    $this->actingAs($admin)
        ->delete(route('suppliers.destroy', $supplier))
        ->assertRedirect(route('suppliers.index'));

    $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
});

it('sales user cannot delete a supplier', function () {
    $sales = supplierSalesUser();
    $supplier = Supplier::factory()->create();

    $this->actingAs($sales)
        ->delete(route('suppliers.destroy', $supplier))
        ->assertForbidden();
});

// ===========================================================
// CHANGE STATUS
// ===========================================================

it('admin can change supplier status', function () {
    $admin = supplierAdminUser();
    $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active->value]);

    $this->actingAs($admin)
        ->patch(route('suppliers.changeStatus', $supplier), ['status' => 'inactive'])
        ->assertRedirect();

    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'status' => 'inactive']);
});

it('changeStatus fails with invalid status', function () {
    $admin = supplierAdminUser();
    $supplier = Supplier::factory()->create();

    $this->actingAs($admin)
        ->patch(route('suppliers.changeStatus', $supplier), ['status' => 'blocked'])
        ->assertSessionHasErrors('status');
});

it('sales user cannot change supplier status', function () {
    $sales = supplierSalesUser();
    $supplier = Supplier::factory()->create();

    $this->actingAs($sales)
        ->patch(route('suppliers.changeStatus', $supplier), ['status' => 'inactive'])
        ->assertForbidden();
});
```

---

## 2. Unit Test — SupplierServiceTest

**File:** `tests/Unit/SupplierServiceTest.php`

```php
<?php

declare(strict_types=1);

use App\Enums\SupplierStatus;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SupplierService();
});

it('paginate returns a paginator', function () {
    Supplier::factory()->count(5)->create();

    $result = $this->service->paginate([]);

    expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
    expect($result->total())->toBe(5);
});

it('paginate filters by search on name', function () {
    Supplier::factory()->create(['name' => 'Alpha Supplies']);
    Supplier::factory()->create(['name' => 'Beta Corp']);

    $result = $this->service->paginate(['search' => 'Alpha']);

    expect($result->total())->toBe(1);
    expect($result->first()->name)->toBe('Alpha Supplies');
});

it('paginate filters by search on contact_name', function () {
    Supplier::factory()->create(['contact_name' => 'Jane Doe']);
    Supplier::factory()->create(['contact_name' => 'Bob Smith']);

    $result = $this->service->paginate(['search' => 'Jane']);

    expect($result->total())->toBe(1);
});

it('paginate filters by status', function () {
    Supplier::factory()->create(['status' => SupplierStatus::Active->value]);
    Supplier::factory()->inactive()->create();

    $result = $this->service->paginate(['status' => 'active']);

    expect($result->total())->toBe(1);
    expect($result->first()->status)->toBe(SupplierStatus::Active);
});

it('store creates a supplier', function () {
    $data = [
        'name'          => 'Acme Corp',
        'contact_name'  => null,
        'email'         => 'acme@example.com',
        'phone'         => '555-123-4567',
        'address'       => null,
        'city'          => null,
        'state'         => null,
        'postal_code'   => null,
        'country'       => null,
        'payment_terms' => 'Net 30',
        'notes'         => null,
        'status'        => 'active',
    ];

    $supplier = $this->service->store($data);

    expect($supplier)->toBeInstanceOf(Supplier::class);
    expect($supplier->email)->toBe('acme@example.com');
    $this->assertDatabaseHas('suppliers', ['email' => 'acme@example.com']);
});

it('update modifies a supplier', function () {
    $supplier = Supplier::factory()->create(['name' => 'Old Name']);

    $result = $this->service->update($supplier, ['name' => 'New Name']);

    expect($result->name)->toBe('New Name');
    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'New Name']);
});

it('changeStatus updates the status', function () {
    $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active->value]);

    $result = $this->service->changeStatus($supplier, SupplierStatus::Inactive);

    expect($result->status)->toBe(SupplierStatus::Inactive);
    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'status' => 'inactive']);
});

it('delete soft deletes a supplier', function () {
    $supplier = Supplier::factory()->create();

    $this->service->delete($supplier);

    $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
});
```

---

## Running Tests
```bash
php artisan test --filter SupplierControllerTest
php artisan test --filter SupplierServiceTest
```

Or run all:
```bash
php artisan test
```
