<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\ProductListing;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderLineFactory extends Factory
{
    protected $model = OrderLine::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_listing_id' => ProductListing::factory(),
            'sku' => 'ECM-2024',
            'product_name' => 'Engine Control Module',
            'inventory_serial_id' => null,
            'unit_price' => 200.00,
            'tax_amount' => 16.50,
            'line_total' => 216.50,
        ];
    }

    public function withSerial(InventorySerial $serial): static
    {
        return $this->state(['inventory_serial_id' => $serial->id]);
    }

    public function withEcm(): static
    {
        $product = Product::factory()->create(['sku' => 'ECM-2024', 'name' => 'Engine Control Module']);
        $listing = ProductListing::factory()->active()->for($product)->create();

        return $this->state([
            'product_listing_id' => $listing->id,
            'sku' => 'ECM-2024',
            'product_name' => 'Engine Control Module',
        ]);
    }
}
