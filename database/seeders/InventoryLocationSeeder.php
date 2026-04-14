<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\InventoryLocation;
use Illuminate\Database\Seeder;

class InventoryLocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            [
                'code' => 'L1',
                'name' => 'Shelf L1 — Row A',
                'description' => 'First shelf, ground level, row A.',
                'is_active' => true,
            ],
            [
                'code' => 'L2',
                'name' => 'Shelf L2 — Row A',
                'description' => 'Second shelf, ground level, row A.',
                'is_active' => true,
            ],
            [
                'code' => 'L45',
                'name' => 'Shelf L45 — Row C',
                'description' => 'Mid warehouse, row C.',
                'is_active' => true,
            ],
            [
                'code' => 'L99',
                'name' => 'Shelf L99 — Row F',
                'description' => 'Far end of warehouse, top shelf.',
                'is_active' => true,
            ],
            [
                'code' => 'ZONE-A',
                'name' => 'Zone A — Staging Area',
                'description' => 'Temporary staging area for incoming stock.',
                'is_active' => true,
            ],
            [
                'code' => 'ZONE-B',
                'name' => 'Zone B — Returns Bay',
                'description' => 'Receiving area for customer returns.',
                'is_active' => true,
            ],
        ];

        foreach ($locations as $data) {
            InventoryLocation::firstOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
