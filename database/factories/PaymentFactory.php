<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $order = Order::factory()->create();

        return [
            'order_id' => $order->id,
            'payable_type' => 'order',
            'payable_id' => $order->id,
            'method' => PaymentMethod::Cash,
            'amount' => 286.86,
            'status' => PaymentStatus::Paid,
            'cash_received_at' => now(),
            'created_by' => User::factory(),
        ];
    }

    public function cash(): static
    {
        return $this->state([
            'method' => PaymentMethod::Cash,
            'cash_received_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(['status' => PaymentStatus::Paid]);
    }
}
