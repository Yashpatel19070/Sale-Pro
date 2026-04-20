<?php

declare(strict_types=1);

use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(SupplierService::class);
});

// ── list() ───────────────────────────────────────────────────────────────────

it('list() returns paginated suppliers', function () {
    Supplier::factory()->count(30)->create();

    $result = $this->service->list();

    expect($result->total())->toBe(30);
    expect($result->count())->toBe(25);
});

it('list() filters by search on name', function () {
    Supplier::factory()->create(['name' => 'Acme Corp',  'code' => 'SUP-0001']);
    Supplier::factory()->create(['name' => 'Beta Supply', 'code' => 'SUP-0002']);

    $result = $this->service->list(['search' => 'Acme']);

    expect($result->total())->toBe(1);
    expect($result->first()->name)->toBe('Acme Corp');
});

it('list() filters by search on code', function () {
    Supplier::factory()->create(['name' => 'Acme Corp', 'code' => 'SUP-0001']);
    Supplier::factory()->create(['name' => 'Beta Corp', 'code' => 'SUP-0002']);

    $result = $this->service->list(['search' => 'SUP-0001']);

    expect($result->total())->toBe(1);
    expect($result->first()->code)->toBe('SUP-0001');
});

it('list() filters by status active', function () {
    Supplier::factory()->create(['code' => 'SUP-0001']);
    Supplier::factory()->inactive()->create(['code' => 'SUP-0002']);

    $result = $this->service->list(['status' => 'active']);

    expect($result->total())->toBe(1);
    expect($result->first()->is_active)->toBeTrue();
});

it('list() filters by status inactive', function () {
    Supplier::factory()->create(['code' => 'SUP-0001']);
    Supplier::factory()->inactive()->create(['code' => 'SUP-0002']);

    $result = $this->service->list(['status' => 'inactive']);

    expect($result->total())->toBe(1);
    expect($result->first()->is_active)->toBeFalse();
});

it('list() includes soft-deleted in results without status filter', function () {
    Supplier::factory()->create(['code' => 'SUP-0001']);
    Supplier::factory()->inactive()->create(['code' => 'SUP-0002']);

    $result = $this->service->list();

    expect($result->total())->toBe(2);
});

// ── create() ─────────────────────────────────────────────────────────────────

it('create() persists supplier with correct fields', function () {
    $supplier = $this->service->create([
        'name' => 'Test Supplier',
        'contact_name' => 'Alice',
        'contact_email' => 'alice@example.com',
        'contact_phone' => '555-0000',
        'address' => '1 Test St',
        'notes' => 'Some notes',
    ]);

    expect($supplier->name)->toBe('Test Supplier');
    expect($supplier->contact_name)->toBe('Alice');
    expect($supplier->contact_email)->toBe('alice@example.com');
    expect($supplier->contact_phone)->toBe('555-0000');
    expect($supplier->address)->toBe('1 Test St');
    expect($supplier->notes)->toBe('Some notes');
});

it('create() auto-generates code SUP-0001 for first supplier', function () {
    $supplier = $this->service->create(['name' => 'First']);

    expect($supplier->code)->toBe('SUP-0001');
});

it('create() auto-generates sequential codes', function () {
    $s1 = $this->service->create(['name' => 'First']);
    $s2 = $this->service->create(['name' => 'Second']);
    $s3 = $this->service->create(['name' => 'Third']);

    expect($s1->code)->toBe('SUP-0001');
    expect($s2->code)->toBe('SUP-0002');
    expect($s3->code)->toBe('SUP-0003');
});

it('create() sets is_active true', function () {
    $supplier = $this->service->create(['name' => 'Active']);

    expect($supplier->is_active)->toBeTrue();
});

it('create() allows nullable optional fields', function () {
    $supplier = $this->service->create(['name' => 'Minimal']);

    expect($supplier->contact_name)->toBeNull();
    expect($supplier->contact_email)->toBeNull();
    expect($supplier->contact_phone)->toBeNull();
    expect($supplier->address)->toBeNull();
    expect($supplier->notes)->toBeNull();
});

// ── update() ─────────────────────────────────────────────────────────────────

it('update() saves name and contact fields', function () {
    $supplier = Supplier::factory()->create(['code' => 'SUP-0001']);

    $updated = $this->service->update($supplier, [
        'name' => 'New Name',
        'contact_name' => 'Bob',
        'contact_email' => 'bob@example.com',
        'contact_phone' => '555-9999',
        'address' => '2 Updated Ave',
        'notes' => 'Updated notes',
    ]);

    expect($updated->name)->toBe('New Name');
    expect($updated->contact_name)->toBe('Bob');
    expect($updated->contact_email)->toBe('bob@example.com');
});

it('update() does not change code', function () {
    $supplier = Supplier::factory()->create(['code' => 'SUP-0042']);

    $this->service->update($supplier, ['name' => 'New Name']);

    expect($supplier->fresh()->code)->toBe('SUP-0042');
});

it('update() allows clearing optional fields', function () {
    $supplier = Supplier::factory()->create([
        'code' => 'SUP-0001',
        'contact_name' => 'Alice',
        'contact_email' => 'alice@example.com',
    ]);

    $updated = $this->service->update($supplier, [
        'name' => $supplier->name,
        'contact_name' => null,
        'contact_email' => null,
        'contact_phone' => null,
        'address' => null,
        'notes' => null,
    ]);

    expect($updated->contact_name)->toBeNull();
    expect($updated->contact_email)->toBeNull();
});

// ── deactivate() ─────────────────────────────────────────────────────────────

it('deactivate() soft-deletes and sets is_active false', function () {
    $supplier = Supplier::factory()->create();

    $this->service->deactivate($supplier);

    $supplier->refresh();
    expect($supplier->is_active)->toBeFalse();
    expect($supplier->trashed())->toBeTrue();
});

it('deactivate() sets deleted_at', function () {
    $supplier = Supplier::factory()->create();

    $this->service->deactivate($supplier);

    expect(Supplier::withTrashed()->find($supplier->id)->deleted_at)->not->toBeNull();
});

// ── restore() ────────────────────────────────────────────────────────────────

it('restore() clears deleted_at and sets is_active true', function () {
    $supplier = Supplier::factory()->inactive()->create();

    $restored = $this->service->restore($supplier);

    expect($restored->trashed())->toBeFalse();
    expect($restored->is_active)->toBeTrue();
    expect($restored->deleted_at)->toBeNull();
});

// ── generateCode() ───────────────────────────────────────────────────────────

it('generateCode() returns SUP-0001 when no suppliers exist', function () {
    expect($this->service->generateCode())->toBe('SUP-0001');
});

it('generateCode() increments based on count', function () {
    Supplier::factory()->count(5)->create();

    $next = $this->service->generateCode();

    expect($next)->toBe('SUP-0006');
});

it('generateCode() includes soft-deleted in count', function () {
    Supplier::factory()->inactive()->create();

    $next = $this->service->generateCode();

    expect($next)->toBe('SUP-0002');
});
