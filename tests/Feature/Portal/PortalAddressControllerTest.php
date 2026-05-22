<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeCustomer(): Customer
{
    return Customer::factory()->create([
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
        'status' => CustomerStatus::Active,
    ]);
}

// ---------------------------------------------------------------------------
// Guest redirects
// ---------------------------------------------------------------------------

describe('guest access', function () {

    it('redirects guests away from index', function () {
        $this->get(route('portal.addresses.index'))
            ->assertRedirect(route('portal.login'));
    });

    it('redirects guests away from create', function () {
        $this->get(route('portal.addresses.create'))
            ->assertRedirect(route('portal.login'));
    });

});

// ---------------------------------------------------------------------------
// Index
// ---------------------------------------------------------------------------

describe('index', function () {

    beforeEach(function () {
        $this->customer = makeCustomer();
        $this->actingAs($this->customer, 'customer');
    });

    it('shows the addresses page', function () {
        CustomerAddress::factory()->for($this->customer)->create(['is_default' => true]);

        $this->get(route('portal.addresses.index'))
            ->assertOk()
            ->assertViewIs('portal.addresses.index')
            ->assertViewHas('addresses');
    });

    it('only shows own addresses', function () {
        $own = CustomerAddress::factory()->for($this->customer)->create();
        $other = CustomerAddress::factory()->for(makeCustomer())->create();

        $response = $this->get(route('portal.addresses.index'))->assertOk();

        $addresses = $response->viewData('addresses');
        expect($addresses->contains($own))->toBeTrue();
        expect($addresses->contains($other))->toBeFalse();
    });

});

// ---------------------------------------------------------------------------
// Create / Store
// ---------------------------------------------------------------------------

describe('create and store', function () {

    beforeEach(function () {
        $this->customer = makeCustomer();
        $this->actingAs($this->customer, 'customer');
    });

    it('shows the create form', function () {
        $this->get(route('portal.addresses.create'))
            ->assertOk()
            ->assertViewIs('portal.addresses.create');
    });

    it('stores a new address', function () {
        $payload = [
            'label' => 'Home',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'address_line1' => '123 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country' => 'US',
        ];

        $this->post(route('portal.addresses.store'), $payload)
            ->assertRedirect(route('portal.addresses.index'));

        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $this->customer->id,
            'label' => 'Home',
        ]);
    });

    it('validates required fields on store', function () {
        $this->post(route('portal.addresses.store'), [])
            ->assertSessionHasErrors(['label', 'first_name', 'last_name', 'address_line1', 'city', 'state', 'postal_code', 'country']);
    });

    it('first address becomes default automatically', function () {
        $this->post(route('portal.addresses.store'), [
            'label' => 'Work',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'address_line1' => '1 Office Rd',
            'city' => 'Houston',
            'state' => 'TX',
            'postal_code' => '77001',
            'country' => 'US',
        ]);

        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $this->customer->id,
            'label' => 'Work',
            'is_default' => true,
        ]);
    });

});

// ---------------------------------------------------------------------------
// Edit / Update
// ---------------------------------------------------------------------------

describe('edit and update', function () {

    beforeEach(function () {
        $this->customer = makeCustomer();
        $this->actingAs($this->customer, 'customer');
        $this->address = CustomerAddress::factory()->for($this->customer)->create(['is_default' => true]);
    });

    it('shows the edit form for own address', function () {
        $this->get(route('portal.addresses.edit', $this->address))
            ->assertOk()
            ->assertViewIs('portal.addresses.edit')
            ->assertViewHas('address');
    });

    it('returns 404 when editing another customers address', function () {
        $other = CustomerAddress::factory()->for(makeCustomer())->create();

        $this->get(route('portal.addresses.edit', $other))
            ->assertNotFound();
    });

    it('updates own address', function () {
        $this->put(route('portal.addresses.update', $this->address), [
            'label' => 'Updated',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'address_line1' => '99 New Ave',
            'city' => 'Dallas',
            'state' => 'TX',
            'postal_code' => '75201',
            'country' => 'US',
        ])->assertRedirect(route('portal.addresses.index'));

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $this->address->id,
            'label' => 'Updated',
            'city' => 'Dallas',
        ]);
    });

    it('returns 404 when updating another customers address', function () {
        $other = CustomerAddress::factory()->for(makeCustomer())->create();

        $this->put(route('portal.addresses.update', $other), [
            'label' => 'Hacked',
            'first_name' => 'Evil',
            'last_name' => 'Actor',
            'address_line1' => '1 Bad St',
            'city' => 'Somewhere',
            'state' => 'TX',
            'postal_code' => '00000',
            'country' => 'US',
        ])->assertNotFound();
    });

    it('validates required fields on update', function () {
        $this->put(route('portal.addresses.update', $this->address), [])
            ->assertSessionHasErrors(['label', 'first_name', 'last_name', 'address_line1', 'city', 'state', 'postal_code', 'country']);
    });

});

// ---------------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------------

describe('destroy', function () {

    beforeEach(function () {
        $this->customer = makeCustomer();
        $this->actingAs($this->customer, 'customer');
    });

    it('deletes a non-default address', function () {
        CustomerAddress::factory()->for($this->customer)->create(['is_default' => true]);
        $address = CustomerAddress::factory()->for($this->customer)->create(['is_default' => false]);

        $this->delete(route('portal.addresses.destroy', $address))
            ->assertRedirect(route('portal.addresses.index'));

        $this->assertSoftDeleted('customer_addresses', ['id' => $address->id]);
    });

    it('cannot delete the default address', function () {
        $default = CustomerAddress::factory()->for($this->customer)->create(['is_default' => true]);

        $this->delete(route('portal.addresses.destroy', $default))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('customer_addresses', ['id' => $default->id, 'deleted_at' => null]);
    });

    it('returns 404 when deleting another customers address', function () {
        $other = CustomerAddress::factory()->for(makeCustomer())->create();

        $this->delete(route('portal.addresses.destroy', $other))
            ->assertNotFound();
    });

});

// ---------------------------------------------------------------------------
// Set Default
// ---------------------------------------------------------------------------

describe('set default', function () {

    beforeEach(function () {
        $this->customer = makeCustomer();
        $this->actingAs($this->customer, 'customer');
    });

    it('sets a non-default address as default', function () {
        $current = CustomerAddress::factory()->for($this->customer)->create(['is_default' => true]);
        $other = CustomerAddress::factory()->for($this->customer)->create(['is_default' => false]);

        $this->patch(route('portal.addresses.setDefault', $other))
            ->assertRedirect(route('portal.addresses.index'));

        $this->assertDatabaseHas('customer_addresses', ['id' => $other->id, 'is_default' => true]);
        $this->assertDatabaseHas('customer_addresses', ['id' => $current->id, 'is_default' => false]);
    });

    it('returns 404 when setting default on another customers address', function () {
        $other = CustomerAddress::factory()->for(makeCustomer())->create();

        $this->patch(route('portal.addresses.setDefault', $other))
            ->assertNotFound();
    });

});
