<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        static $seq = 1;

        return [
            'number' => 'ORD-2026-'.str_pad((string) $seq++, 4, '0', STR_PAD_LEFT),
            'customer_id' => Customer::factory(),
            'created_by' => User::factory(),
            'source' => OrderSource::WalkIn,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'subtotal' => 0,
            'fees' => 0,
            'shipping' => 0,
            'grand_total' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => OrderStatus::Pending]);
    }

    public function processing(): static
    {
        return $this->state([
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid,
        ]);
    }

    public function complete(): static
    {
        return $this->state([
            'status' => OrderStatus::Complete,
            'payment_status' => PaymentStatus::Paid,
        ]);
    }

    public function walkin(): static
    {
        return $this->state(['source' => OrderSource::WalkIn]);
    }

    public function cash(): static
    {
        return $this->state([
            'billing_first_name' => null,
            'billing_last_name' => null,
            'billing_email' => null,
            'billing_phone' => null,
            'billing_address_line1' => null,
            'billing_address_line2' => null,
            'billing_city' => null,
            'billing_state' => null,
            'billing_postal_code' => null,
            'billing_country' => null,
        ]);
    }
}
