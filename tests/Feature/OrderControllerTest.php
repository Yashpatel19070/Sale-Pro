<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductListing;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\CustomerAddressPermissionSeeder;
use Database\Seeders\CustomerPermissionSeeder;
use Database\Seeders\OrderPermissionSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(OrderPermissionSeeder::class);
    $this->seed(CustomerPermissionSeeder::class);
    $this->seed(CustomerAddressPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo([
        'orders.viewAny', 'orders.view', 'orders.create', 'orders.update',
        'orders.delete', 'orders.recordPayment', 'orders.complete',
        'customers.viewAny', 'customers.view',
        'customer-addresses.create', 'customer-addresses.view-any',
    ]);

    $this->sales = User::factory()->create();
    $this->sales->givePermissionTo([
        'orders.viewAny', 'orders.view', 'orders.create', 'orders.update',
        'orders.recordPayment', 'orders.complete',
    ]);

    $this->customer = Customer::factory()->create([
        'name' => 'Rachel Park',
        'tax_exempt' => false,
    ]);
    $this->location = InventoryLocation::factory()->create(['name' => 'Warehouse A']);
    $this->product = Product::factory()->create(['sku' => 'ECM-2024', 'name' => 'Engine Control Module']);
    $this->listing = ProductListing::factory()->active()->for($this->product)->create();
    $this->serial = InventorySerial::factory()->inStock()->atLocation($this->location)->forProduct($this->product)->create(['serial_number' => 'SN-200']);
});

function ex19FeaturePayload(int $customerId, int $listingId): array
{
    return [
        'customer_id' => $customerId,
        'source' => 'walk_in',
        'payment_method' => 'cash',
        'billing_address_id' => null,
        'shipping_address_id' => null,
        'shipping' => 0,
        'lines' => [
            [
                'product_listing_id' => $listingId,
                'unit_price' => 200.00,
                'tax_amount' => 16.50,
                'fees' => [
                    ['name' => 'Programming Fee', 'amount' => 40.00, 'tax_amount' => 3.30],
                    ['name' => 'Gas Tuning Fee', 'amount' => 25.00, 'tax_amount' => 2.06],
                ],
            ],
        ],
    ];
}

// ── index ────────────────────────────────────────────────────────────────

it('admin can view orders index', function () {
    $this->actingAs($this->admin)
        ->get(route('orders.index'))
        ->assertOk();
});

it('user without orders.viewAny is forbidden from index', function () {
    $u = User::factory()->create();
    $this->actingAs($u)
        ->get(route('orders.index'))
        ->assertForbidden();
});

// ── create ───────────────────────────────────────────────────────────────

it('admin can view create form', function () {
    $this->actingAs($this->admin)
        ->get(route('orders.create'))
        ->assertOk();
});

it('create form items section uses a proper table layout', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('orders.create'))
        ->assertOk();

    $html = $response->getContent();

    expect($html)->toContain('data-testid="items-table"');
    expect($html)->toContain('<thead');
    expect($html)->toContain('<tbody');
    // Column headers
    foreach (['Product', 'Qty', 'Unit Price', 'Tax', 'Stock', 'Subtotal'] as $col) {
        expect($html)->toContain($col);
    }
});

// ── store ────────────────────────────────────────────────────────────────

it('admin can create walk-in cash order with per-line fees', function () {
    config(['shop.billing.first_name' => 'ACME Tuning']);

    $payload = ex19FeaturePayload($this->customer->id, $this->listing->id);

    $response = $this->actingAs($this->admin)
        ->post(route('orders.store'), $payload);

    $response->assertRedirect();

    $this->assertDatabaseHas('orders', [
        'customer_id' => $this->customer->id,
        'source' => 'walk_in',
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'grand_total' => 286.86,
        'billing_first_name' => 'ACME Tuning',
        'shipping_first_name' => null,
    ]);
    $this->assertDatabaseHas('order_lines', [
        'sku' => 'ECM-2024',
        'unit_price' => 200.00,
        'tax_amount' => 16.50,
        'line_total' => 216.50,
    ]);
    $this->assertDatabaseHas('order_line_fees', [
        'name' => 'Programming Fee',
        'amount' => 40.00,
        'fee_total' => 43.30,
    ]);
    $this->assertDatabaseHas('order_line_fees', [
        'name' => 'Gas Tuning Fee',
        'amount' => 25.00,
        'fee_total' => 27.06,
    ]);
    $this->assertDatabaseMissing('inventory_movements', ['type' => 'sale']);
});

it('store fails when lines empty', function () {
    $payload = ex19FeaturePayload($this->customer->id, $this->listing->id);
    $payload['lines'] = [];

    $this->actingAs($this->admin)
        ->post(route('orders.store'), $payload)
        ->assertSessionHasErrors('lines');
});

it('store fails when customer_id missing', function () {
    $payload = ex19FeaturePayload($this->customer->id, $this->listing->id);
    unset($payload['customer_id']);

    $this->actingAs($this->admin)
        ->post(route('orders.store'), $payload)
        ->assertSessionHasErrors('customer_id');
});

it('user without orders.create cannot post store', function () {
    $u = User::factory()->create();
    $this->actingAs($u)
        ->post(route('orders.store'), ex19FeaturePayload($this->customer->id, $this->listing->id))
        ->assertForbidden();
});

// ── show ─────────────────────────────────────────────────────────────────

it('admin can view show page', function () {
    $order = app(OrderService::class)->store(
        ex19FeaturePayload($this->customer->id, $this->listing->id),
        $this->admin
    );

    $this->actingAs($this->admin)
        ->get(route('orders.show', $order))
        ->assertOk();
});

// ── edit ─────────────────────────────────────────────────────────────────

it('admin can edit pending order', function () {
    $order = app(OrderService::class)->store(
        ex19FeaturePayload($this->customer->id, $this->listing->id),
        $this->admin
    );

    $this->actingAs($this->admin)
        ->get(route('orders.edit', $order))
        ->assertOk();
});

it('edit redirects to show when order not pending', function () {
    $order = app(OrderService::class)->store(
        ex19FeaturePayload($this->customer->id, $this->listing->id),
        $this->admin
    );
    app(OrderService::class)->recordCashPayment($order, ['amount' => 286.86], $this->admin);

    $this->actingAs($this->admin)
        ->get(route('orders.edit', $order->fresh()))
        ->assertRedirect(route('orders.show', $order));
});

// ── update ───────────────────────────────────────────────────────────────

it('admin can update pending order', function () {
    $order = app(OrderService::class)->store(
        ex19FeaturePayload($this->customer->id, $this->listing->id),
        $this->admin
    );

    $payload = ex19FeaturePayload($this->customer->id, $this->listing->id);
    $payload['lines'][0]['unit_price'] = 250.00;
    $payload['lines'][0]['fees'] = [];

    $this->actingAs($this->admin)
        ->put(route('orders.update', $order), $payload)
        ->assertRedirect(route('orders.show', $order));

    $this->assertDatabaseHas('order_lines', ['unit_price' => 250.00]);
});

// ── destroy ──────────────────────────────────────────────────────────────

it('admin can hard delete pending order', function () {
    $order = app(OrderService::class)->store(
        ex19FeaturePayload($this->customer->id, $this->listing->id),
        $this->admin
    );
    $orderId = $order->id;

    $this->actingAs($this->admin)
        ->delete(route('orders.destroy', $order))
        ->assertRedirect(route('orders.index'));

    $this->assertDatabaseMissing('orders', ['id' => $orderId]);
});

it('sales cannot delete order', function () {
    $order = app(OrderService::class)->store(
        ex19FeaturePayload($this->customer->id, $this->listing->id),
        $this->admin
    );

    $this->actingAs($this->sales)
        ->delete(route('orders.destroy', $order))
        ->assertForbidden();
});

// ── recordCashPayment ────────────────────────────────────────────────────

it('admin can record cash payment', function () {
    $order = app(OrderService::class)->store(
        ex19FeaturePayload($this->customer->id, $this->listing->id),
        $this->admin
    );

    $this->actingAs($this->admin)
        ->post(route('orders.cash-payment', $order), ['amount' => 286.86])
        ->assertRedirect(route('orders.show', $order));

    $this->assertDatabaseHas('payments', [
        'order_id' => $order->id,
        'method' => 'cash',
        'status' => 'paid',
        'amount' => 286.86,
    ]);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'processing',
        'payment_status' => 'paid',
    ]);
});

it('record_cash_payment fails when already paid', function () {
    $order = app(OrderService::class)->store(
        ex19FeaturePayload($this->customer->id, $this->listing->id),
        $this->admin
    );
    app(OrderService::class)->recordCashPayment($order, ['amount' => 286.86], $this->admin);

    $this->actingAs($this->admin)
        ->post(route('orders.cash-payment', $order->fresh()), ['amount' => 286.86])
        ->assertForbidden();
});

// ── complete ─────────────────────────────────────────────────────────────

it('admin can complete order', function () {
    $order = app(OrderService::class)->store(
        ex19FeaturePayload($this->customer->id, $this->listing->id),
        $this->admin
    );
    app(OrderService::class)->recordCashPayment($order, ['amount' => 286.86], $this->admin);

    $this->actingAs($this->admin)
        ->post(route('orders.complete', $order->fresh()))
        ->assertRedirect(route('orders.show', $order));

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'complete',
    ]);
    $this->assertDatabaseHas('inventory_movements', [
        'type' => 'sale',
        'reference' => $order->number,
    ]);
});

it('complete fails when not processing', function () {
    $order = app(OrderService::class)->store(
        ex19FeaturePayload($this->customer->id, $this->listing->id),
        $this->admin
    );

    $this->actingAs($this->admin)
        ->post(route('orders.complete', $order))
        ->assertForbidden();
});

// ── helpers ──────────────────────────────────────────────────────────────

it('calculate-tax returns zeros when customer is tax_exempt', function () {
    $this->customer->update(['tax_exempt' => true]);

    $this->actingAs($this->admin)
        ->postJson(route('orders.calculate-tax'), [
            'customer_id' => $this->customer->id,
            'shipping_address' => null,
            'lines' => [
                ['unit_price' => 200, 'sku' => 'ECM-2024', 'fees' => [
                    ['name' => 'Programming Fee', 'amount' => 40],
                ]],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('lines.0.tax_amount', 0);
});

it('customer-addresses returns JSON for given customer', function () {
    $this->actingAs($this->admin)
        ->get(route('orders.customer-addresses', $this->customer))
        ->assertOk()
        ->assertJsonStructure([]);
});

it('listing-stock returns JSON for given listing', function () {
    $this->actingAs($this->admin)
        ->get(route('orders.listing-stock', $this->listing))
        ->assertOk()
        ->assertJsonStructure(['sku', 'stock']);
});

it('receipt shows shop letterhead when shop config is set', function () {
    config([
        'shop.billing.first_name' => 'ACME Tuning',
        'shop.billing.address_line1' => '123 Test St',
        'shop.billing.city' => 'Austin',
        'shop.billing.state' => 'TX',
        'shop.billing.postal_code' => '78701',
        'shop.billing.email' => 'shop@acme.test',
        'shop.billing.phone' => '555-1234',
    ]);

    $payload = ex19FeaturePayload($this->customer->id, $this->listing->id);
    $order = app(OrderService::class)->store($payload, $this->admin);

    $response = $this->actingAs($this->admin)
        ->get(route('orders.receipt', $order))
        ->assertOk();

    $html = $response->getContent();

    expect($html)->toContain('data-testid="shop-letterhead"');
    expect($html)->toContain('ACME Tuning');
    expect($html)->toContain('Austin');
});

it('receipt omits shop letterhead when shop config is unset', function () {
    config([
        'shop.billing.first_name' => null,
        'shop.billing.address_line1' => null,
        'shop.billing.city' => null,
        'shop.billing.state' => null,
        'shop.billing.postal_code' => null,
        'shop.billing.email' => null,
        'shop.billing.phone' => null,
    ]);

    $payload = ex19FeaturePayload($this->customer->id, $this->listing->id);
    $order = app(OrderService::class)->store($payload, $this->admin);

    $response = $this->actingAs($this->admin)
        ->get(route('orders.receipt', $order))
        ->assertOk();

    $html = $response->getContent();

    expect($html)->not->toContain('data-testid="shop-letterhead"');
    expect($html)->not->toContain('NPC Sales Pro LLC');
});

// ── #3 edit form hydration ───────────────────────────────────────────────

it('edit form prefills all fields from existing order', function () {
    $payload = ex19FeaturePayload($this->customer->id, $this->listing->id);
    $order = app(OrderService::class)->store($payload, $this->admin);

    $response = $this->actingAs($this->admin)
        ->get(route('orders.edit', $order))
        ->assertOk();

    $html = $response->getContent();

    // Hydration payload present
    expect($html)->toContain('window.__existingOrder');
    expect($html)->toContain('data-testid="items-table"');

    // Critical fields surface in the JSON blob
    expect($html)->toContain('"customer_id":'.$this->customer->id);
    expect($html)->toContain('"source":"walk_in"');
    expect($html)->toContain('"sku":"ECM-2024"');
    expect($html)->toContain('Programming Fee');
    expect($html)->toContain('Gas Tuning Fee');
});

it('edit form matches snapshot back to billing and shipping address ids', function () {
    $billing = CustomerAddress::factory()->create([
        'customer_id' => $this->customer->id,
        'label' => 'Billing',
        'first_name' => 'Bill',
        'last_name' => 'Person',
        'address_line1' => '11 Billing St',
        'city' => 'Houston',
        'state' => 'TX',
        'postal_code' => '77002',
        'country' => 'US',
    ]);
    $shipping = CustomerAddress::factory()->create([
        'customer_id' => $this->customer->id,
        'label' => 'Shipping',
        'first_name' => 'Ship',
        'last_name' => 'Person',
        'address_line1' => '22 Ship Ave',
        'city' => 'Austin',
        'state' => 'TX',
        'postal_code' => '78701',
        'country' => 'US',
    ]);

    $payload = ex19FeaturePayload($this->customer->id, $this->listing->id);
    $payload['billing_address_id'] = $billing->id;
    $payload['shipping_address_id'] = $shipping->id;

    $order = app(OrderService::class)->store($payload, $this->admin);

    $response = $this->actingAs($this->admin)
        ->get(route('orders.edit', $order))
        ->assertOk();

    $html = $response->getContent();

    expect($html)->toContain('"billing_address_id":'.$billing->id);
    expect($html)->toContain('"shipping_address_id":'.$shipping->id);
});

// ── #4 record payment modal ──────────────────────────────────────────────

it('show page has record payment modal when unpaid', function () {
    $payload = ex19FeaturePayload($this->customer->id, $this->listing->id);
    $order = app(OrderService::class)->store($payload, $this->admin);

    $response = $this->actingAs($this->admin)
        ->get(route('orders.show', $order))
        ->assertOk();

    expect($response->getContent())->toContain('data-testid="record-payment-modal"');
});

it('show page omits record payment modal when paid', function () {
    $payload = ex19FeaturePayload($this->customer->id, $this->listing->id);
    $order = app(OrderService::class)->store($payload, $this->admin);

    app(OrderService::class)->recordCashPayment($order, [
        'amount' => 286.86,
        'cash_received_at' => now()->toDateTimeString(),
    ], $this->admin);

    $response = $this->actingAs($this->admin)
        ->get(route('orders.show', $order->fresh()))
        ->assertOk();

    expect($response->getContent())->not->toContain('data-testid="record-payment-modal"');
});

// ── #5 new-address modal ─────────────────────────────────────────────────

it('create form has new address modal', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('orders.create'))
        ->assertOk();

    $html = $response->getContent();
    expect($html)->toContain('data-testid="new-address-modal"');
    expect($html)->toContain('data-testid="new-address-button"');
});

it('admin can store customer address via json', function () {
    $payload = [
        'customer_id' => $this->customer->id,
        'label' => 'Home',
        'first_name' => 'Test',
        'last_name' => 'User',
        'address_line1' => '123 Test St',
        'city' => 'Austin',
        'state' => 'TX',
        'postal_code' => '78701',
        'country' => 'US',
    ];

    $response = $this->actingAs($this->admin)
        ->postJson(route('orders.customer-addresses.store'), $payload)
        ->assertCreated()
        ->assertJsonStructure(['id', 'label', 'summary', 'address_line1', 'city', 'state', 'postal_code', 'country']);

    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $this->customer->id,
        'address_line1' => '123 Test St',
    ]);
});

it('store customer address returns json errors on validation failure', function () {
    $this->actingAs($this->admin)
        ->postJson(route('orders.customer-addresses.store'), ['customer_id' => $this->customer->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['first_name', 'last_name', 'address_line1', 'city', 'state', 'postal_code', 'country']);
});

it('store customer address rejects truncated lowercase country code Un', function () {
    $payload = [
        'customer_id' => $this->customer->id,
        'label' => 'Home',
        'first_name' => 'Test',
        'last_name' => 'User',
        'address_line1' => '123 Test St',
        'city' => 'Austin',
        'state' => 'TX',
        'postal_code' => '78701',
        'country' => 'Un',
    ];

    $this->actingAs($this->admin)
        ->postJson(route('orders.customer-addresses.store'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['country']);
});

it('calculate-tax rejects non-iso2 country in shipping_address', function () {
    $this->actingAs($this->admin)
        ->postJson(route('orders.calculate-tax'), [
            'customer_id' => $this->customer->id,
            'lines' => [['unit_price' => 100.00]],
            'shipping_address' => [
                'address_line1' => '1 A',
                'city' => 'Houston',
                'state' => 'TX',
                'postal_code' => '77091',
                'country' => 'Un',
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['shipping_address.country']);
});

// ── #7 Texas seeder ──────────────────────────────────────────────────────

it('order seeder creates texas test buyer with tx address', function () {
    // Need full dependency chain — seed minimal then OrderSeeder
    $this->seed(OrderSeeder::class);

    $tx = Customer::where('email', 'texas@example.com')->first();

    expect($tx)->not->toBeNull();
    expect($tx->name)->toBe('Texas Test Buyer');
    expect($tx->tax_exempt)->toBeFalse();

    $addr = $tx->addresses()->first();
    expect($addr)->not->toBeNull();
    expect($addr->state)->toBe('TX');
    expect($addr->city)->toBe('Austin');
    expect($addr->postal_code)->toBe('78701');
});

it('order seeder is registered in database seeder', function () {
    $database = file_get_contents(database_path('seeders/DatabaseSeeder.php'));
    expect($database)->toContain('OrderSeeder::class');
});

it('order seeder source guards by environment', function () {
    $source = file_get_contents(database_path('seeders/OrderSeeder.php'));
    expect($source)->toContain("environment(['local', 'testing'])");
});

// ── Security: cross-customer IDOR (CRIT-1) ───────────────────────────────

it('store order rejects billing address id belonging to a different customer', function () {
    $otherCustomer = Customer::factory()->create();
    $otherAddress = CustomerAddress::factory()->create([
        'customer_id' => $otherCustomer->id,
        'label' => 'Other',
    ]);

    $payload = ex19FeaturePayload($this->customer->id, $this->listing->id);
    $payload['billing_address_id'] = $otherAddress->id;

    $this->actingAs($this->admin)
        ->post(route('orders.store'), $payload)
        ->assertSessionHasErrors('billing_address_id');
});

it('store order rejects shipping address id belonging to a different customer', function () {
    $otherCustomer = Customer::factory()->create();
    $otherAddress = CustomerAddress::factory()->create([
        'customer_id' => $otherCustomer->id,
        'label' => 'Other',
    ]);

    $payload = ex19FeaturePayload($this->customer->id, $this->listing->id);
    $payload['shipping_address_id'] = $otherAddress->id;

    $this->actingAs($this->admin)
        ->post(route('orders.store'), $payload)
        ->assertSessionHasErrors('shipping_address_id');
});

it('update order rejects billing address id belonging to a different customer', function () {
    $payload = ex19FeaturePayload($this->customer->id, $this->listing->id);
    $order = app(OrderService::class)->store($payload, $this->admin);

    $otherCustomer = Customer::factory()->create();
    $otherAddress = CustomerAddress::factory()->create([
        'customer_id' => $otherCustomer->id,
        'label' => 'Other',
    ]);

    $update = $payload;
    $update['billing_address_id'] = $otherAddress->id;

    $this->actingAs($this->admin)
        ->put(route('orders.update', $order), $update)
        ->assertSessionHasErrors('billing_address_id');
});

// ── Security: storeCustomerAddress permission (HIGH-1) ───────────────────

it('store customer address requires customer-addresses create permission', function () {
    $userMissingAddrPerm = User::factory()->create();
    $userMissingAddrPerm->givePermissionTo(['orders.create']);

    $payload = [
        'customer_id' => $this->customer->id,
        'label' => 'Home',
        'first_name' => 'X',
        'last_name' => 'Y',
        'address_line1' => '1 Test',
        'city' => 'Austin',
        'state' => 'TX',
        'postal_code' => '78701',
        'country' => 'US',
    ];

    $this->actingAs($userMissingAddrPerm)
        ->postJson(route('orders.customer-addresses.store'), $payload)
        ->assertForbidden();
});

// ── Security: customerAddresses endpoint view auth (HIGH) ────────────────

it('customer-addresses endpoint requires customers view permission', function () {
    $userNoCustomerView = User::factory()->create();
    $userNoCustomerView->givePermissionTo(['orders.viewAny']);

    $this->actingAs($userNoCustomerView)
        ->get(route('orders.customer-addresses', $this->customer))
        ->assertForbidden();
});

// ── Security: calculateTax FormRequest (CRIT-1 code-review) ──────────────

it('calculate tax rejects empty payload via FormRequest', function () {
    $this->actingAs($this->admin)
        ->postJson(route('orders.calculate-tax'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id', 'lines']);
});

it('calculate tax rejects too-many-lines payload', function () {
    $lines = [];
    for ($i = 0; $i < 60; $i++) {
        $lines[] = ['unit_price' => 1, 'sku' => 'X'];
    }

    $this->actingAs($this->admin)
        ->postJson(route('orders.calculate-tax'), [
            'customer_id' => $this->customer->id,
            'shipping_address' => null,
            'lines' => $lines,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['lines']);
});

// ── Security: Order mass-assignment guard (HIGH) ─────────────────────────

it('order mass assignment cannot set status payment_status or grand_total', function () {
    $order = new Order;
    $order->fill([
        'status' => 'complete',
        'payment_status' => 'paid',
        'grand_total' => 99999.99,
    ]);

    // None of these should be in $fillable post-fix
    expect($order->getAttributes())->not->toHaveKey('status');
    expect($order->getAttributes())->not->toHaveKey('payment_status');
    expect($order->getAttributes())->not->toHaveKey('grand_total');
});

// ── Security: Payment mass-assignment guard (HIGH) ───────────────────────

it('payment mass assignment cannot set created_by from request', function () {
    $payment = new Payment;
    $payment->fill(['created_by' => 999]);

    expect($payment->getAttributes())->not->toHaveKey('created_by');
});

// ── MEDIUM #1: payment amount max ceiling ────────────────────────────────

it('record cash payment rejects absurd amount via FormRequest', function () {
    $payload = ex19FeaturePayload($this->customer->id, $this->listing->id);
    $order = app(OrderService::class)->store($payload, $this->admin);

    $this->actingAs($this->admin)
        ->post(route('orders.cash-payment', $order), ['amount' => 100000000])
        ->assertSessionHasErrors('amount');
});

// ── MEDIUM #2: calculateTax throttle ─────────────────────────────────────

it('calculate tax endpoint is throttled', function () {
    $payload = [
        'customer_id' => $this->customer->id,
        'lines' => [['unit_price' => 10, 'sku' => 'X']],
    ];

    for ($i = 0; $i < 30; $i++) {
        $this->actingAs($this->admin)
            ->postJson(route('orders.calculate-tax'), $payload)
            ->assertOk();
    }

    $this->actingAs($this->admin)
        ->postJson(route('orders.calculate-tax'), $payload)
        ->assertStatus(429);
});

// ── MEDIUM #7: calculateTax feature coverage ─────────────────────────────

it('calculate tax returns zeros when avatax is disabled', function () {
    config(['avatax.enabled' => false]);

    $payload = [
        'customer_id' => $this->customer->id,
        'lines' => [
            ['unit_price' => 200, 'sku' => 'ECM-2024', 'fees' => [
                ['name' => 'Programming Fee', 'amount' => 40],
            ]],
        ],
    ];

    $this->actingAs($this->admin)
        ->postJson(route('orders.calculate-tax'), $payload)
        ->assertOk()
        ->assertJsonPath('lines.0.tax_amount', 0)
        ->assertJsonPath('lines.0.fees.0.tax_amount', 0);
});

it('calculate tax returns zeros for tax-exempt customer', function () {
    $exempt = Customer::factory()->create(['tax_exempt' => true]);

    $payload = [
        'customer_id' => $exempt->id,
        'lines' => [['unit_price' => 100, 'sku' => 'X']],
    ];

    $this->actingAs($this->admin)
        ->postJson(route('orders.calculate-tax'), $payload)
        ->assertOk()
        ->assertJsonPath('lines.0.tax_amount', 0);
});

it('calculate tax requires orders viewAny permission', function () {
    $blocked = User::factory()->create();

    $this->actingAs($blocked)
        ->postJson(route('orders.calculate-tax'), [
            'customer_id' => $this->customer->id,
            'lines' => [['unit_price' => 1, 'sku' => 'X']],
        ])
        ->assertForbidden();
});

it('store customer address rejects soft-deleted customer id', function () {
    $deleted = Customer::factory()->create();
    $deleted->delete();

    $this->actingAs($this->admin)
        ->postJson(route('orders.customer-addresses.store'), [
            'customer_id' => $deleted->id,
            'label' => 'Home',
            'first_name' => 'X',
            'last_name' => 'Y',
            'address_line1' => '1 Test',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country' => 'US',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('customer_id');
});

it('calculate-tax passes entityUseCode to AvaTax for tax-exempt customer', function () {
    $this->customer->update(['tax_exempt' => true, 'entity_use_code' => 'G']);

    $spy = Mockery::mock(App\Services\AvaTaxService::class);
    $spy->shouldReceive('calculateTax')
        ->once()
        ->withArgs(function ($items, $shipTo, $customerCode, $entityUseCode = null) {
            return $entityUseCode === 'G';
        })
        ->andReturn([['tax_rate' => 0, 'tax_amount' => 0]]);
    app()->instance(App\Services\AvaTaxService::class, $spy);

    $this->actingAs($this->admin)
        ->postJson(route('orders.calculate-tax'), [
            'customer_id' => $this->customer->id,
            'shipping_address' => [
                'address_line1' => '1 A', 'city' => 'Houston', 'state' => 'TX',
                'postal_code' => '77091', 'country' => 'US',
            ],
            'lines' => [['unit_price' => 200, 'sku' => 'ECM-2024']],
        ])
        ->assertOk();
});
