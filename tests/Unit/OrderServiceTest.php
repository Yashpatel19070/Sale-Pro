<?php

declare(strict_types=1);

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SerialStatus;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderLine;
use App\Models\OrderLineFee;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductListing;
use App\Models\User;
use App\Services\AvaTaxService;
use App\Services\InventoryMovementService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function orderService(): OrderService
{
    return app(OrderService::class);
}

function ex19Customer(): Customer
{
    return Customer::factory()->create([
        'name' => 'Rachel Park',
        'email' => 'rachel@example.com',
        'phone' => '555-190-0001',
        'tax_exempt' => false,
    ]);
}

function ex19Setup(): array
{
    $admin = User::factory()->create();
    $customer = ex19Customer();
    $location = InventoryLocation::factory()->create(['name' => 'Warehouse A']);
    $product = Product::factory()->create(['sku' => 'ECM-2024', 'name' => 'Engine Control Module']);
    $listing = ProductListing::factory()->active()->for($product)->create();
    $serial = InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create(['serial_number' => 'SN-200']);

    return compact('admin', 'customer', 'location', 'product', 'listing', 'serial');
}

function ex19Payload(int $customerId, int $listingId): array
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

// ── store() ──────────────────────────────────────────────────────────────

it('creates order with walk_in source and pending status', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);

    expect($order->source)->toBe(OrderSource::WalkIn);
    expect($order->status)->toBe(OrderStatus::Pending);
    expect($order->payment_status)->toBe(PaymentStatus::Unpaid);
    expect($order->created_by)->toBe($f['admin']->id);
});

it('sets billing snapshot to shop address for cash when shop config is set', function () {
    config([
        'shop.billing.first_name' => 'ACME Tuning',
        'shop.billing.city' => 'Austin',
        'shop.billing.state' => 'TX',
    ]);

    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);

    expect($order->billing_first_name)->toBe('ACME Tuning');
    expect($order->billing_city)->toBe('Austin');
    expect($order->billing_state)->toBe('TX');
});

it('uses customer billing address when one is provided even for cash', function () {
    $f = ex19Setup();

    $address = CustomerAddress::factory()->create([
        'customer_id' => $f['customer']->id,
        'label' => 'Home',
        'first_name' => 'Real',
        'last_name' => 'Person',
        'address_line1' => '500 Main St',
        'city' => 'Dallas',
        'state' => 'TX',
        'postal_code' => '75201',
        'country' => 'US',
    ]);

    $payload = ex19Payload($f['customer']->id, $f['listing']->id);
    $payload['billing_address_id'] = $address->id;

    $order = orderService()->store($payload, $f['admin']);

    expect($order->billing_first_name)->toBe('Real');
    expect($order->billing_city)->toBe('Dallas');
    expect($order->billing_address_line1)->toBe('500 Main St');
});

it('sets billing snapshot to null when shop config is unset', function () {
    config([
        'shop.billing.first_name' => null,
        'shop.billing.last_name' => null,
        'shop.billing.email' => null,
        'shop.billing.phone' => null,
        'shop.billing.address_line1' => null,
        'shop.billing.address_line2' => null,
        'shop.billing.city' => null,
        'shop.billing.state' => null,
        'shop.billing.postal_code' => null,
        'shop.billing.country' => null,
    ]);

    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);

    expect($order->billing_first_name)->toBeNull();
    expect($order->billing_city)->toBeNull();
    expect($order->billing_state)->toBeNull();
    expect($order->billing_email)->toBeNull();
    expect($order->billing_postal_code)->toBeNull();
});

it('sets shipping snapshot to null for pickup', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);

    expect($order->shipping_first_name)->toBeNull();
    expect($order->shipping_address_line1)->toBeNull();
    expect($order->shipping_city)->toBeNull();
});

it('generates order number in correct format', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);

    expect($order->number)->toMatch('/^ORD-\d{4}-\d{4}$/');
});

it('creates order_line with snapshots and line_total', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);
    $line = $order->lines->first();

    expect($line->sku)->toBe('ECM-2024');
    expect($line->product_name)->toBe('Engine Control Module');
    expect((float) $line->unit_price)->toBe(200.0);
    expect((float) $line->tax_amount)->toBe(16.5);
    expect((float) $line->line_total)->toBe(216.5);
});

it('leaves inventory_serial_id null at store (allocation moved to payment per #6)', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);
    $line = $order->lines->first();

    expect($line->inventory_serial_id)->toBeNull();
    expect($f['serial']->fresh()->status)->toBe(SerialStatus::InStock);
});

it('creates order_line_fees with fee_total', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);
    $fees = $order->lines->first()->lineFees;

    expect($fees)->toHaveCount(2);
    expect($fees[0]->name)->toBe('Programming Fee');
    expect((float) $fees[0]->fee_total)->toBe(43.30);
    expect($fees[1]->name)->toBe('Gas Tuning Fee');
    expect((float) $fees[1]->fee_total)->toBe(27.06);
    expect($fees[0]->created_by)->toBe($f['admin']->id);
});

it('computes grand_total correctly', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);

    expect((float) $order->grand_total)->toBe(286.86);
});

it('does not create inventory movement on store', function () {
    $f = ex19Setup();
    orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);

    expect(InventoryMovement::count())->toBe(0);
});

it('does not create payment on store', function () {
    $f = ex19Setup();
    orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);

    expect(Payment::count())->toBe(0);
});

it('inserts order_placed event with correct metadata', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);
    $event = $order->events->first();

    expect($event->event)->toBe(App\Enums\OrderEvent::OrderPlaced);
    expect($event->metadata['sku'])->toBe('ECM-2024');
    expect($event->metadata['grand_total'])->toBe('286.86');
    expect($event->created_by)->toBe($f['admin']->id);
});

// (removed obsolete test "throws when no in-stock serial is available" —
// store() no longer allocates serials; the check moved to recordCashPayment(),
// covered by "throws when no in-stock serial at payment" below)

// ── recordCashPayment() ──────────────────────────────────────────────────

it('records cash payment and advances status to processing', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);

    $payment = orderService()->recordCashPayment($order, ['amount' => 286.86], $f['admin']);

    expect($payment->method)->toBe(PaymentMethod::Cash);
    expect($payment->status)->toBe(PaymentStatus::Paid);
    expect((float) $payment->amount)->toBe(286.86);
    expect($payment->payable_type)->toBe('order');
    expect($order->fresh()->status)->toBe(OrderStatus::Processing);
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('creates inventory movement and flips serial to sold on payment', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);
    orderService()->recordCashPayment($order, ['amount' => 286.86], $f['admin']);

    expect(InventoryMovement::count())->toBe(1);
    $movement = InventoryMovement::first();
    expect($movement->type->value)->toBe('sale');
    expect($movement->reference)->toBe($order->number);

    expect($f['serial']->fresh()->status)->toBe(SerialStatus::Sold);
    expect($f['serial']->fresh()->inventory_location_id)->toBeNull();
});

it('allocates serial when recording payment', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);

    expect($order->lines->first()->inventory_serial_id)->toBeNull();

    orderService()->recordCashPayment($order, ['amount' => 286.86], $f['admin']);

    expect($order->lines->first()->fresh()->inventory_serial_id)->toBe($f['serial']->id);
    expect($f['serial']->fresh()->status)->toBe(SerialStatus::Sold);
});

it('throws when no in-stock serial at payment', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);

    // Drain the only in-stock serial: simulate it being sold to someone else
    $f['serial']->update(['status' => SerialStatus::Sold, 'inventory_location_id' => null]);

    expect(fn () => orderService()->recordCashPayment($order, ['amount' => 286.86], $f['admin']))
        ->toThrow(DomainException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Unpaid);
});

it('inserts payment_received event', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);
    orderService()->recordCashPayment($order, ['amount' => 286.86], $f['admin']);

    $event = $order->events()->where('event', 'payment_received')->first();
    expect($event)->not->toBeNull();
    expect($event->metadata['method'])->toBe('cash');
});

it('throws when order already paid', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);
    orderService()->recordCashPayment($order, ['amount' => 286.86], $f['admin']);

    orderService()->recordCashPayment($order->fresh(), ['amount' => 286.86], $f['admin']);
})->throws(DomainException::class);

it('throws when amount does not match grand_total', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);

    orderService()->recordCashPayment($order, ['amount' => 100.00], $f['admin']);
})->throws(DomainException::class);

it('calls AvaTaxService::commitInvoice after recording cash payment', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);

    $spy = Mockery::mock(AvaTaxService::class);
    $spy->shouldReceive('commitInvoice')
        ->once()
        ->withArgs(function (array $lines, array $shipTo, string $customerCode, string $documentCode) use ($order, $f) {
            return count($lines) === 3
                && $lines[0]['sku'] === 'ECM-2024'
                && $lines[1]['sku'] === 'FEE-Programming Fee'
                && $lines[2]['sku'] === 'FEE-Gas Tuning Fee'
                && $customerCode === (string) $f['customer']->id
                && $documentCode === $order->number;
        })
        ->andReturn(true);
    app()->instance(AvaTaxService::class, $spy);

    $service = new OrderService(
        app(InventoryMovementService::class),
        $spy,
    );
    $service->recordCashPayment($order, ['amount' => 286.86], $f['admin']);
});

// ── complete() ───────────────────────────────────────────────────────────

it('completes order and does not duplicate inventory movement', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);
    orderService()->recordCashPayment($order, ['amount' => 286.86], $f['admin']);

    // Movement + serial flip already happened at payment
    expect(InventoryMovement::count())->toBe(1);
    expect($f['serial']->fresh()->status)->toBe(SerialStatus::Sold);

    $completed = orderService()->complete($order->fresh(), $f['admin']);

    expect($completed->status)->toBe(OrderStatus::Complete);
    // complete() must NOT create a new movement or change serial again
    expect(InventoryMovement::count())->toBe(1);
    expect($f['serial']->fresh()->status)->toBe(SerialStatus::Sold);
});

it('inserts completed event with empty metadata', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);
    orderService()->recordCashPayment($order, ['amount' => 286.86], $f['admin']);
    orderService()->complete($order->fresh(), $f['admin']);

    $event = $order->events()->where('event', 'completed')->first();
    expect($event)->not->toBeNull();
    expect($event->metadata)->toBe([]);
});

it('throws when order not in processing status', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);

    orderService()->complete($order, $f['admin']);
})->throws(DomainException::class);

// ── update() ─────────────────────────────────────────────────────────────

it('updates order when pending', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);

    $payload = ex19Payload($f['customer']->id, $f['listing']->id);
    $payload['lines'][0]['unit_price'] = 250.00;
    $payload['lines'][0]['fees'] = [];
    $updated = orderService()->update($order, $payload);

    expect((float) $updated->lines->first()->unit_price)->toBe(250.0);
});

it('throws when updating non-pending order', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);
    orderService()->recordCashPayment($order, ['amount' => 286.86], $f['admin']);

    orderService()->update($order->fresh(), ex19Payload($f['customer']->id, $f['listing']->id));
})->throws(DomainException::class);

// ── delete() ─────────────────────────────────────────────────────────────

it('hard-deletes pending order and cascades children', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);
    $orderId = $order->id;
    $lineId = $order->lines->first()->id;

    orderService()->delete($order);

    expect(Order::find($orderId))->toBeNull();
    expect(OrderLine::find($lineId))->toBeNull();
    expect(OrderLineFee::where('order_line_id', $lineId)->count())->toBe(0);
    expect(OrderEvent::where('order_id', $orderId)->count())->toBe(0);
});

it('logs audit row before deleting order so history persists', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);
    $orderId = $order->id;

    orderService()->delete($order);

    // activity_log row persists with subject_id reference even after CASCADE wipe
    $log = DB::table('activity_log')
        ->where('subject_type', 'order')
        ->where('subject_id', $orderId)
        ->where('event', 'deleted')
        ->first();
    expect($log)->not->toBeNull();
});

it('throws when deleting non-pending order', function () {
    $f = ex19Setup();
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);
    orderService()->recordCashPayment($order, ['amount' => 286.86], $f['admin']);

    orderService()->delete($order->fresh());
})->throws(DomainException::class);

// ── Full lifecycle integration ───────────────────────────────────────────

it('shows correct state at each stage of the lifecycle', function () {
    $f = ex19Setup();

    // After store()
    $order = orderService()->store(ex19Payload($f['customer']->id, $f['listing']->id), $f['admin']);
    expect($f['serial']->fresh()->status)->toBe(SerialStatus::InStock);
    expect(InventoryMovement::count())->toBe(0);
    expect($order->events()->count())->toBe(1);

    // After recordCashPayment() — serial flipped + movement created here
    orderService()->recordCashPayment($order, ['amount' => 286.86], $f['admin']);
    expect($f['serial']->fresh()->status)->toBe(SerialStatus::Sold);
    expect(InventoryMovement::count())->toBe(1);
    expect($order->fresh()->events()->count())->toBe(2);

    // After complete() — status flip only, no new movement
    orderService()->complete($order->fresh(), $f['admin']);
    expect($f['serial']->fresh()->status)->toBe(SerialStatus::Sold);
    expect(InventoryMovement::count())->toBe(1);
    expect($order->fresh()->events()->count())->toBe(3);
    expect($order->fresh()->status)->toBe(OrderStatus::Complete);
});
