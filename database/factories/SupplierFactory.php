<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SupplierStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'contact_name' => fake()->optional()->name(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('###-###-####'),
            'address' => fake()->optional()->streetAddress(),
            'city' => fake()->optional()->city(),
            'state' => fake()->optional()->state(),
            'postal_code' => fake()->optional()->postcode(),
            'country' => fake()->optional()->country(),
            'payment_terms' => fake()->optional()->randomElement(['Net 30', 'Net 60', 'COD', 'Net 15']),
            'notes' => fake()->optional()->sentence(),
            'status' => SupplierStatus::Active->value,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => SupplierStatus::Inactive->value]);
    }
}
