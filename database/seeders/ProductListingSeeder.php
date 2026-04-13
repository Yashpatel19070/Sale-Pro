<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ListingVisibility;
use App\Models\Product;
use App\Services\ProductListingService;
use Illuminate\Database\Seeder;

class ProductListingSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(ProductListingService::class);

        $tshirt = Product::where('sku', 'TSHIRT-001')->first();
        $widget = Product::where('sku', 'WIDGET-001')->first();

        if ($tshirt) {
            foreach (['Blue / M', 'Blue / XL', 'Red / M'] as $title) {
                $service->create([
                    'product_id' => $tshirt->id,
                    'title' => $title,
                    'visibility' => ListingVisibility::Public->value,
                    'is_active' => true,
                ]);
            }
        }

        if ($widget) {
            $service->create([
                'product_id' => $widget->id,
                'title' => 'Standard',
                'visibility' => ListingVisibility::Public->value,
                'is_active' => true,
            ]);
        }
    }
}
