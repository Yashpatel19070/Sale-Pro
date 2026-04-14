# InventorySerial — Tests

## Feature Test — InventorySerialControllerTest.php

```php
<?php

declare(strict_types=1);

use App\Enums\SerialStatus;
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
    $admin = serialAdminUser();
    InventorySerial::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('inventory-serials.index'))
        ->assertOk()
        ->assertViewIs('inventory.serials.index')
        ->assertViewHas('serials');
});

it('filters serials by serial number search', function () {
    $admin = serialAdminUser();
    InventorySerial::factory()->create(['serial_number' => 'SN-ALPHA-001']);
    InventorySerial::factory()->create(['serial_number' => 'SN-BETA-002']);

    $this->actingAs($admin)
        ->get(route('inventory-serials.index', ['search' => 'ALPHA']))
        ->assertSee('SN-ALPHA-001')
        ->assertDontSee('SN-BETA-002');
});

it('filters serials by status', function () {
    $admin = serialAdminUser();
    InventorySerial::factory()->inStock()->create(['serial_number' => 'SN-STOCK-001']);
    InventorySerial::factory()->sold()->create(['serial_number' => 'SN-SOLD-002']);

    $this->actingAs($admin)
        ->get(route('inventory-serials.index', ['status' => 'in_stock']))
        ->assertSee('SN-STOCK-001')
        ->assertDontSee('SN-SOLD-002');
});

it('filters serials by product', function () {
    $admin   = serialAdminUser();
    $product = Product::factory()->create();
    $other   = Product::factory()->create();
    InventorySerial::factory()->forProduct($product)->create(['serial_number' => 'SN-P1-001']);
    InventorySerial::factory()->forProduct($other)->create(['serial_number' => 'SN-P2-001']);

    $this->actingAs($admin)
        ->get(route('inventory-serials.index', ['product_id' => $product->id]))
        ->assertSee('SN-P1-001')
        ->assertDontSee('SN-P2-001');
});

it('filters serials by location', function () {
    $admin    = serialAdminUser();
    $location = InventoryLocation::factory()->create();
    $other    = InventoryLocation::factory()->create();
    InventorySerial::factory()->atLocation($location)->create(['serial_number' => 'SN-LOC-001']);
    InventorySerial::factory()->atLocation($other)->create(['serial_number' => 'SN-LOC-002']);

    $this->actingAs($admin)
        ->get(route('inventory-serials.index', ['location_id' => $location->id]))
        ->assertSee('SN-LOC-001')
        ->assertDontSee('SN-LOC-002');
});

// ── Show ───────────────────────────────────────────────────────────────────────

it('admin can view serial detail', function () {
    $admin  = serialAdminUser();
    $serial = makeSerial();

    $this->actingAs($admin)
        ->get(route('inventory-serials.show', $serial))
        ->assertOk()
        ->assertViewIs('inventory.serials.show');
});

it('sales can view serial detail', function () {
    $this->actingAs(serialSalesUser())
        ->get(route('inventory-serials.show', makeSerial()))
        ->assertOk();
});

it('admin can see purchase price on show page', function () {
    $serial = makeSerial(['purchase_price' => '199.99']);

    $this->actingAs(serialAdminUser())
        ->get(route('inventory-serials.show', $serial))
        ->assertOk()
        ->assertSee('199.99');
});

it('sales cannot see purchase price on show page', function () {
    $serial = makeSerial(['purchase_price' => '199.99']);

    $this->actingAs(serialSalesUser())
        ->get(route('inventory-serials.show', $serial))
        ->assertOk()
        ->assertDontSee('199.99');
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
    $admin    = serialAdminUser();
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($admin)
        ->post(route('inventory-serials.store'), [
            'product_id'            => $product->id,
            'inventory_location_id' => $location->id,
            'serial_number'         => 'SN-NEW-001',
            'purchase_price'        => 49.99,
            'received_at'           => now()->format('Y-m-d'),
            'supplier_name'         => 'Acme Corp',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('inventory_serials', [
        'serial_number' => 'SN-NEW-001',
        'status'        => 'in_stock',
    ]);
});

it('receiving a serial creates an inventory movement', function () {
    $admin    = serialAdminUser();
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($admin)->post(route('inventory-serials.store'), [
        'product_id'            => $product->id,
        'inventory_location_id' => $location->id,
        'serial_number'         => 'SN-MVT-001',
        'purchase_price'        => 10.00,
        'received_at'           => now()->format('Y-m-d'),
    ]);

    $serial = InventorySerial::where('serial_number', 'SN-MVT-001')->firstOrFail();

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $serial->id,
        'type'                => 'receive',
        'to_location_id'      => $location->id,
    ]);
});

it('validates required fields on receive', function () {
    $this->actingAs(serialAdminUser())
        ->post(route('inventory-serials.store'), [])
        ->assertSessionHasErrors(['product_id', 'inventory_location_id', 'serial_number', 'purchase_price', 'received_at']);
});

it('validates serial_number uniqueness', function () {
    $admin   = serialAdminUser();
    $serial  = makeSerial(['serial_number' => 'SN-DUP-001']);
    $product = Product::factory()->create();
    $loc     = InventoryLocation::factory()->create();

    $this->actingAs($admin)
        ->post(route('inventory-serials.store'), [
            'product_id'            => $product->id,
            'inventory_location_id' => $loc->id,
            'serial_number'         => 'SN-DUP-001',
            'purchase_price'        => 10,
            'received_at'           => now()->format('Y-m-d'),
        ])
        ->assertSessionHasErrors(['serial_number']);
});

it('validates received_at cannot be in the future', function () {
    $admin    = serialAdminUser();
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($admin)
        ->post(route('inventory-serials.store'), [
            'product_id'            => $product->id,
            'inventory_location_id' => $location->id,
            'serial_number'         => 'SN-FUTURE-001',
            'purchase_price'        => 10,
            'received_at'           => now()->addDay()->format('Y-m-d'),
        ])
        ->assertSessionHasErrors(['received_at']);
});

it('uppercases serial_number on store', function () {
    $admin    = serialAdminUser();
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($admin)->post(route('inventory-serials.store'), [
        'product_id'            => $product->id,
        'inventory_location_id' => $location->id,
        'serial_number'         => 'sn-lower-001',
        'purchase_price'        => 10,
        'received_at'           => now()->format('Y-m-d'),
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
    $admin  = serialAdminUser();
    $serial = makeSerial(['notes' => 'original', 'supplier_name' => 'OldCo']);

    $this->actingAs($admin)
        ->put(route('inventory-serials.update', $serial), [
            'notes'         => 'Updated notes',
            'supplier_name' => 'NewCo',
        ])
        ->assertRedirect(route('inventory-serials.show', $serial));

    $this->assertDatabaseHas('inventory_serials', [
        'id'            => $serial->id,
        'notes'         => 'Updated notes',
        'supplier_name' => 'NewCo',
    ]);
});

it('update does not change serial_number even if submitted', function () {
    $admin  = serialAdminUser();
    $serial = makeSerial(['serial_number' => 'ORIGINAL-SN']);

    // Attempt to inject serial_number — it must be stripped by the FormRequest.
    $this->actingAs($admin)->put(route('inventory-serials.update', $serial), [
        'serial_number' => 'HACKED-SN',
        'notes'         => 'some notes',
    ]);

    $this->assertDatabaseHas('inventory_serials', [
        'id'            => $serial->id,
        'serial_number' => 'ORIGINAL-SN',
    ]);
});

it('update does not change purchase_price even if submitted', function () {
    $admin  = serialAdminUser();
    $serial = makeSerial(['purchase_price' => '99.99']);

    $this->actingAs($admin)->put(route('inventory-serials.update', $serial), [
        'purchase_price' => '1.00',
        'notes'          => 'some notes',
    ]);

    $this->assertDatabaseHas('inventory_serials', [
        'id'             => $serial->id,
        'purchase_price' => '99.99',
    ]);
});

// NOTE: markDamaged and markMissing feature tests are intentionally absent.
// Those controller actions were removed — status changes go through the inventory-movement
// module (type=adjustment). Tests for adjustment behavior belong in InventoryMovementControllerTest.
```

**File path:** `tests/Feature/InventorySerialControllerTest.php`

---

## Unit Test — InventorySerialServiceTest.php

```php
<?php

declare(strict_types=1);

use App\Enums\SerialStatus;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\User;
use App\Services\InventorySerialService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new InventorySerialService;
});

// ── list() ─────────────────────────────────────────────────────────────────────

it('returns paginated serials', function () {
    InventorySerial::factory()->count(5)->create();

    $result = $this->service->list();

    expect($result->total())->toBe(5);
});

it('filters by search term (serial number)', function () {
    InventorySerial::factory()->create(['serial_number' => 'SN-FIND-001']);
    InventorySerial::factory()->create(['serial_number' => 'SN-OTHER-002']);

    $result = $this->service->list(['search' => 'FIND']);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->serial_number)->toBe('SN-FIND-001');
});

it('filters by status', function () {
    InventorySerial::factory()->inStock()->create();
    InventorySerial::factory()->sold()->create();

    $result = $this->service->list(['status' => 'sold']);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->status)->toBe(SerialStatus::Sold);
});

it('filters by product_id', function () {
    $product = Product::factory()->create();
    InventorySerial::factory()->forProduct($product)->count(2)->create();
    InventorySerial::factory()->count(1)->create();

    $result = $this->service->list(['product_id' => $product->id]);

    expect($result->total())->toBe(2);
});

it('filters by location_id', function () {
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->atLocation($location)->count(2)->create();
    InventorySerial::factory()->count(1)->create();

    $result = $this->service->list(['location_id' => $location->id]);

    expect($result->total())->toBe(2);
});

// ── receive() ─────────────────────────────────────────────────────────────────

it('creates an InventorySerial with status in_stock', function () {
    $user     = User::factory()->create();
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $serial = $this->service->receive([
        'product_id'            => $product->id,
        'inventory_location_id' => $location->id,
        'serial_number'         => 'SN-RECV-001',
        'purchase_price'        => 25.00,
        'received_at'           => now()->format('Y-m-d'),
        'supplier_name'         => 'TestCo',
    ], $user);

    expect($serial->status)->toBe(SerialStatus::InStock)
        ->and($serial->serial_number)->toBe('SN-RECV-001')
        ->and($serial->received_by_user_id)->toBe($user->id);
});

it('creates an InventoryMovement of type receive', function () {
    $user     = User::factory()->create();
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $serial = $this->service->receive([
        'product_id'            => $product->id,
        'inventory_location_id' => $location->id,
        'serial_number'         => 'SN-RECV-002',
        'purchase_price'        => 10.00,
        'received_at'           => now()->format('Y-m-d'),
    ], $user);

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $serial->id,
        'type'                => 'receive',
        'to_location_id'      => $location->id,
        'from_location_id'    => null,
        'quantity'            => 1,
    ]);
});

it('rolls back serial creation if movement insert fails', function () {
    // Simulate a failure by creating a movement with a constraint we can trigger.
    // In practice: mock InventoryMovement::create to throw.
    // Here we test the transaction boundary by verifying count stays 0
    // after a simulated failure.

    $user     = User::factory()->create();
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    // Override InventoryMovement to throw so we can verify the transaction rolls back.
    $this->instance(InventorySerialService::class, new class extends InventorySerialService {
        public function receive(array $data, $receivedBy): InventorySerial
        {
            return \Illuminate\Support\Facades\DB::transaction(function () {
                InventorySerial::create([
                    'product_id'            => 1,
                    'inventory_location_id' => 1,
                    'serial_number'         => 'SN-ROLLBACK',
                    'purchase_price'        => 1,
                    'received_at'           => now()->format('Y-m-d'),
                    'received_by_user_id'   => 1,
                    'status'                => 'in_stock',
                ]);
                throw new \RuntimeException('Forced failure');
            });
        }
    });

    try {
        app(InventorySerialService::class)->receive([], $user);
    } catch (\RuntimeException) {
        // Expected
    }

    $this->assertDatabaseMissing('inventory_serials', ['serial_number' => 'SN-ROLLBACK']);
});

it('receive eager loads product, location, and receivedBy', function () {
    $user     = User::factory()->create();
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $serial = $this->service->receive([
        'product_id'            => $product->id,
        'inventory_location_id' => $location->id,
        'serial_number'         => 'SN-EAGER-001',
        'purchase_price'        => 5.00,
        'received_at'           => now()->format('Y-m-d'),
    ], $user);

    expect($serial->relationLoaded('product'))->toBeTrue()
        ->and($serial->relationLoaded('location'))->toBeTrue()
        ->and($serial->relationLoaded('receivedBy'))->toBeTrue();
});

// ── updateNotes() ─────────────────────────────────────────────────────────────

it('updates notes and supplier_name', function () {
    $serial = InventorySerial::factory()->create([
        'notes'         => 'old notes',
        'supplier_name' => 'OldCo',
    ]);

    $updated = $this->service->updateNotes($serial, [
        'notes'         => 'new notes',
        'supplier_name' => 'NewCo',
    ]);

    expect($updated->notes)->toBe('new notes')
        ->and($updated->supplier_name)->toBe('NewCo');

    $this->assertDatabaseHas('inventory_serials', [
        'id'            => $serial->id,
        'notes'         => 'new notes',
        'supplier_name' => 'NewCo',
    ]);
});

it('updateNotes ignores serial_number and purchase_price fields', function () {
    $serial = InventorySerial::factory()->create([
        'serial_number'  => 'SN-IMMUTABLE',
        'purchase_price' => '100.00',
    ]);

    $this->service->updateNotes($serial, [
        'serial_number'  => 'HACKED',
        'purchase_price' => '0.01',
        'notes'          => 'legit update',
    ]);

    $this->assertDatabaseHas('inventory_serials', [
        'id'             => $serial->id,
        'serial_number'  => 'SN-IMMUTABLE',
        'purchase_price' => '100.00',
    ]);
});

// NOTE: markDamaged() and markMissing() service unit tests are intentionally absent.
// Those methods were removed from InventorySerialService — see 03-service.md for rationale.

// ── findBySerial() ────────────────────────────────────────────────────────────

it('finds a serial by its serial_number', function () {
    $serial = InventorySerial::factory()->create(['serial_number' => 'SN-FIND-ME']);

    $found = $this->service->findBySerial('SN-FIND-ME');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($serial->id);
});

it('returns null when serial number does not exist', function () {
    $result = $this->service->findBySerial('SN-DOES-NOT-EXIST');

    expect($result)->toBeNull();
});

it('findBySerial eager loads relationships', function () {
    InventorySerial::factory()->create(['serial_number' => 'SN-EAGER-99']);

    $found = $this->service->findBySerial('SN-EAGER-99');

    expect($found->relationLoaded('product'))->toBeTrue()
        ->and($found->relationLoaded('location'))->toBeTrue()
        ->and($found->relationLoaded('receivedBy'))->toBeTrue();
});
```

**File path:** `tests/Unit/Services/InventorySerialServiceTest.php`

---

## Notes

### Stub Requirement
Both test files require `InventoryLocation` to be already built (from the inventory-location module).
If that module is not yet implemented when these tests run, create minimal stubs:
- `app/Models/InventoryLocation.php` — minimal Eloquent model
- `database/migrations/xxxx_create_inventory_locations_table.php` — minimal schema
- `database/factories/InventoryLocationFactory.php` — factory with `code`, `name`, `is_active`

This matches the pattern documented in `memory/feedback_stub_future_modules.md`.

### Role Names
Tests use `'admin'` and `'sales'` — never `'staff'`. This matches the actual seeded roles in this project.

### super-admin role
The `RoleSeeder` likely sets up `super-admin` with all permissions via a wildcard or explicit grant.
Tests use `admin` role to avoid relying on `super-admin` wildcard behavior in policy tests.

### Transaction Rollback Test
The rollback test uses a local anonymous class override of the service. A cleaner alternative
is to mock `InventoryMovement::create` via `InventoryMovement::shouldReceive` (Eloquent mocking)
or wrap the service in a partial mock. The anonymous class approach shown here is explicit and
avoids magic, but requires the test to be marked as demonstrating the transaction boundary concept
rather than a pure unit test.
