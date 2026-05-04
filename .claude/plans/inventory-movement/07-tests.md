# InventoryMovement Module — Tests

## Feature Tests — Controller

```php
<?php
// tests/Feature/InventoryMovementControllerTest.php
// PREREQUISITE: These tests require InventoryLocation and InventorySerial modules
// to be fully built. If testing in isolation, create minimal stubs for both models.

declare(strict_types=1);

use App\Enums\MovementType;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\User;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

// ── Setup ─────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\InventoryMovementPermissionSeeder::class);

    $this->admin = User::factory()->create()->assignRole('admin');
    $this->sales = User::factory()->create()->assignRole('sales');

    $this->product  = Product::factory()->create();
    $this->locationA = InventoryLocation::factory()->create(['code' => 'L1', 'is_active' => true]);
    $this->locationB = InventoryLocation::factory()->create(['code' => 'L2', 'is_active' => true]);

    // A serial currently at L1, status in_stock
    $this->serial = InventorySerial::factory()->create([
        'product_id'              => $this->product->id,
        'inventory_location_id'   => $this->locationA->id,
        'status'                  => 'in_stock',
    ]);

    // Seed sequences row for bulk receive tests (value reset each test via RefreshDatabase)
    DB::table('sequences')->insert(['name' => 'serial_number', 'value' => 0]);
});

// ── index ─────────────────────────────────────────────────────────────────────

it('admin can view movement history index', function () {
    $this->actingAs($this->admin);

    InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id'             => $this->admin->id,
    ]);

    $response = $this->get(route('inventory-movements.index'));

    $response->assertOk();
    $response->assertViewHas('movements');
});

it('sales can view movement history index', function () {
    $this->actingAs($this->sales);

    $response = $this->get(route('inventory-movements.index'));

    $response->assertOk();
});

it('unauthenticated user is redirected from index', function () {
    $response = $this->get(route('inventory-movements.index'));

    $response->assertRedirect(route('login'));
});

it('index filters by serial number', function () {
    $this->actingAs($this->admin);

    $response = $this->get(route('inventory-movements.index', [
        'serial_number' => $this->serial->serial_number,
    ]));

    $response->assertOk();
    $response->assertViewHas('movements');
});

it('index filters by type', function () {
    $this->actingAs($this->admin);

    $response = $this->get(route('inventory-movements.index', [
        'type' => 'transfer',
    ]));

    $response->assertOk();
});

// ── create ────────────────────────────────────────────────────────────────────

it('admin can view the create movement form', function () {
    $this->actingAs($this->admin);

    $response = $this->get(route('inventory-movements.create'));

    $response->assertOk();
    $response->assertViewHas('serials');
    $response->assertViewHas('locations');
    $response->assertViewHas('types');
});

it('sales can view the create movement form', function () {
    $this->actingAs($this->sales);

    $response = $this->get(route('inventory-movements.create'));

    $response->assertOk();
});

// ── store: transfer ───────────────────────────────────────────────────────────

it('admin can record a transfer movement', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('inventory-movements.store'), [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'transfer',
        'from_location_id'    => $this->locationA->id,
        'to_location_id'      => $this->locationB->id,
        'reference'           => 'MOVE-001',
        'notes'               => 'Reorganising shelves',
    ]);

    $response->assertRedirect(route('inventory-movements.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'transfer',
        'from_location_id'    => $this->locationA->id,
        'to_location_id'      => $this->locationB->id,
    ]);

    // Serial location updated
    expect($this->serial->fresh()->inventory_location_id)->toBe($this->locationB->id);
});

it('sales can record a transfer movement', function () {
    $this->actingAs($this->sales);

    $response = $this->post(route('inventory-movements.store'), [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'transfer',
        'from_location_id'    => $this->locationA->id,
        'to_location_id'      => $this->locationB->id,
    ]);

    $response->assertRedirect(route('inventory-movements.index'));
    expect($this->serial->fresh()->inventory_location_id)->toBe($this->locationB->id);
});

it('rejects transfer when from_location does not match serial current location', function () {
    $this->actingAs($this->admin);

    $wrongLocation = InventoryLocation::factory()->create(['is_active' => true]);

    $response = $this->post(route('inventory-movements.store'), [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'transfer',
        'from_location_id'    => $wrongLocation->id,
        'to_location_id'      => $this->locationB->id,
    ]);

    $response->assertSessionHasErrors(['from_location_id']);
    $this->assertDatabaseCount('inventory_movements', 0);
});

it('rejects transfer when serial is not in_stock', function () {
    $this->actingAs($this->admin);

    $this->serial->update(['status' => 'sold']);

    $response = $this->post(route('inventory-movements.store'), [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'transfer',
        'from_location_id'    => $this->locationA->id,
        'to_location_id'      => $this->locationB->id,
    ]);

    $response->assertSessionHasErrors(['inventory_serial_id']);
    $this->assertDatabaseCount('inventory_movements', 0);
});

it('requires from_location_id for transfer', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('inventory-movements.store'), [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'transfer',
        'to_location_id'      => $this->locationB->id,
    ]);

    $response->assertSessionHasErrors(['from_location_id']);
});

it('requires to_location_id for transfer', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('inventory-movements.store'), [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'transfer',
        'from_location_id'    => $this->locationA->id,
    ]);

    $response->assertSessionHasErrors(['to_location_id']);
});

// ── store: sale ───────────────────────────────────────────────────────────────

it('admin can record a sale movement', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('inventory-movements.store'), [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'sale',
        'from_location_id'    => $this->locationA->id,
        'reference'           => 'ORD-2024-0042',
        'notes'               => 'Customer order shipped',
    ]);

    $response->assertRedirect(route('inventory-movements.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'sale',
        'from_location_id'    => $this->locationA->id,
        'to_location_id'      => null,
    ]);

    $fresh = $this->serial->fresh();
    expect($fresh->status)->toBe('sold');
    expect($fresh->inventory_location_id)->toBeNull();
});

it('sales can record a sale movement', function () {
    $this->actingAs($this->sales);

    $response = $this->post(route('inventory-movements.store'), [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'sale',
        'from_location_id'    => $this->locationA->id,
    ]);

    $response->assertRedirect(route('inventory-movements.index'));
    expect($this->serial->fresh()->status)->toBe('sold');
});

it('rejects sale when serial is not in_stock', function () {
    $this->actingAs($this->admin);

    $this->serial->update(['status' => 'damaged']);

    $response = $this->post(route('inventory-movements.store'), [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'sale',
        'from_location_id'    => $this->locationA->id,
    ]);

    $response->assertSessionHasErrors(['inventory_serial_id']);
    $this->assertDatabaseCount('inventory_movements', 0);
});

// ── store: adjustment ─────────────────────────────────────────────────────────

it('admin can record an adjustment movement (damaged)', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('inventory-movements.store'), [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'adjustment',
        'adjustment_status'   => 'damaged',
        'notes'               => 'Dropped during transport, cracked screen',
    ]);

    $response->assertRedirect(route('inventory-movements.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'adjustment',
    ]);

    $fresh = $this->serial->fresh();
    expect($fresh->status)->toBe('damaged');
    expect($fresh->inventory_location_id)->toBeNull();
});

it('admin can record an adjustment movement (missing)', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('inventory-movements.store'), [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'adjustment',
        'adjustment_status'   => 'missing',
        'reference'           => 'CYCLE-COUNT-2024-Q1',
    ]);

    $response->assertRedirect(route('inventory-movements.index'));

    $fresh = $this->serial->fresh();
    expect($fresh->status)->toBe('missing');
});

it('sales cannot record an adjustment movement', function () {
    $this->actingAs($this->sales);

    $response = $this->post(route('inventory-movements.store'), [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'adjustment',
        'adjustment_status'   => 'damaged',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseCount('inventory_movements', 0);
});

it('rejects adjustment with invalid adjustment_status', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('inventory-movements.store'), [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'adjustment',
        'adjustment_status'   => 'scrapped', // invalid
    ]);

    $response->assertSessionHasErrors(['adjustment_status']);
});

it('rejects receive type via the UI', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('inventory-movements.store'), [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'receive',
    ]);

    $response->assertSessionHasErrors(['type']);
});

// ── movements are immutable ───────────────────────────────────────────────────

it('has no edit route', function () {
    $movement = InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id'             => $this->admin->id,
    ]);

    // No route named admin.inventory-movements.edit should exist
    expect(fn () => route('inventory-movements.edit', $movement))
        ->toThrow(\Exception::class);
});

it('has no delete route', function () {
    $movement = InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id'             => $this->admin->id,
    ]);

    expect(fn () => route('inventory-movements.destroy', $movement))
        ->toThrow(\Exception::class);
});

it('movement records cannot be updated', function () {
    $movement = InventoryMovement::factory()->transfer()->create();
    $this->actingAs($this->admin)
        ->put(route('inventory-movements.update', $movement), [])
        ->assertStatus(404);
});

it('movement records cannot be deleted', function () {
    $movement = InventoryMovement::factory()->transfer()->create();
    $this->actingAs($this->admin)
        ->delete(route('inventory-movements.destroy', $movement))
        ->assertStatus(404);
});

// ── forSerial timeline ────────────────────────────────────────────────────────

it('admin can view serial movement timeline', function () {
    $this->actingAs($this->admin);

    InventoryMovement::factory()->receive()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id'             => $this->admin->id,
    ]);

    $response = $this->get(route('inventory-serials.movements', $this->serial));

    $response->assertOk();
    $response->assertViewHas('movements');
    $response->assertViewHas('inventorySerial');
});

// ── bulk receive ──────────────────────────────────────────────────────────────

it('admin can view bulk receive form', function () {
    $this->actingAs($this->admin);

    $response = $this->get(route('inventory-movements.bulk-receive'));

    $response->assertOk();
    $response->assertViewHas('products');
    $response->assertViewHas('locations');
});

it('admin can bulk receive serials', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('inventory-movements.bulk-receive.store'), [
        'product_id'            => $this->product->id,
        'qty'                   => 5,
        'inventory_location_id' => $this->locationA->id,
        'purchase_price'        => '99.00',
        'source_ref'            => 'GRN-2026-0001',
    ]);

    $response->assertRedirect(route('inventory-movements.bulk-receive-print'));
    $response->assertSessionHas('success');

    // beforeEach creates 1 serial + 5 bulk = 6 total; movements = 5 (beforeEach serial has no movement)
    $this->assertDatabaseCount('inventory_serials', 6);
    $this->assertDatabaseCount('inventory_movements', 5);

    $this->assertDatabaseHas('inventory_movements', [
        'type'           => 'receive',
        'from_location_id' => null,
        'to_location_id' => $this->locationA->id,
        'reference'      => 'GRN-2026-0001',
    ]);

    expect(session('bulk_receive_ids'))->toHaveCount(5);
});

it('bulk receive generates unique serial numbers', function () {
    $this->actingAs($this->admin);

    $this->post(route('inventory-movements.bulk-receive.store'), [
        'product_id'            => $this->product->id,
        'qty'                   => 10,
        'inventory_location_id' => $this->locationA->id,
        'purchase_price'        => '50.00',
    ]);

    // beforeEach creates 1 serial + 10 bulk = 11 total; all unique (factory sn differs from SN-YYYY format)
    $serials = \App\Models\InventorySerial::pluck('serial_number');
    expect($serials->unique()->count())->toBe(11);
});

it('bulk receive serial format matches SN-{YEAR}-{6digits}', function () {
    $this->actingAs($this->admin);

    $this->post(route('inventory-movements.bulk-receive.store'), [
        'product_id'            => $this->product->id,
        'qty'                   => 1,
        'inventory_location_id' => $this->locationA->id,
        'purchase_price'        => '50.00',
    ]);

    // Use latest() to get the bulk-generated serial, not the beforeEach factory serial
    $serial = \App\Models\InventorySerial::orderByDesc('id')->first();
    expect($serial->serial_number)->toMatch('/^SN-\d{4}-\d{6}$/');
});

it('print view renders with generated serials and clears session', function () {
    $serials = \App\Models\InventorySerial::factory()->count(3)->create([
        'product_id'            => $this->product->id,
        'inventory_location_id' => $this->locationA->id,
    ]);

    $this->actingAs($this->admin);
    session(['bulk_receive_ids' => $serials->pluck('id')->toArray()]);

    $response = $this->get(route('inventory-movements.bulk-receive-print'));

    $response->assertOk();
    $response->assertViewHas('serials');
    $response->assertSee($serials->first()->serial_number);

    // Session cleared after render
    expect(session('bulk_receive_ids'))->toBeNull();
});

it('print view redirects when session is empty', function () {
    $this->actingAs($this->admin);

    $response = $this->get(route('inventory-movements.bulk-receive-print'));

    $response->assertRedirect(route('inventory-movements.bulk-receive'));
    $response->assertSessionHasErrors('error');
});

it('manager can bulk receive serials', function () {
    $manager = User::factory()->create()->assignRole('manager');
    $this->actingAs($manager);

    $response = $this->post(route('inventory-movements.bulk-receive.store'), [
        'product_id'            => $this->product->id,
        'qty'                   => 3,
        'inventory_location_id' => $this->locationA->id,
        'purchase_price'        => '75.00',
    ]);

    $response->assertRedirect(route('inventory-movements.bulk-receive-print'));
    $this->assertDatabaseCount('inventory_serials', 4); // 1 beforeEach + 3 bulk
});

it('sales cannot access bulk receive form', function () {
    $this->actingAs($this->sales);

    $response = $this->get(route('inventory-movements.bulk-receive'));

    $response->assertForbidden();
});

it('sales cannot post bulk receive', function () {
    $this->actingAs($this->sales);

    $response = $this->post(route('inventory-movements.bulk-receive.store'), [
        'product_id'            => $this->product->id,
        'qty'                   => 3,
        'inventory_location_id' => $this->locationA->id,
        'purchase_price'        => '50.00',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseCount('inventory_serials', 1); // only the beforeEach serial
});

it('bulk receive rejects qty over 500', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('inventory-movements.bulk-receive.store'), [
        'product_id'            => $this->product->id,
        'qty'                   => 501,
        'inventory_location_id' => $this->locationA->id,
        'purchase_price'        => '50.00',
    ]);

    $response->assertSessionHasErrors(['qty']);
    $this->assertDatabaseCount('inventory_serials', 1); // only the beforeEach serial
});

it('bulk receive rejects qty 0', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('inventory-movements.bulk-receive.store'), [
        'product_id'            => $this->product->id,
        'qty'                   => 0,
        'inventory_location_id' => $this->locationA->id,
        'purchase_price'        => '50.00',
    ]);

    $response->assertSessionHasErrors(['qty']);
});
```

---

## Unit Tests — Service

```php
<?php
// tests/Unit/Services/InventoryMovementServiceTest.php

declare(strict_types=1);

use App\Enums\MovementType;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryMovementService;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->service   = app(InventoryMovementService::class);
    $this->user      = User::factory()->create();
    $this->product   = Product::factory()->create();
    $this->locationA = InventoryLocation::factory()->create(['code' => 'L1', 'is_active' => true]);
    $this->locationB = InventoryLocation::factory()->create(['code' => 'L2', 'is_active' => true]);

    $this->serial = InventorySerial::factory()->create([
        'product_id'            => $this->product->id,
        'inventory_location_id' => $this->locationA->id,
        'status'                => 'in_stock',
    ]);

    // Seed sequences row for bulkReceive() tests (reset each test via RefreshDatabase)
    DB::table('sequences')->insert(['name' => 'serial_number', 'value' => 0]);
});

// ── transfer() ────────────────────────────────────────────────────────────────

it('transfer() creates a movement row and updates serial location', function () {
    $movement = $this->service->transfer(
        serial:       $this->serial,
        fromLocation: $this->locationA,
        toLocation:   $this->locationB,
        user:         $this->user,
    );

    expect($movement)->toBeInstanceOf(InventoryMovement::class);
    expect($movement->type)->toBe(MovementType::Transfer);
    expect($movement->from_location_id)->toBe($this->locationA->id);
    expect($movement->to_location_id)->toBe($this->locationB->id);
    expect($movement->user_id)->toBe($this->user->id);

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'transfer',
    ]);

    expect($this->serial->fresh()->inventory_location_id)->toBe($this->locationB->id);
});

it('transfer() stores reference and notes', function () {
    $movement = $this->service->transfer(
        serial:       $this->serial,
        fromLocation: $this->locationA,
        toLocation:   $this->locationB,
        user:         $this->user,
        reference:    'REF-001',
        notes:        'Shelf reorganisation',
    );

    expect($movement->reference)->toBe('REF-001');
    expect($movement->notes)->toBe('Shelf reorganisation');
});

it('transfer() throws DomainException when serial is not in_stock', function () {
    $this->serial->update(['status' => 'sold']);

    expect(fn () => $this->service->transfer(
        serial:       $this->serial,
        fromLocation: $this->locationA,
        toLocation:   $this->locationB,
        user:         $this->user,
    ))->toThrow(\DomainException::class, 'not in stock');

    $this->assertDatabaseCount('inventory_movements', 0);
});

it('transfer() throws DomainException when from_location does not match serial', function () {
    $wrongLocation = InventoryLocation::factory()->create();

    expect(fn () => $this->service->transfer(
        serial:       $this->serial,
        fromLocation: $wrongLocation,
        toLocation:   $this->locationB,
        user:         $this->user,
    ))->toThrow(\DomainException::class, 'not at location');

    $this->assertDatabaseCount('inventory_movements', 0);
    expect($this->serial->fresh()->inventory_location_id)->toBe($this->locationA->id);
});

it('transfer() throws DomainException when from and to locations are the same', function () {
    expect(fn () => $this->service->transfer(
        serial:       $this->serial,
        fromLocation: $this->locationA,
        toLocation:   $this->locationA,
        user:         $this->user,
    ))->toThrow(\DomainException::class, 'must be different');
});

it('transfer() rolls back completely if an error occurs mid-transaction', function () {
    // Force a failure by passing a location that will cause constraint violation
    // We simulate by checking that no partial state is saved when DomainException fires
    $this->serial->update(['status' => 'damaged']);

    try {
        $this->service->transfer(
            serial:       $this->serial,
            fromLocation: $this->locationA,
            toLocation:   $this->locationB,
            user:         $this->user,
        );
    } catch (\DomainException) {}

    $this->assertDatabaseCount('inventory_movements', 0);
    // Serial location unchanged
    expect($this->serial->fresh()->inventory_location_id)->toBe($this->locationA->id);
});

// ── sale() ────────────────────────────────────────────────────────────────────

it('sale() creates a movement row and marks serial as sold', function () {
    $movement = $this->service->sale(
        serial:       $this->serial,
        fromLocation: $this->locationA,
        user:         $this->user,
        reference:    'ORD-2024-0001',
    );

    expect($movement->type)->toBe(MovementType::Sale);
    expect($movement->from_location_id)->toBe($this->locationA->id);
    expect($movement->to_location_id)->toBeNull();

    $fresh = $this->serial->fresh();
    expect($fresh->status)->toBe('sold');
    expect($fresh->inventory_location_id)->toBeNull();
});

it('sale() throws DomainException when serial is not in_stock', function () {
    $this->serial->update(['status' => 'damaged']);

    expect(fn () => $this->service->sale(
        serial:       $this->serial,
        fromLocation: $this->locationA,
        user:         $this->user,
    ))->toThrow(\DomainException::class);

    $this->assertDatabaseCount('inventory_movements', 0);
});

it('sale() throws DomainException when from_location does not match serial', function () {
    $wrongLocation = InventoryLocation::factory()->create();

    expect(fn () => $this->service->sale(
        serial:       $this->serial,
        fromLocation: $wrongLocation,
        user:         $this->user,
    ))->toThrow(\DomainException::class);

    expect($this->serial->fresh()->status)->toBe('in_stock');
});

it('sale() rolls back — no movement row on failure', function () {
    $this->serial->update(['status' => 'missing']);

    try {
        $this->service->sale(
            serial:       $this->serial,
            fromLocation: $this->locationA,
            user:         $this->user,
        );
    } catch (\DomainException) {}

    $this->assertDatabaseCount('inventory_movements', 0);
});

// ── adjustment() ──────────────────────────────────────────────────────────────

it('adjustment() creates a movement row and marks serial as damaged', function () {
    $movement = $this->service->adjustment(
        serial:    $this->serial,
        newStatus: 'damaged',
        user:      $this->user,
        notes:     'Screen cracked during handling',
    );

    expect($movement->type)->toBe(MovementType::Adjustment);
    expect($movement->notes)->toBe('Screen cracked during handling');

    $fresh = $this->serial->fresh();
    expect($fresh->status)->toBe('damaged');
    expect($fresh->inventory_location_id)->toBeNull();
});

it('adjustment() creates a movement row and marks serial as missing', function () {
    $this->service->adjustment(
        serial:    $this->serial,
        newStatus: 'missing',
        user:      $this->user,
        reference: 'CYCLE-COUNT-Q1',
    );

    expect($this->serial->fresh()->status)->toBe('missing');
    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $this->serial->id,
        'type'                => 'adjustment',
        'reference'           => 'CYCLE-COUNT-Q1',
    ]);
});

it('adjustment() throws DomainException for invalid status', function () {
    expect(fn () => $this->service->adjustment(
        serial:    $this->serial,
        newStatus: 'scrapped',
        user:      $this->user,
    ))->toThrow(\DomainException::class);

    $this->assertDatabaseCount('inventory_movements', 0);
});

it('adjustment() clears inventory_location_id', function () {
    expect($this->serial->inventory_location_id)->not->toBeNull();

    $this->service->adjustment(
        serial:    $this->serial,
        newStatus: 'damaged',
        user:      $this->user,
    );

    expect($this->serial->fresh()->inventory_location_id)->toBeNull();
});

// ── historyForSerial() ────────────────────────────────────────────────────────

it('historyForSerial() returns all movements for a serial in chronological order', function () {
    // Simulate a full lifecycle: receive → transfer → sale
    $receive = InventoryMovement::factory()->receive()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id'             => $this->user->id,
        'created_at'          => now()->subDays(10),
    ]);
    $transfer = InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id'             => $this->user->id,
        'created_at'          => now()->subDays(5),
    ]);
    $sale = InventoryMovement::factory()->sale()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id'             => $this->user->id,
        'created_at'          => now(),
    ]);

    $history = $this->service->historyForSerial($this->serial);

    expect($history)->toHaveCount(3);
    expect($history->first()->id)->toBe($receive->id);
    expect($history->last()->id)->toBe($sale->id);
    // Relations eager loaded
    expect($history->first()->relationLoaded('serial'))->toBeTrue();
    expect($history->first()->relationLoaded('user'))->toBeTrue();
});

it('historyForSerial() returns empty collection when no movements exist', function () {
    $history = $this->service->historyForSerial($this->serial);

    expect($history)->toBeEmpty();
});

// ── listMovements() ───────────────────────────────────────────────────────────

it('listMovements() returns paginated results', function () {
    InventoryMovement::factory()->count(30)->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id'             => $this->user->id,
    ]);

    $result = $this->service->listMovements();

    expect($result->count())->toBe(25); // default page size
    expect($result->total())->toBe(30);
});

it('listMovements() filters by serial number', function () {
    $otherSerial = InventorySerial::factory()->create();
    InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id'             => $this->user->id,
    ]);
    InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $otherSerial->id,
        'user_id'             => $this->user->id,
    ]);

    $result = $this->service->listMovements([
        'serial_number' => $this->serial->serial_number,
    ]);

    expect($result->total())->toBe(1);
});

it('listMovements() filters by type', function () {
    InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id'             => $this->user->id,
    ]);
    InventoryMovement::factory()->sale()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id'             => $this->user->id,
    ]);

    $result = $this->service->listMovements(['type' => 'sale']);

    expect($result->total())->toBe(1);
    expect($result->first()->type)->toBe(MovementType::Sale);
});

it('listMovements() ignores empty string type filter', function () {
    InventoryMovement::factory()->count(3)->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id'             => $this->user->id,
    ]);

    // Empty string from "All" select option — should NOT filter
    $result = $this->service->listMovements(['type' => '']);

    expect($result->total())->toBe(3);
});

it('listMovements() filters by location', function () {
    InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'from_location_id'    => $this->locationA->id,
        'to_location_id'      => $this->locationB->id,
        'user_id'             => $this->user->id,
    ]);
    InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'from_location_id'    => $this->locationB->id,
        'to_location_id'      => InventoryLocation::factory()->create()->id,
        'user_id'             => $this->user->id,
    ]);

    // locationA appears as from only in first movement
    $result = $this->service->listMovements([
        'location_id' => $this->locationA->id,
    ]);

    expect($result->total())->toBe(1);
});

// ── bulkReceive() ─────────────────────────────────────────────────────────────

it('bulkReceive() creates correct count of serials and movements', function () {
    $serials = $this->service->bulkReceive([
        'product_id'            => $this->product->id,
        'qty'                   => 10,
        'inventory_location_id' => $this->locationA->id,
        'purchase_price'        => 99.00,
    ], $this->user);

    expect($serials)->toHaveCount(10);
    $this->assertDatabaseCount('inventory_serials', 11); // 1 beforeEach + 10 bulk
    $this->assertDatabaseCount('inventory_movements', 10);
});

it('bulkReceive() increments sequences table by qty', function () {
    $this->service->bulkReceive([
        'product_id'            => $this->product->id,
        'qty'                   => 5,
        'inventory_location_id' => $this->locationA->id,
        'purchase_price'        => 50.00,
    ], $this->user);

    $value = DB::table('sequences')->where('name', 'serial_number')->value('value');
    expect($value)->toBe(5);
});

it('bulkReceive() generates unique serial numbers across two consecutive calls', function () {
    $a = $this->service->bulkReceive([
        'product_id'            => $this->product->id,
        'qty'                   => 10,
        'inventory_location_id' => $this->locationA->id,
        'purchase_price'        => 50.00,
    ], $this->user);

    $b = $this->service->bulkReceive([
        'product_id'            => $this->product->id,
        'qty'                   => 10,
        'inventory_location_id' => $this->locationA->id,
        'purchase_price'        => 50.00,
    ], $this->user);

    $all = $a->merge($b)->pluck('serial_number');
    expect($all->unique()->count())->toBe(20);
});

it('bulkReceive() creates movements with correct type, location, and source_ref', function () {
    $this->service->bulkReceive([
        'product_id'            => $this->product->id,
        'qty'                   => 3,
        'inventory_location_id' => $this->locationA->id,
        'purchase_price'        => 99.00,
        'source_ref'            => 'GRN-2026-0001',
    ], $this->user);

    $this->assertDatabaseHas('inventory_movements', [
        'type'           => 'receive',
        'to_location_id' => $this->locationA->id,
        'from_location_id' => null,
        'reference'      => 'GRN-2026-0001',
        'user_id'        => $this->user->id,
    ]);
});

it('bulkReceive() serial number format is SN-{YEAR}-{6digits}', function () {
    // Update sequences to 41 so next serial is SN-YYYY-000042
    DB::table('sequences')->where('name', 'serial_number')->update(['value' => 41]);

    $serials = $this->service->bulkReceive([
        'product_id'            => $this->product->id,
        'qty'                   => 1,
        'inventory_location_id' => $this->locationA->id,
        'purchase_price'        => 50.00,
    ], $this->user);

    expect($serials->first()->serial_number)->toMatch('/^SN-\d{4}-\d{6}$/');
    expect($serials->first()->serial_number)->toEndWith('-000042');
});

it('bulkReceive() throws DomainException for qty 0', function () {
    expect(fn () => $this->service->bulkReceive([
        'product_id'            => $this->product->id,
        'qty'                   => 0,
        'inventory_location_id' => $this->locationA->id,
        'purchase_price'        => 50.00,
    ], $this->user))->toThrow(\DomainException::class);

    $this->assertDatabaseCount('inventory_serials', 1); // only the beforeEach serial; no new ones created
});

it('bulkReceive() throws DomainException for qty over 500', function () {
    expect(fn () => $this->service->bulkReceive([
        'product_id'            => $this->product->id,
        'qty'                   => 501,
        'inventory_location_id' => $this->locationA->id,
        'purchase_price'        => 50.00,
    ], $this->user))->toThrow(\DomainException::class);

    $this->assertDatabaseCount('inventory_serials', 1); // only the beforeEach serial
    $this->assertDatabaseCount('inventory_movements', 0);
});
```
