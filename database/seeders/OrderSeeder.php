<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrderSource;
use App\Models\Customer;
use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(OrderService::class);
        $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();
        $customer = Customer::inRandomOrder()->first();
        $serials = InventorySerial::inStock()->inRandomOrder()->limit(5)->get();

        if (! $admin || ! $customer || $serials->isEmpty()) {
            $this->command->warn('OrderSeeder: Missing admin, customer, or in-stock serials — skipping.');

            return;
        }

        DB::table('sequences')->insertOrIgnore(['name' => 'orders', 'value' => 0]);

        // Pending (unpaid)
        $service->create([
            'customer_id' => $customer->id,
            'source' => OrderSource::WalkIn->value,
            'shipping_amount' => 15.00,
            'lines' => [
                ['serial_id' => $serials[0]->id, 'unit_price' => 199.99, 'tax_rate' => 0.08],
            ],
            'fees' => [['name' => 'Service Fee', 'amount' => 25.00]],
            'address' => [],
        ], $admin);

        // Processing (paid cash)
        $order2 = $service->create([
            'customer_id' => $customer->id,
            'source' => OrderSource::Phone->value,
            'shipping_amount' => 12.00,
            'lines' => [
                ['serial_id' => $serials[1]->id, 'unit_price' => 349.00, 'tax_rate' => 0.0],
            ],
            'fees' => [],
            'address' => [],
        ], $admin);

        $service->recordCashPayment($order2, [
            'amount' => $order2->grand_total,
            'cash_received_at' => now()->toDateTimeString(),
        ], $admin);

        // Shipped
        if ($serials->count() >= 3) {
            $order3 = $service->create([
                'customer_id' => $customer->id,
                'source' => OrderSource::Online->value,
                'shipping_amount' => 9.99,
                'lines' => [
                    ['serial_id' => $serials[2]->id, 'unit_price' => 275.00, 'tax_rate' => 0.0],
                ],
                'fees' => [],
                'address' => [
                    'first_name' => 'Demo', 'last_name' => 'User',
                    'email' => 'demo@example.com', 'phone' => '555-000-0001',
                    'line1' => '100 Main St', 'city' => 'Austin',
                    'state' => 'TX', 'postal_code' => '78701', 'country' => 'US',
                ],
            ], $admin);

            $service->recordCashPayment($order3, [
                'amount' => $order3->grand_total,
                'cash_received_at' => now()->subDay()->toDateTimeString(),
            ], $admin);

            $service->ship($order3, [
                'carrier' => 'FedEx',
                'tracking' => 'FX-DEMO-'.rand(10000, 99999),
                'label_cost' => 12.50,
                'shipped_at' => now()->subDay()->toDateTimeString(),
            ], $admin);
        }

        $this->command->info('OrderSeeder: Created '.Order::count().' demo orders.');
    }
}
