<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic seed data for Playwright E2E tests.
 *
 * Known state after seeding:
 *   Users:    admin@sale-pro.test / password  (admin role)
 *             sales@sale-pro.test / password   (sales role)
 *   Locations: L1 (active), L2 (active)
 *   Products:  WIDGET-001 (Widget Alpha), WIDGET-002 (Widget Beta)
 *   Serials:
 *     SN-E2E-001  WIDGET-001  L1  in_stock  ← used in transfer test
 *     SN-E2E-002  WIDGET-001  L1  in_stock
 *     SN-E2E-003  WIDGET-001  L2  in_stock
 *     SN-E2E-SOLD WIDGET-001  —   sold
 *     SN-E2E-004  WIDGET-002  L1  in_stock  ← used in sale test
 *
 * After seeding stock overview shows:
 *   WIDGET-001 → qty 3 (L1: 2, L2: 1)
 *   WIDGET-002 → qty 1 (L1: 1)
 */
class E2ESeeder extends Seeder
{
    public function run(): void
    {
        // ── Roles & permissions ───────────────────────────────────────────────
        $this->call([
            RoleSeeder::class,
            CustomerRoleSeeder::class,
            CustomerPermissionSeeder::class,
            ProductCategoryPermissionSeeder::class,
            ProductPermissionSeeder::class,
            ProductListingPermissionSeeder::class,
            AuditLogPermissionSeeder::class,
            InventoryLocationPermissionSeeder::class,
            InventorySerialPermissionSeeder::class,
            InventoryMovementPermissionSeeder::class,
            InventoryPermissionSeeder::class,
        ]);

        // ── Users ─────────────────────────────────────────────────────────────
        $admin = User::create([
            'name' => 'E2E Admin',
            'email' => 'admin@sale-pro.test',
            'password' => Hash::make('password'),
            'status' => 'active',
            'timezone' => 'UTC',
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        $sales = User::create([
            'name' => 'E2E Sales',
            'email' => 'sales@sale-pro.test',
            'password' => Hash::make('password'),
            'status' => 'active',
            'timezone' => 'UTC',
            'email_verified_at' => now(),
        ]);
        $sales->assignRole('sales');

        // ── Locations ─────────────────────────────────────────────────────────
        $l1 = InventoryLocation::create([
            'code' => 'L1',
            'name' => 'Shelf L1',
            'is_active' => true,
        ]);

        $l2 = InventoryLocation::create([
            'code' => 'L2',
            'name' => 'Shelf L2',
            'is_active' => true,
        ]);

        // ── Products ──────────────────────────────────────────────────────────
        $category = ProductCategory::factory()->create(['name' => 'E2E Widgets']);

        $widget1 = Product::factory()->create([
            'sku' => 'WIDGET-001',
            'name' => 'Widget Alpha',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $widget2 = Product::factory()->create([
            'sku' => 'WIDGET-002',
            'name' => 'Widget Beta',
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        // ── Serials ───────────────────────────────────────────────────────────
        InventorySerial::factory()->inStock()->forProduct($widget1)->atLocation($l1)->receivedBy($admin)
            ->create(['serial_number' => 'SN-E2E-001']);

        InventorySerial::factory()->inStock()->forProduct($widget1)->atLocation($l1)->receivedBy($admin)
            ->create(['serial_number' => 'SN-E2E-002']);

        InventorySerial::factory()->inStock()->forProduct($widget1)->atLocation($l2)->receivedBy($admin)
            ->create(['serial_number' => 'SN-E2E-003']);

        InventorySerial::factory()->sold()->forProduct($widget1)->receivedBy($admin)
            ->create(['serial_number' => 'SN-E2E-SOLD']);

        InventorySerial::factory()->inStock()->forProduct($widget2)->atLocation($l1)->receivedBy($admin)
            ->create(['serial_number' => 'SN-E2E-004']);
    }
}
