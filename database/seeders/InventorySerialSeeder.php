<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventorySerialSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::active()->inRandomOrder()->limit(5)->get();
        $locations = InventoryLocation::where('is_active', true)->get();
        $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first()
            ?? User::factory()->create()->assignRole('admin');

        if ($products->isEmpty() || $locations->isEmpty()) {
            $this->command->warn('InventorySerialSeeder: No products or locations found — skipping.');

            return;
        }

        foreach ($products as $product) {
            $location = $locations->random();

            InventorySerial::factory()
                ->count(3)
                ->forProduct($product)
                ->atLocation($location)
                ->receivedBy($admin)
                ->create();

            InventorySerial::factory()
                ->sold()
                ->forProduct($product)
                ->receivedBy($admin)
                ->create();

            InventorySerial::factory()
                ->damaged()
                ->forProduct($product)
                ->receivedBy($admin)
                ->create();
        }

        $this->command->info('InventorySerialSeeder: Created '.InventorySerial::count().' serials.');
    }
}
