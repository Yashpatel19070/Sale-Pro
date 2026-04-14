<?php

declare(strict_types=1);

use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\InventorySerialPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(InventorySerialPermissionSeeder::class);
});

// ── Helpers ────────────────────────────────────────────────────────────────────

function serialAdminUser(): User
{
    return User::factory()->create()->assignRole('admin');
}

function serialSalesUser(): User
{
    return User::factory()->create()->assignRole('sales');
}

function makeSerial(array $attrs = []): InventorySerial
{
    return InventorySerial::factory()->create($attrs);
}

// ── Authorization ──────────────────────────────────────────────────────────────

it('denies unauthenticated access to serials index', function () {
    $this->get(route('inventory-serials.index'))->assertRedirect(route('login'));
});

it('allows admin to access serials index', function () {
    $this->actingAs(serialAdminUser())
        ->get(route('inventory-serials.index'))
        ->assertOk();
});

it('allows sales to access serials index', function () {
    $this->actingAs(serialSalesUser())
        ->get(route('inventory-serials.index'))
        ->assertOk();
});

// ── Index / Filtering ──────────────────────────────────────────────────────────

it('lists serials paginated', function () {
    InventorySerial::factory()->count(3)->create();

    $this->actingAs(serialAdminUser())
        ->get(route('inventory-serials.index'))
        ->assertOk()
        ->assertViewIs('inventory.serials.index')
        ->assertViewHas('serials');
});

it('filters serials by serial number search', function () {
    InventorySerial::factory()->create(['serial_number' => 'SN-ALPHA-001']);
    InventorySerial::factory()->create(['serial_number' => 'SN-BETA-002']);

    $this->actingAs(serialAdminUser())
        ->get(route('inventory-serials.index', ['search' => 'ALPHA']))
        ->assertSee('SN-ALPHA-001')
        ->assertDontSee('SN-BETA-002');
});

it('filters serials by status', function () {
    InventorySerial::factory()->inStock()->create(['serial_number' => 'SN-STOCK-001']);
    InventorySerial::factory()->sold()->create(['serial_number' => 'SN-SOLD-002']);

    $this->actingAs(serialAdminUser())
        ->get(route('inventory-serials.index', ['status' => 'in_stock']))
        ->assertSee('SN-STOCK-001')
        ->assertDontSee('SN-SOLD-002');
});

it('filters serials by product', function () {
    $product = Product::factory()->create();
    $other = Product::factory()->create();
    InventorySerial::factory()->forProduct($product)->create(['serial_number' => 'SN-P1-001']);
    InventorySerial::factory()->forProduct($other)->create(['serial_number' => 'SN-P2-001']);

    $this->actingAs(serialAdminUser())
        ->get(route('inventory-serials.index', ['product_id' => $product->id]))
        ->assertSee('SN-P1-001')
        ->assertDontSee('SN-P2-001');
});

it('filters serials by location', function () {
    $location = InventoryLocation::factory()->create();
    $other = InventoryLocation::factory()->create();
    InventorySerial::factory()->atLocation($location)->create(['serial_number' => 'SN-LOC-001']);
    InventorySerial::factory()->atLocation($other)->create(['serial_number' => 'SN-LOC-002']);

    $this->actingAs(serialAdminUser())
        ->get(route('inventory-serials.index', ['location_id' => $location->id]))
        ->assertSee('SN-LOC-001')
        ->assertDontSee('SN-LOC-002');
});

// ── Show ───────────────────────────────────────────────────────────────────────

it('admin can view serial detail', function () {
    $serial = makeSerial();

    $this->actingAs(serialAdminUser())
        ->get(route('inventory-serials.show', $serial))
        ->assertOk()
        ->assertViewIs('inventory.serials.show');
});

it('sales can view serial detail', function () {
    $this->actingAs(serialSalesUser())
        ->get(route('inventory-serials.show', makeSerial()))
        ->assertOk();
});

// ── Create / Receive ───────────────────────────────────────────────────────────

it('admin can view receive form', function () {
    $this->actingAs(serialAdminUser())
        ->get(route('inventory-serials.create'))
        ->assertOk()
        ->assertViewIs('inventory.serials.create');
});

it('sales can view receive form', function () {
    $this->actingAs(serialSalesUser())
        ->get(route('inventory-serials.create'))
        ->assertOk();
});

it('admin can receive a new serial', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $this->actingAs(serialAdminUser())
        ->post(route('inventory-serials.store'), [
            'product_id' => $product->id,
            'inventory_location_id' => $location->id,
            'serial_number' => 'SN-NEW-001',
            'purchase_price' => 49.99,
            'received_at' => now()->format('Y-m-d'),
            'supplier_name' => 'Acme Corp',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('inventory_serials', [
        'serial_number' => 'SN-NEW-001',
        'status' => 'in_stock',
    ]);
});

it('receiving a serial creates an inventory movement', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $this->actingAs(serialAdminUser())->post(route('inventory-serials.store'), [
        'product_id' => $product->id,
        'inventory_location_id' => $location->id,
        'serial_number' => 'SN-MVT-001',
        'purchase_price' => 10.00,
        'received_at' => now()->format('Y-m-d'),
    ]);

    $serial = InventorySerial::where('serial_number', 'SN-MVT-001')->firstOrFail();

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $serial->id,
        'type' => 'receive',
        'to_location_id' => $location->id,
    ]);
});

it('validates required fields on receive', function () {
    $this->actingAs(serialAdminUser())
        ->post(route('inventory-serials.store'), [])
        ->assertSessionHasErrors(['product_id', 'inventory_location_id', 'serial_number', 'purchase_price', 'received_at']);
});

it('validates serial_number uniqueness', function () {
    makeSerial(['serial_number' => 'SN-DUP-001']);
    $product = Product::factory()->create();
    $loc = InventoryLocation::factory()->create();

    $this->actingAs(serialAdminUser())
        ->post(route('inventory-serials.store'), [
            'product_id' => $product->id,
            'inventory_location_id' => $loc->id,
            'serial_number' => 'SN-DUP-001',
            'purchase_price' => 10,
            'received_at' => now()->format('Y-m-d'),
        ])
        ->assertSessionHasErrors(['serial_number']);
});

it('validates received_at cannot be in the future', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $this->actingAs(serialAdminUser())
        ->post(route('inventory-serials.store'), [
            'product_id' => $product->id,
            'inventory_location_id' => $location->id,
            'serial_number' => 'SN-FUTURE-001',
            'purchase_price' => 10,
            'received_at' => now()->addDay()->format('Y-m-d'),
        ])
        ->assertSessionHasErrors(['received_at']);
});

it('uppercases serial_number on store', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $this->actingAs(serialAdminUser())->post(route('inventory-serials.store'), [
        'product_id' => $product->id,
        'inventory_location_id' => $location->id,
        'serial_number' => 'sn-lower-001',
        'purchase_price' => 10,
        'received_at' => now()->format('Y-m-d'),
    ]);

    $this->assertDatabaseHas('inventory_serials', ['serial_number' => 'SN-LOWER-001']);
});

// ── Edit / Update ──────────────────────────────────────────────────────────────

it('admin can view edit form', function () {
    $this->actingAs(serialAdminUser())
        ->get(route('inventory-serials.edit', makeSerial()))
        ->assertOk()
        ->assertViewIs('inventory.serials.edit');
});

it('sales is denied access to edit form', function () {
    $this->actingAs(serialSalesUser())
        ->get(route('inventory-serials.edit', makeSerial()))
        ->assertForbidden();
});

it('sales is denied update', function () {
    $serial = makeSerial(['notes' => 'original']);

    $this->actingAs(serialSalesUser())
        ->put(route('inventory-serials.update', $serial), ['notes' => 'hacked'])
        ->assertForbidden();

    $this->assertDatabaseHas('inventory_serials', ['id' => $serial->id, 'notes' => 'original']);
});

it('admin can update notes and supplier_name', function () {
    $serial = makeSerial(['notes' => 'original', 'supplier_name' => 'OldCo']);

    $this->actingAs(serialAdminUser())
        ->put(route('inventory-serials.update', $serial), [
            'notes' => 'Updated notes',
            'supplier_name' => 'NewCo',
        ])
        ->assertRedirect(route('inventory-serials.show', $serial));

    $this->assertDatabaseHas('inventory_serials', [
        'id' => $serial->id,
        'notes' => 'Updated notes',
        'supplier_name' => 'NewCo',
    ]);
});

it('update does not change serial_number even if submitted', function () {
    $serial = makeSerial(['serial_number' => 'ORIGINAL-SN']);

    $this->actingAs(serialAdminUser())->put(route('inventory-serials.update', $serial), [
        'serial_number' => 'HACKED-SN',
        'notes' => 'some notes',
    ]);

    $this->assertDatabaseHas('inventory_serials', [
        'id' => $serial->id,
        'serial_number' => 'ORIGINAL-SN',
    ]);
});

it('update does not change purchase_price even if submitted', function () {
    $serial = makeSerial(['purchase_price' => '99.99']);

    $this->actingAs(serialAdminUser())->put(route('inventory-serials.update', $serial), [
        'purchase_price' => '1.00',
        'notes' => 'some notes',
    ]);

    $this->assertDatabaseHas('inventory_serials', [
        'id' => $serial->id,
        'purchase_price' => '99.99',
    ]);
});
