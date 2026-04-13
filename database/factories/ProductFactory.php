<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => ProductCategory::factory(),
            'sku' => strtoupper($this->faker->unique()->bothify('???-####')),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->paragraph(),
            'purchase_price' => $this->faker->optional()->randomFloat(2, 1, 100),
            'regular_price' => $this->faker->randomFloat(2, 5, 500),
            'sale_price' => null,
            'notes' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function uncategorised(): static
    {
        return $this->state(['category_id' => null]);
    }

    public function onSale(float $salePrice): static
    {
        return $this->state(['sale_price' => $salePrice]);
    }

    public function withPrices(float $purchase, float $regular, ?float $sale = null): static
    {
        return $this->state([
            'purchase_price' => $purchase,
            'regular_price' => $regular,
            'sale_price' => $sale,
        ]);
    }
}
