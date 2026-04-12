<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $roots = [
            ['name' => 'Electronics',    'description' => 'Gadgets, devices, and accessories'],
            ['name' => 'Clothing',       'description' => 'Apparel, footwear, and fashion'],
            ['name' => 'Home & Garden',  'description' => 'Furniture, decor, and outdoor items'],
            ['name' => 'Sports',         'description' => 'Equipment, activewear, and fitness gear'],
            ['name' => 'Books',          'description' => 'Print books, e-books, and educational material'],
            ['name' => 'Food & Beverage', 'description' => 'Consumables, snacks, and drinks'],
            ['name' => 'Software',       'description' => 'Applications, licenses, and digital tools'],
            ['name' => 'Services',       'description' => 'Professional and consulting services'],
        ];

        foreach ($roots as $data) {
            ProductCategory::firstOrCreate(
                ['name' => $data['name'], 'parent_id' => null],
                [...$data, 'is_active' => true],
            );
        }

        // Sample subcategories under Electronics
        $electronics = ProductCategory::where('name', 'Electronics')->where('parent_id', null)->first();

        if ($electronics) {
            $subs = ['Phones', 'Laptops', 'Audio', 'Cameras'];

            foreach ($subs as $name) {
                ProductCategory::firstOrCreate(
                    ['name' => $name, 'parent_id' => $electronics->id],
                    ['name' => $name, 'parent_id' => $electronics->id, 'is_active' => true],
                );
            }

            // Deeper level under Phones
            $phones = ProductCategory::where('name', 'Phones')->where('parent_id', $electronics->id)->first();

            if ($phones) {
                foreach (['Smartphones', 'Feature Phones', 'Accessories'] as $name) {
                    ProductCategory::firstOrCreate(
                        ['name' => $name, 'parent_id' => $phones->id],
                        ['name' => $name, 'parent_id' => $phones->id, 'is_active' => true],
                    );
                }
            }
        }
    }
}
