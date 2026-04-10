<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            'phone'             => fake()->phoneNumber(),
            'job_title'         => fake()->jobTitle(),
            'employee_id'       => null,
            'department_id'     => null,
            'status'            => UserStatus::Active,
            'hired_at'          => fake()->dateTimeBetween('-5 years', 'now'),
            'timezone'          => 'UTC',
        ];
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => UserStatus::Inactive]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => UserStatus::Suspended]);
    }

    public function inDepartment(Department $department): static
    {
        return $this->state(['department_id' => $department->id]);
    }
}
