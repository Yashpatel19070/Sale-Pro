<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $electronics = ProductCategory::where('name', 'Electronics')->first();
        $clothing = ProductCategory::where('name', 'Clothing')->first();

        Product::factory()->withPrices(12.50, 29.99)->create([
            'sku' => 'WIDGET-001',
            'name' => 'Premium Widget',
            'category_id' => $electronics?->id,
            'description' => 'A premium widget for all your needs.',
        ]);

        Product::factory()->withPrices(5.00, 14.99, 9.99)->create([
            'sku' => 'TSHIRT-001',
            'name' => 'Classic T-Shirt',
            'category_id' => $clothing?->id,
        ]);

        Product::factory()->count(10)->create();
    }
}
