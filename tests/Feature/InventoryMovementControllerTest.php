<?php

declare(strict_types=1);

use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\InventoryMovementPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(InventoryMovementPermissionSeeder::class);

    $this->admin = User::factory()->create()->assignRole('admin');
    $this->sales = User::factory()->create()->assignRole('sales');

    $this->product = Product::factory()->create();
    $this->locationA = InventoryLocation::factory()->create(['code' => 'L1', 'is_active' => true]);
    $this->locationB = InventoryLocation::factory()->create(['code' => 'L2', 'is_active' => true]);

    // Serial currently at L1, in_stock
    $this->serial = InventorySerial::factory()->create([
        'product_id' => $this->product->id,
        'inventory_location_id' => $this->locationA->id,
        'status' => 'in_stock',
    ]);
});

// ── index ─────────────────────────────────────────────────────────────────────

it('admin can view movement history index', function () {
    InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('inventory-movements.index'))
        ->assertOk()
        ->assertViewHas('movements');
});

it('sales can view movement history index', function () {
    $this->actingAs($this->sales)
        ->get(route('inventory-movements.index'))
        ->assertOk();
});

it('unauthenticated user is redirected from index', function () {
    $this->get(route('inventory-movements.index'))
        ->assertRedirect(route('login'));
});

it('index filters by serial number', function () {
    $this->actingAs($this->admin)
        ->get(route('inventory-movements.index', ['serial_number' => $this->serial->serial_number]))
        ->assertOk()
        ->assertViewHas('movements');
});

it('index filters by type', function () {
    $this->actingAs($this->admin)
        ->get(route('inventory-movements.index', ['type' => 'transfer']))
        ->assertOk();
});

// ── create ────────────────────────────────────────────────────────────────────

it('admin can view the create movement form', function () {
    $this->actingAs($this->admin)
        ->get(route('inventory-movements.create'))
        ->assertOk()
        ->assertViewHas('serials')
        ->assertViewHas('locations')
        ->assertViewHas('types');
});

it('sales can view the create movement form', function () {
    $this->actingAs($this->sales)
        ->get(route('inventory-movements.create'))
        ->assertOk();
});

// ── store: transfer ───────────────────────────────────────────────────────────

it('admin can record a transfer movement', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $this->serial->id,
            'type' => 'transfer',
            'from_location_id' => $this->locationA->id,
            'to_location_id' => $this->locationB->id,
            'reference' => 'MOVE-001',
            'notes' => 'Reorganising shelves',
        ])
        ->assertRedirect(route('inventory-movements.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $this->serial->id,
        'type' => 'transfer',
        'from_location_id' => $this->locationA->id,
        'to_location_id' => $this->locationB->id,
    ]);

    expect($this->serial->fresh()->inventory_location_id)->toBe($this->locationB->id);
});

it('sales can record a transfer movement', function () {
    $this->actingAs($this->sales)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $this->serial->id,
            'type' => 'transfer',
            'from_location_id' => $this->locationA->id,
            'to_location_id' => $this->locationB->id,
        ])
        ->assertRedirect(route('inventory-movements.index'));

    expect($this->serial->fresh()->inventory_location_id)->toBe($this->locationB->id);
});

it('rejects transfer when from_location does not match serial current location', function () {
    $wrongLocation = InventoryLocation::factory()->create(['is_active' => true]);

    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $this->serial->id,
            'type' => 'transfer',
            'from_location_id' => $wrongLocation->id,
            'to_location_id' => $this->locationB->id,
        ])
        ->assertSessionHasErrors(['from_location_id']);

    $this->assertDatabaseCount('inventory_movements', 0);
});

it('rejects transfer when serial is not in_stock', function () {
    $this->serial->update(['status' => 'sold']);

    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $this->serial->id,
            'type' => 'transfer',
            'from_location_id' => $this->locationA->id,
            'to_location_id' => $this->locationB->id,
        ])
        ->assertSessionHasErrors(['inventory_serial_id']);

    $this->assertDatabaseCount('inventory_movements', 0);
});

it('requires from_location_id for transfer', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $this->serial->id,
            'type' => 'transfer',
            'to_location_id' => $this->locationB->id,
        ])
        ->assertSessionHasErrors(['from_location_id']);
});

it('requires to_location_id for transfer', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $this->serial->id,
            'type' => 'transfer',
            'from_location_id' => $this->locationA->id,
        ])
        ->assertSessionHasErrors(['to_location_id']);
});

// ── store: sale ───────────────────────────────────────────────────────────────

it('admin can record a sale movement', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $this->serial->id,
            'type' => 'sale',
            'from_location_id' => $this->locationA->id,
            'reference' => 'ORD-2024-0042',
            'notes' => 'Customer order shipped',
        ])
        ->assertRedirect(route('inventory-movements.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $this->serial->id,
        'type' => 'sale',
        'from_location_id' => $this->locationA->id,
        'to_location_id' => null,
    ]);

    $fresh = $this->serial->fresh();
    expect($fresh->status->value)->toBe('sold');
    expect($fresh->inventory_location_id)->toBeNull();
});

it('sales can record a sale movement', function () {
    $this->actingAs($this->sales)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $this->serial->id,
            'type' => 'sale',
            'from_location_id' => $this->locationA->id,
        ])
        ->assertRedirect(route('inventory-movements.index'));

    expect($this->serial->fresh()->status->value)->toBe('sold');
});

it('rejects sale when serial is not in_stock', function () {
    $this->serial->update(['status' => 'damaged']);

    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $this->serial->id,
            'type' => 'sale',
            'from_location_id' => $this->locationA->id,
        ])
        ->assertSessionHasErrors(['inventory_serial_id']);

    $this->assertDatabaseCount('inventory_movements', 0);
});

// ── store: adjustment ─────────────────────────────────────────────────────────

it('admin can record an adjustment movement (damaged)', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $this->serial->id,
            'type' => 'adjustment',
            'adjustment_status' => 'damaged',
            'notes' => 'Dropped during transport, cracked screen',
        ])
        ->assertRedirect(route('inventory-movements.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $this->serial->id,
        'type' => 'adjustment',
    ]);

    $fresh = $this->serial->fresh();
    expect($fresh->status->value)->toBe('damaged');
    expect($fresh->inventory_location_id)->toBeNull();
});

it('admin can record an adjustment movement (missing)', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $this->serial->id,
            'type' => 'adjustment',
            'adjustment_status' => 'missing',
            'reference' => 'CYCLE-COUNT-2024-Q1',
        ])
        ->assertRedirect(route('inventory-movements.index'));

    expect($this->serial->fresh()->status->value)->toBe('missing');
});

it('sales cannot record an adjustment movement', function () {
    $this->actingAs($this->sales)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $this->serial->id,
            'type' => 'adjustment',
            'adjustment_status' => 'damaged',
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('inventory_movements', 0);
});

it('rejects adjustment with invalid adjustment_status', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $this->serial->id,
            'type' => 'adjustment',
            'adjustment_status' => 'scrapped',
        ])
        ->assertSessionHasErrors(['adjustment_status']);
});

it('rejects receive type via the UI', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $this->serial->id,
            'type' => 'receive',
        ])
        ->assertSessionHasErrors(['type']);
});

// ── immutability ──────────────────────────────────────────────────────────────

it('has no edit route', function () {
    $movement = InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id' => $this->admin->id,
    ]);

    expect(fn () => route('inventory-movements.edit', $movement))
        ->toThrow(Exception::class);
});

it('has no delete route', function () {
    $movement = InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id' => $this->admin->id,
    ]);

    expect(fn () => route('inventory-movements.destroy', $movement))
        ->toThrow(Exception::class);
});

// ── forSerial timeline ────────────────────────────────────────────────────────

it('admin can view serial movement timeline', function () {
    InventoryMovement::factory()->receive()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('inventory-serials.movements', $this->serial))
        ->assertOk()
        ->assertViewHas('movements')
        ->assertViewHas('inventorySerial');
});

it('sales can view serial movement timeline', function () {
    $this->actingAs($this->sales)
        ->get(route('inventory-serials.movements', $this->serial))
        ->assertOk();
});

it('unauthenticated user is redirected from serial timeline', function () {
    $this->get(route('inventory-serials.movements', $this->serial))
        ->assertRedirect(route('login'));
});
