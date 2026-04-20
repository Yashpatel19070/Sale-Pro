<?php

declare(strict_types=1);

use App\Enums\SupplierStatus;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\SupplierPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SupplierPermissionSeeder::class);
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
        'suppliers.restore',
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
        'name' => 'Acme Corp',
        'contact_name' => 'John Smith',
        'email' => 'acme@example.com',
        'phone' => '555-123-4567',
        'address' => null,
        'city' => null,
        'state' => null,
        'postal_code' => null,
        'country' => null,
        'payment_terms' => 'Net 30',
        'notes' => null,
        'status' => 'active',
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
            'contact_name' => null,
            'address' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country' => null,
            'payment_terms' => null,
            'notes' => null,
        ]))
        ->assertRedirect(route('suppliers.index'));

    $this->assertDatabaseHas('suppliers', [
        'email' => 'acme@example.com',
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

// ===========================================================
// RESTORE
// ===========================================================

it('admin can restore a soft-deleted supplier', function () {
    $supplier = Supplier::factory()->create();
    $supplier->delete();

    $this->actingAs(supplierAdminUser())
        ->post(route('suppliers.restore', $supplier->id))
        ->assertRedirect(route('suppliers.index'));

    $this->assertNotSoftDeleted('suppliers', ['id' => $supplier->id]);
});

it('sales user cannot restore a supplier', function () {
    $supplier = Supplier::factory()->create();
    $supplier->delete();

    $this->actingAs(supplierSalesUser())
        ->post(route('suppliers.restore', $supplier->id))
        ->assertForbidden();
});

it('guest is redirected to login from restore', function () {
    $supplier = Supplier::factory()->create();
    $supplier->delete();

    $this->post(route('suppliers.restore', $supplier->id))
        ->assertRedirect(route('login'));
});
