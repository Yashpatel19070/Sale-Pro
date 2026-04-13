<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

// Stub factory — full implementation in the product-list module.
class ProductListingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'title' => $this->faker->sentence(3),
            'is_active' => false,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(['product_id' => $product->id]);
    }

    /** Active / publicly visible listing. */
    public function public(): static
    {
        return $this->state(['is_active' => true]);
    }
}
