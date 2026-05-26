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
        return [
            'order_id' => Order::factory(),
            'payable_type' => 'order',
            'payable_id' => 0,
            'method' => PaymentMethod::Cash,
            'amount' => 185.00,
            'status' => PaymentStatus::Paid,
            'cash_received_at' => now(),
            'created_by' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Payment $payment) {
            $payment->update(['payable_id' => $payment->order_id]);
        });
    }
}
