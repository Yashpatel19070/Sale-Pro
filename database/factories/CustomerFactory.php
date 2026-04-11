<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'first_name'    => $this->faker->firstName(),
            'last_name'     => $this->faker->lastName(),
            'email'         => $this->faker->unique()->safeEmail(),
            'phone'         => $this->faker->phoneNumber(),
            'company_name'  => $this->faker->company(),
            'job_title'     => $this->faker->jobTitle(),
            'status'        => CustomerStatus::Lead,
            'source'        => $this->faker->randomElement(CustomerSource::cases()),
            'assigned_to'   => null,
            'department_id' => null,
            'address_line1' => $this->faker->streetAddress(),
            'city'          => $this->faker->city(),
            'state'         => $this->faker->state(),
            'postcode'      => $this->faker->postcode(),
            'country'       => 'Australia',
            'notes'         => null,
        ];
    }

    public function lead(): static
    {
        return $this->state(['status' => CustomerStatus::Lead]);
    }

    public function prospect(): static
    {
        return $this->state(['status' => CustomerStatus::Prospect]);
    }

    public function active(): static
    {
        return $this->state(['status' => CustomerStatus::Active]);
    }

    public function churned(): static
    {
        return $this->state(['status' => CustomerStatus::Churned]);
    }

    public function assignedTo(int $userId): static
    {
        return $this->state(['assigned_to' => $userId]);
    }

    public function inDepartment(int $departmentId): static
    {
        return $this->state(['department_id' => $departmentId]);
    }

    public function withoutEmail(): static
    {
        return $this->state(['email' => null]);
    }
}
