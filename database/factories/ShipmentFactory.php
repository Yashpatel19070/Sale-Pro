<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'shippable_type' => 'order',
            'shippable_id' => Order::factory(),
            'direction' => 'outbound',
            'carrier' => 'FedEx',
            'tracking' => 'FX-'.rand(10000, 99999),
            'label_cost' => 12.00,
            'status' => ShipmentStatus::InTransit->value,
            'created_by' => User::factory(),
            'shipped_at' => now()->toDateTimeString(),
        ];
    }

    public function delivered(): static
    {
        return $this->state([
            'status' => ShipmentStatus::Delivered->value,
            'delivered_at' => now()->toDateTimeString(),
        ]);
    }
}
