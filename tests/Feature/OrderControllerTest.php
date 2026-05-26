<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductListing;
use App\Models\User;
use Database\Seeders\OrderPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(OrderPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo(['view-orders', 'create-orders', 'manage-orders']);

    $this->customer = Customer::factory()->create();
    $this->location = InventoryLocation::factory()->create(['name' => 'Warehouse A']);
    $this->product = Product::factory()->create();
    $this->listing = ProductListing::factory()->active()->for($this->product)->create();
    $this->serial = InventorySerial::factory()->inStock()->atLocation($this->location)->forProduct($this->product)->create();
});

// ── index ─────────────────────────────────────────────────────────────────────

it('admin_can_view_orders_index', function () {
    $this->actingAs($this->admin)
        ->get(route('orders.index'))
        ->assertOk();
});

it('user_without_permission_cannot_view_orders', function () {
    $noPerms = User::factory()->create();

    $this->actingAs($noPerms)
        ->get(route('orders.index'))
        ->assertForbidden();
});

// ── create ────────────────────────────────────────────────────────────────────

it('admin_can_view_create_order_form', function () {
    $this->actingAs($this->admin)
        ->get(route('orders.create'))
        ->assertOk();
});

// ── store ─────────────────────────────────────────────────────────────────────

it('admin_can_create_walkin_cash_order', function () {
    $this->actingAs($this->admin)
        ->post(route('orders.store'), [
            'customer_id' => $this->customer->id,
            'source' => 'walk_in',
            'payment_method' => 'cash',
            'shipping' => 0,
            'lines' => [
                [
                    'product_listing_id' => $this->listing->id,
                    'unit_price' => 170,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                ],
            ],
            'fees' => [
                ['name' => 'Service Fee', 'amount' => 15],
            ],
        ])
        ->assertRedirect();

    $order = Order::first();
    expect($order)->not->toBeNull();
    expect($order->source->value)->toBe('walk_in');
    expect($order->status)->toBe(OrderStatus::Pending);
    expect($order->payment_status)->toBe(PaymentStatus::Unpaid);
    expect((float) $order->grand_total)->toBe(185.0);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'billing_first_name' => null,
        'shipping_first_name' => null,
        'shipped_at' => null,
        'shipped_by' => null,
    ]);

    $this->assertDatabaseHas('order_lines', [
        'order_id' => $order->id,
        'inventory_serial_id' => null,
    ]);

    $this->assertDatabaseHas('order_fees', [
        'order_id' => $order->id,
        'name' => 'Service Fee',
        'amount' => 15,
    ]);

    $this->assertDatabaseMissing('customer_addresses', [
        'customer_id' => $this->customer->id,
    ]);
});

it('store_fails_validation_without_required_fields', function () {
    $this->actingAs($this->admin)
        ->post(route('orders.store'), [])
        ->assertSessionHasErrors(['customer_id', 'source', 'lines']);
});

// ── show ──────────────────────────────────────────────────────────────────────

it('admin_can_view_order_show_page', function () {
    $order = Order::factory()->pending()->for($this->customer)->create([
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('orders.show', $order))
        ->assertOk();
});

// ── edit ──────────────────────────────────────────────────────────────────────

it('admin_can_view_edit_form_when_order_is_pending', function () {
    $order = Order::factory()->pending()->for($this->customer)->create([
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('orders.edit', $order))
        ->assertOk();
});

it('edit_redirects_to_show_when_order_is_not_pending', function () {
    $order = Order::factory()->processing()->for($this->customer)->create([
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('orders.edit', $order))
        ->assertRedirect(route('orders.show', $order));
});

// ── update ────────────────────────────────────────────────────────────────────

it('admin_can_update_pending_order', function () {
    $order = Order::factory()->pending()->for($this->customer)->create([
        'created_by' => $this->admin->id,
    ]);

    $serial2 = InventorySerial::factory()->inStock()->atLocation($this->location)->forProduct($this->product)->create();

    $this->actingAs($this->admin)
        ->put(route('orders.update', $order), [
            'customer_id' => $this->customer->id,
            'source' => 'walk_in',
            'payment_method' => 'cash',
            'shipping' => 10,
            'lines' => [
                ['product_listing_id' => $this->listing->id, 'unit_price' => 200, 'tax_rate' => 0, 'tax_amount' => 0],
            ],
            'fees' => [],
        ])
        ->assertRedirect(route('orders.show', $order));

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'shipping' => 10,
        'subtotal' => 200,
        'grand_total' => 210,
    ]);
});

it('update_fails_when_order_is_not_pending', function () {
    $order = Order::factory()->processing()->for($this->customer)->create([
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('orders.update', $order), [
            'customer_id' => $this->customer->id,
            'source' => 'walk_in',
            'payment_method' => 'cash',
            'shipping' => 0,
            'lines' => [
                ['product_listing_id' => $this->listing->id, 'unit_price' => 100, 'tax_rate' => 0, 'tax_amount' => 0],
            ],
            'fees' => [],
        ])
        ->assertForbidden();
});

// ── destroy ───────────────────────────────────────────────────────────────────

it('admin_can_delete_pending_order', function () {
    $order = Order::factory()->pending()->for($this->customer)->create([
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('orders.destroy', $order))
        ->assertRedirect(route('orders.index'));

    $this->assertSoftDeleted('orders', ['id' => $order->id]);
});

it('destroy_fails_when_order_is_not_pending', function () {
    $order = Order::factory()->processing()->for($this->customer)->create([
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('orders.destroy', $order))
        ->assertForbidden();

    $this->assertDatabaseHas('orders', ['id' => $order->id]);
});

// ── recordCashPayment ─────────────────────────────────────────────────────────

it('admin_can_record_cash_payment', function () {
    $order = Order::factory()->pending()->for($this->customer)->create([
        'created_by' => $this->admin->id,
        'subtotal' => 185,
        'grand_total' => 185,
    ]);
    // Add a line with serial so advanceToProcessing fires
    $order->lines()->create([
        'product_listing_id' => $this->listing->id,
        'inventory_serial_id' => $this->serial->id,
        'sku' => $this->product->sku,
        'product_name' => $this->product->name,
        'unit_price' => 185,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'line_total' => 185,
    ]);

    $this->actingAs($this->admin)
        ->post(route('orders.cash-payment', $order), ['amount' => 185.00])
        ->assertRedirect(route('orders.show', $order));

    $this->assertDatabaseHas('payments', [
        'order_id' => $order->id,
        'method' => 'cash',
        'status' => 'paid',
    ]);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'payment_status' => 'paid',
        'status' => 'processing',
    ]);
});

it('record_cash_payment_fails_if_already_paid', function () {
    $order = Order::factory()->processing()->for($this->customer)->create([
        'created_by' => $this->admin->id,
        'payment_status' => PaymentStatus::Paid,
    ]);

    $this->actingAs($this->admin)
        ->post(route('orders.cash-payment', $order), ['amount' => 185.00])
        ->assertForbidden();

    $this->assertDatabaseCount('payments', 0);
});

it('user_without_permission_cannot_record_cash_payment', function () {
    $order = Order::factory()->pending()->for($this->customer)->create(['created_by' => $this->admin->id]);
    $noPerms = User::factory()->create();

    $this->actingAs($noPerms)
        ->post(route('orders.cash-payment', $order), ['amount' => 185.00])
        ->assertForbidden();
});

// ── complete ──────────────────────────────────────────────────────────────────

it('admin_can_complete_order', function () {
    $order = Order::factory()->processing()->for($this->customer)->create([
        'created_by' => $this->admin->id,
    ]);
    $order->lines()->create([
        'product_listing_id' => $this->listing->id,
        'inventory_serial_id' => $this->serial->id,
        'sku' => $this->product->sku,
        'product_name' => $this->product->name,
        'unit_price' => 170,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'line_total' => 170,
    ]);

    $this->actingAs($this->admin)
        ->post(route('orders.complete', $order))
        ->assertRedirect(route('orders.show', $order));

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'complete']);

    // Inventory movement and sold status are set at payment, not at complete.
    // This order was created directly as processing (bypassing payment), so no movement expected.
    $this->assertDatabaseMissing('inventory_movements', [
        'reference' => $order->number,
    ]);

    $this->assertDatabaseMissing('shipments', [
        'shippable_id' => $order->id,
        'shippable_type' => 'order',
    ]);
});

it('complete_fails_if_order_not_in_processing', function () {
    $order = Order::factory()->pending()->for($this->customer)->create([
        'created_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('orders.complete', $order))
        ->assertForbidden();

    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
});

it('user_without_permission_cannot_complete_order', function () {
    $order = Order::factory()->processing()->for($this->customer)->create(['created_by' => $this->admin->id]);
    $noPerms = User::factory()->create();

    $this->actingAs($noPerms)
        ->post(route('orders.complete', $order))
        ->assertForbidden();
});
