<?php

declare(strict_types=1);

use App\Models\InventoryLocation;
use App\Models\User;
use Database\Seeders\InventoryLocationPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(InventoryLocationPermissionSeeder::class);
});

// ── Helpers ────────────────────────────────────────────────────────────────

function locationAdminUser(): User
{
    return User::factory()->create()->assignRole('admin');
}

function locationSalesUser(): User
{
    return User::factory()->create()->assignRole('sales');
}

function inventoryLocationPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'L1',
        'name' => 'Shelf L1 Row A',
        'description' => 'Test location description.',
    ], $overrides);
}

// ===========================================================
// AUTHENTICATION
// ===========================================================

it('redirects unauthenticated users from index', function () {
    $this->get(route('inventory-locations.index'))
        ->assertRedirect(route('login'));
});

it('redirects unauthenticated users from create', function () {
    $this->get(route('inventory-locations.create'))
        ->assertRedirect(route('login'));
});

// ===========================================================
// INDEX
// ===========================================================

it('admin can view the locations list', function () {
    $admin = locationAdminUser();
    InventoryLocation::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('inventory-locations.index'))
        ->assertOk()
        ->assertViewIs('inventory.locations.index')
        ->assertViewHas('locations');
});

it('sales user can view the locations list', function () {
    $sales = locationSalesUser();

    $this->actingAs($sales)
        ->get(route('inventory-locations.index'))
        ->assertOk();
});

it('index filters by search term', function () {
    $admin = locationAdminUser();
    InventoryLocation::factory()->create(['code' => 'L1', 'name' => 'Shelf L1 Row A']);
    InventoryLocation::factory()->create(['code' => 'ZONE-B', 'name' => 'Zone B Bin']);

    $this->actingAs($admin)
        ->get(route('inventory-locations.index', ['search' => 'L1']))
        ->assertSee('L1')
        ->assertDontSee('ZONE-B');
});

it('index filters by active status', function () {
    $admin = locationAdminUser();
    InventoryLocation::factory()->create(['code' => 'ACTIVE-1', 'is_active' => true]);
    InventoryLocation::factory()->inactive()->create(['code' => 'INACTIVE-1']);

    $this->actingAs($admin)
        ->get(route('inventory-locations.index', ['status' => 'active']))
        ->assertSee('ACTIVE-1')
        ->assertDontSee('INACTIVE-1');
});

// ===========================================================
// SHOW
// ===========================================================

it('admin can view a location', function () {
    $admin = locationAdminUser();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($admin)
        ->get(route('inventory-locations.show', $location))
        ->assertOk()
        ->assertViewIs('inventory.locations.show')
        ->assertViewHas('location', $location);
});

it('sales user can view a location', function () {
    $sales = locationSalesUser();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($sales)
        ->get(route('inventory-locations.show', $location))
        ->assertOk();
});

// ===========================================================
// CREATE
// ===========================================================

it('admin can access the create form', function () {
    $admin = locationAdminUser();

    $this->actingAs($admin)
        ->get(route('inventory-locations.create'))
        ->assertOk()
        ->assertViewIs('inventory.locations.create');
});

it('sales user is forbidden from create form', function () {
    $sales = locationSalesUser();

    $this->actingAs($sales)
        ->get(route('inventory-locations.create'))
        ->assertForbidden();
});

// ===========================================================
// STORE
// ===========================================================

it('admin can create a location', function () {
    $admin = locationAdminUser();

    $this->actingAs($admin)
        ->post(route('inventory-locations.store'), inventoryLocationPayload())
        ->assertRedirect();

    $this->assertDatabaseHas('inventory_locations', [
        'code' => 'L1',
        'name' => 'Shelf L1 Row A',
    ]);
});

it('store redirects to show on success', function () {
    $admin = locationAdminUser();

    $response = $this->actingAs($admin)
        ->post(route('inventory-locations.store'), inventoryLocationPayload(['code' => 'L99']));

    $location = InventoryLocation::where('code', 'L99')->first();
    $response->assertRedirect(route('inventory-locations.show', $location));
});

it('store normalizes code to uppercase', function () {
    $admin = locationAdminUser();

    $this->actingAs($admin)
        ->post(route('inventory-locations.store'), inventoryLocationPayload(['code' => 'l5']))
        ->assertRedirect();

    $this->assertDatabaseHas('inventory_locations', ['code' => 'L5']);
});

it('store fails with duplicate code', function () {
    $admin = locationAdminUser();
    InventoryLocation::factory()->create(['code' => 'L1']);

    $this->actingAs($admin)
        ->post(route('inventory-locations.store'), inventoryLocationPayload(['code' => 'L1']))
        ->assertSessionHasErrors('code');
});

it('store fails if code is missing', function () {
    $admin = locationAdminUser();

    $this->actingAs($admin)
        ->post(route('inventory-locations.store'), ['name' => 'Shelf A'])
        ->assertSessionHasErrors('code');
});

it('store fails if name is missing', function () {
    $admin = locationAdminUser();

    $this->actingAs($admin)
        ->post(route('inventory-locations.store'), ['code' => 'L1'])
        ->assertSessionHasErrors('name');
});

it('sales user cannot store a location', function () {
    $sales = locationSalesUser();

    $this->actingAs($sales)
        ->post(route('inventory-locations.store'), inventoryLocationPayload())
        ->assertForbidden();
});

// ===========================================================
// EDIT
// ===========================================================

it('admin can access the edit form', function () {
    $admin = locationAdminUser();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($admin)
        ->get(route('inventory-locations.edit', $location))
        ->assertOk()
        ->assertViewIs('inventory.locations.edit')
        ->assertViewHas('location', $location);
});

it('sales user is forbidden from edit form', function () {
    $sales = locationSalesUser();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($sales)
        ->get(route('inventory-locations.edit', $location))
        ->assertForbidden();
});

// ===========================================================
// UPDATE
// ===========================================================

it('admin can update name and description', function () {
    $admin = locationAdminUser();
    $location = InventoryLocation::factory()->create(['name' => 'Old Name']);

    $this->actingAs($admin)
        ->put(route('inventory-locations.update', $location), [
            'name' => 'New Name',
            'description' => 'Updated description.',
        ])
        ->assertRedirect(route('inventory-locations.show', $location));

    $this->assertDatabaseHas('inventory_locations', [
        'id' => $location->id,
        'name' => 'New Name',
    ]);
});

it('update does not change the code', function () {
    $admin = locationAdminUser();
    $location = InventoryLocation::factory()->create(['code' => 'L1']);

    $this->actingAs($admin)
        ->put(route('inventory-locations.update', $location), [
            'code' => 'HACKED',
            'name' => 'New Name',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('inventory_locations', ['id' => $location->id, 'code' => 'L1']);
    $this->assertDatabaseMissing('inventory_locations', ['code' => 'HACKED']);
});

it('sales user cannot update a location', function () {
    $sales = locationSalesUser();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($sales)
        ->put(route('inventory-locations.update', $location), ['name' => 'New Name'])
        ->assertForbidden();
});

// ===========================================================
// DESTROY (Deactivate)
// ===========================================================

it('admin can deactivate a location with no active serials', function () {
    $admin = locationAdminUser();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($admin)
        ->delete(route('inventory-locations.destroy', $location))
        ->assertRedirect(route('inventory-locations.index'));

    $this->assertSoftDeleted('inventory_locations', ['id' => $location->id]);
});

it('sales user cannot deactivate a location', function () {
    $sales = locationSalesUser();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($sales)
        ->delete(route('inventory-locations.destroy', $location))
        ->assertForbidden();
});

// ===========================================================
// RESTORE
// ===========================================================

it('admin can restore a deactivated location', function () {
    $admin = locationAdminUser();
    $location = InventoryLocation::factory()->create();
    $location->delete();

    $this->actingAs($admin)
        ->post(route('inventory-locations.restore', $location->id))
        ->assertRedirect(route('inventory-locations.show', $location));

    $this->assertDatabaseHas('inventory_locations', [
        'id' => $location->id,
        'deleted_at' => null,
        'is_active' => true,
    ]);
});

it('sales user cannot restore a location', function () {
    $sales = locationSalesUser();
    $location = InventoryLocation::factory()->create();
    $location->delete();

    $this->actingAs($sales)
        ->post(route('inventory-locations.restore', $location->id))
        ->assertForbidden();
});
