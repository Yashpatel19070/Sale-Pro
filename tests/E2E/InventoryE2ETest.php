<?php

declare(strict_types=1);

/**
 * Inventory E2E Test Suite
 *
 * Covers plan cases from:
 *   inventory/09-e2e-tests.md          (I-xx)
 *   inventory-location/09-e2e-tests.md (L-xx)
 *   inventory-serial/09-e2e-tests.md   (S-xx)
 *   inventory-movement/09-e2e-tests.md (M-xx)
 *
 * These complement the Feature tests which already pass.
 * Test IDs from plan documents are annotated inline.
 */

use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\InventoryLocationPermissionSeeder;
use Database\Seeders\InventoryMovementPermissionSeeder;
use Database\Seeders\InventoryPermissionSeeder;
use Database\Seeders\InventorySerialPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ──────────────────────────────────────────────────────────────────────────────
// SETUP HELPERS
// ──────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(InventoryPermissionSeeder::class);
    $this->seed(InventoryLocationPermissionSeeder::class);
    $this->seed(InventorySerialPermissionSeeder::class);
    $this->seed(InventoryMovementPermissionSeeder::class);

    $this->admin = User::factory()->create()->assignRole('admin');
    $this->manager = User::factory()->create()->assignRole('manager');
    $this->sales = User::factory()->create()->assignRole('sales');

    $this->product1 = Product::factory()->create(['sku' => 'WIDGET-001', 'name' => 'Widget One']);
    $this->product2 = Product::factory()->create(['sku' => 'WIDGET-002', 'name' => 'Widget Two']);

    $this->locationL1 = InventoryLocation::factory()->create(['code' => 'L1',  'name' => 'Shelf L1']);
    $this->locationL2 = InventoryLocation::factory()->create(['code' => 'L2',  'name' => 'Shelf L2']);
    $this->locationL45 = InventoryLocation::factory()->create(['code' => 'L45', 'name' => 'Shelf L45']);
});

// ==============================================================================
// MODULE I — Stock View (inventory/09-e2e-tests.md)
// ==============================================================================

// ── I-05 / I-06: Stock dashboard counts ──────────────────────────────────────

it('[I-05] dashboard shows correct in_stock count excluding sold serials', function () {
    // 2 in_stock + 1 sold — dashboard should show qty = 2
    InventorySerial::factory()->inStock()->forProduct($this->product1)->atLocation($this->locationL1)->create();
    InventorySerial::factory()->inStock()->forProduct($this->product1)->atLocation($this->locationL45)->create();
    InventorySerial::factory()->sold()->forProduct($this->product1)->create();

    $stockOverview = $this->actingAs($this->admin)
        ->get(route('inventory.index'))
        ->viewData('stockOverview');

    expect($stockOverview->get($this->product1->id)->count())->toBe(2);
});

it('[I-06] dashboard does NOT show product with only sold/damaged serials', function () {
    InventorySerial::factory()->sold()->forProduct($this->product2)->create();
    InventorySerial::factory()->damaged()->forProduct($this->product2)->create();

    $stockOverview = $this->actingAs($this->admin)
        ->get(route('inventory.index'))
        ->viewData('stockOverview');

    expect($stockOverview->has($this->product2->id))->toBeFalse();
});

// ── I-07: Empty state ─────────────────────────────────────────────────────────

it('[I-07] dashboard shows empty state when no serials exist', function () {
    $this->actingAs($this->admin)
        ->get(route('inventory.index'))
        ->assertSee('No stock on hand');
});

// ── I-08: Soft-deleted product serials excluded from overview, shown in orphan notice ──

it('[I-08] soft-deleted product serials are excluded from overview but counted in orphan notice', function () {
    InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create(['serial_number' => 'SN-SDEL-001']);

    $this->product1->delete();

    $response = $this->actingAs($this->admin)->get(route('inventory.index'));

    $stockOverview = $response->viewData('stockOverview');
    $orphanedSerialCount = $response->viewData('orphanedSerialCount');

    // Excluded from main table — accessing ->product on the serial would null-crash
    expect($stockOverview->has($this->product1->id))->toBeFalse();
    // Counted separately so admin knows stock is not silently hidden
    expect($orphanedSerialCount)->toBe(1);
    // Yellow notice visible in the rendered page
    $response->assertSee('not shown');
});

// ── I-09 / I-10: showBySku breakdown ─────────────────────────────────────────

it('[I-09] showBySku shows serials grouped by location', function () {
    InventorySerial::factory()->inStock()->forProduct($this->product1)->atLocation($this->locationL1)->count(2)->create();
    InventorySerial::factory()->inStock()->forProduct($this->product1)->atLocation($this->locationL45)->count(1)->create();

    $stockByLocation = $this->actingAs($this->admin)
        ->get(route('inventory.by-sku', $this->product1))
        ->viewData('stockByLocation');

    expect($stockByLocation->get($this->locationL1->id)->count())->toBe(2);
    expect($stockByLocation->get($this->locationL45->id)->count())->toBe(1);
});

it('[I-10] showBySku total on hand card sums correctly', function () {
    InventorySerial::factory()->inStock()->forProduct($this->product1)->atLocation($this->locationL1)->count(2)->create();
    InventorySerial::factory()->inStock()->forProduct($this->product1)->atLocation($this->locationL45)->count(1)->create();

    $stockByLocation = $this->actingAs($this->admin)
        ->get(route('inventory.by-sku', $this->product1))
        ->viewData('stockByLocation');

    $total = $stockByLocation->sum(fn ($group) => $group->count());
    expect($total)->toBe(3);
});

// ── I-11: Back link ───────────────────────────────────────────────────────────

it('[I-11] showBySku page renders a back link to inventory index', function () {
    $this->actingAs($this->admin)
        ->get(route('inventory.by-sku', $this->product1))
        ->assertSee(route('inventory.index'));
});

// ── I-12: 404 for unknown product ────────────────────────────────────────────

it('[I-12] showBySku returns 404 for non-existent product', function () {
    $this->actingAs($this->admin)
        ->get(route('inventory.by-sku', 99999))
        ->assertNotFound();
});

// ── I-13: showBySkuAtLocation serial list ────────────────────────────────────

it('[I-13] showBySkuAtLocation shows serials ordered at correct location', function () {
    InventorySerial::factory()->inStock()->forProduct($this->product1)->atLocation($this->locationL1)
        ->create(['serial_number' => 'SN-001']);
    InventorySerial::factory()->inStock()->forProduct($this->product1)->atLocation($this->locationL1)
        ->create(['serial_number' => 'SN-002']);

    $this->actingAs($this->admin)
        ->get(route('inventory.by-sku-at-location', [$this->product1, $this->locationL1]))
        ->assertSee('SN-001')
        ->assertSee('SN-002');
});

// ── I-14: Detail link to inventory-serials.show ───────────────────────────────

it('[I-14] showBySkuAtLocation page links to inventory-serials.show for each serial', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $this->actingAs($this->admin)
        ->get(route('inventory.by-sku-at-location', [$this->product1, $this->locationL1]))
        ->assertSee(route('inventory-serials.show', $serial));
});

// ── I-15: Empty state for location with no stock ─────────────────────────────

it('[I-15] showBySkuAtLocation shows empty state when no serials at location', function () {
    // L45 has no stock for product1
    $this->actingAs($this->admin)
        ->get(route('inventory.by-sku-at-location', [$this->product1, $this->locationL45]))
        ->assertOk();

    $serials = $this->actingAs($this->admin)
        ->get(route('inventory.by-sku-at-location', [$this->product1, $this->locationL45]))
        ->viewData('serials');

    expect($serials->count())->toBe(0);
});

// ── I-16: 404 for unknown location ───────────────────────────────────────────

it('[I-16] showBySkuAtLocation returns 404 for non-existent location', function () {
    $this->actingAs($this->admin)
        ->get(route('inventory.by-sku-at-location', [$this->product1, 99999]))
        ->assertNotFound();
});

// ── I-17: Soft-deleted location returns 404 ───────────────────────────────────

it('[I-17] showBySkuAtLocation returns 404 for soft-deleted location', function () {
    $this->locationL2->delete(); // soft delete

    $this->actingAs($this->admin)
        ->get(route('inventory.by-sku-at-location', [$this->product1, $this->locationL2->id]))
        ->assertNotFound();
});

// ==============================================================================
// MODULE L — InventoryLocation (inventory-location/09-e2e-tests.md)
// ==============================================================================

// ── L-01 to L-08: Auth & Access ──────────────────────────────────────────────

it('[L-01] unauthenticated user is redirected from locations index', function () {
    $this->get(route('inventory-locations.index'))
        ->assertRedirect(route('login'));
});

it('[L-02] sales can view locations list', function () {
    $this->actingAs($this->sales)
        ->get(route('inventory-locations.index'))
        ->assertOk();
});

it('[L-03] sales is forbidden from create form', function () {
    $this->actingAs($this->sales)
        ->get(route('inventory-locations.create'))
        ->assertForbidden();
});

it('[L-04] sales is forbidden from store', function () {
    $this->actingAs($this->sales)
        ->post(route('inventory-locations.store'), ['code' => 'L99', 'name' => 'Test'])
        ->assertForbidden();
});

it('[L-05] sales is forbidden from edit form', function () {
    $this->actingAs($this->sales)
        ->get(route('inventory-locations.edit', $this->locationL1))
        ->assertForbidden();
});

it('[L-06] sales is forbidden from update', function () {
    $this->actingAs($this->sales)
        ->put(route('inventory-locations.update', $this->locationL1), ['name' => 'Hacked'])
        ->assertForbidden();
});

it('[L-07] sales is forbidden from destroy', function () {
    $this->actingAs($this->sales)
        ->delete(route('inventory-locations.destroy', $this->locationL1))
        ->assertForbidden();
});

it('[L-08] sales is forbidden from restore', function () {
    $this->locationL1->delete();

    $this->actingAs($this->sales)
        ->post(route('inventory-locations.restore', $this->locationL1->id))
        ->assertForbidden();
});

// ── L-09 / L-10 / L-11: Happy Path — Create ──────────────────────────────────

it('[L-09] admin can access create form', function () {
    $this->actingAs($this->admin)
        ->get(route('inventory-locations.create'))
        ->assertOk();
});

it('[L-10] admin can create a location and is redirected to show', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('inventory-locations.store'), [
            'code' => 'L99',
            'name' => 'Shelf L99',
        ]);

    $location = InventoryLocation::where('code', 'L99')->firstOrFail();
    $response->assertRedirect(route('inventory-locations.show', $location));

    $this->actingAs($this->admin)
        ->get(route('inventory-locations.index'))
        ->assertSee('L99');
});

it('[L-11] manager can create a location', function () {
    $this->actingAs($this->manager)
        ->post(route('inventory-locations.store'), [
            'code' => 'L100',
            'name' => 'Shelf L100',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('inventory_locations', ['code' => 'L100']);
});

// ── L-12 to L-17: Validation — Create ────────────────────────────────────────

it('[L-12] store fails with blank code', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-locations.store'), ['name' => 'Shelf A'])
        ->assertSessionHasErrors('code');
});

it('[L-13] store fails with blank name', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-locations.store'), ['code' => 'L50'])
        ->assertSessionHasErrors('name');
});

it('[L-14] store fails with duplicate active code', function () {
    // L1 already exists from beforeEach
    $this->actingAs($this->admin)
        ->post(route('inventory-locations.store'), ['code' => 'L1', 'name' => 'Duplicate'])
        ->assertSessionHasErrors('code');
});

it('[L-15] store succeeds when reusing code of a soft-deleted location', function () {
    // Create and soft-delete a location with code RECYCLED
    $old = InventoryLocation::factory()->create(['code' => 'RECYCLED', 'name' => 'Old']);
    $old->delete();

    $this->actingAs($this->admin)
        ->post(route('inventory-locations.store'), [
            'code' => 'RECYCLED',
            'name' => 'Reused',
        ])
        ->assertRedirect(); // Should succeed — withoutTrashed() in unique rule

    $this->assertDatabaseHas('inventory_locations', ['code' => 'RECYCLED', 'deleted_at' => null]);
});

it('[L-16] store fails when code exceeds max length (> 20 chars)', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-locations.store'), [
            'code' => str_repeat('A', 21),
            'name' => 'Too Long Code',
        ])
        ->assertSessionHasErrors('code');
});

it('[L-17] store fails when name exceeds max length (> 100 chars)', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-locations.store'), [
            'code' => 'L50',
            'name' => str_repeat('A', 101),
        ])
        ->assertSessionHasErrors('name');
});

// ── L-18 / L-19: Happy Path — Edit ───────────────────────────────────────────

it('[L-18] edit form renders with location code visible', function () {
    $this->actingAs($this->admin)
        ->get(route('inventory-locations.edit', $this->locationL1))
        ->assertOk()
        ->assertSee('L1');
});

it('[L-19] admin can update name and description', function () {
    $this->actingAs($this->admin)
        ->put(route('inventory-locations.update', $this->locationL1), [
            'name' => 'Updated Name',
            'description' => 'New description',
        ])
        ->assertRedirect(route('inventory-locations.show', $this->locationL1));

    $this->assertDatabaseHas('inventory_locations', [
        'id' => $this->locationL1->id,
        'name' => 'Updated Name',
        'description' => 'New description',
    ]);
});

// ── L-20 / L-21: Deactivate ───────────────────────────────────────────────────

it('[L-20] admin can deactivate location with no active serials', function () {
    $this->actingAs($this->admin)
        ->delete(route('inventory-locations.destroy', $this->locationL2))
        ->assertRedirect(route('inventory-locations.index'));

    $this->assertSoftDeleted('inventory_locations', ['id' => $this->locationL2->id]);
});

it('[L-21] admin cannot deactivate location with in_stock serials', function () {
    // Add an in_stock serial to L1
    InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $response = $this->actingAs($this->admin)
        ->delete(route('inventory-locations.destroy', $this->locationL1));

    // Should redirect back with validation error
    $response->assertRedirect();
    $this->assertNotSoftDeleted('inventory_locations', ['id' => $this->locationL1->id]);
});

// ── L-22 / L-23: Restore ─────────────────────────────────────────────────────

it('[L-22] admin can restore a soft-deleted location', function () {
    $this->locationL2->delete();

    $this->actingAs($this->admin)
        ->post(route('inventory-locations.restore', $this->locationL2->id))
        ->assertRedirect();

    $this->assertDatabaseHas('inventory_locations', [
        'id' => $this->locationL2->id,
        'deleted_at' => null,
        'is_active' => true,
    ]);
});

it('[L-23] deleted then restored location returns to active state', function () {
    // Delete then restore
    $this->actingAs($this->admin)
        ->delete(route('inventory-locations.destroy', $this->locationL2));

    $this->actingAs($this->admin)
        ->post(route('inventory-locations.restore', $this->locationL2->id));

    $restored = $this->locationL2->fresh();
    expect($restored->deleted_at)->toBeNull();
    expect($restored->is_active)->toBeTrue();
});

// ── L-25 / L-26: Soft-deleted location not in list or dropdowns ───────────────

it('[L-25] soft-deleted location does NOT appear in active list', function () {
    $extra = InventoryLocation::factory()->create(['code' => 'HIDDEN', 'name' => 'Hidden Location']);
    $extra->delete();

    $this->actingAs($this->admin)
        ->get(route('inventory-locations.index'))
        ->assertDontSee('HIDDEN');
});

it('[L-26] soft-deleted location does not appear in movement create dropdown', function () {
    $this->locationL2->delete();

    // Assert the specific option text is absent — not just 'L2' which also appears
    // in the SVG logo path data (e.g. "L251.32") and would cause a false positive.
    $this->actingAs($this->admin)
        ->get(route('inventory-movements.create'))
        ->assertOk()
        ->assertDontSee('L2 — Shelf L2');
});

// ── Soft-deleted location returns 404 via route model binding ──────────────────

it('[L-notes] GET show on soft-deleted location returns 404', function () {
    $this->locationL2->delete();

    $this->actingAs($this->admin)
        ->get(route('inventory-locations.show', $this->locationL2->id))
        ->assertNotFound();
});

it('[L-notes] POST restore on soft-deleted location succeeds', function () {
    $this->locationL2->delete();

    // restore uses withTrashed(), so it resolves even though deleted
    $this->actingAs($this->admin)
        ->post(route('inventory-locations.restore', $this->locationL2->id))
        ->assertRedirect();

    $this->assertDatabaseHas('inventory_locations', [
        'id' => $this->locationL2->id,
        'deleted_at' => null,
    ]);
});

// ==============================================================================
// MODULE S — InventorySerial (inventory-serial/09-e2e-tests.md)
// ==============================================================================

// ── S-06 / S-07 / S-08: Happy Path — Receive ─────────────────────────────────

it('[S-06] admin can receive a serial and it creates a receive movement', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('inventory-serials.store'), [
            'product_id' => $this->product1->id,
            'inventory_location_id' => $this->locationL1->id,
            'serial_number' => 'SN-RCVD-001',
            'purchase_price' => 49.99,
            'received_at' => now()->format('Y-m-d'),
        ]);

    $serial = InventorySerial::where('serial_number', 'SN-RCVD-001')->firstOrFail();
    $response->assertRedirect(route('inventory-serials.show', $serial));
    expect($serial->status->value)->toBe('in_stock');

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $serial->id,
        'type' => 'receive',
        'to_location_id' => $this->locationL1->id,
    ]);
});

it('[S-07] duplicate serial_number for same product is rejected', function () {
    InventorySerial::factory()->create([
        'serial_number' => 'SN-DUP-001',
        'product_id' => $this->product1->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('inventory-serials.store'), [
            'product_id' => $this->product1->id,
            'inventory_location_id' => $this->locationL1->id,
            'serial_number' => 'SN-DUP-001',
            'purchase_price' => 10,
            'received_at' => now()->format('Y-m-d'),
        ])
        ->assertSessionHasErrors('serial_number');
});

it('[S-08] same serial_number for a different product is rejected — serial numbers are globally unique', function () {
    // Serial numbers are physically stamped on hardware (IMEI, device S/N) and never
    // repeat across items, regardless of product. Global uniqueness is correct.
    InventorySerial::factory()->create([
        'serial_number' => 'SN-SHARED-001',
        'product_id' => $this->product1->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('inventory-serials.store'), [
            'product_id' => $this->product2->id,
            'inventory_location_id' => $this->locationL1->id,
            'serial_number' => 'SN-SHARED-001',
            'purchase_price' => 10,
            'received_at' => now()->format('Y-m-d'),
        ])
        ->assertSessionHasErrors('serial_number');
});

it('[S-09] receive with soft-deleted inactive location is rejected', function () {
    $inactive = InventoryLocation::factory()->create(['code' => 'INACTIVE', 'is_active' => false]);
    $inactive->delete();

    $this->actingAs($this->admin)
        ->post(route('inventory-serials.store'), [
            'product_id' => $this->product1->id,
            'inventory_location_id' => $inactive->id,
            'serial_number' => 'SN-INACTIVE-001',
            'purchase_price' => 10,
            'received_at' => now()->format('Y-m-d'),
        ])
        ->assertSessionHasErrors('inventory_location_id');
});

it('[S-09b] receive with soft-deleted location (was active) is also rejected', function () {
    // Location was active when deleted — deleted_at check must catch this,
    // not just the is_active flag
    $location = InventoryLocation::factory()->create(['code' => 'WASACTIVE', 'is_active' => true]);
    $location->delete();

    $this->actingAs($this->admin)
        ->post(route('inventory-serials.store'), [
            'product_id' => $this->product1->id,
            'inventory_location_id' => $location->id,
            'serial_number' => 'SN-WASACTIVE-001',
            'purchase_price' => 10,
            'received_at' => now()->format('Y-m-d'),
        ])
        ->assertSessionHasErrors('inventory_location_id');
});

it('[S-10] receive with zero purchase_price succeeds', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-serials.store'), [
            'product_id' => $this->product1->id,
            'inventory_location_id' => $this->locationL1->id,
            'serial_number' => 'SN-ZERO-001',
            'purchase_price' => 0,
            'received_at' => now()->format('Y-m-d'),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('inventory_serials', [
        'serial_number' => 'SN-ZERO-001',
        'purchase_price' => '0.00',
    ]);
});

it('[S-11] receive with negative purchase_price is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-serials.store'), [
            'product_id' => $this->product1->id,
            'inventory_location_id' => $this->locationL1->id,
            'serial_number' => 'SN-NEG-001',
            'purchase_price' => -1,
            'received_at' => now()->format('Y-m-d'),
        ])
        ->assertSessionHasErrors('purchase_price');
});

// ── S-12 / S-13: Serial Show — Movement pagination ───────────────────────────

it('[S-12] show page displays all movements when serial has fewer than 15', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    InventoryMovement::factory()->receive()->count(5)->create([
        'inventory_serial_id' => $serial->id,
        'user_id' => $this->admin->id,
    ]);

    $movements = $this->actingAs($this->admin)
        ->get(route('inventory-serials.show', $serial))
        ->viewData('movements');

    expect($movements->count())->toBe(5);
});

it('[S-13] show page paginates movements when serial has more than 15', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    InventoryMovement::factory()->receive()->count(20)->create([
        'inventory_serial_id' => $serial->id,
        'user_id' => $this->admin->id,
    ]);

    $movements = $this->actingAs($this->admin)
        ->get(route('inventory-serials.show', $serial))
        ->viewData('movements');

    expect($movements->hasPages())->toBeTrue();
    expect($movements->count())->toBeLessThan(20);
});

// ── S-14 / S-15 / S-16: Record Adjustment link visibility ────────────────────

it('[S-14] in_stock serial shows Record Adjustment link', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $expectedUrl = route('inventory-movements.create', [
        'serial_id' => $serial->id,
        'type' => 'adjustment',
    ]);

    $this->actingAs($this->admin)
        ->get(route('inventory-serials.show', $serial))
        ->assertSee('Record Adjustment')
        ->assertSee($expectedUrl);
});

it('[S-15] sold serial does NOT show Record Adjustment link', function () {
    $serial = InventorySerial::factory()->sold()
        ->forProduct($this->product1)
        ->create();

    $this->actingAs($this->admin)
        ->get(route('inventory-serials.show', $serial))
        ->assertDontSee('Record Adjustment');
});

it('[S-16] damaged serial does NOT show Record Adjustment link', function () {
    $serial = InventorySerial::factory()->damaged()
        ->forProduct($this->product1)
        ->create();

    $this->actingAs($this->admin)
        ->get(route('inventory-serials.show', $serial))
        ->assertDontSee('Record Adjustment');
});

// ── S-17 / S-18 / S-19: purchase_price visibility ────────────────────────────

it('[S-17] admin can see purchase_price on show page', function () {
    $serial = InventorySerial::factory()->create(['purchase_price' => '299.99']);

    $this->actingAs($this->admin)
        ->get(route('inventory-serials.show', $serial))
        ->assertSee('299.99');
});

it('[S-18] manager can see purchase_price on show page', function () {
    $serial = InventorySerial::factory()->create(['purchase_price' => '299.99']);

    $this->actingAs($this->manager)
        ->get(route('inventory-serials.show', $serial))
        ->assertSee('299.99');
});

it('[S-19] sales cannot see purchase_price on show page', function () {
    $serial = InventorySerial::factory()->create(['purchase_price' => '299.99']);

    $this->actingAs($this->sales)
        ->get(route('inventory-serials.show', $serial))
        ->assertDontSee('299.99');
});

// ── S-20 / S-21 / S-22 / S-23: Edit Notes ────────────────────────────────────

it('[S-20] admin can update serial notes', function () {
    $serial = InventorySerial::factory()->create(['notes' => 'original']);

    $this->actingAs($this->admin)
        ->put(route('inventory-serials.update', $serial), ['notes' => 'Damaged corner box'])
        ->assertRedirect(route('inventory-serials.show', $serial));

    $this->assertDatabaseHas('inventory_serials', [
        'id' => $serial->id,
        'notes' => 'Damaged corner box',
    ]);
});

it('[S-21] update ignores serial_number in submitted body', function () {
    $serial = InventorySerial::factory()->create(['serial_number' => 'ORIGINAL-SN']);

    $this->actingAs($this->admin)
        ->put(route('inventory-serials.update', $serial), [
            'serial_number' => 'HACKED-SN',
            'notes' => 'some notes',
        ]);

    $this->assertDatabaseHas('inventory_serials', [
        'id' => $serial->id,
        'serial_number' => 'ORIGINAL-SN',
    ]);
});

it('[S-22] update ignores purchase_price in submitted body', function () {
    $serial = InventorySerial::factory()->create(['purchase_price' => '199.99']);

    $this->actingAs($this->admin)
        ->put(route('inventory-serials.update', $serial), [
            'purchase_price' => '1.00',
            'notes' => 'some notes',
        ]);

    $this->assertDatabaseHas('inventory_serials', [
        'id' => $serial->id,
        'purchase_price' => '199.99',
    ]);
});

it('[S-23] update fails when notes exceed 5000 characters', function () {
    // Notes max is 5000 chars (not 2000 — original plan was updated to reflect code).
    $serial = InventorySerial::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('inventory-serials.update', $serial), [
            'notes' => str_repeat('x', 5001),
        ])
        ->assertSessionHasErrors('notes');

    // 5000 chars exactly should be accepted
    $this->actingAs($this->admin)
        ->put(route('inventory-serials.update', $serial), [
            'notes' => str_repeat('x', 5000),
        ])
        ->assertSessionDoesntHaveErrors('notes');
});

// ── S-25 / S-26: List all statuses, sales no purchase_price column ───────────

it('[S-25] serials list shows all statuses, not filtered', function () {
    InventorySerial::factory()->inStock()->create(['serial_number' => 'SN-INSTOCK-001']);
    InventorySerial::factory()->sold()->create(['serial_number' => 'SN-SOLD-001']);
    InventorySerial::factory()->damaged()->create(['serial_number' => 'SN-DAMAGED-001']);

    $this->actingAs($this->admin)
        ->get(route('inventory-serials.index'))
        ->assertSee('SN-INSTOCK-001')
        ->assertSee('SN-SOLD-001')
        ->assertSee('SN-DAMAGED-001');
});

it('[S-26] sales cannot see purchase_price column on serials list', function () {
    InventorySerial::factory()->create(['purchase_price' => '555.55', 'serial_number' => 'SN-PRICETEST-001']);

    $this->actingAs($this->sales)
        ->get(route('inventory-serials.index'))
        ->assertDontSee('555.55');
});

// ==============================================================================
// MODULE M — InventoryMovement (inventory-movement/09-e2e-tests.md)
// ==============================================================================

// ── M-04: Record Movement button visible to sales ────────────────────────────

it('[M-04] Record Movement button is visible to sales on movement index', function () {
    $this->actingAs($this->sales)
        ->get(route('inventory-movements.index'))
        ->assertSee('Record Movement');
});

// ── M-05: Adjustment radio NOT rendered for sales ────────────────────────────

it('[M-05] Adjustment radio button is NOT rendered in create form for sales', function () {
    $this->actingAs($this->sales)
        ->get(route('inventory-movements.create'))
        ->assertDontSee('value="adjustment"', false);
});

it('[M-05] Adjustment radio button IS rendered in create form for admin', function () {
    $this->actingAs($this->admin)
        ->get(route('inventory-movements.create'))
        ->assertSee('value="adjustment"', false);
});

// ── M-10: Transfer — from_location mismatch ──────────────────────────────────

it('[M-10] transfer fails when from_location does not match serial current location', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    // Serial is at L1, but we say it's at L2
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'transfer',
            'from_location_id' => $this->locationL2->id,
            'to_location_id' => $this->locationL45->id,
        ])
        ->assertSessionHasErrors('from_location_id');
});

// ── M-11: Transfer — same from and to location ───────────────────────────────

it('[M-11] transfer fails when from and to locations are the same', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'transfer',
            'from_location_id' => $this->locationL1->id,
            'to_location_id' => $this->locationL1->id,
        ])
        ->assertSessionHasErrors('to_location_id');
});

// ── M-12: Transfer — sold serial ─────────────────────────────────────────────

it('[M-12] transfer fails when serial is sold (not in_stock)', function () {
    $serial = InventorySerial::factory()->sold()->forProduct($this->product1)->create();

    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'transfer',
            'from_location_id' => $this->locationL1->id,
            'to_location_id' => $this->locationL2->id,
        ])
        ->assertSessionHasErrors(['inventory_serial_id']);
});

// ── M-13: Transfer — missing to_location_id ──────────────────────────────────

it('[M-13] transfer fails when to_location_id is missing', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'transfer',
            'from_location_id' => $this->locationL1->id,
        ])
        ->assertSessionHasErrors('to_location_id');
});

// ── M-14: Transfer — nonexistent serial ──────────────────────────────────────

it('[M-14] transfer fails with non-existent serial id', function () {
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => 99999,
            'type' => 'transfer',
            'from_location_id' => $this->locationL1->id,
            'to_location_id' => $this->locationL2->id,
        ])
        ->assertSessionHasErrors('inventory_serial_id');
});

// ── M-17: Sale — location mismatch ───────────────────────────────────────────

it('[M-17] sale fails when sale_location_id does not match serial location', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'sale',
            'from_location_id' => $this->locationL2->id, // Wrong location
            'reference' => 'ORD-001',
        ])
        ->assertSessionHasErrors('from_location_id');
});

// ── M-18: Sale — already sold ────────────────────────────────────────────────

it('[M-18] sale fails when serial is already sold', function () {
    $serial = InventorySerial::factory()->sold()->forProduct($this->product1)->create();

    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'sale',
            'from_location_id' => $this->locationL1->id,
        ])
        ->assertSessionHasErrors('inventory_serial_id');
});

// ── M-19: Sale — to_location_id is prohibited ────────────────────────────────

it('[M-19] sale fails when to_location_id is sent (prohibited for sale type)', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'sale',
            'from_location_id' => $this->locationL1->id,
            'to_location_id' => $this->locationL2->id, // Prohibited
        ])
        ->assertSessionHasErrors('to_location_id');
});

// ── M-23 / M-24: Adjustment Guards ───────────────────────────────────────────

it('[M-23] adjustment fails on already-damaged serial', function () {
    $serial = InventorySerial::factory()->damaged()->forProduct($this->product1)->create();

    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'adjustment',
            'adjustment_status' => 'missing',
        ])
        ->assertSessionHasErrors('inventory_serial_id');
});

it('[M-24] adjustment fails on sold serial', function () {
    $serial = InventorySerial::factory()->sold()->forProduct($this->product1)->create();

    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'adjustment',
            'adjustment_status' => 'damaged',
        ])
        ->assertSessionHasErrors('inventory_serial_id');
});

// ── M-26: Receive type via UI is rejected ────────────────────────────────────

it('[M-26] receive type is rejected via movement create form', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'receive',
        ])
        ->assertSessionHasErrors('type');
});

// ── M-27 / M-28 / M-29: Adjustment Validation ────────────────────────────────

it('[M-27] adjustment fails when adjustment_status is sold', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'adjustment',
            'adjustment_status' => 'sold', // Invalid: only damaged/missing allowed
        ])
        ->assertSessionHasErrors('adjustment_status');
});

it('[M-28] adjustment fails when adjustment_status is missing from request', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'adjustment',
            // adjustment_status intentionally omitted
        ])
        ->assertSessionHasErrors('adjustment_status');
});

it('[M-29] adjustment fails when from_location_id is sent (prohibited for adjustment)', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'adjustment',
            'adjustment_status' => 'damaged',
            'from_location_id' => $this->locationL1->id, // Prohibited for adjustment
        ])
        ->assertSessionHasErrors('from_location_id');
});

// ── M-30 to M-36: Movement Index Filters ─────────────────────────────────────

it('[M-30] movement index filters by serial_number', function () {
    $serial1 = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create(['serial_number' => 'SN-FILTER-001']);

    $serial2 = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL2)
        ->create(['serial_number' => 'SN-OTHER-001']);

    InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $serial1->id,
        'user_id' => $this->admin->id,
    ]);
    InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $serial2->id,
        'user_id' => $this->admin->id,
    ]);

    $movements = $this->actingAs($this->admin)
        ->get(route('inventory-movements.index', ['serial_number' => 'SN-FILTER-001']))
        ->viewData('movements');

    expect($movements->every(fn ($m) => $m->serial->serial_number === 'SN-FILTER-001'))->toBeTrue();
});

it('[M-32] movement index filters by type', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $serial->id,
        'user_id' => $this->admin->id,
    ]);
    InventoryMovement::factory()->sale()->create([
        'inventory_serial_id' => $serial->id,
        'user_id' => $this->admin->id,
    ]);

    $movements = $this->actingAs($this->admin)
        ->get(route('inventory-movements.index', ['type' => 'transfer']))
        ->viewData('movements');

    expect($movements->every(fn ($m) => $m->type->value === 'transfer'))->toBeTrue();
});

// ── M-37 / M-38 / M-39: Movement Immutability — no edit/update/delete routes ──

it('[M-37] GET edit route for movement returns 404', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $movement = InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $serial->id,
        'user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->get("/admin/inventory-movements/{$movement->id}/edit")
        ->assertNotFound();
});

it('[M-38] PUT update route for movement returns 404', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $movement = InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $serial->id,
        'user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->put("/admin/inventory-movements/{$movement->id}", ['notes' => 'hacked'])
        ->assertNotFound();
});

it('[M-39] DELETE route for movement returns 404', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $movement = InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $serial->id,
        'user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->delete("/admin/inventory-movements/{$movement->id}")
        ->assertNotFound();
});

// ── M-40 / M-41 / M-42 / M-43 / M-44: Serial Timeline ───────────────────────

it('[M-40] admin can view serial timeline with 3 movements in order', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    InventoryMovement::factory()->receive()->count(3)->create([
        'inventory_serial_id' => $serial->id,
        'user_id' => $this->admin->id,
    ]);

    $movements = $this->actingAs($this->admin)
        ->get(route('inventory-serials.movements', $serial))
        ->assertOk()
        ->viewData('movements');

    expect($movements->count())->toBe(3);
});

it('[M-41] serial timeline returns all movements unpaginated — pagination not needed for single-serial timelines', function () {
    // historyForSerial() returns a flat Collection, not a paginator.
    // A single serial rarely has hundreds of movements; pagination adds complexity for no gain.
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    InventoryMovement::factory()->receive()->count(25)->create([
        'inventory_serial_id' => $serial->id,
        'user_id' => $this->admin->id,
    ]);

    $movements = $this->actingAs($this->admin)
        ->get(route('inventory-serials.movements', $serial))
        ->viewData('movements');

    // All 25 movements returned — no pagination truncation
    expect($movements)->toHaveCount(25);
});

it('[M-42] serial timeline shows empty state when no movements', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $this->actingAs($this->admin)
        ->get(route('inventory-serials.movements', $serial))
        ->assertSee('No movements recorded yet.');
});

it('[M-43] sales can view serial timeline', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $this->actingAs($this->sales)
        ->get(route('inventory-serials.movements', $serial))
        ->assertOk();
});

it('[M-44] serial timeline page has back link to inventory-serials.show', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $this->actingAs($this->admin)
        ->get(route('inventory-serials.movements', $serial))
        ->assertSee(route('inventory-serials.show', $serial));
});

// ── M-45 / M-46 / M-47 / M-48: Create Form Pre-population ───────────────────

it('[M-45] clicking Record Adjustment from serial show pre-selects serial and type=adjustment', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    $response = $this->actingAs($this->admin)
        ->get(route('inventory-movements.create', [
            'serial_id' => $serial->id,
            'type' => 'adjustment',
        ]));

    $response->assertOk();

    $selectedSerial = $response->viewData('selectedSerial');
    $selectedType = $response->viewData('selectedType');

    expect($selectedSerial?->id)->toBe($serial->id);
    expect($selectedType)->toBe('adjustment');
});

it('[M-46] manual navigation to create form has type=transfer by default, no serial pre-selected', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('inventory-movements.create'));

    $response->assertOk();

    expect($response->viewData('selectedSerial'))->toBeNull();
    expect($response->viewData('selectedType'))->toBe('transfer');
});

it('[M-47] GET create with ?type=sale pre-checks sale radio', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('inventory-movements.create', ['type' => 'sale']));

    $response->assertOk();
    expect($response->viewData('selectedType'))->toBe('sale');
});

it('[M-48] GET create with ?type=adjustment pre-checks adjustment radio', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('inventory-movements.create', ['type' => 'adjustment']));

    $response->assertOk();
    expect($response->viewData('selectedType'))->toBe('adjustment');
});

// ==============================================================================
// CROSS-MODULE JOURNEYS (inventory/09-e2e-tests.md)
// ==============================================================================

// ── Journey 1: Full Lifecycle (receive → transfer → sell → off dashboard) ─────

it('[Journey-1] full lifecycle: receive → transfer → sell → serial gone from dashboard', function () {
    // Step 1: Admin receives SN-999 for WIDGET-001 at L1
    $this->actingAs($this->admin)
        ->post(route('inventory-serials.store'), [
            'product_id' => $this->product1->id,
            'inventory_location_id' => $this->locationL1->id,
            'serial_number' => 'SN-999',
            'purchase_price' => 99.99,
            'received_at' => now()->format('Y-m-d'),
        ])
        ->assertRedirect();

    $serial = InventorySerial::where('serial_number', 'SN-999')->firstOrFail();

    // Step 2: Dashboard shows WIDGET-001 with at least 1 in_stock
    $stockOverview = $this->actingAs($this->admin)
        ->get(route('inventory.index'))
        ->viewData('stockOverview');

    expect($stockOverview->has($this->product1->id))->toBeTrue();

    // Step 3: Transfer SN-999 from L1 to L45
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'transfer',
            'from_location_id' => $this->locationL1->id,
            'to_location_id' => $this->locationL45->id,
        ])
        ->assertRedirect();

    expect($serial->fresh()->inventory_location_id)->toBe($this->locationL45->id);

    // Step 4: showBySku — L45 count increased
    $stockByLocation = $this->actingAs($this->admin)
        ->get(route('inventory.by-sku', $this->product1))
        ->viewData('stockByLocation');

    expect($stockByLocation->get($this->locationL45->id))->not->toBeNull();
    expect($stockByLocation->get($this->locationL45->id)->count())->toBe(1);

    // Step 5: Sell SN-999 from L45
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'sale',
            'from_location_id' => $this->locationL45->id,
            'reference' => 'ORD-999',
        ])
        ->assertRedirect();

    expect($serial->fresh()->status->value)->toBe('sold');

    // Step 6: Dashboard — product1 count drops (SN-999 gone from in_stock)
    $stockOverview = $this->actingAs($this->admin)
        ->get(route('inventory.index'))
        ->viewData('stockOverview');

    // No more in_stock serials for product1
    expect($stockOverview->has($this->product1->id))->toBeFalse();

    // Step 7: Serial timeline has 3 movements: receive → transfer → sale
    $movements = $this->actingAs($this->admin)
        ->get(route('inventory-serials.movements', $serial))
        ->viewData('movements');

    $types = $movements->pluck('type')->map(fn ($t) => $t->value)->values()->toArray();
    expect($types)->toContain('receive');
    expect($types)->toContain('transfer');
    expect($types)->toContain('sale');
    expect(count($types))->toBe(3);
});

// ── Journey 2: Adjustment Flow (receive → dashboard → adjust → removed) ───────

it('[Journey-2] adjustment flow: receive → adjust → disappears from dashboard', function () {
    // Step 1: Admin creates SN-888 for WIDGET-002 at L2
    $this->actingAs($this->admin)
        ->post(route('inventory-serials.store'), [
            'product_id' => $this->product2->id,
            'inventory_location_id' => $this->locationL2->id,
            'serial_number' => 'SN-888',
            'purchase_price' => 10.00,
            'received_at' => now()->format('Y-m-d'),
        ]);

    $serial = InventorySerial::where('serial_number', 'SN-888')->firstOrFail();

    // Step 2: Dashboard shows WIDGET-002 with qty=1
    $stockOverview = $this->actingAs($this->admin)
        ->get(route('inventory.index'))
        ->viewData('stockOverview');

    expect($stockOverview->has($this->product2->id))->toBeTrue();
    expect($stockOverview->get($this->product2->id)->count())->toBe(1);

    // Step 3: Admin adjusts SN-888 → damaged
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'adjustment',
            'adjustment_status' => 'damaged',
        ]);

    expect($serial->fresh()->status->value)->toBe('damaged');

    // Step 4: Dashboard — WIDGET-002 disappears (0 in_stock)
    $stockOverview = $this->actingAs($this->admin)
        ->get(route('inventory.index'))
        ->viewData('stockOverview');

    expect($stockOverview->has($this->product2->id))->toBeFalse();

    // Step 5: Trying to adjust SN-888 again fails
    $this->actingAs($this->admin)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'adjustment',
            'adjustment_status' => 'missing',
        ])
        ->assertSessionHasErrors('inventory_serial_id');

    // Step 6: Serial show page does NOT show "Record Adjustment" link
    $this->actingAs($this->admin)
        ->get(route('inventory-serials.show', $serial))
        ->assertDontSee('Record Adjustment');
});

// ── Journey 3: Permission Boundary ────────────────────────────────────────────

it('[Journey-3] sales user sees all stock views but is blocked from admin write actions', function () {
    $serial = InventorySerial::factory()->inStock()
        ->forProduct($this->product1)
        ->atLocation($this->locationL1)
        ->create();

    // Step 2: GET /admin/inventory → 200
    $this->actingAs($this->sales)
        ->get(route('inventory.index'))
        ->assertOk();

    // Step 3: Drill through all 3 stock views
    $this->actingAs($this->sales)
        ->get(route('inventory.by-sku', $this->product1))
        ->assertOk();

    $this->actingAs($this->sales)
        ->get(route('inventory.by-sku-at-location', [$this->product1, $this->locationL1]))
        ->assertOk();

    // Step 4: Serial list and show accessible; purchase_price NOT rendered
    $this->actingAs($this->sales)
        ->get(route('inventory-serials.index'))
        ->assertOk();

    $this->actingAs($this->sales)
        ->get(route('inventory-serials.show', $serial))
        ->assertOk()
        ->assertDontSee($serial->purchase_price);

    // Step 5: Movement create form — Adjustment radio NOT rendered
    $this->actingAs($this->sales)
        ->get(route('inventory-movements.create'))
        ->assertOk()
        ->assertDontSee('value="adjustment"', false);

    // Step 6: Sales can submit transfer
    $this->actingAs($this->sales)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'transfer',
            'from_location_id' => $this->locationL1->id,
            'to_location_id' => $this->locationL2->id,
        ])
        ->assertRedirect(route('inventory-movements.index'));

    // Step 7: POST adjustment directly → 403
    $this->actingAs($this->sales)
        ->post(route('inventory-movements.store'), [
            'inventory_serial_id' => $serial->id,
            'type' => 'adjustment',
            'adjustment_status' => 'damaged',
        ])
        ->assertForbidden();

    // Step 8: GET location create → 403
    $this->actingAs($this->sales)
        ->get(route('inventory-locations.create'))
        ->assertForbidden();
});
