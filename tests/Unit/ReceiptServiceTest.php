<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductListing;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ReceiptService;
use Database\Seeders\OrderPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(OrderPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo([
        'orders.viewAny', 'orders.view', 'orders.create', 'orders.update',
        'orders.delete', 'orders.recordPayment', 'orders.complete',
    ]);

    $this->customer = Customer::factory()->create(['name' => 'Rachel Park', 'tax_exempt' => false]);
    $this->location = InventoryLocation::factory()->create(['name' => 'Warehouse A']);
    $this->product = Product::factory()->create(['sku' => 'ECM-2024', 'name' => 'Engine Control Module']);
    $this->listing = ProductListing::factory()->active()->for($this->product)->create();
    $this->serial = InventorySerial::factory()
        ->inStock()
        ->atLocation($this->location)
        ->forProduct($this->product)
        ->create(['serial_number' => 'SN-200']);
});

function receiptOrder(): Order
{
    return app(OrderService::class)->store([
        'customer_id' => test()->customer->id,
        'source' => 'walk_in',
        'payment_method' => 'cash',
        'billing_address_id' => null,
        'shipping_address_id' => null,
        'shipping' => 0,
        'lines' => [[
            'product_listing_id' => test()->listing->id,
            'unit_price' => 200.00,
            'tax_amount' => 16.50,
            'fees' => [
                ['name' => 'Programming Fee', 'amount' => 40.00, 'tax_amount' => 3.30],
            ],
        ]],
    ], test()->admin);
}

it('assembles receipt data array with all expected sections', function () {
    $order = receiptOrder();
    $data = app(ReceiptService::class)->build($order);

    expect($data)->toHaveKeys(['shop', 'order', 'customer', 'lines', 'totals', 'payments', 'footer']);
});

it('exposes shop info from config', function () {
    config([
        'shop.billing.first_name' => 'ACME Tuning',
        'shop.billing.email' => 'shop@acme.test',
        'shop.billing.phone' => '555-1234',
        'shop.billing.address_line1' => '500 Main St',
        'shop.billing.city' => 'Austin',
        'shop.billing.state' => 'TX',
        'shop.billing.postal_code' => '78701',
    ]);

    $order = receiptOrder();
    $data = app(ReceiptService::class)->build($order);

    expect($data['shop']['name'])->toBe('ACME Tuning');
    expect($data['shop']['email'])->toBe('shop@acme.test');
    expect($data['shop']['city'])->toBe('Austin');
    expect($data['shop']['has_letterhead'])->toBeTrue();
});

it('flags has_letterhead false when shop config unset', function () {
    config(['shop.billing.first_name' => null]);

    $order = receiptOrder();
    $data = app(ReceiptService::class)->build($order);

    expect($data['shop']['has_letterhead'])->toBeFalse();
});

it('totals sum line + fee subtotals + shipping', function () {
    $order = receiptOrder();
    $data = app(ReceiptService::class)->build($order);

    expect($data['totals']['line_totals'])->toBe(216.50);
    expect($data['totals']['fee_totals'])->toBe(43.30);
    expect($data['totals']['shipping'])->toBe(0.0);
    expect($data['totals']['grand_total'])->toBe((float) $order->grand_total);
});

it('exposes nested per-line fees on each line', function () {
    $order = receiptOrder();
    $data = app(ReceiptService::class)->build($order);

    expect($data['lines'])->toHaveCount(1);
    expect($data['lines'][0]['fees'])->toHaveCount(1);
    expect($data['lines'][0]['fees'][0]['name'])->toBe('Programming Fee');
});
