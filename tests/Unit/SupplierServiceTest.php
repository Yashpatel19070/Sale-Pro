<?php

declare(strict_types=1);

use App\Enums\SupplierStatus;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SupplierService;
});

// ===========================================================
// paginate()
// ===========================================================

it('paginate returns a length aware paginator', function () {
    Supplier::factory()->count(5)->create();

    $result = $this->service->paginate([]);

    expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($result->total())->toBe(5);
});

it('paginate with no filters returns all suppliers', function () {
    Supplier::factory()->count(3)->create();

    $result = $this->service->paginate([]);

    expect($result->total())->toBe(3);
});

it('paginate with empty search string returns all suppliers', function () {
    Supplier::factory()->count(3)->create();

    $result = $this->service->paginate(['search' => '']);

    expect($result->total())->toBe(3);
});

it('paginate with empty status string returns all suppliers', function () {
    Supplier::factory()->create(['status' => SupplierStatus::Active->value]);
    Supplier::factory()->inactive()->create();

    $result = $this->service->paginate(['status' => '']);

    expect($result->total())->toBe(2);
});

it('paginate filters by search on name', function () {
    Supplier::factory()->create(['name' => 'Alpha Supplies']);
    Supplier::factory()->create(['name' => 'Beta Corp']);

    $result = $this->service->paginate(['search' => 'Alpha']);

    expect($result->total())->toBe(1);
    expect($result->first()->name)->toBe('Alpha Supplies');
});

it('paginate filters by search on contact_name', function () {
    Supplier::factory()->create(['contact_name' => 'Jane Doe']);
    Supplier::factory()->create(['contact_name' => 'Bob Smith']);

    $result = $this->service->paginate(['search' => 'Jane']);

    expect($result->total())->toBe(1);
    expect($result->first()->contact_name)->toBe('Jane Doe');
});

it('paginate filters by search on email', function () {
    Supplier::factory()->create(['email' => 'alpha@acme.com']);
    Supplier::factory()->create(['email' => 'beta@corp.com']);

    $result = $this->service->paginate(['search' => 'acme']);

    expect($result->total())->toBe(1);
    expect($result->first()->email)->toBe('alpha@acme.com');
});

it('paginate filters by active status', function () {
    Supplier::factory()->create(['status' => SupplierStatus::Active->value]);
    Supplier::factory()->inactive()->create();

    $result = $this->service->paginate(['status' => 'active']);

    expect($result->total())->toBe(1);
    expect($result->first()->status)->toBe(SupplierStatus::Active);
});

it('paginate filters by inactive status', function () {
    Supplier::factory()->create(['status' => SupplierStatus::Active->value]);
    Supplier::factory()->inactive()->create();

    $result = $this->service->paginate(['status' => 'inactive']);

    expect($result->total())->toBe(1);
    expect($result->first()->status)->toBe(SupplierStatus::Inactive);
});

it('paginate returns 20 per page', function () {
    Supplier::factory()->count(25)->create();

    $result = $this->service->paginate([]);

    expect($result->perPage())->toBe(20);
    expect($result->total())->toBe(25);
});

// ===========================================================
// store()
// ===========================================================

it('store creates and returns a supplier', function () {
    $data = [
        'name' => 'Acme Corp',
        'contact_name' => 'Jane Doe',
        'email' => 'acme@example.com',
        'phone' => '555-123-4567',
        'address' => '123 Main St',
        'city' => 'Chicago',
        'state' => 'IL',
        'postal_code' => '60601',
        'country' => 'USA',
        'payment_terms' => 'Net 30',
        'notes' => 'Reliable supplier.',
        'status' => 'active',
    ];

    $supplier = $this->service->store($data);

    expect($supplier)->toBeInstanceOf(Supplier::class);
    expect($supplier->email)->toBe('acme@example.com');
    expect($supplier->name)->toBe('Acme Corp');
    $this->assertDatabaseHas('suppliers', ['email' => 'acme@example.com']);
});

it('store creates supplier with all nullable fields as null', function () {
    $data = [
        'name' => 'Minimal Supplier',
        'contact_name' => null,
        'email' => 'minimal@example.com',
        'phone' => '555-000-0000',
        'address' => null,
        'city' => null,
        'state' => null,
        'postal_code' => null,
        'country' => null,
        'payment_terms' => null,
        'notes' => null,
        'status' => 'active',
    ];

    $supplier = $this->service->store($data);

    expect($supplier->contact_name)->toBeNull();
    expect($supplier->notes)->toBeNull();
    $this->assertDatabaseHas('suppliers', ['email' => 'minimal@example.com', 'contact_name' => null]);
});

// ===========================================================
// update()
// ===========================================================

it('update modifies the supplier and returns fresh model', function () {
    $supplier = Supplier::factory()->create(['name' => 'Old Name']);

    $result = $this->service->update($supplier, ['name' => 'New Name']);

    expect($result)->toBeInstanceOf(Supplier::class);
    expect($result->name)->toBe('New Name');
    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'New Name']);
});

it('update returns model reflecting DB state not stale in-memory state', function () {
    $supplier = Supplier::factory()->create(['name' => 'Original']);

    $result = $this->service->update($supplier, ['name' => 'Updated']);

    expect($result->name)->toBe('Updated');
    expect($supplier->name)->toBe('Original');
});

// ===========================================================
// changeStatus()
// ===========================================================

it('changeStatus sets active supplier to inactive', function () {
    $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active->value]);

    $result = $this->service->changeStatus($supplier, SupplierStatus::Inactive);

    expect($result->status)->toBe(SupplierStatus::Inactive);
    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'status' => 'inactive']);
});

it('changeStatus sets inactive supplier back to active', function () {
    $supplier = Supplier::factory()->inactive()->create();

    $result = $this->service->changeStatus($supplier, SupplierStatus::Active);

    expect($result->status)->toBe(SupplierStatus::Active);
    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'status' => 'active']);
});

it('changeStatus returns fresh model with updated status', function () {
    $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active->value]);

    $result = $this->service->changeStatus($supplier, SupplierStatus::Inactive);

    expect($result)->toBeInstanceOf(Supplier::class);
    expect($result->status)->toBe(SupplierStatus::Inactive);
    expect($supplier->status)->toBe(SupplierStatus::Active);
});

// ===========================================================
// delete()
// ===========================================================

it('delete soft deletes the supplier', function () {
    $supplier = Supplier::factory()->create();

    $this->service->delete($supplier);

    $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
});

it('delete does not permanently remove the supplier', function () {
    $supplier = Supplier::factory()->create();

    $this->service->delete($supplier);

    $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
});

it('soft deleted supplier is excluded from normal queries', function () {
    $supplier = Supplier::factory()->create();

    $this->service->delete($supplier);

    expect(Supplier::find($supplier->id))->toBeNull();
    expect(Supplier::withTrashed()->find($supplier->id))->not->toBeNull();
});

// ===========================================================
// restore()
// ===========================================================

it('restore recovers a soft-deleted supplier', function () {
    $supplier = Supplier::factory()->create();
    $supplier->delete();

    $this->service->restore($supplier);

    $this->assertNotSoftDeleted('suppliers', ['id' => $supplier->id]);
});

it('restored supplier is visible in normal queries', function () {
    $supplier = Supplier::factory()->create();
    $supplier->delete();

    $this->service->restore($supplier);

    expect(Supplier::find($supplier->id))->not->toBeNull();
});
