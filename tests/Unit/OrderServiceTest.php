<?php

declare(strict_types=1);

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SerialStatus;
use App\Models\Customer;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductListing;
use App\Models\User;
use App\Services\InventoryMovementService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function orderService(): OrderService
{
    return new OrderService(app(InventoryMovementService::class));
}

function walkInCashPayload(Customer $customer, ProductListing $listing): array
{
    return [
        'customer_id' => $customer->id,
        'source' => OrderSource::WalkIn->value,
        'payment_method' => PaymentMethod::Cash->value,
        'shipping_address_id' => null,
        'shipping' => 0,
        'lines' => [
            [
                'product_listing_id' => $listing->id,
                'unit_price' => 170.00,
                'tax_rate' => 0,
            ],
        ],
        'fees' => [
            ['name' => 'Service Fee', 'amount' => 15.00],
        ],
    ];
}

// ── paginate() ────────────────────────────────────────────────────────────────

it('it_returns_paginated_orders', function () {
    Order::factory()->count(3)->create();

    $result = orderService()->paginate([]);

    expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($result->total())->toBe(3);
});

it('it_filters_by_status', function () {
    Order::factory()->pending()->create();
    Order::factory()->processing()->create();

    $result = orderService()->paginate(['status' => 'pending']);

    expect($result->total())->toBe(1);
    expect($result->first()->status)->toBe(OrderStatus::Pending);
});

it('it_filters_by_source', function () {
    Order::factory()->walkin()->create();
    Order::factory()->state(['source' => OrderSource::Online])->create();

    $result = orderService()->paginate(['source' => 'walk_in']);

    expect($result->total())->toBe(1);
    expect($result->first()->source)->toBe(OrderSource::WalkIn);
});

// ── generateNumber() ─────────────────────────────────────────────────────────

it('it_generates_order_number_in_correct_format', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    $serial = InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    expect($order->number)->toMatch('/^ORD-\d{4}-\d{4}$/');
});

it('it_increments_order_number_on_each_call', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $user = User::factory()->create();
    $location = InventoryLocation::factory()->create();

    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->count(2)->create();

    $first = orderService()->store(walkInCashPayload($customer, $listing), $user);
    $second = orderService()->store(walkInCashPayload($customer, $listing), $user);

    $year = now()->year;
    expect($first->number)->toBe("ORD-{$year}-0001");
    expect($second->number)->toBe("ORD-{$year}-0002");
});

// ── store() ───────────────────────────────────────────────────────────────────

it('it_creates_order_with_walk_in_source', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    $serial = InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    expect($order->source)->toBe(OrderSource::WalkIn);
    expect($order->status)->toBe(OrderStatus::Pending);
    expect($order->payment_status)->toBe(PaymentStatus::Unpaid);
    expect($order->created_by)->toBe($user->id);
});

it('it_sets_billing_snapshot_to_null_for_cash_payment', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    expect($order->billing_first_name)->toBeNull();
    expect($order->billing_last_name)->toBeNull();
    expect($order->billing_email)->toBeNull();
    expect($order->billing_phone)->toBeNull();
    expect($order->billing_address_line1)->toBeNull();
    expect($order->billing_city)->toBeNull();
    expect($order->billing_state)->toBeNull();
    expect($order->billing_postal_code)->toBeNull();
    expect($order->billing_country)->toBeNull();
});

it('it_sets_shipping_snapshot_to_null_for_instore_pickup', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    expect($order->shipping_first_name)->toBeNull();
    expect($order->shipping_last_name)->toBeNull();
    expect($order->shipping_email)->toBeNull();
    expect($order->shipping_address_line1)->toBeNull();
    expect($order->shipping_city)->toBeNull();
    expect($order->shipping_country)->toBeNull();
});

it('it_sets_shipped_at_and_shipped_by_to_null', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    expect($order->shipped_at)->toBeNull();
    expect($order->shipped_by)->toBeNull();
});

it('it_calculates_subtotal_fees_and_grand_total_correctly', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    expect((float) $order->subtotal)->toBe(170.0);
    expect((float) $order->fees)->toBe(15.0);
    expect((float) $order->shipping)->toBe(0.0);
    expect((float) $order->grand_total)->toBe(185.0);
});

it('it_creates_order_line_with_null_serial_on_store', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    $this->assertDatabaseHas('order_lines', [
        'order_id' => $order->id,
        'inventory_serial_id' => null,
    ]);
});

it('it_snapshots_sku_and_product_name_on_order_line', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    $this->assertDatabaseHas('order_lines', [
        'order_id' => $order->id,
        'product_listing_id' => $listing->id,
        'sku' => $product->sku,
        'product_name' => $product->name,
    ]);
});

it('it_creates_order_fee_row', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    $this->assertDatabaseHas('order_fees', [
        'order_id' => $order->id,
        'name' => 'Service Fee',
        'amount' => 15.00,
    ]);
});

it('it_does_not_create_inventory_movement_on_store', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    orderService()->store(walkInCashPayload($customer, $listing), $user);

    $this->assertDatabaseCount('inventory_movements', 0);
});

it('it_does_not_change_serial_status_on_store', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    $serial = InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    orderService()->store(walkInCashPayload($customer, $listing), $user);

    expect($serial->fresh()->status)->toBe(SerialStatus::InStock);
});

it('it_assigns_serial_on_cash_payment', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    $serial = InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    $line = OrderLine::where('order_id', $order->id)->first();
    expect($line->inventory_serial_id)->toBeNull();

    orderService()->recordCashPayment($order, ['amount' => 185.00], $user);

    $line->refresh();
    expect($line->inventory_serial_id)->toBe($serial->id);
    expect($serial->fresh()->status)->toBe(SerialStatus::Sold);
    $this->assertDatabaseCount('inventory_movements', 1);
});

it('it_throws_if_serial_status_is_not_in_stock', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->sold()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    expect(fn () => orderService()->recordCashPayment($order, ['amount' => 185.00], $user))
        ->toThrow(DomainException::class);
});

it('it_records_order_placed_event', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['sku' => 'PROD-C', 'name' => 'Widget Basic']);
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    $event = OrderEvent::where('order_id', $order->id)->where('event', 'order_placed')->first();
    expect($event)->not->toBeNull();
    expect($event->metadata['sku'])->toBe('PROD-C');
    expect($event->metadata['product_name'])->toBe('Widget Basic');
    expect($event->metadata['grand_total'])->toBe('185.00');
    expect($event->created_by)->toBe($user->id);
});

it('it_rolls_back_order_if_line_creation_fails', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    // Pass invalid listing id to force failure inside transaction
    $payload = walkInCashPayload($customer, $listing);
    $payload['lines'][0]['product_listing_id'] = 99999;

    try {
        orderService()->store($payload, $user);
    } catch (Throwable) {
        // expected
    }

    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('order_events', 0);
});

// ── update() ─────────────────────────────────────────────────────────────────

it('it_updates_order_when_status_is_pending', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->count(2)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    $updated = orderService()->update($order, [
        'customer_id' => $customer->id,
        'source' => 'walk_in',
        'payment_method' => 'cash',
        'shipping' => 10.00,
        'lines' => [['product_listing_id' => $listing->id, 'unit_price' => 200.00, 'tax_rate' => 0]],
        'fees' => [],
    ]);

    expect((float) $updated->shipping)->toBe(10.0);
    expect((float) $updated->subtotal)->toBe(200.0);
    expect((float) $updated->grand_total)->toBe(210.0);
});

it('it_throws_when_order_is_not_pending_on_update', function () {
    $order = Order::factory()->processing()->create();

    expect(fn () => orderService()->update($order, [
        'shipping' => 0,
        'lines' => [['product_listing_id' => 1, 'unit_price' => 100, 'tax_rate' => 0]],
        'fees' => [],
    ]))->toThrow(DomainException::class);
});

// ── delete() ─────────────────────────────────────────────────────────────────

it('it_deletes_order_when_status_is_pending', function () {
    $order = Order::factory()->pending()->create();

    orderService()->delete($order);

    $this->assertSoftDeleted('orders', ['id' => $order->id]);
});

it('it_throws_when_order_is_not_pending_on_delete', function () {
    $order = Order::factory()->processing()->create();

    expect(fn () => orderService()->delete($order))
        ->toThrow(DomainException::class);
});

// ── recordCashPayment() ───────────────────────────────────────────────────────

it('it_creates_cash_payment_row', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();
    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    orderService()->recordCashPayment($order, ['amount' => 185.00], $user);

    $this->assertDatabaseHas('payments', [
        'order_id' => $order->id,
        'method' => PaymentMethod::Cash->value,
        'status' => PaymentStatus::Paid->value,
        'amount' => 185.00,
        'created_by' => $user->id,
    ]);

    $payment = Payment::where('order_id', $order->id)->first();
    expect($payment->cash_received_at)->not->toBeNull();
});

it('it_sets_order_payment_status_to_paid', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();
    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    orderService()->recordCashPayment($order, ['amount' => 185.00], $user);

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('it_advances_order_to_processing_when_all_serials_assigned', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();
    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    orderService()->recordCashPayment($order, ['amount' => 185.00], $user);

    expect($order->fresh()->status)->toBe(OrderStatus::Processing);
});

it('it_throws_when_no_serial_available_on_payment', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $user = User::factory()->create();

    // Store with no serials in stock — creates line with null serial
    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    // Payment should throw because no in-stock serial can be assigned
    expect(fn () => orderService()->recordCashPayment($order, ['amount' => 185.00], $user))
        ->toThrow(DomainException::class);
});

it('it_throws_if_order_already_paid', function () {
    $order = Order::factory()->state(['payment_status' => PaymentStatus::Paid])->create();
    $user = User::factory()->create();

    expect(fn () => orderService()->recordCashPayment($order, ['amount' => 100.00], $user))
        ->toThrow(DomainException::class);
});

it('it_creates_inventory_movement_on_full_payment', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create(['name' => 'Warehouse A']);
    $serial = InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();
    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    orderService()->recordCashPayment($order, ['amount' => 185.00], $user);

    $this->assertDatabaseHas('inventory_movements', [
        'inventory_serial_id' => $serial->id,
        'type' => 'sale',
        'from_location_id' => $location->id,
        'to_location_id' => null,
        'reference' => $order->number,
    ]);
});

it('it_marks_serial_as_sold_on_full_payment', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    $serial = InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();
    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    orderService()->recordCashPayment($order, ['amount' => 185.00], $user);

    expect($serial->fresh()->status)->toBe(SerialStatus::Sold);
});

it('it_records_partial_payment_with_partial_status', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $user = User::factory()->create();
    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    orderService()->recordCashPayment($order, ['amount' => 100.00], $user);

    $payment = Payment::where('order_id', $order->id)->first();
    expect($payment->status)->toBe(PaymentStatus::Partial);
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Partial);
});

it('it_does_not_assign_serial_on_partial_payment', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();
    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    orderService()->recordCashPayment($order, ['amount' => 100.00], $user);

    $line = OrderLine::where('order_id', $order->id)->first();
    expect($line->inventory_serial_id)->toBeNull();
});

it('it_does_not_create_inventory_movement_on_partial_payment', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();
    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    orderService()->recordCashPayment($order, ['amount' => 100.00], $user);

    $this->assertDatabaseCount('inventory_movements', 0);
});

it('it_does_not_change_serial_status_on_partial_payment', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    $serial = InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();
    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    orderService()->recordCashPayment($order, ['amount' => 100.00], $user);

    expect($serial->fresh()->status)->toBe(SerialStatus::InStock);
});

it('it_does_not_advance_order_status_on_partial_payment', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $user = User::factory()->create();
    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    orderService()->recordCashPayment($order, ['amount' => 100.00], $user);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('it_records_payment_received_event', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();
    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);

    orderService()->recordCashPayment($order, ['amount' => 185.00], $user);

    $event = OrderEvent::where('order_id', $order->id)->where('event', 'payment_received')->first();
    expect($event)->not->toBeNull();
    expect($event->metadata['method'])->toBe('cash');
    expect($event->metadata['amount'])->toBe('185.00');
    expect($event->metadata['subtotal'])->toBe('170.00');
    expect($event->metadata['fees'])->toBe('15.00');
    expect($event->metadata['shipping'])->toBe('0.00');
    expect($event->created_by)->toBe($user->id);
});

// ── complete() ────────────────────────────────────────────────────────────────

it('it_sets_order_status_to_complete', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);
    orderService()->recordCashPayment($order, ['amount' => 185.00], $user);
    $result = orderService()->complete($order->fresh(), $user);

    expect($result->status)->toBe(OrderStatus::Complete);
});

it('it_does_not_create_shipment_row', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);
    orderService()->recordCashPayment($order, ['amount' => 185.00], $user);
    orderService()->complete($order->fresh(), $user);

    $this->assertDatabaseCount('shipments', 0);
});

it('it_throws_if_order_is_not_processing', function () {
    $order = Order::factory()->pending()->create();
    $user = User::factory()->create();

    expect(fn () => orderService()->complete($order, $user))
        ->toThrow(DomainException::class);
});

it('it_does_not_touch_inventory_on_complete', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    $serial = InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);
    orderService()->recordCashPayment($order, ['amount' => 185.00], $user);

    // At this point movement already created and serial is sold
    $movementCount = InventoryMovement::count();

    orderService()->complete($order->fresh(), $user);

    // complete() must not add more movements or change serial
    $this->assertDatabaseCount('inventory_movements', $movementCount);
    expect($serial->fresh()->status)->toBe(SerialStatus::Sold);
});

it('it_records_completed_event', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);
    orderService()->recordCashPayment($order, ['amount' => 185.00], $user);
    orderService()->complete($order->fresh(), $user);

    $event = OrderEvent::where('order_id', $order->id)->where('event', 'completed')->first();
    expect($event)->not->toBeNull();
    expect($event->created_by)->toBe($user->id);
});

it('it_rolls_back_if_movement_creation_fails', function () {
    // Bypass normal flow: create a pending order with a sold serial already on the line.
    // assignSerialsToLines() skips lines that already have a serial_id, so it reaches
    // recordSaleMovements() which calls recordSale() — that throws because serial is sold.
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    $serial = InventorySerial::factory()->sold()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = Order::factory()->state([
        'customer_id' => $customer->id,
        'status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
        'grand_total' => 185.00,
    ])->create();
    OrderLine::factory()->for($order)->state(['inventory_serial_id' => $serial->id])->create();

    try {
        orderService()->recordCashPayment($order, ['amount' => 185.00], $user);
    } catch (Throwable) {
        // expected
    }

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Unpaid);
    $this->assertDatabaseMissing('order_events', ['order_id' => $order->id, 'event' => 'payment_received']);
    $this->assertDatabaseCount('inventory_movements', 0);
});

// ── Full lifecycle ────────────────────────────────────────────────────────────

it('it_shows_correct_serial_status_at_each_stage', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    $serial = InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);
    expect($serial->fresh()->status)->toBe(SerialStatus::InStock);
    $this->assertDatabaseCount('inventory_movements', 0);

    orderService()->recordCashPayment($order, ['amount' => 185.00], $user);
    expect($serial->fresh()->status)->toBe(SerialStatus::Sold);
    $this->assertDatabaseCount('inventory_movements', 1);

    orderService()->complete($order->fresh(), $user);
    expect($serial->fresh()->status)->toBe(SerialStatus::Sold);
    $this->assertDatabaseCount('inventory_movements', 1);
});

it('it_shows_correct_order_events_at_each_stage', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create();
    $listing = ProductListing::factory()->active()->for($product)->create();
    $location = InventoryLocation::factory()->create();
    InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
    $user = User::factory()->create();

    $order = orderService()->store(walkInCashPayload($customer, $listing), $user);
    expect(OrderEvent::where('order_id', $order->id)->pluck('event')->toArray())
        ->toBe(['order_placed']);

    orderService()->recordCashPayment($order, ['amount' => 185.00], $user);
    expect(OrderEvent::where('order_id', $order->id)->pluck('event')->toArray())
        ->toBe(['order_placed', 'payment_received']);

    orderService()->complete($order->fresh(), $user);
    expect(OrderEvent::where('order_id', $order->id)->pluck('event')->toArray())
        ->toBe(['order_placed', 'payment_received', 'completed']);
});
