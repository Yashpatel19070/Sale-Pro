<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SerialStatus;
use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventorySerial>
 */
class InventorySerialFactory extends Factory
{
    protected $model = InventorySerial::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'inventory_location_id' => InventoryLocation::factory(),
            'serial_number' => strtoupper($this->faker->bothify('SN-#####-??')),
            'purchase_price' => $this->faker->randomFloat(2, 1, 999),
            'received_at' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'supplier_name' => $this->faker->optional()->company(),
            'received_by_user_id' => User::factory(),
            'status' => SerialStatus::InStock->value,
            'notes' => null,
        ];
    }

    public function inStock(): static
    {
        return $this->state([
            'status' => SerialStatus::InStock->value,
        ]);
    }

    public function sold(): static
    {
        return $this->state([
            'status' => SerialStatus::Sold->value,
            'inventory_location_id' => null,
        ]);
    }

    public function damaged(): static
    {
        return $this->state([
            'status' => SerialStatus::Damaged->value,
            'inventory_location_id' => null,
            'notes' => 'Damaged on arrival.',
        ]);
    }

    public function missing(): static
    {
        return $this->state([
            'status' => SerialStatus::Missing->value,
            'inventory_location_id' => null,
            'notes' => 'Not found during stock count.',
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(['product_id' => $product->id]);
    }

    public function atLocation(InventoryLocation $location): static
    {
        return $this->state(['inventory_location_id' => $location->id]);
    }

    public function receivedBy(User $user): static
    {
        return $this->state(['received_by_user_id' => $user->id]);
    }
}
