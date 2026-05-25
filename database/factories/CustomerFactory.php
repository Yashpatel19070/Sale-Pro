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
            'status' => CustomerStatus::Active->value,
            'email_verified_at' => now(),
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

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    public function noPassword(): static
    {
        return $this->state(['password' => null]);
    }
}
