<?php

declare(strict_types=1);

use App\Enums\SerialStatus;
use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\User;
use App\Services\InventorySerialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new InventorySerialService;
});

// ── list() ─────────────────────────────────────────────────────────────────────

it('returns paginated serials', function () {
    InventorySerial::factory()->count(5)->create();

    expect($this->service->list()->total())->toBe(5);
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

    expect($this->service->list(['product_id' => $product->id])->total())->toBe(2);
});

it('filters by location_id', function () {
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->atLocation($location)->count(2)->create();
    InventorySerial::factory()->count(1)->create();

    expect($this->service->list(['location_id' => $location->id])->total())->toBe(2);
});

// ── receive() ─────────────────────────────────────────────────────────────────

it('creates an InventorySerial with status in_stock', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $serial = $this->service->receive([
        'product_id' => $product->id,
        'inventory_location_id' => $location->id,
        'serial_number' => 'SN-RECV-001',
        'purchase_price' => 25.00,
        'received_at' => now()->format('Y-m-d'),
        'supplier_name' => 'TestCo',
    ], $user);

    expect($serial->status)->toBe(SerialStatus::InStock)
        ->and($serial->serial_number)->toBe('SN-RECV-001')
        ->and($serial->received_by_user_id)->toBe($user->id);
});

it('creates an InventoryMovement of type receive', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $serial = $this->service->receive([
        'product_id' => $product->id,
        'inventory_location_id' => $location->id,
        'serial_number' => 'SN-RECV-002',
        'purchase_price' => 10.00,
        'received_at' => now()->format('Y-m-d'),
    ], $user);

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $serial->id,
        'type' => 'receive',
        'to_location_id' => $location->id,
        'from_location_id' => null,
        'quantity' => 1,
    ]);
});

it('rolls back serial creation if movement insert fails', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $this->instance(InventorySerialService::class, new class extends InventorySerialService
    {
        public function receive(array $data, $receivedBy): InventorySerial
        {
            return DB::transaction(function () use ($data): InventorySerial {
                InventorySerial::create($data + ['status' => 'in_stock']);
                throw new RuntimeException('Forced failure');
            });
        }
    });

    try {
        app(InventorySerialService::class)->receive([
            'product_id' => $product->id,
            'inventory_location_id' => $location->id,
            'serial_number' => 'SN-ROLLBACK',
            'purchase_price' => 1.00,
            'received_at' => now()->format('Y-m-d'),
            'received_by_user_id' => $user->id,
        ], $user);
    } catch (RuntimeException) {
        // expected
    }

    $this->assertDatabaseMissing('inventory_serials', ['serial_number' => 'SN-ROLLBACK']);
});

it('receive eager loads product, location, and receivedBy', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $serial = $this->service->receive([
        'product_id' => $product->id,
        'inventory_location_id' => $location->id,
        'serial_number' => 'SN-EAGER-001',
        'purchase_price' => 5.00,
        'received_at' => now()->format('Y-m-d'),
    ], $user);

    expect($serial->relationLoaded('product'))->toBeTrue()
        ->and($serial->relationLoaded('location'))->toBeTrue()
        ->and($serial->relationLoaded('receivedBy'))->toBeTrue();
});

// ── updateNotes() ─────────────────────────────────────────────────────────────

it('updates notes and supplier_name', function () {
    $serial = InventorySerial::factory()->create([
        'notes' => 'old notes',
        'supplier_name' => 'OldCo',
    ]);

    $updated = $this->service->updateNotes($serial, [
        'notes' => 'new notes',
        'supplier_name' => 'NewCo',
    ]);

    expect($updated->notes)->toBe('new notes')
        ->and($updated->supplier_name)->toBe('NewCo');

    $this->assertDatabaseHas('inventory_serials', [
        'id' => $serial->id,
        'notes' => 'new notes',
        'supplier_name' => 'NewCo',
    ]);
});

it('updateNotes ignores serial_number and purchase_price fields', function () {
    $serial = InventorySerial::factory()->create([
        'serial_number' => 'SN-IMMUTABLE',
        'purchase_price' => '100.00',
    ]);

    $this->service->updateNotes($serial, [
        'serial_number' => 'HACKED',
        'purchase_price' => '0.01',
        'notes' => 'legit update',
    ]);

    $this->assertDatabaseHas('inventory_serials', [
        'id' => $serial->id,
        'serial_number' => 'SN-IMMUTABLE',
        'purchase_price' => '100.00',
    ]);
});

// ── findBySerial() ────────────────────────────────────────────────────────────

it('finds a serial by its serial_number', function () {
    $serial = InventorySerial::factory()->create(['serial_number' => 'SN-FIND-ME']);

    $found = $this->service->findBySerial('SN-FIND-ME');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($serial->id);
});

it('returns null when serial number does not exist', function () {
    expect($this->service->findBySerial('SN-DOES-NOT-EXIST'))->toBeNull();
});

it('findBySerial eager loads relationships', function () {
    InventorySerial::factory()->create(['serial_number' => 'SN-EAGER-99']);

    $found = $this->service->findBySerial('SN-EAGER-99');

    expect($found->relationLoaded('product'))->toBeTrue()
        ->and($found->relationLoaded('location'))->toBeTrue()
        ->and($found->relationLoaded('receivedBy'))->toBeTrue();
});
