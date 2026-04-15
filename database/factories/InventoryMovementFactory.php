<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        return [
            'inventory_serial_id' => InventorySerial::factory(),
            'type' => MovementType::Transfer,
            'from_location_id' => InventoryLocation::factory(),
            'to_location_id' => InventoryLocation::factory(),
            'purchase_price' => null,
            'reference' => null,
            'notes' => null,
            'user_id' => User::factory(),
        ];
    }

    public function receive(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MovementType::Receive,
            'from_location_id' => null,
            'to_location_id' => InventoryLocation::factory(),
            'purchase_price' => $this->faker->randomFloat(2, 10, 500),
        ]);
    }

    public function transfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MovementType::Transfer,
            'from_location_id' => InventoryLocation::factory(),
            'to_location_id' => InventoryLocation::factory(),
            'purchase_price' => null,
        ]);
    }

    public function sale(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MovementType::Sale,
            'from_location_id' => InventoryLocation::factory(),
            'to_location_id' => null,
            'purchase_price' => null,
            'reference' => 'ORD-'.$this->faker->numerify('####'),
        ]);
    }

    public function adjustment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MovementType::Adjustment,
            'from_location_id' => null,
            'to_location_id' => null,
            'purchase_price' => null,
            'notes' => $this->faker->sentence(),
        ]);
    }
}
