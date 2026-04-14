# InventoryLocation Module — Tests

Two test files: Feature (controller) and Unit (service). All tests use Pest with `RefreshDatabase`.

---

## 1. Feature Test — InventoryLocationControllerTest

**File:** `tests/Feature/InventoryLocationControllerTest.php`

```php
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

function adminUser(): User
{
    return User::factory()->create()->assignRole('admin');
}

function salesUser(): User
{
    return User::factory()->create()->assignRole('sales');
}

function locationPayload(array $overrides = []): array
{
    return array_merge([
        'code'        => 'L1',
        'name'        => 'Shelf L1 Row A',
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
    $admin = adminUser();
    InventoryLocation::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('inventory-locations.index'))
        ->assertOk()
        ->assertViewIs('inventory.locations.index')
        ->assertViewHas('locations');
});

it('sales user can view the locations list', function () {
    $sales = salesUser();

    $this->actingAs($sales)
        ->get(route('inventory-locations.index'))
        ->assertOk();
});

it('index filters by search term', function () {
    $admin = adminUser();
    InventoryLocation::factory()->create(['code' => 'L1', 'name' => 'Shelf L1 Row A']);
    InventoryLocation::factory()->create(['code' => 'ZONE-B', 'name' => 'Zone B Bin']);

    $this->actingAs($admin)
        ->get(route('inventory-locations.index', ['search' => 'L1']))
        ->assertSee('L1')
        ->assertDontSee('ZONE-B');
});

it('index filters by active status', function () {
    $admin = adminUser();
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
    $admin    = adminUser();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($admin)
        ->get(route('inventory-locations.show', $location))
        ->assertOk()
        ->assertViewIs('inventory.locations.show')
        ->assertViewHas('location', $location);
});

it('sales user can view a location', function () {
    $sales    = salesUser();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($sales)
        ->get(route('inventory-locations.show', $location))
        ->assertOk();
});

// ===========================================================
// CREATE
// ===========================================================

it('admin can access the create form', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('inventory-locations.create'))
        ->assertOk()
        ->assertViewIs('inventory.locations.create');
});

it('sales user is forbidden from create form', function () {
    $sales = salesUser();

    $this->actingAs($sales)
        ->get(route('inventory-locations.create'))
        ->assertForbidden();
});

// ===========================================================
// STORE
// ===========================================================

it('admin can create a location', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('inventory-locations.store'), locationPayload())
        ->assertRedirect();

    $this->assertDatabaseHas('inventory_locations', [
        'code' => 'L1',
        'name' => 'Shelf L1 Row A',
    ]);
});

it('store redirects to show on success', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)
        ->post(route('inventory-locations.store'), locationPayload(['code' => 'L99']));

    $location = InventoryLocation::where('code', 'L99')->first();
    $response->assertRedirect(route('inventory-locations.show', $location));
});

it('store normalizes code to uppercase', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('inventory-locations.store'), locationPayload(['code' => 'l5']))
        ->assertRedirect();

    $this->assertDatabaseHas('inventory_locations', ['code' => 'L5']);
});

it('store fails with duplicate code', function () {
    $admin = adminUser();
    InventoryLocation::factory()->create(['code' => 'L1']);

    $this->actingAs($admin)
        ->post(route('inventory-locations.store'), locationPayload(['code' => 'L1']))
        ->assertSessionHasErrors('code');
});

it('store fails if code is missing', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('inventory-locations.store'), ['name' => 'Shelf A'])
        ->assertSessionHasErrors('code');
});

it('store fails if name is missing', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('inventory-locations.store'), ['code' => 'L1'])
        ->assertSessionHasErrors('name');
});

it('sales user cannot store a location', function () {
    $sales = salesUser();

    $this->actingAs($sales)
        ->post(route('inventory-locations.store'), locationPayload())
        ->assertForbidden();
});

// ===========================================================
// EDIT
// ===========================================================

it('admin can access the edit form', function () {
    $admin    = adminUser();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($admin)
        ->get(route('inventory-locations.edit', $location))
        ->assertOk()
        ->assertViewIs('inventory.locations.edit')
        ->assertViewHas('location', $location);
});

it('sales user is forbidden from edit form', function () {
    $sales    = salesUser();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($sales)
        ->get(route('inventory-locations.edit', $location))
        ->assertForbidden();
});

// ===========================================================
// UPDATE
// ===========================================================

it('admin can update name and description', function () {
    $admin    = adminUser();
    $location = InventoryLocation::factory()->create(['name' => 'Old Name']);

    $this->actingAs($admin)
        ->put(route('inventory-locations.update', $location), [
            'name'        => 'New Name',
            'description' => 'Updated description.',
        ])
        ->assertRedirect(route('inventory-locations.show', $location));

    $this->assertDatabaseHas('inventory_locations', [
        'id'   => $location->id,
        'name' => 'New Name',
    ]);
});

it('update does not change the code', function () {
    $admin    = adminUser();
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
    $sales    = salesUser();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($sales)
        ->put(route('inventory-locations.update', $location), ['name' => 'New Name'])
        ->assertForbidden();
});

// ===========================================================
// DESTROY (Deactivate)
// ===========================================================

it('admin can deactivate a location with no active serials', function () {
    $admin    = adminUser();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($admin)
        ->delete(route('inventory-locations.destroy', $location))
        ->assertRedirect(route('inventory-locations.index'));

    $this->assertSoftDeleted('inventory_locations', ['id' => $location->id]);
});

it('sales user cannot deactivate a location', function () {
    $sales    = salesUser();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($sales)
        ->delete(route('inventory-locations.destroy', $location))
        ->assertForbidden();
});

// ===========================================================
// RESTORE
// ===========================================================

it('admin can restore a deactivated location', function () {
    $admin    = adminUser();
    $location = InventoryLocation::factory()->create();
    $location->delete();

    $this->actingAs($admin)
        ->post(route('inventory-locations.restore', $location->id))
        ->assertRedirect(route('inventory-locations.show', $location));

    $this->assertDatabaseHas('inventory_locations', [
        'id'         => $location->id,
        'deleted_at' => null,
        'is_active'  => true,
    ]);
});

it('sales user cannot restore a location', function () {
    $sales    = salesUser();
    $location = InventoryLocation::factory()->create();
    $location->delete();

    $this->actingAs($sales)
        ->post(route('inventory-locations.restore', $location->id))
        ->assertForbidden();
});
```

---

## 2. Unit Test — InventoryLocationServiceTest

**File:** `tests/Unit/Services/InventoryLocationServiceTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\InventoryLocation;
use App\Services\InventoryLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new InventoryLocationService();
});

// ===========================================================
// list()
// ===========================================================

it('list returns a paginator', function () {
    InventoryLocation::factory()->count(5)->create();

    $result = $this->service->list([]);

    expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
    expect($result->total())->toBe(5);
});

it('list filters by search on code', function () {
    InventoryLocation::factory()->create(['code' => 'L1', 'name' => 'Shelf L1']);
    InventoryLocation::factory()->create(['code' => 'ZONE-B', 'name' => 'Zone B']);

    $result = $this->service->list(['search' => 'L1']);

    expect($result->total())->toBe(1);
    expect($result->first()->code)->toBe('L1');
});

it('list filters by search on name', function () {
    InventoryLocation::factory()->create(['code' => 'L1', 'name' => 'Shelf Aardvark']);
    InventoryLocation::factory()->create(['code' => 'L2', 'name' => 'Zone Zebra']);

    $result = $this->service->list(['search' => 'Aardvark']);

    expect($result->total())->toBe(1);
    expect($result->first()->code)->toBe('L1');
});

it('list filters active locations', function () {
    InventoryLocation::factory()->create(['is_active' => true]);
    InventoryLocation::factory()->inactive()->create();

    $result = $this->service->list(['status' => 'active']);

    expect($result->total())->toBe(1);
    expect($result->first()->is_active)->toBeTrue();
});

it('list filters inactive locations', function () {
    InventoryLocation::factory()->create(['is_active' => true]);
    InventoryLocation::factory()->inactive()->create();

    $result = $this->service->list(['status' => 'inactive']);

    expect($result->total())->toBe(1);
    expect($result->first()->is_active)->toBeFalse();
});

it('list returns all when no filters given', function () {
    InventoryLocation::factory()->count(3)->create();

    $result = $this->service->list([]);

    expect($result->total())->toBe(3);
});

// ===========================================================
// store()
// ===========================================================

it('store creates a location', function () {
    $location = $this->service->store([
        'code' => 'L99',
        'name' => 'Shelf L99',
    ]);

    expect($location)->toBeInstanceOf(InventoryLocation::class);
    expect($location->code)->toBe('L99');
    expect($location->is_active)->toBeTrue();
});

it('store uppercases the code', function () {
    $location = $this->service->store([
        'code' => 'l5',
        'name' => 'Shelf L5',
    ]);

    expect($location->code)->toBe('L5');
    $this->assertDatabaseHas('inventory_locations', ['code' => 'L5']);
});

it('store sets is_active to true by default', function () {
    $location = $this->service->store([
        'code' => 'L10',
        'name' => 'Shelf L10',
    ]);

    expect($location->is_active)->toBeTrue();
});

// ===========================================================
// update()
// ===========================================================

it('update changes name and description', function () {
    $location = InventoryLocation::factory()->create(['name' => 'Old Name']);

    $updated = $this->service->update($location, [
        'name'        => 'New Name',
        'description' => 'New description.',
    ]);

    expect($updated->name)->toBe('New Name');
    expect($updated->description)->toBe('New description.');
    $this->assertDatabaseHas('inventory_locations', [
        'id'   => $location->id,
        'name' => 'New Name',
    ]);
});

it('update does not change the code', function () {
    $location = InventoryLocation::factory()->create(['code' => 'ORIGINAL']);

    $this->service->update($location, [
        'name' => 'New Name',
    ]);

    $this->assertDatabaseHas('inventory_locations', [
        'id'   => $location->id,
        'code' => 'ORIGINAL',
    ]);
});

// ===========================================================
// deactivate()
// ===========================================================

it('deactivate soft-deletes a location with no active serials', function () {
    $location = InventoryLocation::factory()->create();

    $this->service->deactivate($location);

    $this->assertSoftDeleted('inventory_locations', ['id' => $location->id]);
});

it('deactivate sets is_active to false', function () {
    $location = InventoryLocation::factory()->create(['is_active' => true]);

    $this->service->deactivate($location);

    $this->assertDatabaseHas('inventory_locations', [
        'id'        => $location->id,
        'is_active' => false,
    ]);
});

// TODO: Uncomment and complete this test once the inventory_serials migration exists.
// Uses DB::table() deliberately — avoids a hard dependency on the InventorySerial model
// before that module is built.
//
// it('deactivate throws ValidationException when active serials exist', function () {
//     $location = InventoryLocation::factory()->create();
//
//     // Insert a minimal serial row directly — no InventorySerial model needed yet.
//     DB::table('inventory_serials')->insert([
//         'inventory_location_id' => $location->id,
//         'serial_number'         => 'SN-TEST-001',
//         'status'                => 'in_stock',
//         'created_at'            => now(),
//         'updated_at'            => now(),
//     ]);
//
//     expect(fn () => $this->service->deactivate($location))
//         ->toThrow(ValidationException::class);
//
//     // Location must still be active and not soft-deleted
//     $this->assertDatabaseHas('inventory_locations', [
//         'id'        => $location->id,
//         'is_active' => true,
//     ]);
//     $this->assertNotSoftDeleted('inventory_locations', ['id' => $location->id]);
// });

// ===========================================================
// restore()
// ===========================================================

it('restore clears deleted_at', function () {
    $location = InventoryLocation::factory()->create();
    $location->delete();

    $restored = $this->service->restore($location);

    expect($restored->deleted_at)->toBeNull();
    $this->assertDatabaseHas('inventory_locations', [
        'id'         => $location->id,
        'deleted_at' => null,
    ]);
});

it('restore sets is_active to true', function () {
    $location = InventoryLocation::factory()->create(['is_active' => false]);
    $location->delete();

    $restored = $this->service->restore($location);

    expect($restored->is_active)->toBeTrue();
});

// ===========================================================
// activeSerialCount()
// ===========================================================

it('activeSerialCount returns 0 when inventory_serials table does not exist', function () {
    $location = InventoryLocation::factory()->create();

    // Table won't exist in test DB at this stage of development
    $count = $this->service->activeSerialCount($location);

    expect($count)->toBe(0);
});
```

---

## Running Tests

```bash
php artisan test --filter InventoryLocationControllerTest
php artisan test --filter InventoryLocationServiceTest

# Or all at once:
php artisan test
```
