<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\CustomerAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(CustomerAddressService::class);
    $this->customer = Customer::factory()->create();
});

it('list returns all addresses ordered default first', function () {
    CustomerAddress::factory()->create(['customer_id' => $this->customer->id, 'label' => 'Work', 'is_default' => false]);
    CustomerAddress::factory()->default()->create(['customer_id' => $this->customer->id, 'label' => 'Home']);

    $result = $this->service->list($this->customer);

    expect($result)->toHaveCount(2);
    expect($result->first()->label)->toBe('Home');
    expect($result->first()->is_default)->toBeTrue();
});

it('list returns empty collection when no addresses', function () {
    $result = $this->service->list($this->customer);

    expect($result)->toBeEmpty();
});

it('store creates an address for a customer', function () {
    $data = [
        'label' => 'Home', 'first_name' => 'Jane', 'last_name' => 'Doe',
        'email' => null, 'phone' => null,
        'address_line1' => '123 Main St', 'address_line2' => null,
        'city' => 'Austin', 'state' => 'TX', 'postal_code' => '78701', 'country' => 'US',
    ];

    $address = $this->service->store($this->customer, $data);

    expect($address)->toBeInstanceOf(CustomerAddress::class);
    expect($address->customer_id)->toBe($this->customer->id);
    expect($address->label)->toBe('Home');
    $this->assertDatabaseHas('customer_addresses', ['id' => $address->id, 'is_default' => false]);
});

it('update modifies address fields', function () {
    $address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id, 'label' => 'Old']);

    $result = $this->service->update($address, [
        'label' => 'New', 'first_name' => 'J', 'last_name' => 'D', 'email' => null, 'phone' => null,
        'address_line1' => '1 St', 'address_line2' => null,
        'city' => 'C', 'state' => 'TX', 'postal_code' => '00000', 'country' => 'US',
    ]);

    expect($result->label)->toBe('New');
    $this->assertDatabaseHas('customer_addresses', ['id' => $address->id, 'label' => 'New']);
});

it('setDefault sets one address as default and unsets all others', function () {
    $a = CustomerAddress::factory()->default()->create(['customer_id' => $this->customer->id]);
    $b = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);
    $c = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);

    $result = $this->service->setDefault($b);

    expect($result->is_default)->toBeTrue();
    $this->assertDatabaseHas('customer_addresses', ['id' => $a->id, 'is_default' => false]);
    $this->assertDatabaseHas('customer_addresses', ['id' => $b->id, 'is_default' => true]);
    $this->assertDatabaseHas('customer_addresses', ['id' => $c->id, 'is_default' => false]);
});

it('delete soft deletes an address', function () {
    $address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id, 'is_default' => false]);

    $this->service->delete($address);

    $this->assertSoftDeleted('customer_addresses', ['id' => $address->id]);
});

it('delete throws when address is default', function () {
    $address = CustomerAddress::factory()->default()->create(['customer_id' => $this->customer->id]);

    expect(fn () => $this->service->delete($address))
        ->toThrow(RuntimeException::class, 'Cannot delete the default address.');

    $this->assertDatabaseHas('customer_addresses', ['id' => $address->id, 'deleted_at' => null]);
});

it('store dispatches SyncCustomerToAvaTaxJob', function () {
    Illuminate\Support\Facades\Queue::fake();

    $customer = Customer::factory()->create();
    (new CustomerAddressService)->store($customer, [
        'label' => 'Home',
        'first_name' => 'A', 'last_name' => 'B',
        'address_line1' => '1 Main', 'city' => 'X', 'state' => 'TX',
        'postal_code' => '00000', 'country' => 'US',
    ]);

    Illuminate\Support\Facades\Queue::assertPushed(App\Jobs\SyncCustomerToAvaTaxJob::class, 1);
});

it('update dispatches SyncCustomerToAvaTaxJob', function () {
    Illuminate\Support\Facades\Queue::fake();
    $customer = Customer::factory()->create();
    $addr = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

    (new CustomerAddressService)->update($addr, ['address_line1' => 'changed']);

    Illuminate\Support\Facades\Queue::assertPushed(App\Jobs\SyncCustomerToAvaTaxJob::class, 1);
});

it('setDefault dispatches SyncCustomerToAvaTaxJob', function () {
    Illuminate\Support\Facades\Queue::fake();
    $customer = Customer::factory()->create();
    $addr = CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_default' => false]);

    (new CustomerAddressService)->setDefault($addr);

    Illuminate\Support\Facades\Queue::assertPushed(App\Jobs\SyncCustomerToAvaTaxJob::class, 1);
});

it('delete dispatches SyncCustomerToAvaTaxJob', function () {
    Illuminate\Support\Facades\Queue::fake();
    $customer = Customer::factory()->create();
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_default' => true]);
    $extra = CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_default' => false]);

    (new CustomerAddressService)->delete($extra);

    Illuminate\Support\Facades\Queue::assertPushed(App\Jobs\SyncCustomerToAvaTaxJob::class, 1);
});
