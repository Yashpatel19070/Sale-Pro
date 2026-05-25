<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\OrderPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(OrderPermissionSeeder::class);
    DB::table('sequences')->insertOrIgnore(['name' => 'orders', 'value' => 0]);
});

// ── Helpers ──────────────────────────────────────────────────────────────────

function orderAdminUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        'orders.viewAny', 'orders.view', 'orders.create',
        'orders.update', 'orders.cancel',
        'orders.pay', 'orders.ship', 'orders.deliver',
    ]);

    return $user;
}

function orderStaffUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(['orders.viewAny', 'orders.view']);

    return $user;
}

function orderSalesUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(['orders.viewAny', 'orders.view']);

    return $user;
}

function orderPayload(int $customerId, int $serialId): array
{
    return [
        'customer_id' => $customerId,
        'source' => 'walk_in',
        'shipping_amount' => 15.00,
        'lines' => [
            ['serial_id' => $serialId, 'unit_price' => 200.00, 'tax_rate' => 0.0],
        ],
        'fees' => [['name' => 'Service Fee', 'amount' => 30.00]],
        'address' => [
            'first_name' => 'Mike', 'last_name' => 'Torres',
            'email' => 'mike@example.com', 'phone' => '555-100-0002',
            'line1' => '456 Oak Ave', 'city' => 'Houston',
            'state' => 'TX', 'postal_code' => '77001', 'country' => 'US',
        ],
    ];
}

// ── INDEX ─────────────────────────────────────────────────────────────────────

it('admin can list orders', function () {
    Order::factory()->count(3)->create();

    $this->actingAs(orderAdminUser())
        ->get(route('orders.index'))
        ->assertOk()
        ->assertViewIs('orders.index')
        ->assertViewHas('orders');
});

it('staff can list orders', function () {
    $this->actingAs(orderStaffUser())
        ->get(route('orders.index'))
        ->assertOk();
});

it('guest is redirected from index', function () {
    $this->get(route('orders.index'))
        ->assertRedirect(route('login'));
});

it('forbids order index to user with no permissions', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('orders.index'))
        ->assertForbidden();
});

it('index filters by status', function () {
    Order::factory()->create(['status' => OrderStatus::Pending]);
    Order::factory()->create(['status' => OrderStatus::Shipped]);

    $this->actingAs(orderAdminUser())
        ->get(route('orders.index', ['status' => 'pending']))
        ->assertOk()
        ->assertViewHas('orders', fn ($orders) => $orders->total() === 1);
});

// ── CREATE ────────────────────────────────────────────────────────────────────

it('admin can view create form', function () {
    $this->actingAs(orderAdminUser())
        ->get(route('orders.create'))
        ->assertOk()
        ->assertViewIs('orders.create')
        ->assertViewHas('customers')
        ->assertViewHas('sources');
});

it('staff cannot view create form', function () {
    $this->actingAs(orderStaffUser())
        ->get(route('orders.create'))
        ->assertForbidden();
});

it('guest is redirected from create', function () {
    $this->get(route('orders.create'))
        ->assertRedirect(route('login'));
});

// ── STORE ─────────────────────────────────────────────────────────────────────

it('admin can create an order', function () {
    $customer = Customer::factory()->create();
    $serial = InventorySerial::factory()->inStock()->create();

    $this->actingAs(orderAdminUser())
        ->post(route('orders.store'), orderPayload($customer->id, $serial->id))
        ->assertRedirect();

    $this->assertDatabaseHas('orders', ['customer_id' => $customer->id, 'status' => 'pending']);
    $this->assertDatabaseHas('order_lines', ['inventory_serial_id' => $serial->id]);
    $this->assertDatabaseHas('order_fees', ['name' => 'Service Fee']);
});

it('staff cannot store an order', function () {
    $customer = Customer::factory()->create();
    $serial = InventorySerial::factory()->inStock()->create();

    $this->actingAs(orderStaffUser())
        ->post(route('orders.store'), orderPayload($customer->id, $serial->id))
        ->assertForbidden();
});

it('sales role cannot store an order', function () {
    $customer = Customer::factory()->create();
    $serial = InventorySerial::factory()->inStock()->create();

    $this->actingAs(orderSalesUser())
        ->post(route('orders.store'), orderPayload($customer->id, $serial->id))
        ->assertForbidden();
});

it('guest is redirected from store', function () {
    $this->post(route('orders.store'), [])
        ->assertRedirect(route('login'));
});

it('store fails with invalid serial_id', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(orderAdminUser())
        ->post(route('orders.store'), orderPayload($customer->id, 99999))
        ->assertSessionHasErrors('lines.0.serial_id');
});

it('fails store when lines array is missing', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(orderAdminUser())
        ->post(route('orders.store'), [
            'customer_id' => $customer->id,
            'source' => 'walk_in',
            'shipping_amount' => 0,
        ])
        ->assertSessionHasErrors('lines');
});

it('fails store with invalid source value', function () {
    $customer = Customer::factory()->create();
    $serial = InventorySerial::factory()->inStock()->create();

    $this->actingAs(orderAdminUser())
        ->post(route('orders.store'), [
            'customer_id' => $customer->id,
            'source' => 'invalid_source',
            'shipping_amount' => 0,
            'lines' => [['serial_id' => $serial->id, 'unit_price' => 100]],
        ])
        ->assertSessionHasErrors('source');
});

// ── SHOW ──────────────────────────────────────────────────────────────────────

it('admin can view order show page', function () {
    $order = Order::factory()->create();

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertViewIs('orders.show')
        ->assertViewHas('order');
});

it('staff can view order show page', function () {
    $order = Order::factory()->create();

    $this->actingAs(orderStaffUser())
        ->get(route('orders.show', $order))
        ->assertOk();
});

it('guest is redirected from show', function () {
    $order = Order::factory()->create();

    $this->get(route('orders.show', $order))
        ->assertRedirect(route('login'));
});

// ── SHOW PAGE CONTENT ─────────────────────────────────────────────────────────

it('displays order number on show page', function () {
    $order = Order::factory()->create();

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertSee($order->number);
});

it('displays status label not raw value on show page', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertSee($order->status->label())
        ->assertDontSee($order->status->value);
});

it('displays source label on show page', function () {
    $order = Order::factory()->create();

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertSee($order->source->label());
});

it('displays customer name on show page', function () {
    $customer = Customer::factory()->create(['name' => 'John Doe XYZ']);
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertSee('John Doe XYZ');
});

it('displays subtotal incl tax label on show page', function () {
    $order = Order::factory()->create();

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertSee('Subtotal (incl. tax)');
});

it('displays correct grand total on show page', function () {
    $order = Order::factory()->create(['grand_total' => 245.00]);

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertSee(number_format(245.00, 2));
});

it('uses order fees column not fees_total on show page', function () {
    $order = Order::factory()->create(['fees' => 30.00]);

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertSee(number_format(30.00, 2));
});

it('uses order shipping column not shipping_amount on show page', function () {
    $order = Order::factory()->create(['shipping' => 15.00]);

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertSee(number_format(15.00, 2));
});

it('displays line sku from snapshot on show page', function () {
    $order = Order::factory()->create();
    OrderLine::factory()->create([
        'order_id' => $order->id,
        'sku' => 'SKU-TEST-9999',
    ]);

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertSee('SKU-TEST-9999');
});

it('displays payment method label not raw on show page', function () {
    $order = Order::factory()->create(['payment_status' => 'paid']);
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'payable_id' => $order->id,
        'method' => PaymentMethod::Cash,
    ]);

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertSee($payment->method->label());
});

it('displays payment status label not raw on show page', function () {
    $order = Order::factory()->create(['payment_status' => 'paid']);
    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'payable_id' => $order->id,
        'status' => PaymentStatus::Paid,
    ]);

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertSee($payment->status->label());
});

it('displays shipment status label not raw on show page', function () {
    $order = Order::factory()->shipped()->create();
    $shipment = $order->shipments()->first();

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertSee($shipment->status->label());
});

it('shows shipping address when present on show page', function () {
    $order = Order::factory()->create([
        'shipping_address_line1' => '123 Test Street',
    ]);

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertSee('123 Test Street');
});

it('hides billing address section when null on show page', function () {
    $order = Order::factory()->create(['billing_address_line1' => null]);

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertDontSee('Billing Address');
});

it('shows inline pay form when order is unpaid and user can pay', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::Pending,
        'payment_status' => 'unpaid',
    ]);

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertSee('Record Payment');
});

it('shows inline ship form when order is processing and user can ship', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Processing]);

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertSee('Mark Shipped');
});

it('shows inline deliver form when order is shipped and not yet delivered', function () {
    $order = Order::factory()->shipped()->create();

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertSee('Mark Delivered');
});

it('hides pay form after payment recorded', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::Processing,
        'payment_status' => 'paid',
    ]);

    $this->actingAs(orderAdminUser())
        ->get(route('orders.show', $order))
        ->assertDontSee('Record Payment');
});

// ── PAY ───────────────────────────────────────────────────────────────────────

it('admin can record cash payment', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::Pending,
        'payment_status' => 'unpaid',
        'grand_total' => 245.00,
    ]);

    $this->actingAs(orderAdminUser())
        ->post(route('orders.pay', $order), [
            'amount' => 245.00,
            'cash_received_at' => now()->toDateTimeString(),
        ])
        ->assertRedirect(route('orders.show', $order));

    expect($order->fresh()->payment_status)->toBe('paid')
        ->and($order->fresh()->status)->toBe(OrderStatus::Processing);
    $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'method' => 'cash']);
});

it('staff cannot record payment', function () {
    $order = Order::factory()->create();

    $this->actingAs(orderStaffUser())
        ->post(route('orders.pay', $order), ['amount' => 100, 'cash_received_at' => now()])
        ->assertForbidden();
});

it('guest is redirected from pay', function () {
    $order = Order::factory()->create();

    $this->post(route('orders.pay', $order), [])
        ->assertRedirect(route('login'));
});

it('returns error when paying non-pending order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Processing]);

    $this->actingAs(orderAdminUser())
        ->post(route('orders.pay', $order), [
            'amount' => 100.00,
            'cash_received_at' => now()->toDateTimeString(),
        ])
        ->assertSessionHasErrors('error');
});

// ── SHIP ──────────────────────────────────────────────────────────────────────

it('admin can ship an order', function () {
    $order = Order::factory()->withLines(1)->create(['status' => OrderStatus::Processing]);

    $this->actingAs(orderAdminUser())
        ->post(route('orders.ship', $order), [
            'carrier' => 'FedEx',
            'tracking' => 'FX-10002',
            'label_cost' => 12.00,
            'shipped_at' => now()->toDateTimeString(),
        ])
        ->assertRedirect(route('orders.show', $order));

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
    $this->assertDatabaseHas('shipments', ['shippable_id' => $order->id, 'carrier' => 'FedEx']);
});

it('staff cannot ship', function () {
    $order = Order::factory()->create();

    $this->actingAs(orderStaffUser())
        ->post(route('orders.ship', $order), [])
        ->assertForbidden();
});

it('guest is redirected from ship', function () {
    $order = Order::factory()->create();

    $this->post(route('orders.ship', $order), [])
        ->assertRedirect(route('login'));
});

it('returns error when shipping non-processing order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->actingAs(orderAdminUser())
        ->post(route('orders.ship', $order), [
            'carrier' => 'FedEx',
            'tracking' => 'FX-99999',
            'label_cost' => 5.00,
            'shipped_at' => now()->toDateTimeString(),
        ])
        ->assertSessionHasErrors('error');
});

// ── DELIVER ───────────────────────────────────────────────────────────────────

it('admin can mark order as delivered', function () {
    $order = Order::factory()->shipped()->create();

    $deliveredAt = now()->toDateTimeString();

    $this->actingAs(orderAdminUser())
        ->post(route('orders.deliver', $order), ['delivered_at' => $deliveredAt])
        ->assertRedirect(route('orders.show', $order));

    expect($order->fresh()->delivered_at)->not->toBeNull();
    $this->assertDatabaseHas('shipments', [
        'shippable_id' => $order->id,
        'status' => 'delivered',
    ]);
});

it('staff cannot mark delivered', function () {
    $order = Order::factory()->shipped()->create();

    $this->actingAs(orderStaffUser())
        ->post(route('orders.deliver', $order), ['delivered_at' => now()])
        ->assertForbidden();
});

it('guest is redirected from deliver', function () {
    $order = Order::factory()->create();

    $this->post(route('orders.deliver', $order), [])
        ->assertRedirect(route('login'));
});

it('returns error when delivering non-shipped order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Processing]);

    $this->actingAs(orderAdminUser())
        ->post(route('orders.deliver', $order), ['delivered_at' => now()->toDateTimeString()])
        ->assertSessionHasErrors('error');
});

// ── Helpers for update / cancel / delete ──────────────────────────────────────

function orderManagerUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        'orders.viewAny', 'orders.view', 'orders.create',
        'orders.update', 'orders.cancel',
        'orders.pay', 'orders.ship', 'orders.deliver',
    ]);

    return $user;
}

function orderSuperAdminUser(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo([
        'orders.viewAny', 'orders.view', 'orders.create',
        'orders.update', 'orders.cancel', 'orders.delete',
        'orders.pay', 'orders.ship', 'orders.deliver',
    ]);

    return $user;
}

function updateFormPayload(array $overrides = []): array
{
    return array_merge([
        'source' => 'online',
        'shipping_amount' => 10.00,
        'fees' => [],
    ], $overrides);
}

// ── EDIT / UPDATE ─────────────────────────────────────────────────────────────

it('renders edit page for pending order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->actingAs(orderManagerUser())
        ->get(route('orders.edit', $order))
        ->assertOk()
        ->assertViewIs('orders.edit');
});

it('returns 403 on edit for sales role (view-only)', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->actingAs(orderStaffUser())
        ->get(route('orders.edit', $order))
        ->assertForbidden();
});

it('forbids edit form for non-pending order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Processing]);

    $this->actingAs(orderAdminUser())
        ->get(route('orders.edit', $order))
        ->assertForbidden();
});

it('redirects to show on successful update', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->actingAs(orderManagerUser())
        ->put(route('orders.update', $order), updateFormPayload())
        ->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('success');
});

it('shows validation error when source missing on update', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->actingAs(orderManagerUser())
        ->put(route('orders.update', $order), updateFormPayload(['source' => '']))
        ->assertSessionHasErrors('source');
});

it('shows error when editing non-pending order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Processing]);

    $this->actingAs(orderManagerUser())
        ->put(route('orders.update', $order), updateFormPayload())
        ->assertSessionHasErrors('error');
});

it('preserves input on DomainException during update', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Processing]);

    $this->actingAs(orderManagerUser())
        ->put(route('orders.update', $order), updateFormPayload(['source' => 'online']))
        ->assertSessionHasErrors('error')
        ->assertSessionHasInput('source', 'online');
});

// ── CANCEL ────────────────────────────────────────────────────────────────────

it('cancels a pending order via controller', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->actingAs(orderManagerUser())
        ->post(route('orders.cancel', $order))
        ->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('success');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('returns 403 on cancel for sales role (view-only)', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->actingAs(orderStaffUser())
        ->post(route('orders.cancel', $order))
        ->assertForbidden();
});

it('shows error when cancelling shipped order via controller', function () {
    $order = Order::factory()->shipped()->create();

    $this->actingAs(orderManagerUser())
        ->post(route('orders.cancel', $order))
        ->assertSessionHasErrors('error');
});

// ── DELETE ────────────────────────────────────────────────────────────────────

it('deletes a cancelled order via controller', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Cancelled]);

    $this->actingAs(orderSuperAdminUser())
        ->delete(route('orders.destroy', $order))
        ->assertRedirect(route('orders.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('orders', ['id' => $order->id]);
});

it('returns 403 on delete for admin (no delete permission)', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Cancelled]);

    $this->actingAs(orderManagerUser())
        ->delete(route('orders.destroy', $order))
        ->assertForbidden();
});

it('shows error when deleting non-cancelled order via controller', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->actingAs(orderSuperAdminUser())
        ->delete(route('orders.destroy', $order))
        ->assertSessionHasErrors('error');
});

// ── TAX PREVIEW ───────────────────────────────────────────────────────────────

it('returns 200 JSON from taxPreview for admin', function () {
    $this->actingAs(orderAdminUser())
        ->postJson(route('orders.tax-preview'), [
            'lines' => [['serial_id' => null, 'unit_price' => 100.00]],
            'shipping' => [],
        ])
        ->assertOk()
        ->assertJson([]);
});

it('returns 403 on taxPreview for sales role', function () {
    $this->actingAs(orderSalesUser())
        ->postJson(route('orders.tax-preview'), [
            'lines' => [['serial_id' => null, 'unit_price' => 100.00]],
            'shipping' => [],
        ])
        ->assertForbidden();
});
