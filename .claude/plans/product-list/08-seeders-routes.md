# ProductList Module — Seeders & Routes

## Permission Seeder
`database/seeders/ProductListingPermissionSeeder.php`

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class ProductListingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::PRODUCT_LISTINGS_VIEW_ANY,
            Permission::PRODUCT_LISTINGS_VIEW,
            Permission::PRODUCT_LISTINGS_CREATE,
            Permission::PRODUCT_LISTINGS_EDIT,
            Permission::PRODUCT_LISTINGS_DELETE,
            Permission::PRODUCT_LISTINGS_RESTORE,
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission]);
        }

        // Super Admin — all permissions (null-safe: role may not exist in all envs)
        Role::where('name', 'super-admin')->first()?->givePermissionTo($permissions);

        // Admin — all permissions
        Role::where('name', 'admin')->first()?->givePermissionTo($permissions);

        // Sales — view only (maps to staff-level access in this project; tests use assignRole('sales'))
        Role::where('name', 'sales')->first()?->givePermissionTo([
            Permission::PRODUCT_LISTINGS_VIEW_ANY,
            Permission::PRODUCT_LISTINGS_VIEW,
        ]);
    }
}
```

---

## Data Seeder
`database/seeders/ProductListingSeeder.php`

```php
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
                    'title'      => $title,
                    'visibility' => ListingVisibility::Public->value,
                    'is_active'  => true,
                ]);
            }
        }

        if ($widget) {
            $service->create([
                'product_id' => $widget->id,
                'title'      => 'Standard',
                'visibility' => ListingVisibility::Public->value,
                'is_active'  => true,
            ]);
        }
    }
}
```

---

## DatabaseSeeder additions
Add after `ProductSeeder`:

```php
$this->call([
    // ... existing ...
    ProductSeeder::class,
    ProductListingPermissionSeeder::class,
    ProductListingSeeder::class,
]);
```

---

## Routes

### Admin routes (inside admin middleware group)
```php
// Product Listings
Route::resource('product-listings', ProductListingController::class);
Route::post('product-listings/{productListing}/toggle-visibility', [ProductListingController::class, 'toggleVisibility'])
    ->name('product-listings.toggle-visibility');
Route::post('product-listings/{productListing}/restore', [ProductListingController::class, 'restore'])
    ->name('product-listings.restore')
    ->withTrashed();
```

### Portal slug redirect route
> See `product-slug/04-routes.md` — `GET /shop/{slug}` with 3-step lookup (current slug → old slug 301 → 404)

---

## AppServiceProvider — Policy Registration

```php
use App\Models\ProductListing;
use App\Policies\ProductListingPolicy;

// in boot():
Gate::policy(ProductListing::class, ProductListingPolicy::class);
```

## Checklist
- [ ] `ProductListingPermissionSeeder` — 6 permissions (no ADJUST_STOCK); staff gets view only
- [ ] `ProductListingSeeder` — uses `ProductListingService::create()` to seed (generates slugs correctly); no price/stock data
- [ ] `DatabaseSeeder` updated to call both seeders after ProductSeeder
- [ ] Routes: `Route::resource('product-listings', ...)` inside admin middleware group
- [ ] Routes: `toggle-visibility` and `restore` POST routes only (no adjust-stock)
- [ ] Routes: `restore` uses `->withTrashed()`
- [ ] Portal slug redirect route — see `product-slug/04-routes.md`
- [ ] Policy registered in AppServiceProvider
- [ ] `php artisan db:seed --class=ProductListingPermissionSeeder` works
