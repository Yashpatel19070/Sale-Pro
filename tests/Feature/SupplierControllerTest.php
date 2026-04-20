<?php

declare(strict_types=1);

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PurchaseOrderPermissionSeeder;
use Database\Seeders\SupplierPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([SupplierPermissionSeeder::class, PurchaseOrderPermissionSeeder::class]);

    $this->admin = User::factory()->create()->assignRole('admin');
    $this->manager = User::factory()->create()->assignRole('manager');
    $this->procurement = User::factory()->create()->assignRole('procurement');
    $this->warehouse = User::factory()->create()->assignRole('warehouse');
});

// ── Index ────────────────────────────────────────────────────────────────────

it('index returns 200 for admin', function () {
    $this->actingAs($this->admin)
        ->get(route('suppliers.index'))
        ->assertOk();
});

it('index returns 200 for manager', function () {
    $this->actingAs($this->manager)
        ->get(route('suppliers.index'))
        ->assertOk();
});

it('index returns 200 for procurement', function () {
    $this->actingAs($this->procurement)
        ->get(route('suppliers.index'))
        ->assertOk();
});

it('index returns 403 for warehouse', function () {
    $this->actingAs($this->warehouse)
        ->get(route('suppliers.index'))
        ->assertForbidden();
});

it('index filters by search', function () {
    Supplier::factory()->create(['name' => 'Acme Corp', 'code' => 'SUP-0001']);
    Supplier::factory()->create(['name' => 'Beta Ltd',  'code' => 'SUP-0002']);

    $this->actingAs($this->admin)
        ->get(route('suppliers.index', ['search' => 'Acme']))
        ->assertOk()
        ->assertSee('Acme Corp')
        ->assertDontSee('Beta Ltd');
});

it('index filters by status active', function () {
    Supplier::factory()->create(['name' => 'Active Co', 'code' => 'SUP-0001']);
    Supplier::factory()->inactive()->create(['name' => 'Gone Ltd', 'code' => 'SUP-0002']);

    $this->actingAs($this->admin)
        ->get(route('suppliers.index', ['status' => 'active']))
        ->assertOk()
        ->assertSee('Active Co')
        ->assertDontSee('Gone Ltd');
});

it('index filters by status inactive', function () {
    Supplier::factory()->create(['name' => 'Active Co', 'code' => 'SUP-0001']);
    Supplier::factory()->inactive()->create(['name' => 'Gone Ltd', 'code' => 'SUP-0002']);

    $this->actingAs($this->admin)
        ->get(route('suppliers.index', ['status' => 'inactive']))
        ->assertOk()
        ->assertDontSee('Active Co')
        ->assertSee('Gone Ltd');
});

// ── Show ─────────────────────────────────────────────────────────────────────

it('show returns 200 for admin', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('suppliers.show', $supplier))
        ->assertOk();
});

it('show returns 200 for procurement', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->procurement)
        ->get(route('suppliers.show', $supplier))
        ->assertOk();
});

it('show returns 403 for warehouse', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->warehouse)
        ->get(route('suppliers.show', $supplier))
        ->assertForbidden();
});

it('show resolves soft-deleted supplier', function () {
    $supplier = Supplier::factory()->inactive()->create();

    $this->actingAs($this->admin)
        ->get(route('suppliers.show', $supplier))
        ->assertOk();
});

// ── Create / Store ────────────────────────────────────────────────────────────

it('create returns 200 for admin', function () {
    $this->actingAs($this->admin)
        ->get(route('suppliers.create'))
        ->assertOk();
});

it('create returns 403 for procurement', function () {
    $this->actingAs($this->procurement)
        ->get(route('suppliers.create'))
        ->assertForbidden();
});

it('store creates supplier and redirects', function () {
    $this->actingAs($this->admin)
        ->post(route('suppliers.store'), [
            'name' => 'New Supplier Co',
            'contact_name' => 'Jane Doe',
            'contact_email' => 'jane@example.com',
            'contact_phone' => '555-1234',
            'address' => '123 Main St',
            'notes' => null,
        ])
        ->assertRedirect();

    $supplier = Supplier::where('name', 'New Supplier Co')->first();
    expect($supplier)->not->toBeNull();
    expect($supplier->is_active)->toBeTrue();
    expect($supplier->code)->toStartWith('SUP-');
});

it('store requires name', function () {
    $this->actingAs($this->admin)
        ->post(route('suppliers.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('store rejects duplicate name', function () {
    Supplier::factory()->create(['name' => 'Existing Co', 'code' => 'SUP-0001']);

    $this->actingAs($this->admin)
        ->post(route('suppliers.store'), ['name' => 'Existing Co'])
        ->assertSessionHasErrors('name');
});

it('store allows name of soft-deleted supplier', function () {
    Supplier::factory()->inactive()->create(['name' => 'Gone Co', 'code' => 'SUP-0001']);

    $this->actingAs($this->admin)
        ->post(route('suppliers.store'), ['name' => 'Gone Co'])
        ->assertRedirect();

    expect(Supplier::withTrashed()->where('name', 'Gone Co')->count())->toBe(2);
});

it('store auto-generates sequential code', function () {
    $this->actingAs($this->admin)
        ->post(route('suppliers.store'), ['name' => 'First Supplier']);

    $this->actingAs($this->admin)
        ->post(route('suppliers.store'), ['name' => 'Second Supplier']);

    $first = Supplier::where('name', 'First Supplier')->first();
    $second = Supplier::where('name', 'Second Supplier')->first();

    expect($first->code)->toBe('SUP-0001');
    expect($second->code)->toBe('SUP-0002');
});

it('store returns 403 for procurement', function () {
    $this->actingAs($this->procurement)
        ->post(route('suppliers.store'), ['name' => 'X'])
        ->assertForbidden();
});

// ── Edit / Update ─────────────────────────────────────────────────────────────

it('edit returns 200 for admin', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('suppliers.edit', $supplier))
        ->assertOk();
});

it('edit returns 403 for procurement', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->procurement)
        ->get(route('suppliers.edit', $supplier))
        ->assertForbidden();
});

it('update saves changes and redirects to show', function () {
    $supplier = Supplier::factory()->create(['name' => 'Old Name', 'code' => 'SUP-0001']);

    $this->actingAs($this->admin)
        ->patch(route('suppliers.update', $supplier), [
            'name' => 'New Name',
            'contact_name' => 'Bob',
            'contact_email' => null,
            'contact_phone' => null,
            'address' => null,
            'notes' => null,
        ])
        ->assertRedirect(route('suppliers.show', $supplier));

    expect($supplier->fresh()->name)->toBe('New Name');
});

it('update cannot change code', function () {
    $supplier = Supplier::factory()->create(['code' => 'SUP-0001']);

    $this->actingAs($this->admin)
        ->patch(route('suppliers.update', $supplier), [
            'name' => 'Updated Name',
            'contact_name' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'address' => null,
            'notes' => null,
        ]);

    expect($supplier->fresh()->code)->toBe('SUP-0001');
});

it('update rejects duplicate name', function () {
    Supplier::factory()->create(['name' => 'Other Co', 'code' => 'SUP-0001']);
    $supplier = Supplier::factory()->create(['name' => 'My Co', 'code' => 'SUP-0002']);

    $this->actingAs($this->admin)
        ->patch(route('suppliers.update', $supplier), [
            'name' => 'Other Co',
            'contact_name' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'address' => null,
            'notes' => null,
        ])
        ->assertSessionHasErrors('name');
});

it('update returns 403 for procurement', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->procurement)
        ->patch(route('suppliers.update', $supplier), ['name' => 'X'])
        ->assertForbidden();
});

// ── Destroy ───────────────────────────────────────────────────────────────────

it('destroy deactivates supplier', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('suppliers.destroy', $supplier));

    $supplier->refresh();
    expect($supplier->trashed())->toBeTrue();
    expect($supplier->is_active)->toBeFalse();
});

it('destroy redirects to index with success message', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('suppliers.destroy', $supplier))
        ->assertRedirect(route('suppliers.index'))
        ->assertSessionHas('success');
});

it('destroy returns 403 for procurement', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->procurement)
        ->delete(route('suppliers.destroy', $supplier))
        ->assertForbidden();
});

it('destroy is blocked when supplier has open purchase orders', function () {
    $supplier = Supplier::factory()->create();
    PurchaseOrder::factory()->open()->create(['supplier_id' => $supplier->id]);

    $this->actingAs($this->admin)
        ->delete(route('suppliers.destroy', $supplier))
        ->assertRedirect()
        ->assertSessionHasErrors('supplier');

    expect($supplier->fresh()->trashed())->toBeFalse();
});

// ── Restore ───────────────────────────────────────────────────────────────────

it('restore reactivates supplier', function () {
    $supplier = Supplier::factory()->inactive()->create();

    $this->actingAs($this->admin)
        ->post(route('suppliers.restore', $supplier));

    $supplier->refresh();
    expect($supplier->trashed())->toBeFalse();
    expect($supplier->is_active)->toBeTrue();
});

it('restore redirects to show with success message', function () {
    $supplier = Supplier::factory()->inactive()->create();

    $this->actingAs($this->admin)
        ->post(route('suppliers.restore', $supplier))
        ->assertRedirect(route('suppliers.show', $supplier))
        ->assertSessionHas('success');
});

it('restore returns 403 for procurement', function () {
    $supplier = Supplier::factory()->inactive()->create();

    $this->actingAs($this->procurement)
        ->post(route('suppliers.restore', $supplier))
        ->assertForbidden();
});

it('restore resolves soft-deleted supplier via withTrashed', function () {
    $supplier = Supplier::factory()->inactive()->create();

    $this->actingAs($this->admin)
        ->post(route('suppliers.restore', $supplier))
        ->assertRedirect();
});
