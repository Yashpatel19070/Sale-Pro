<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CustomerService;
});

it('paginate returns a paginator', function () {
    Customer::factory()->count(5)->create();

    $result = $this->service->paginate([]);

    expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($result->total())->toBe(5);
});

it('paginate filters by search', function () {
    Customer::factory()->create(['name' => 'Alice Smith']);
    Customer::factory()->create(['name' => 'Bob Jones']);

    $result = $this->service->paginate(['search' => 'Alice']);

    expect($result->total())->toBe(1);
    expect($result->first()->name)->toBe('Alice Smith');
});

it('paginate filters by status', function () {
    Customer::factory()->create(['status' => CustomerStatus::Active->value]);
    Customer::factory()->inactive()->create();

    $result = $this->service->paginate(['status' => 'active']);

    expect($result->total())->toBe(1);
    expect($result->first()->status)->toBe(CustomerStatus::Active);
});

it('store creates a customer', function () {
    $data = [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '555-123-4567',
        'company_name' => null,
        'address' => '123 Main St',
        'city' => 'Springfield',
        'state' => 'IL',
        'postal_code' => '62701',
        'country' => 'USA',
        'status' => 'active',
    ];

    $customer = $this->service->store($data);

    expect($customer)->toBeInstanceOf(Customer::class);
    expect($customer->email)->toBe('jane@example.com');
    $this->assertDatabaseHas('customers', ['email' => 'jane@example.com']);
});

it('update modifies a customer', function () {
    $customer = Customer::factory()->create(['name' => 'Old Name']);

    $result = $this->service->update($customer, ['name' => 'New Name']);

    expect($result->name)->toBe('New Name');
    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'New Name']);
});

it('changeStatus updates the status', function () {
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active->value]);

    $result = $this->service->changeStatus($customer, CustomerStatus::Blocked);

    expect($result->status)->toBe(CustomerStatus::Blocked);
    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'status' => 'blocked']);
});

it('delete soft deletes a customer', function () {
    $customer = Customer::factory()->create();

    $this->service->delete($customer);

    $this->assertSoftDeleted('customers', ['id' => $customer->id]);
});
