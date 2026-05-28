<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'number' => 'ORD-2026-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'customer_id' => Customer::factory(),
            'source' => OrderSource::WalkIn,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'shipping' => 0.00,
            'grand_total' => 286.86,
            'billing_first_name' => 'NPC Sales Pro LLC',
            'billing_last_name' => null,
            'billing_email' => 'sales@npcsalespro.com',
            'billing_phone' => '713-555-0100',
            'billing_address_line1' => '5426 N Shepherd Dr',
            'billing_address_line2' => null,
            'billing_city' => 'Houston',
            'billing_state' => 'TX',
            'billing_postal_code' => '77091',
            'billing_country' => 'US',
            'shipping_first_name' => null,
            'shipping_last_name' => null,
            'shipping_email' => null,
            'shipping_phone' => null,
            'shipping_address_line1' => null,
            'shipping_address_line2' => null,
            'shipping_city' => null,
            'shipping_state' => null,
            'shipping_postal_code' => null,
            'shipping_country' => null,
            'shipped_at' => null,
            'shipped_by' => null,
            'delivered_at' => null,
            'delivered_by' => null,
            'created_by' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => OrderStatus::Pending, 'payment_status' => PaymentStatus::Unpaid]);
    }

    public function processing(): static
    {
        return $this->state(['status' => OrderStatus::Processing, 'payment_status' => PaymentStatus::Paid]);
    }

    public function complete(): static
    {
        return $this->state(['status' => OrderStatus::Complete, 'payment_status' => PaymentStatus::Paid]);
    }

    public function paid(): static
    {
        return $this->state(['payment_status' => PaymentStatus::Paid]);
    }

    public function walkInCash(): static
    {
        return $this->state(['source' => OrderSource::WalkIn]);
    }

    public function withShopBilling(): static
    {
        return $this->state([
            'billing_first_name' => 'NPC Sales Pro LLC',
            'billing_address_line1' => '5426 N Shepherd Dr',
            'billing_city' => 'Houston',
            'billing_state' => 'TX',
            'billing_postal_code' => '77091',
            'billing_country' => 'US',
        ]);
    }
}
