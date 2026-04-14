<?php

declare(strict_types=1);

use App\Models\InventoryLocation;
use App\Services\InventoryLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new InventoryLocationService;
});

// ===========================================================
// list()
// ===========================================================

it('list returns a paginator', function () {
    InventoryLocation::factory()->count(5)->create();

    $result = $this->service->list([]);

    expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
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
        'name' => 'New Name',
        'description' => 'New description.',
    ]);

    expect($updated->name)->toBe('New Name');
    expect($updated->description)->toBe('New description.');
    $this->assertDatabaseHas('inventory_locations', [
        'id' => $location->id,
        'name' => 'New Name',
    ]);
});

it('update does not change the code', function () {
    $location = InventoryLocation::factory()->create(['code' => 'ORIGINAL']);

    $this->service->update($location, [
        'name' => 'New Name',
    ]);

    $this->assertDatabaseHas('inventory_locations', [
        'id' => $location->id,
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
        'id' => $location->id,
        'is_active' => false,
    ]);
});

// ===========================================================
// restore()
// ===========================================================

it('restore clears deleted_at', function () {
    $location = InventoryLocation::factory()->create();
    $location->delete();

    $restored = $this->service->restore($location);

    expect($restored->deleted_at)->toBeNull();
    $this->assertDatabaseHas('inventory_locations', [
        'id' => $location->id,
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

    $count = $this->service->activeSerialCount($location);

    expect($count)->toBe(0);
});
