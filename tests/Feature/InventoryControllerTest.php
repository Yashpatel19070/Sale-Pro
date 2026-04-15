<?php

declare(strict_types=1);

use App\Enums\SerialStatus;
use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\InventoryPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(InventoryPermissionSeeder::class);
});

function makeInStockSerial(array $attributes = []): InventorySerial
{
    return InventorySerial::factory()->create(array_merge([
        'status' => SerialStatus::InStock,
    ], $attributes));
}

// ── Authorization: index ───────────────────────────────────────────────────────

it('redirects unauthenticated users from stock dashboard', function () {
    $this->get(route('inventory.index'))
        ->assertRedirect(route('login'));
});

it('denies access to stock dashboard for users without inventory permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('inventory.index'))
        ->assertForbidden();
});

it('allows admin to access stock dashboard', function () {
    $user = User::factory()->create()->assignRole('admin');

    $this->actingAs($user)
        ->get(route('inventory.index'))
        ->assertOk();
});

it('allows manager to access stock dashboard', function () {
    $user = User::factory()->create()->assignRole('manager');

    $this->actingAs($user)
        ->get(route('inventory.index'))
        ->assertOk();
});

it('allows sales to access stock dashboard', function () {
    $user = User::factory()->create()->assignRole('sales');

    $this->actingAs($user)
        ->get(route('inventory.index'))
        ->assertOk();
});

// ── index / overview ───────────────────────────────────────────────────────────

it('shows stock overview with correct product data', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['sku' => 'TEST-001', 'name' => 'Test Widget']);
    $location = InventoryLocation::factory()->create(['code' => 'L1']);

    makeInStockSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id]);
    makeInStockSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id]);

    $this->actingAs($admin)
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertViewIs('inventory.index')
        ->assertViewHas('stockOverview')
        ->assertSee('TEST-001')
        ->assertSee('Test Widget');
});

it('does not count sold serials in overview', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['sku' => 'SOLD-SKU']);
    $location = InventoryLocation::factory()->create();

    makeInStockSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id]);
    InventorySerial::factory()->create(['product_id' => $product->id, 'status' => SerialStatus::Sold]);
    InventorySerial::factory()->create(['product_id' => $product->id, 'status' => SerialStatus::Sold]);

    $stockOverview = $this->actingAs($admin)
        ->get(route('inventory.index'))
        ->viewData('stockOverview');

    expect($stockOverview->get($product->id)->count())->toBe(1);
});

it('shows empty state when no stock on hand', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertSee('No stock on hand');
});

// ── Authorization: showBySku ───────────────────────────────────────────────────

it('redirects unauthenticated users from stock by sku', function () {
    $product = Product::factory()->create();

    $this->get(route('inventory.by-sku', $product))
        ->assertRedirect(route('login'));
});

it('denies stock by sku for users without inventory permission', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user)
        ->get(route('inventory.by-sku', $product))
        ->assertForbidden();
});

it('allows sales role to view stock by sku', function () {
    $sales = User::factory()->create()->assignRole('sales');
    $product = Product::factory()->create();

    $this->actingAs($sales)
        ->get(route('inventory.by-sku', $product))
        ->assertOk();
});

// ── showBySku ─────────────────────────────────────────────────────────────────

it('shows stock by sku with serials at multiple locations', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['sku' => 'MULTI-SKU', 'name' => 'Multi Widget']);
    $locationA = InventoryLocation::factory()->create(['code' => 'L1', 'name' => 'Shelf L1']);
    $locationB = InventoryLocation::factory()->create(['code' => 'L2', 'name' => 'Shelf L2']);

    makeInStockSerial(['product_id' => $product->id, 'inventory_location_id' => $locationA->id, 'serial_number' => 'SN-001']);
    makeInStockSerial(['product_id' => $product->id, 'inventory_location_id' => $locationB->id, 'serial_number' => 'SN-002']);

    $this->actingAs($admin)
        ->get(route('inventory.by-sku', $product))
        ->assertOk()
        ->assertViewIs('inventory.show-by-sku')
        ->assertViewHas('product')
        ->assertViewHas('stockByLocation')
        ->assertSee('L1')
        ->assertSee('L2');
});

it('excludes non-in_stock serials from stock by sku view', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    makeInStockSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'GOOD-SN']);
    InventorySerial::factory()->create([
        'product_id' => $product->id,
        'inventory_location_id' => $location->id,
        'serial_number' => 'SOLD-SN',
        'status' => SerialStatus::Sold,
    ]);

    $stockByLocation = $this->actingAs($admin)
        ->get(route('inventory.by-sku', $product))
        ->viewData('stockByLocation');

    expect($stockByLocation->get($location->id)->count())->toBe(1);
});

it('shows empty state on stock by sku when product has no in_stock serials', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->get(route('inventory.by-sku', $product))
        ->assertOk()
        ->assertSee('No in_stock serials found');
});

it('returns 404 for stock by sku with non-existent product', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('inventory.by-sku', 9999))
        ->assertNotFound();
});

// ── Authorization: showBySkuAtLocation ────────────────────────────────────────

it('redirects unauthenticated users from sku-at-location view', function () {
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $this->get(route('inventory.by-sku-at-location', [$product, $location]))
        ->assertRedirect(route('login'));
});

it('denies sku-at-location view for users without inventory permission', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($user)
        ->get(route('inventory.by-sku-at-location', [$product, $location]))
        ->assertForbidden();
});

// ── showBySkuAtLocation ───────────────────────────────────────────────────────

it('admin can view serials for a SKU at a location', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create(['sku' => 'LOC-SKU', 'name' => 'Loc Widget']);
    $location = InventoryLocation::factory()->create(['code' => 'L99', 'name' => 'Shelf L99']);

    makeInStockSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'SN-LOC-001']);

    $this->actingAs($admin)
        ->get(route('inventory.by-sku-at-location', [$product, $location]))
        ->assertOk()
        ->assertViewIs('inventory.show-by-sku-at-location')
        ->assertViewHas('product')
        ->assertViewHas('location')
        ->assertViewHas('serials')
        ->assertSee('LOC-SKU')
        ->assertSee('L99')
        ->assertSee('SN-LOC-001');
});

it('sales can view serials for a SKU at a location', function () {
    $sales = User::factory()->create()->assignRole('sales');
    $product = Product::factory()->create(['sku' => 'SALES-SKU']);
    $location = InventoryLocation::factory()->create(['code' => 'SL1']);

    makeInStockSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'SN-SALES-001']);

    $this->actingAs($sales)
        ->get(route('inventory.by-sku-at-location', [$product, $location]))
        ->assertOk()
        ->assertSee('SN-SALES-001');
});

it('only in_stock serials are shown on sku-at-location view', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    makeInStockSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'GOOD-SN']);
    InventorySerial::factory()->create([
        'product_id' => $product->id,
        'inventory_location_id' => $location->id,
        'serial_number' => 'SOLD-SN',
        'status' => SerialStatus::Sold,
    ]);

    $this->actingAs($admin)
        ->get(route('inventory.by-sku-at-location', [$product, $location]))
        ->assertSee('GOOD-SN')
        ->assertDontSee('SOLD-SN');
});

it('returns 404 for sku-at-location with non-existent product', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $location = InventoryLocation::factory()->create();

    $this->actingAs($admin)
        ->get(route('inventory.by-sku-at-location', [9999, $location]))
        ->assertNotFound();
});

it('returns 404 for sku-at-location with non-existent location', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->get(route('inventory.by-sku-at-location', [$product, 9999]))
        ->assertNotFound();
});

it('shows serial numbers in the sku-at-location table', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    makeInStockSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'AAA-001']);
    makeInStockSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'AAA-002']);

    $response = $this->actingAs($admin)
        ->get(route('inventory.by-sku-at-location', [$product, $location]));

    expect($response->viewData('serials')->count())->toBe(2);
    $response->assertSee('AAA-001')->assertSee('AAA-002');
});
