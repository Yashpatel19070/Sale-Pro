<?php

declare(strict_types=1);

use App\Enums\MovementType;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(InventoryMovementService::class);
    $this->user = User::factory()->create();
    $this->product = Product::factory()->create();
    $this->locationA = InventoryLocation::factory()->create(['code' => 'L1', 'is_active' => true]);
    $this->locationB = InventoryLocation::factory()->create(['code' => 'L2', 'is_active' => true]);

    $this->serial = InventorySerial::factory()->create([
        'product_id' => $this->product->id,
        'inventory_location_id' => $this->locationA->id,
        'status' => 'in_stock',
    ]);
});

// ── receive() ─────────────────────────────────────────────────────────────────

it('receive() creates an InventorySerial with status in_stock', function () {
    $serial = $this->service->receive([
        'product_id' => $this->product->id,
        'inventory_location_id' => $this->locationA->id,
        'serial_number' => 'SN-RECV-001',
        'purchase_price' => 25.00,
        'received_at' => now()->format('Y-m-d'),
        'supplier_name' => 'TestCo',
    ], $this->user);

    expect($serial->status->value)->toBe('in_stock')
        ->and($serial->serial_number)->toBe('SN-RECV-001')
        ->and($serial->received_by_user_id)->toBe($this->user->id);
});

it('receive() creates an InventoryMovement of type receive', function () {
    $serial = $this->service->receive([
        'product_id' => $this->product->id,
        'inventory_location_id' => $this->locationA->id,
        'serial_number' => 'SN-RECV-002',
        'purchase_price' => 10.00,
        'received_at' => now()->format('Y-m-d'),
    ], $this->user);

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $serial->id,
        'type' => 'receive',
        'to_location_id' => $this->locationA->id,
        'from_location_id' => null,
    ]);
});

it('receive() rolls back serial if movement insert fails', function () {
    $this->instance(InventoryMovementService::class, new class extends InventoryMovementService
    {
        public function receive(array $data, User $receivedBy): InventorySerial
        {
            return DB::transaction(function () use ($data, $receivedBy): InventorySerial {
                InventorySerial::create(array_merge($data, [
                    'received_by_user_id' => $receivedBy->id,
                    'status' => 'in_stock',
                ]));
                throw new RuntimeException('Forced failure');
            });
        }
    });

    try {
        app(InventoryMovementService::class)->receive([
            'product_id' => $this->product->id,
            'inventory_location_id' => $this->locationA->id,
            'serial_number' => 'SN-ROLLBACK',
            'purchase_price' => 1.00,
            'received_at' => now()->format('Y-m-d'),
        ], $this->user);
    } catch (RuntimeException) {
    }

    $this->assertDatabaseMissing('inventory_serials', ['serial_number' => 'SN-ROLLBACK']);
});

it('receive() eager loads product, location, and receivedBy', function () {
    $serial = $this->service->receive([
        'product_id' => $this->product->id,
        'inventory_location_id' => $this->locationA->id,
        'serial_number' => 'SN-EAGER-001',
        'purchase_price' => 5.00,
        'received_at' => now()->format('Y-m-d'),
    ], $this->user);

    expect($serial->relationLoaded('product'))->toBeTrue()
        ->and($serial->relationLoaded('location'))->toBeTrue()
        ->and($serial->relationLoaded('receivedBy'))->toBeTrue();
});

// ── transfer() ────────────────────────────────────────────────────────────────

it('transfer() creates a movement row and updates serial location', function () {
    $movement = $this->service->transfer(
        serial: $this->serial,
        fromLocation: $this->locationA,
        toLocation: $this->locationB,
        user: $this->user,
    );

    expect($movement)->toBeInstanceOf(InventoryMovement::class);
    expect($movement->type)->toBe(MovementType::Transfer);
    expect($movement->from_location_id)->toBe($this->locationA->id);
    expect($movement->to_location_id)->toBe($this->locationB->id);
    expect($movement->user_id)->toBe($this->user->id);

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $this->serial->id,
        'type' => 'transfer',
    ]);

    expect($this->serial->fresh()->inventory_location_id)->toBe($this->locationB->id);
});

it('transfer() stores reference and notes', function () {
    $movement = $this->service->transfer(
        serial: $this->serial,
        fromLocation: $this->locationA,
        toLocation: $this->locationB,
        user: $this->user,
        reference: 'REF-001',
        notes: 'Shelf reorganisation',
    );

    expect($movement->reference)->toBe('REF-001');
    expect($movement->notes)->toBe('Shelf reorganisation');
});

it('transfer() throws DomainException when serial is not in_stock', function () {
    $this->serial->update(['status' => 'sold']);

    expect(fn () => $this->service->transfer(
        serial: $this->serial,
        fromLocation: $this->locationA,
        toLocation: $this->locationB,
        user: $this->user,
    ))->toThrow(DomainException::class, 'not in stock');

    $this->assertDatabaseCount('inventory_movements', 0);
});

it('transfer() throws DomainException when from_location does not match serial', function () {
    $wrongLocation = InventoryLocation::factory()->create();

    expect(fn () => $this->service->transfer(
        serial: $this->serial,
        fromLocation: $wrongLocation,
        toLocation: $this->locationB,
        user: $this->user,
    ))->toThrow(DomainException::class, 'not at location');

    $this->assertDatabaseCount('inventory_movements', 0);
    expect($this->serial->fresh()->inventory_location_id)->toBe($this->locationA->id);
});

it('transfer() throws DomainException when from and to locations are the same', function () {
    expect(fn () => $this->service->transfer(
        serial: $this->serial,
        fromLocation: $this->locationA,
        toLocation: $this->locationA,
        user: $this->user,
    ))->toThrow(DomainException::class, 'must be different');
});

it('transfer() rolls back completely if an error occurs mid-transaction', function () {
    $this->serial->update(['status' => 'damaged']);

    try {
        $this->service->transfer(
            serial: $this->serial,
            fromLocation: $this->locationA,
            toLocation: $this->locationB,
            user: $this->user,
        );
    } catch (DomainException) {
    }

    $this->assertDatabaseCount('inventory_movements', 0);
    expect($this->serial->fresh()->inventory_location_id)->toBe($this->locationA->id);
});

// ── sale() ────────────────────────────────────────────────────────────────────

it('sale() creates a movement row and marks serial as sold', function () {
    $movement = $this->service->sale(
        serial: $this->serial,
        fromLocation: $this->locationA,
        user: $this->user,
        reference: 'ORD-2024-0001',
    );

    expect($movement->type)->toBe(MovementType::Sale);
    expect($movement->from_location_id)->toBe($this->locationA->id);
    expect($movement->to_location_id)->toBeNull();

    $fresh = $this->serial->fresh();
    expect($fresh->status->value)->toBe('sold');
    expect($fresh->inventory_location_id)->toBeNull();
});

it('sale() throws DomainException when serial is not in_stock', function () {
    $this->serial->update(['status' => 'damaged']);

    expect(fn () => $this->service->sale(
        serial: $this->serial,
        fromLocation: $this->locationA,
        user: $this->user,
    ))->toThrow(DomainException::class);

    $this->assertDatabaseCount('inventory_movements', 0);
});

it('sale() throws DomainException when from_location does not match serial', function () {
    $wrongLocation = InventoryLocation::factory()->create();

    expect(fn () => $this->service->sale(
        serial: $this->serial,
        fromLocation: $wrongLocation,
        user: $this->user,
    ))->toThrow(DomainException::class);

    expect($this->serial->fresh()->status->value)->toBe('in_stock');
});

it('sale() rolls back — no movement row on failure', function () {
    $this->serial->update(['status' => 'missing']);

    try {
        $this->service->sale(
            serial: $this->serial,
            fromLocation: $this->locationA,
            user: $this->user,
        );
    } catch (DomainException) {
    }

    $this->assertDatabaseCount('inventory_movements', 0);
});

// ── adjustment() ──────────────────────────────────────────────────────────────

it('adjustment() creates a movement row and marks serial as damaged', function () {
    $movement = $this->service->adjustment(
        serial: $this->serial,
        newStatus: 'damaged',
        user: $this->user,
        notes: 'Screen cracked during handling',
    );

    expect($movement->type)->toBe(MovementType::Adjustment);
    expect($movement->notes)->toBe('Screen cracked during handling');

    $fresh = $this->serial->fresh();
    expect($fresh->status->value)->toBe('damaged');
    expect($fresh->inventory_location_id)->toBeNull();
});

it('adjustment() creates a movement row and marks serial as missing', function () {
    $this->service->adjustment(
        serial: $this->serial,
        newStatus: 'missing',
        user: $this->user,
        reference: 'CYCLE-COUNT-Q1',
    );

    expect($this->serial->fresh()->status->value)->toBe('missing');

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $this->serial->id,
        'type' => 'adjustment',
        'reference' => 'CYCLE-COUNT-Q1',
    ]);
});

it('adjustment() throws DomainException for invalid status', function () {
    expect(fn () => $this->service->adjustment(
        serial: $this->serial,
        newStatus: 'scrapped',
        user: $this->user,
    ))->toThrow(DomainException::class);

    $this->assertDatabaseCount('inventory_movements', 0);
});

it('adjustment() clears inventory_location_id', function () {
    expect($this->serial->inventory_location_id)->not->toBeNull();

    $this->service->adjustment(
        serial: $this->serial,
        newStatus: 'damaged',
        user: $this->user,
    );

    expect($this->serial->fresh()->inventory_location_id)->toBeNull();
});

// ── historyForSerial() ────────────────────────────────────────────────────────

it('historyForSerial() returns all movements for a serial in chronological order', function () {
    $receive = InventoryMovement::factory()->receive()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id' => $this->user->id,
        'created_at' => now()->subDays(10),
    ]);
    $transfer = InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id' => $this->user->id,
        'created_at' => now()->subDays(5),
    ]);
    $sale = InventoryMovement::factory()->sale()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id' => $this->user->id,
        'created_at' => now(),
    ]);

    $history = $this->service->historyForSerial($this->serial);

    expect($history)->toHaveCount(3);
    expect($history->first()->id)->toBe($receive->id);
    expect($history->last()->id)->toBe($sale->id);
    expect($history->first()->relationLoaded('serial'))->toBeTrue();
    expect($history->first()->relationLoaded('user'))->toBeTrue();
});

it('historyForSerial() returns empty collection when no movements exist', function () {
    expect($this->service->historyForSerial($this->serial))->toBeEmpty();
});

// ── listMovements() ───────────────────────────────────────────────────────────

it('listMovements() returns paginated results', function () {
    InventoryMovement::factory()->count(30)->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id' => $this->user->id,
    ]);

    $result = $this->service->listMovements();

    expect($result->count())->toBe(25);
    expect($result->total())->toBe(30);
});

it('listMovements() filters by serial number', function () {
    $otherSerial = InventorySerial::factory()->create();

    InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id' => $this->user->id,
    ]);
    InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $otherSerial->id,
        'user_id' => $this->user->id,
    ]);

    $result = $this->service->listMovements(['serial_number' => $this->serial->serial_number]);

    expect($result->total())->toBe(1);
});

it('listMovements() filters by type', function () {
    InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id' => $this->user->id,
    ]);
    InventoryMovement::factory()->sale()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id' => $this->user->id,
    ]);

    $result = $this->service->listMovements(['type' => 'sale']);

    expect($result->total())->toBe(1);
    expect($result->first()->type)->toBe(MovementType::Sale);
});

it('listMovements() ignores empty string type filter', function () {
    InventoryMovement::factory()->count(3)->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'user_id' => $this->user->id,
    ]);

    $result = $this->service->listMovements(['type' => '']);

    expect($result->total())->toBe(3);
});

it('listMovements() filters by location', function () {
    InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'from_location_id' => $this->locationA->id,
        'to_location_id' => $this->locationB->id,
        'user_id' => $this->user->id,
    ]);
    InventoryMovement::factory()->transfer()->create([
        'inventory_serial_id' => $this->serial->id,
        'from_location_id' => $this->locationB->id,
        'to_location_id' => InventoryLocation::factory()->create()->id,
        'user_id' => $this->user->id,
    ]);

    $result = $this->service->listMovements(['location_id' => $this->locationA->id]);

    expect($result->total())->toBe(1);
});
