<?php

declare(strict_types=1);

use App\Enums\SerialStatus;
use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Services\InventorySerialService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
