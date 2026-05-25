<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'payable_type' => 'order',
            'payable_id' => fn (array $attrs) => $attrs['order_id'],
            'method' => PaymentMethod::Cash->value,
            'amount' => 245.00,
            'status' => PaymentStatus::Paid->value,
            'created_by' => User::factory(),
            'currency' => 'USD',
            'cash_received_at' => now()->toDateTimeString(),
        ];
    }
}
