<?php

declare(strict_types=1);

use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SerialStatus;
use App\Enums\ShipmentStatus;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\OrderFee;
use App\Models\Payment;
use App\Models\User;
use App\Services\AvaTaxService;
use App\Services\OrderService;
use Database\Seeders\OrderPermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(OrderPermissionSeeder::class);
    DB::table('sequences')->insertOrIgnore(['name' => 'orders', 'value' => 0]);

    // Default: AvaTax returns zero tax so totals in non-AvaTax tests stay deterministic.
    // AvaTax-specific tests override this mock inside their own test body.
    $this->mock(AvaTaxService::class)
        ->shouldReceive('calculateTax')
        ->andReturnUsing(fn (array $lines) => array_fill_keys(
            array_keys($lines),
            ['tax_rate' => 0.0, 'tax_amount' => 0.0]
        ));

    $this->service = app(OrderService::class);
    $this->actor = User::factory()->create()->assignRole('admin');
});

// ── Helpers ──────────────────────────────────────────────────────────────────

function orderMakeSerial(): InventorySerial
{
    return InventorySerial::factory()->inStock()->create();
}

function orderBasePayload(int $customerId, int $serialId, int $addressId = 0): array
{
    $address = $addressId
        ? ['address_id' => $addressId]
        : [
            'first_name' => 'Mike',
            'last_name' => 'Torres',
            'email' => 'mike@example.com',
            'phone' => '555-100-0002',
            'line1' => '456 Oak Avenue',
            'line2' => null,
            'city' => 'Houston',
            'state' => 'TX',
            'postal_code' => '77001',
            'country' => 'US',
        ];

    return [
        'customer_id' => $customerId,
        'source' => 'walk_in',
        'shipping_amount' => 15.00,
        'lines' => [
            ['serial_id' => $serialId, 'unit_price' => 200.00, 'tax_rate' => 0.0],
        ],
        'fees' => [
            ['name' => 'Service Fee', 'amount' => 30.00],
        ],
        'shipping' => $address,
    ];
}

// ── paginate() ───────────────────────────────────────────────────────────────

it('paginates orders with no filters', function () {
    Order::factory()->count(3)->create();

    $result = $this->service->paginate([]);

    expect($result->total())->toBe(3);
});

it('filters orders by status', function () {
    Order::factory()->create(['status' => OrderStatus::Pending]);
    Order::factory()->create(['status' => OrderStatus::Shipped]);

    $result = $this->service->paginate(['status' => 'pending']);

    expect($result->total())->toBe(1);
});

it('filters orders by search on order number', function () {
    $target = Order::factory()->create(['number' => 'ORD-2026-0001']);
    Order::factory()->create(['number' => 'ORD-2026-0002']);

    $result = $this->service->paginate(['search' => 'ORD-2026-0001']);

    expect($result->total())->toBe(1)
        ->and($result->first()->id)->toBe($target->id);
});

it('filters orders by search on customer name', function () {
    $customer = Customer::factory()->create(['name' => 'UniqueCustomerXYZ']);
    Order::factory()->create(['customer_id' => $customer->id]);
    Order::factory()->create();

    $result = $this->service->paginate(['search' => 'UniqueCustomerXYZ']);

    expect($result->total())->toBe(1);
});

// ── create() ─────────────────────────────────────────────────────────────────

it('creates order with correct totals and status', function () {
    $customer = Customer::factory()->create();
    $serial = orderMakeSerial();

    $order = $this->service->create(orderBasePayload($customer->id, $serial->id), $this->actor);

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->payment_status)->toBe('unpaid')
        ->and($order->subtotal)->toBe('200.00')
        ->and($order->fees)->toBe('30.00')
        ->and($order->shipping)->toBe('15.00')
        ->and($order->grand_total)->toBe('245.00');
});

it('generates order number in ORD-YYYY-NNNN format', function () {
    $customer = Customer::factory()->create();
    $serial = orderMakeSerial();

    $order = $this->service->create(orderBasePayload($customer->id, $serial->id), $this->actor);

    expect($order->number)->toMatch('/^ORD-\d{4}-\d{4}$/');
});

it('creates order_lines row with correct line_total', function () {
    $customer = Customer::factory()->create();
    $serial = orderMakeSerial();

    $order = $this->service->create(orderBasePayload($customer->id, $serial->id), $this->actor);

    $this->assertDatabaseHas('order_lines', [
        'order_id' => $order->id,
        'inventory_serial_id' => $serial->id,
        'unit_price' => 200.00,
        'line_total' => 200.00,
    ]);
});

it('creates order_fees row', function () {
    $customer = Customer::factory()->create();
    $serial = orderMakeSerial();

    $order = $this->service->create(orderBasePayload($customer->id, $serial->id), $this->actor);

    $this->assertDatabaseHas('order_fees', [
        'order_id' => $order->id,
        'name' => 'Service Fee',
        'amount' => 30.00,
    ]);
});

it('creates new CustomerAddress when inline address provided', function () {
    $customer = Customer::factory()->create();
    $serial = orderMakeSerial();

    $this->service->create(orderBasePayload($customer->id, $serial->id), $this->actor);

    $this->assertDatabaseHas('customer_addresses', [
        'customer_id' => $customer->id,
        'address_line1' => '456 Oak Avenue',
    ]);
});

it('reuses existing CustomerAddress when address_id provided', function () {
    $customer = Customer::factory()->create();
    $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);
    $serial = orderMakeSerial();

    $addressCount = CustomerAddress::count();

    $this->service->create(orderBasePayload($customer->id, $serial->id, $address->id), $this->actor);

    expect(CustomerAddress::count())->toBe($addressCount);
});

it('copies shipping snapshot onto order from address', function () {
    $customer = Customer::factory()->create();
    $serial = orderMakeSerial();

    $order = $this->service->create(orderBasePayload($customer->id, $serial->id), $this->actor);

    expect($order->shipping_first_name)->toBe('Mike')
        ->and($order->shipping_address_line1)->toBe('456 Oak Avenue');
});

it('sets billing snapshot to null for cash orders', function () {
    $customer = Customer::factory()->create();
    $serial = orderMakeSerial();

    $order = $this->service->create(orderBasePayload($customer->id, $serial->id), $this->actor);

    expect($order->billing_first_name)->toBeNull()
        ->and($order->billing_address_line1)->toBeNull();
});

it('creates order with no fees when fees absent', function () {
    $customer = Customer::factory()->create();
    $serial = orderMakeSerial();
    $payload = orderBasePayload($customer->id, $serial->id);
    unset($payload['fees']);

    $order = $this->service->create($payload, $this->actor);

    expect((float) $order->fees)->toBe(0.0)
        ->and($order->orderFees()->count())->toBe(0);
});

// ── recordCashPayment() ───────────────────────────────────────────────────────

it('creates payment row with method=cash and status=paid', function () {
    $order = Order::factory()->create(['payment_status' => 'unpaid', 'grand_total' => 245.00]);

    $payment = $this->service->recordCashPayment($order, [
        'amount' => 245.00,
        'cash_received_at' => now()->toDateTimeString(),
    ], $this->actor);

    expect($payment)->toBeInstanceOf(Payment::class)
        ->and($payment->method)->toBe(PaymentMethod::Cash)
        ->and($payment->status)->toBe(PaymentStatus::Paid);
});

it('sets payable_type to order on cash payment', function () {
    $order = Order::factory()->create(['payment_status' => 'unpaid', 'grand_total' => 100.00]);

    $payment = $this->service->recordCashPayment($order, [
        'amount' => 100.00,
        'cash_received_at' => now()->toDateTimeString(),
    ], $this->actor);

    expect($payment->payable_type)->toBe('order')
        ->and($payment->payable_id)->toBe($order->id);
});

it('updates order to payment_status=paid and status=processing', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending, 'payment_status' => 'unpaid']);

    $this->service->recordCashPayment($order, [
        'amount' => $order->grand_total,
        'cash_received_at' => now()->toDateTimeString(),
    ], $this->actor);

    expect($order->fresh()->payment_status)->toBe('paid')
        ->and($order->fresh()->status)->toBe(OrderStatus::Processing);
});

it('throws DomainException when paying non-pending order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Processing]);

    expect(fn () => $this->service->recordCashPayment($order, [
        'amount' => 100.00,
        'cash_received_at' => now()->toDateTimeString(),
    ], $this->actor))->toThrow(DomainException::class);
});

// ── ship() ────────────────────────────────────────────────────────────────────

it('creates shipment with direction=outbound and status=in_transit', function () {
    $order = Order::factory()->withLines(1)->create(['status' => OrderStatus::Processing]);
    $serial = $order->lines->first()->serial;

    $this->service->ship($order, [
        'carrier' => 'FedEx',
        'tracking' => 'FX-10002',
        'label_cost' => 12.00,
        'shipped_at' => now()->toDateTimeString(),
    ], $this->actor);

    $this->assertDatabaseHas('shipments', [
        'shippable_id' => $order->id,
        'direction' => 'outbound',
        'carrier' => 'FedEx',
        'status' => 'in_transit',
    ]);
});

it('creates InventoryMovement of type sale for each line', function () {
    $order = Order::factory()->withLines(2)->create(['status' => OrderStatus::Processing]);

    $this->service->ship($order, [
        'carrier' => 'FedEx',
        'tracking' => 'FX-10002',
        'label_cost' => 12.00,
        'shipped_at' => now()->toDateTimeString(),
    ], $this->actor);

    expect($order->lines->count())->toBe(2);

    foreach ($order->lines as $line) {
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_serial_id' => $line->inventory_serial_id,
            'type' => MovementType::Sale->value,
        ]);
    }
});

it('updates serials to status=sold', function () {
    $order = Order::factory()->withLines(1)->create(['status' => OrderStatus::Processing]);
    $serial = $order->lines->first()->serial;

    $this->service->ship($order, [
        'carrier' => 'FedEx',
        'tracking' => 'FX-10002',
        'label_cost' => 12.00,
        'shipped_at' => now()->toDateTimeString(),
    ], $this->actor);

    expect($serial->fresh()->status)->toBe(SerialStatus::Sold);
});

it('updates order to status=shipped with shipped_at and shipped_by', function () {
    $order = Order::factory()->withLines(1)->create(['status' => OrderStatus::Processing]);
    $shippedAt = now()->toDateTimeString();

    $this->service->ship($order, [
        'carrier' => 'FedEx',
        'tracking' => 'FX-10002',
        'label_cost' => 12.00,
        'shipped_at' => $shippedAt,
    ], $this->actor);

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped)
        ->and($order->fresh()->shipped_by)->toBe($this->actor->id);
});

it('throws DomainException when shipping non-processing order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    expect(fn () => $this->service->ship($order, [
        'carrier' => 'FedEx',
        'tracking' => 'FX-10002',
        'label_cost' => 12.00,
        'shipped_at' => now()->toDateTimeString(),
    ], $this->actor))->toThrow(DomainException::class);
});

it('throws DomainException when shipping serial not in stock', function () {
    $order = Order::factory()->withLines(1)->create(['status' => OrderStatus::Processing]);
    $order->lines->first()->serial->update(['status' => SerialStatus::Sold]);

    expect(fn () => $this->service->ship($order, [
        'carrier' => 'FedEx',
        'tracking' => 'FX-10003',
        'label_cost' => 5.00,
        'shipped_at' => now()->toDateTimeString(),
    ], $this->actor))->toThrow(DomainException::class);
});

// ── markDelivered() ───────────────────────────────────────────────────────────

it('updates shipment to delivered and sets delivered_at', function () {
    $order = Order::factory()->shipped()->create();
    $shipment = $order->shipments()->first();

    $deliveredAt = now()->toDateTimeString();
    $this->service->markDelivered($order, ['delivered_at' => $deliveredAt], $this->actor);

    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Delivered)
        ->and($order->fresh()->delivered_at)->not->toBeNull();
});

it('does not change order status on delivery', function () {
    $order = Order::factory()->shipped()->create();

    $this->service->markDelivered($order, ['delivered_at' => now()->toDateTimeString()], $this->actor);

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('sets delivered_by to acting user', function () {
    $order = Order::factory()->shipped()->create();

    $this->service->markDelivered($order, ['delivered_at' => now()->toDateTimeString()], $this->actor);

    expect($order->fresh()->delivered_by)->toBe($this->actor->id);
});

it('throws DomainException when marking non-shipped order as delivered', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Processing]);

    expect(fn () => $this->service->markDelivered($order, [
        'delivered_at' => now()->toDateTimeString(),
    ], $this->actor))->toThrow(DomainException::class);
});

it('throws ModelNotFoundException when no outbound shipment on deliver', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Shipped]);

    expect(fn () => $this->service->markDelivered($order, [
        'delivered_at' => now()->toDateTimeString(),
    ], $this->actor))->toThrow(ModelNotFoundException::class);
});

// ── update() ──────────────────────────────────────────────────────────────────

function updatePayload(array $overrides = []): array
{
    return array_merge([
        'source' => 'online',
        'shipping_amount' => 20.00,
        'fees' => [['name' => 'Handling', 'amount' => 10.00]],
        'shipping' => [],
        'billing' => [],
        'billing_same_as_shipping' => false,
    ], $overrides);
}

it('updates source, shipping amount, and fees on pending order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $updated = $this->service->update($order, updatePayload(), $this->actor);

    expect($updated->source->value)->toBe('online')
        ->and((float) $updated->shipping)->toBe(20.00);

    $this->assertDatabaseHas('order_fees', [
        'order_id' => $order->id,
        'name' => 'Handling',
        'amount' => 10.00,
    ]);
});

it('recalculates grand_total on update', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::Pending,
        'subtotal' => 200.00,
        'fees' => 30.00,
        'shipping' => 15.00,
        'grand_total' => 245.00,
    ]);

    $this->service->update($order, updatePayload(['shipping_amount' => 5.00, 'fees' => [['name' => 'X', 'amount' => 5.00]]]), $this->actor);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'grand_total' => 210.00,
    ]);
});

it('deletes old fees and recreates on update', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);
    OrderFee::create(['order_id' => $order->id, 'name' => 'Old Fee', 'amount' => 99.00]);

    $this->service->update($order, updatePayload(['fees' => [['name' => 'New Fee', 'amount' => 5.00]]]), $this->actor);

    $this->assertDatabaseMissing('order_fees', ['order_id' => $order->id, 'name' => 'Old Fee']);
    $this->assertDatabaseHas('order_fees', ['order_id' => $order->id, 'name' => 'New Fee']);
});

it('updates shipping snapshot when new address given', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->service->update($order, updatePayload([
        'shipping' => [
            'first_name' => 'Jane', 'last_name' => 'Doe',
            'email' => 'jane@test.com', 'phone' => '555-0001',
            'line1' => '99 Elm St', 'city' => 'Dallas',
            'state' => 'TX', 'postal_code' => '75201', 'country' => 'US',
        ],
    ]), $this->actor);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'shipping_first_name' => 'Jane',
        'shipping_address_line1' => '99 Elm St',
    ]);
});

it('clears shipping snapshot when shipping set to none', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::Pending,
        'shipping_first_name' => 'Old Name',
        'shipping_address_line1' => '1 Old St',
    ]);

    $this->service->update($order, updatePayload(['shipping' => []]), $this->actor);

    expect($order->fresh()->shipping_first_name)->toBeNull()
        ->and($order->fresh()->shipping_address_line1)->toBeNull();
});

it('throws DomainException when editing non-pending order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Processing]);

    expect(fn () => $this->service->update($order, updatePayload(), $this->actor))
        ->toThrow(DomainException::class, 'Only pending orders can be edited.');
});

it('does not change subtotal on update', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::Pending,
        'subtotal' => 200.00,
    ]);

    $this->service->update($order, updatePayload(), $this->actor);

    expect((float) $order->fresh()->subtotal)->toBe(200.00);
});

it('copies billing snapshot from shipping when billing_same_as_shipping is true', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->service->update($order, updatePayload([
        'billing_same_as_shipping' => true,
        'shipping' => [
            'first_name' => 'Jane', 'last_name' => 'Doe',
            'email' => 'jane@test.com', 'phone' => '555-0001',
            'line1' => '99 Elm St', 'city' => 'Dallas',
            'state' => 'TX', 'postal_code' => '75201', 'country' => 'US',
        ],
    ]), $this->actor);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'billing_first_name' => 'Jane',
        'billing_address_line1' => '99 Elm St',
    ]);
});

it('sets fees to zero when fees absent on update', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::Pending,
        'fees' => 50.00,
        'subtotal' => 200.00,
        'shipping' => 10.00,
        'grand_total' => 260.00,
    ]);
    OrderFee::create(['order_id' => $order->id, 'name' => 'Old Fee', 'amount' => 50.00]);

    $this->service->update($order, updatePayload(['fees' => []]), $this->actor);

    expect((float) $order->fresh()->fees)->toBe(0.0);
    $this->assertDatabaseMissing('order_fees', ['order_id' => $order->id]);
});

// ── cancel() ──────────────────────────────────────────────────────────────────

it('cancels a pending order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $result = $this->service->cancel($order, $this->actor);

    expect($result->status)->toBe(OrderStatus::Cancelled)
        ->and($result->cancelled_at)->not->toBeNull()
        ->and($result->cancelled_by)->toBe($this->actor->id);
});

it('cancels a processing order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Processing]);

    $result = $this->service->cancel($order, $this->actor);

    expect($result->status)->toBe(OrderStatus::Cancelled);
});

it('throws DomainException when cancelling shipped order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Shipped]);

    expect(fn () => $this->service->cancel($order, $this->actor))
        ->toThrow(DomainException::class, 'Only pending or processing orders can be cancelled.');
});

it('throws DomainException when cancelling already-cancelled order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Cancelled]);

    expect(fn () => $this->service->cancel($order, $this->actor))
        ->toThrow(DomainException::class, 'Only pending or processing orders can be cancelled.');
});

// ── delete() ──────────────────────────────────────────────────────────────────

it('deletes a cancelled order and its lines and fees', function () {
    $order = Order::factory()->withLines(1)->create(['status' => OrderStatus::Cancelled]);
    OrderFee::create(['order_id' => $order->id, 'name' => 'Fee', 'amount' => 5.00]);

    $this->service->delete($order);

    $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    $this->assertDatabaseMissing('order_lines', ['order_id' => $order->id]);
    $this->assertDatabaseMissing('order_fees', ['order_id' => $order->id]);
});

it('throws DomainException when deleting non-cancelled order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    expect(fn () => $this->service->delete($order))
        ->toThrow(DomainException::class, 'Only cancelled orders can be deleted.');
});

it('preserves payment rows when deleting cancelled order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Cancelled]);
    Payment::factory()->create([
        'order_id' => $order->id,
        'payable_id' => $order->id,
    ]);

    $this->service->delete($order);

    $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    $this->assertDatabaseHas('payments', ['order_id' => $order->id]);
});

// ── AvaTax integration ────────────────────────────────────────────────────────

it('writes tax_rate and tax_amount from AvaTax into order lines', function () {
    $customer = Customer::factory()->create();
    $serial = orderMakeSerial();

    $this->mock(AvaTaxService::class)
        ->shouldReceive('calculateTax')
        ->once()
        ->andReturn([0 => ['tax_rate' => 0.0825, 'tax_amount' => 16.50]]);

    $service = app(OrderService::class);
    $order = $service->create(orderBasePayload($customer->id, $serial->id), $this->actor);

    $line = $order->lines()->first();
    expect($line->tax_rate)->toBe('0.0825')
        ->and($line->tax_amount)->toBe('16.50')
        ->and($line->line_total)->toBe('216.50');
});

it('does not save order when AvaTax throws', function () {
    $customer = Customer::factory()->create();
    $serial = orderMakeSerial();

    $this->mock(AvaTaxService::class)
        ->shouldReceive('calculateTax')
        ->andThrow(new RuntimeException('AvaTax calculateTax failed: connection error'));

    $service = app(OrderService::class);

    expect(fn () => $service->create(orderBasePayload($customer->id, $serial->id), $this->actor))
        ->toThrow(RuntimeException::class, 'AvaTax calculateTax failed');

    expect(Order::count())->toBe(0);
});

it('passes inline shipping fields as shipTo to calculateTax', function () {
    $customer = Customer::factory()->create();
    $serial = orderMakeSerial();

    $captured = null;
    $this->mock(AvaTaxService::class)
        ->shouldReceive('calculateTax')
        ->once()
        ->withArgs(function ($lines, $shipTo) use (&$captured) {
            $captured = $shipTo;

            return true;
        })
        ->andReturn([0 => ['tax_rate' => 0.0, 'tax_amount' => 0.0]]);

    $service = app(OrderService::class);
    $service->create(orderBasePayload($customer->id, $serial->id), $this->actor);

    expect($captured)->toMatchArray([
        'line1' => '456 Oak Avenue',
        'city' => 'Houston',
        'state' => 'TX',
        'postal_code' => '77001',
        'country' => 'US',
    ]);
});

it('passes empty shipTo to calculateTax when no shipping address given', function () {
    $customer = Customer::factory()->create();
    $serial = orderMakeSerial();

    $captured = null;
    $this->mock(AvaTaxService::class)
        ->shouldReceive('calculateTax')
        ->once()
        ->withArgs(function ($lines, $shipTo) use (&$captured) {
            $captured = $shipTo;

            return true;
        })
        ->andReturn([0 => ['tax_rate' => 0.0, 'tax_amount' => 0.0]]);

    $service = app(OrderService::class);
    $service->create([
        'customer_id' => $customer->id,
        'source' => 'walk_in',
        'shipping_amount' => 0.0,
        'lines' => [['serial_id' => $serial->id, 'unit_price' => 100.00, 'tax_rate' => 0.0]],
        'shipping' => [],
    ], $this->actor);

    expect($captured)->toBe([]);
});

it('resolves address_id to shipTo for calculateTax', function () {
    $customer = Customer::factory()->create();
    $serial = orderMakeSerial();
    $address = CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'address_line1' => '789 Pine St',
        'city' => 'Dallas',
        'state' => 'TX',
        'postal_code' => '75201',
        'country' => 'US',
    ]);

    $captured = null;
    $this->mock(AvaTaxService::class)
        ->shouldReceive('calculateTax')
        ->once()
        ->withArgs(function ($lines, $shipTo) use (&$captured) {
            $captured = $shipTo;

            return true;
        })
        ->andReturn([0 => ['tax_rate' => 0.0, 'tax_amount' => 0.0]]);

    $service = app(OrderService::class);
    $service->create(orderBasePayload($customer->id, $serial->id, $address->id), $this->actor);

    expect($captured)->toMatchArray([
        'line1' => '789 Pine St',
        'city' => 'Dallas',
        'state' => 'TX',
        'postal_code' => '75201',
        'country' => 'US',
    ]);
});
