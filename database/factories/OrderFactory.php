<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'number' => 'ORD-'.now()->year.'-'.str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'customer_id' => Customer::factory(),
            'source' => OrderSource::WalkIn->value,
            'status' => OrderStatus::Pending->value,
            'payment_status' => 'unpaid',
            'created_by' => User::factory(),
            'subtotal' => 200.00,
            'fees' => 30.00,
            'shipping' => 15.00,
            'grand_total' => 245.00,
            'currency' => 'USD',
        ];
    }

    public function withLines(int $count = 1): static
    {
        return $this->afterCreating(function (Order $order) use ($count) {
            for ($i = 0; $i < $count; $i++) {
                $serial = InventorySerial::factory()->inStock()->create();
                OrderLine::factory()->create([
                    'order_id' => $order->id,
                    'inventory_serial_id' => $serial->id,
                ]);
            }
        });
    }

    public function shipped(): static
    {
        return $this->withLines(1)->state([
            'status' => OrderStatus::Shipped->value,
            'shipped_at' => now()->toDateTimeString(),
        ])->afterCreating(function (Order $order) {
            Shipment::factory()->create([
                'shippable_type' => 'order',
                'shippable_id' => $order->id,
                'direction' => 'outbound',
                'status' => 'in_transit',
                'created_by' => $order->created_by,
            ]);
        });
    }
}
