<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\InventoryMovementPermissionSeeder;
use Database\Seeders\PurchaseOrderPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PurchaseOrderPermissionSeeder::class);
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

    // sequences table seeded by migration (create_sequences_table) via RefreshDatabase
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
    // authorize() returns false for type=receive → 403, not a validation error
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $this->serial->id,
            'type' => 'receive',
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('inventory_movements', 0);
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

// ── bulk receive ──────────────────────────────────────────────────────────────

it('admin can view bulk receive form', function () {
    $this->actingAs($this->admin)
        ->get(route('inventory-movements.bulk-receive'))
        ->assertOk()
        ->assertViewHas('products')
        ->assertViewHas('locations');
});

it('admin can bulk receive serials', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.bulk-receive.store'), [
            'product_id' => $this->product->id,
            'qty' => 5,
            'inventory_location_id' => $this->locationA->id,
            'purchase_price' => '99.00',
            'source_ref' => 'GRN-2026-0001',
        ])
        ->assertRedirect(route('inventory-movements.bulk-receive-print'))
        ->assertSessionHas('success');

    // beforeEach creates 1 serial; 5 bulk added = 6 total
    $this->assertDatabaseCount('inventory_serials', 6);
    $this->assertDatabaseCount('inventory_movements', 5);

    $this->assertDatabaseHas('inventory_movements', [
        'type' => 'receive',
        'from_location_id' => null,
        'to_location_id' => $this->locationA->id,
        'reference' => 'GRN-2026-0001',
    ]);

    expect(session('bulk_receive_ids'))->toHaveCount(5);
});

it('bulk receive generates unique serial numbers', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.bulk-receive.store'), [
            'product_id' => $this->product->id,
            'qty' => 10,
            'inventory_location_id' => $this->locationA->id,
            'purchase_price' => '50.00',
        ]);

    $serials = InventorySerial::pluck('serial_number');
    expect($serials->unique()->count())->toBe(11); // 1 beforeEach + 10 bulk
});

it('bulk receive serial format matches SN-{YEAR}-{6digits}', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.bulk-receive.store'), [
            'product_id' => $this->product->id,
            'qty' => 1,
            'inventory_location_id' => $this->locationA->id,
            'purchase_price' => '50.00',
        ]);

    $serial = InventorySerial::orderByDesc('id')->first();
    expect($serial->serial_number)->toMatch('/^SN-\d{4}-\d{6}$/');
});

it('print view renders with generated serials and clears session', function () {
    $serials = InventorySerial::factory()->count(3)->create([
        'product_id' => $this->product->id,
        'inventory_location_id' => $this->locationA->id,
    ]);

    $this->actingAs($this->admin);
    session(['bulk_receive_ids' => $serials->pluck('id')->toArray()]);

    $response = $this->get(route('inventory-movements.bulk-receive-print'));

    $response->assertOk()
        ->assertViewHas('serials')
        ->assertSee($serials->first()->serial_number);

    expect(session('bulk_receive_ids'))->toBeNull();
});

it('print view redirects when session is empty', function () {
    $this->actingAs($this->admin)
        ->get(route('inventory-movements.bulk-receive-print'))
        ->assertRedirect(route('inventory-movements.bulk-receive'))
        ->assertSessionHasErrors('error');
});

it('manager can bulk receive serials', function () {
    $manager = User::factory()->create()->assignRole('manager');

    $this->actingAs($manager)
        ->post(route('inventory-movements.bulk-receive.store'), [
            'product_id' => $this->product->id,
            'qty' => 3,
            'inventory_location_id' => $this->locationA->id,
            'purchase_price' => '75.00',
        ])
        ->assertRedirect(route('inventory-movements.bulk-receive-print'));

    $this->assertDatabaseCount('inventory_serials', 4); // 1 beforeEach + 3 bulk
});

it('sales cannot access bulk receive form', function () {
    $this->actingAs($this->sales)
        ->get(route('inventory-movements.bulk-receive'))
        ->assertForbidden();
});

it('sales cannot post bulk receive', function () {
    $this->actingAs($this->sales)
        ->post(route('inventory-movements.bulk-receive.store'), [
            'product_id' => $this->product->id,
            'qty' => 3,
            'inventory_location_id' => $this->locationA->id,
            'purchase_price' => '50.00',
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('inventory_serials', 1); // only the beforeEach serial
});

it('bulk receive rejects qty over 500', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.bulk-receive.store'), [
            'product_id' => $this->product->id,
            'qty' => 501,
            'inventory_location_id' => $this->locationA->id,
            'purchase_price' => '50.00',
        ])
        ->assertSessionHasErrors(['qty']);

    $this->assertDatabaseCount('inventory_serials', 1);
});

it('bulk receive rejects qty 0', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.bulk-receive.store'), [
            'product_id' => $this->product->id,
            'qty' => 0,
            'inventory_location_id' => $this->locationA->id,
            'purchase_price' => '50.00',
        ])
        ->assertSessionHasErrors(['qty']);
});

// ── BUG-002: bulk-receive authorization via direct POST ──────────────────────

it('sales user cannot POST to inventory-movements.bulk-receive without bulk-receive permission (403)', function () {
    $user = User::factory()->create();
    $user->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::INVENTORY_MOVEMENTS_VIEW,
    ]);

    $this->actingAs($user)
        ->post(route('inventory-movements.bulk-receive'), [
            'product_id' => $this->product->id,
            'qty' => 3,
            'inventory_location_id' => $this->locationA->id,
            'purchase_price' => '100.00',
        ])
        ->assertForbidden();
});

it('inventory manager can POST to inventory-movements.bulk-receive with bulk-receive permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo([
        Permission::PURCHASE_ORDERS_VIEW_ANY,
        Permission::PURCHASE_ORDERS_VIEW,
        Permission::GOODS_RECEIPTS_VIEW_ANY,
        Permission::GOODS_RECEIPTS_VIEW,
        Permission::INVENTORY_MOVEMENTS_VIEW,
        Permission::INVENTORY_MOVEMENTS_BULK_RECEIVE,
    ]);

    $this->actingAs($user)
        ->post(route('inventory-movements.bulk-receive'), [
            'product_id' => $this->product->id,
            'qty' => 2,
            'inventory_location_id' => $this->locationA->id,
            'purchase_price' => '50.00',
        ])
        ->assertRedirect(route('inventory-movements.bulk-receive-print'));
});

it('guest is redirected to login on inventory-movements.bulk-receive POST', function () {
    $this->post(route('inventory-movements.bulk-receive'), [
        'product_id' => $this->product->id,
        'qty' => 1,
        'inventory_location_id' => $this->locationA->id,
        'purchase_price' => '100.00',
    ])
        ->assertRedirect(route('login'));
});
