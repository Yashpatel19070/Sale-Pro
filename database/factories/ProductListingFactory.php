<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ListingVisibility;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductListingFactory extends Factory
{
    public function definition(): array
    {
        $product = Product::factory()->create();
        $title = $this->faker->words(2, true);

        return [
            'product_id' => $product->id,
            'title' => $title,
            // slug intentionally absent — HasSlug generates it on create
            'visibility' => ListingVisibility::Draft->value,
            'is_active' => true,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state([
            'product_id' => $product->id,
            // slug intentionally absent — HasSlug generates it on create
        ]);
    }

    public function public(): static
    {
        return $this->state([
            'visibility' => ListingVisibility::Public->value,
            'is_active' => true,
        ]);
    }
}
