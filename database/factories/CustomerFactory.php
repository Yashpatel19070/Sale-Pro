<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('###-###-####'),
            'company_name' => fake()->optional()->company(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => fake()->country(),
            'status' => CustomerStatus::Active->value,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => CustomerStatus::Inactive->value]);
    }

    public function blocked(): static
    {
        return $this->state(['status' => CustomerStatus::Blocked->value]);
    }
}
