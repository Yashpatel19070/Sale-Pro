# Supplier Module — Tests

Two test files: Feature (controller) and Unit (service).
All tests use Pest. `RefreshDatabase` on every class.

---

## Agent Execution Rules

> **AGENT: Use model `claude-haiku-4-5` for all test execution tasks in this module.**

When running tests:
1. Run tests only — NO code edits, NO file writes, NO fixes
2. Execute both test files and capture full output
3. Report back with:
   - Pass count / fail count per file
   - Exact failure messages (quoted verbatim)
   - Which test names failed
4. Stop after reporting — do NOT attempt fixes

```bash
php artisan test --filter SupplierControllerTest
php artisan test --filter SupplierServiceTest
```

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
        ->assertViewHas('suppliers')
        ->assertViewHas('statuses')
        ->assertViewHas('filters');
});

it('sales user can list suppliers', function () {
    $this->actingAs(supplierSalesUser())
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

it('index with empty search returns all suppliers', function () {
    $admin = supplierAdminUser();
    Supplier::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('suppliers.index', ['search' => '']))
        ->assertOk()
        ->assertViewHas('suppliers', fn ($s) => $s->total() === 3);
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
    $supplier = Supplier::factory()->create();

    $this->actingAs(supplierSalesUser())
        ->get(route('suppliers.show', $supplier))
        ->assertOk();
});

it('guest is redirected to login from show', function () {
    $supplier = Supplier::factory()->create();

    $this->get(route('suppliers.show', $supplier))
        ->assertRedirect(route('login'));
});

it('show returns 404 for soft-deleted supplier', function () {
    $admin = supplierAdminUser();
    $supplier = Supplier::factory()->create();
    $supplier->delete();

    $this->actingAs($admin)
        ->get(route('suppliers.show', $supplier))
        ->assertNotFound();
});

// ===========================================================
// CREATE
// ===========================================================

it('admin can see create supplier form', function () {
    $this->actingAs(supplierAdminUser())
        ->get(route('suppliers.create'))
        ->assertOk()
        ->assertViewIs('suppliers.create')
        ->assertViewHas('statuses');
});

it('sales user cannot see create supplier form', function () {
    $this->actingAs(supplierSalesUser())
        ->get(route('suppliers.create'))
        ->assertForbidden();
});

it('guest is redirected to login from create', function () {
    $this->get(route('suppliers.create'))
        ->assertRedirect(route('login'));
});

// ===========================================================
// STORE
// ===========================================================

it('admin can create a supplier', function () {
    $this->actingAs(supplierAdminUser())
        ->post(route('suppliers.store'), supplierPayload())
        ->assertRedirect(route('suppliers.index'));

    $this->assertDatabaseHas('suppliers', ['email' => 'acme@example.com']);
});

it('store fails with missing name', function () {
    $this->actingAs(supplierAdminUser())
        ->post(route('suppliers.store'), supplierPayload(['name' => '']))
        ->assertSessionHasErrors('name');
});

it('store fails with missing email', function () {
    $this->actingAs(supplierAdminUser())
        ->post(route('suppliers.store'), supplierPayload(['email' => '']))
        ->assertSessionHasErrors('email');
});

it('store fails with invalid email format', function () {
    $this->actingAs(supplierAdminUser())
        ->post(route('suppliers.store'), supplierPayload(['email' => 'not-an-email']))
        ->assertSessionHasErrors('email');
});

it('store fails with missing phone', function () {
    $this->actingAs(supplierAdminUser())
        ->post(route('suppliers.store'), supplierPayload(['phone' => '']))
        ->assertSessionHasErrors('phone');
});

it('store fails with duplicate email', function () {
    Supplier::factory()->create(['email' => 'acme@example.com']);

    $this->actingAs(supplierAdminUser())
        ->post(route('suppliers.store'), supplierPayload(['email' => 'acme@example.com']))
        ->assertSessionHasErrors('email');
});

it('store fails with invalid status', function () {
    $this->actingAs(supplierAdminUser())
        ->post(route('suppliers.store'), supplierPayload(['status' => 'blocked']))
        ->assertSessionHasErrors('status');
});

it('store fails when notes exceeds max length', function () {
    $this->actingAs(supplierAdminUser())
        ->post(route('suppliers.store'), supplierPayload(['notes' => str_repeat('a', 10001)]))
        ->assertSessionHasErrors('notes');
});

it('sales user cannot create a supplier', function () {
    $this->actingAs(supplierSalesUser())
        ->post(route('suppliers.store'), supplierPayload())
        ->assertForbidden();
});

it('nullable fields are optional on store', function () {
    $this->actingAs(supplierAdminUser())
        ->post(route('suppliers.store'), supplierPayload([
            'contact_name'  => null,
            'address'       => null,
            'city'          => null,
            'state'         => null,
            'postal_code'   => null,
            'country'       => null,
            'payment_terms' => null,
            'notes'         => null,
        ]))
        ->assertRedirect(route('suppliers.index'));

    $this->assertDatabaseHas('suppliers', [
        'email'        => 'acme@example.com',
        'contact_name' => null,
    ]);
});

// ===========================================================
// EDIT
// ===========================================================

it('admin can see edit form', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs(supplierAdminUser())
        ->get(route('suppliers.edit', $supplier))
        ->assertOk()
        ->assertViewIs('suppliers.edit')
        ->assertViewHas('supplier')
        ->assertViewHas('statuses');
});

it('sales user cannot see edit form', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs(supplierSalesUser())
        ->get(route('suppliers.edit', $supplier))
        ->assertForbidden();
});

it('guest is redirected to login from edit', function () {
    $supplier = Supplier::factory()->create();

    $this->get(route('suppliers.edit', $supplier))
        ->assertRedirect(route('login'));
});

// ===========================================================
// UPDATE
// ===========================================================

it('admin can update a supplier', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs(supplierAdminUser())
        ->put(route('suppliers.update', $supplier), supplierPayload(['name' => 'Updated Name']))
        ->assertRedirect(route('suppliers.show', $supplier));

    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'Updated Name']);
});

it('update allows same email on same supplier', function () {
    $supplier = Supplier::factory()->create(['email' => 'acme@example.com']);

    $this->actingAs(supplierAdminUser())
        ->put(route('suppliers.update', $supplier), supplierPayload(['email' => 'acme@example.com']))
        ->assertRedirect(route('suppliers.show', $supplier));
});

it('update rejects email already used by another supplier', function () {
    Supplier::factory()->create(['email' => 'taken@example.com']);
    $supplier = Supplier::factory()->create(['email' => 'mine@example.com']);

    $this->actingAs(supplierAdminUser())
        ->put(route('suppliers.update', $supplier), supplierPayload(['email' => 'taken@example.com']))
        ->assertSessionHasErrors('email');
});

it('update fails with missing name', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs(supplierAdminUser())
        ->put(route('suppliers.update', $supplier), supplierPayload(['name' => '']))
        ->assertSessionHasErrors('name');
});

it('update fails with missing phone', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs(supplierAdminUser())
        ->put(route('suppliers.update', $supplier), supplierPayload(['phone' => '']))
        ->assertSessionHasErrors('phone');
});

it('update fails with invalid email format', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs(supplierAdminUser())
        ->put(route('suppliers.update', $supplier), supplierPayload(['email' => 'bad-email']))
        ->assertSessionHasErrors('email');
});

it('sales user cannot update a supplier', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs(supplierSalesUser())
        ->put(route('suppliers.update', $supplier), supplierPayload())
        ->assertForbidden();
});

// ===========================================================
// DESTROY
// ===========================================================

it('admin can delete a supplier', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs(supplierAdminUser())
        ->delete(route('suppliers.destroy', $supplier))
        ->assertRedirect(route('suppliers.index'));

    $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
});

it('sales user cannot delete a supplier', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs(supplierSalesUser())
        ->delete(route('suppliers.destroy', $supplier))
        ->assertForbidden();
});

it('guest is redirected to login from destroy', function () {
    $supplier = Supplier::factory()->create();

    $this->delete(route('suppliers.destroy', $supplier))
        ->assertRedirect(route('login'));
});

// ===========================================================
// CHANGE STATUS
// ===========================================================

it('admin can change supplier status to inactive', function () {
    $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active->value]);

    $this->actingAs(supplierAdminUser())
        ->patch(route('suppliers.changeStatus', $supplier), ['status' => 'inactive'])
        ->assertRedirect();

    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'status' => 'inactive']);
});

it('admin can change supplier status back to active', function () {
    $supplier = Supplier::factory()->inactive()->create();

    $this->actingAs(supplierAdminUser())
        ->patch(route('suppliers.changeStatus', $supplier), ['status' => 'active'])
        ->assertRedirect();

    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'status' => 'active']);
});

it('changeStatus fails with invalid status value', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs(supplierAdminUser())
        ->patch(route('suppliers.changeStatus', $supplier), ['status' => 'blocked'])
        ->assertSessionHasErrors('status');
});

it('changeStatus fails with empty status', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs(supplierAdminUser())
        ->patch(route('suppliers.changeStatus', $supplier), ['status' => ''])
        ->assertSessionHasErrors('status');
});

it('sales user cannot change supplier status', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs(supplierSalesUser())
        ->patch(route('suppliers.changeStatus', $supplier), ['status' => 'inactive'])
        ->assertForbidden();
});

it('guest is redirected to login from changeStatus', function () {
    $supplier = Supplier::factory()->create();

    $this->patch(route('suppliers.changeStatus', $supplier), ['status' => 'inactive'])
        ->assertRedirect(route('login'));
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
use Illuminate\Pagination\LengthAwarePaginator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SupplierService();
});

// ===========================================================
// paginate()
// ===========================================================

it('paginate returns a length aware paginator', function () {
    Supplier::factory()->count(5)->create();

    $result = $this->service->paginate([]);

    expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($result->total())->toBe(5);
});

it('paginate with no filters returns all suppliers', function () {
    Supplier::factory()->count(3)->create();

    $result = $this->service->paginate([]);

    expect($result->total())->toBe(3);
});

it('paginate with empty search string returns all suppliers', function () {
    Supplier::factory()->count(3)->create();

    $result = $this->service->paginate(['search' => '']);

    expect($result->total())->toBe(3);
});

it('paginate with empty status string returns all suppliers', function () {
    Supplier::factory()->create(['status' => SupplierStatus::Active->value]);
    Supplier::factory()->inactive()->create();

    $result = $this->service->paginate(['status' => '']);

    expect($result->total())->toBe(2);
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
    expect($result->first()->contact_name)->toBe('Jane Doe');
});

it('paginate filters by search on email', function () {
    Supplier::factory()->create(['email' => 'alpha@acme.com']);
    Supplier::factory()->create(['email' => 'beta@corp.com']);

    $result = $this->service->paginate(['search' => 'acme']);

    expect($result->total())->toBe(1);
    expect($result->first()->email)->toBe('alpha@acme.com');
});

it('paginate filters by active status', function () {
    Supplier::factory()->create(['status' => SupplierStatus::Active->value]);
    Supplier::factory()->inactive()->create();

    $result = $this->service->paginate(['status' => 'active']);

    expect($result->total())->toBe(1);
    expect($result->first()->status)->toBe(SupplierStatus::Active);
});

it('paginate filters by inactive status', function () {
    Supplier::factory()->create(['status' => SupplierStatus::Active->value]);
    Supplier::factory()->inactive()->create();

    $result = $this->service->paginate(['status' => 'inactive']);

    expect($result->total())->toBe(1);
    expect($result->first()->status)->toBe(SupplierStatus::Inactive);
});

it('paginate returns 20 per page', function () {
    Supplier::factory()->count(25)->create();

    $result = $this->service->paginate([]);

    expect($result->perPage())->toBe(20);
    expect($result->total())->toBe(25);
});

// ===========================================================
// store()
// ===========================================================

it('store creates and returns a supplier', function () {
    $data = [
        'name'          => 'Acme Corp',
        'contact_name'  => 'Jane Doe',
        'email'         => 'acme@example.com',
        'phone'         => '555-123-4567',
        'address'       => '123 Main St',
        'city'          => 'Chicago',
        'state'         => 'IL',
        'postal_code'   => '60601',
        'country'       => 'USA',
        'payment_terms' => 'Net 30',
        'notes'         => 'Reliable supplier.',
        'status'        => 'active',
    ];

    $supplier = $this->service->store($data);

    expect($supplier)->toBeInstanceOf(Supplier::class);
    expect($supplier->email)->toBe('acme@example.com');
    expect($supplier->name)->toBe('Acme Corp');
    $this->assertDatabaseHas('suppliers', ['email' => 'acme@example.com']);
});

it('store creates supplier with all nullable fields as null', function () {
    $data = [
        'name'          => 'Minimal Supplier',
        'contact_name'  => null,
        'email'         => 'minimal@example.com',
        'phone'         => '555-000-0000',
        'address'       => null,
        'city'          => null,
        'state'         => null,
        'postal_code'   => null,
        'country'       => null,
        'payment_terms' => null,
        'notes'         => null,
        'status'        => 'active',
    ];

    $supplier = $this->service->store($data);

    expect($supplier->contact_name)->toBeNull();
    expect($supplier->notes)->toBeNull();
    $this->assertDatabaseHas('suppliers', ['email' => 'minimal@example.com', 'contact_name' => null]);
});

// ===========================================================
// update()
// ===========================================================

it('update modifies the supplier and returns fresh model', function () {
    $supplier = Supplier::factory()->create(['name' => 'Old Name']);

    $result = $this->service->update($supplier, ['name' => 'New Name']);

    expect($result)->toBeInstanceOf(Supplier::class);
    expect($result->name)->toBe('New Name');
    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'New Name']);
});

it('update returns model reflecting DB state not stale in-memory state', function () {
    $supplier = Supplier::factory()->create(['name' => 'Original']);

    $result = $this->service->update($supplier, ['name' => 'Updated']);

    expect($result->name)->toBe('Updated');
    expect($supplier->name)->toBe('Original'); // original instance unchanged
});

// ===========================================================
// changeStatus()
// ===========================================================

it('changeStatus sets active supplier to inactive', function () {
    $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active->value]);

    $result = $this->service->changeStatus($supplier, SupplierStatus::Inactive);

    expect($result->status)->toBe(SupplierStatus::Inactive);
    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'status' => 'inactive']);
});

it('changeStatus sets inactive supplier back to active', function () {
    $supplier = Supplier::factory()->inactive()->create();

    $result = $this->service->changeStatus($supplier, SupplierStatus::Active);

    expect($result->status)->toBe(SupplierStatus::Active);
    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'status' => 'active']);
});

it('changeStatus returns fresh model with updated status', function () {
    $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active->value]);

    $result = $this->service->changeStatus($supplier, SupplierStatus::Inactive);

    expect($result)->toBeInstanceOf(Supplier::class);
    expect($result->status)->toBe(SupplierStatus::Inactive);
    expect($supplier->status)->toBe(SupplierStatus::Active); // original unchanged
});

// ===========================================================
// delete()
// ===========================================================

it('delete soft deletes the supplier', function () {
    $supplier = Supplier::factory()->create();

    $this->service->delete($supplier);

    $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
});

it('delete does not permanently remove the supplier', function () {
    $supplier = Supplier::factory()->create();

    $this->service->delete($supplier);

    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
});

it('soft deleted supplier is excluded from normal queries', function () {
    $supplier = Supplier::factory()->create();

    $this->service->delete($supplier);

    expect(Supplier::find($supplier->id))->toBeNull();
    expect(Supplier::withTrashed()->find($supplier->id))->not->toBeNull();
});
```

---

## Running Tests
```bash
php artisan test --filter SupplierControllerTest
php artisan test --filter SupplierServiceTest
```

## Coverage Summary

| Area | Feature tests | Unit tests |
|------|--------------|------------|
| index (list + filter) | 6 | 10 |
| show | 4 | — |
| create form | 3 | — |
| store (happy + validation) | 9 | 2 |
| edit form | 3 | — |
| update (happy + validation) | 7 | 2 |
| destroy | 3 | 3 |
| changeStatus | 6 | 3 |
| **Total** | **41** | **20** |
