# Product Module — Seeders & Routes

## Permission Seeder
`database/seeders/ProductPermissionSeeder.php`

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class ProductPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            Permission::PRODUCTS_VIEW_ANY,
            Permission::PRODUCTS_VIEW,
            Permission::PRODUCTS_CREATE,
            Permission::PRODUCTS_EDIT,
            Permission::PRODUCTS_DELETE,
            Permission::PRODUCTS_RESTORE,
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission]);
        }

        // Super Admin — all permissions
        $superAdmin = Role::findByName('super-admin');
        $superAdmin->givePermissionTo($permissions);

        // Admin — all except restore (or include restore if desired)
        $admin = Role::findByName('admin');
        $admin->givePermissionTo([
            Permission::PRODUCTS_VIEW_ANY,
            Permission::PRODUCTS_VIEW,
            Permission::PRODUCTS_CREATE,
            Permission::PRODUCTS_EDIT,
            Permission::PRODUCTS_DELETE,
            Permission::PRODUCTS_RESTORE,
        ]);

        // Staff — view only
        $staff = Role::findByName('staff');
        $staff->givePermissionTo([
            Permission::PRODUCTS_VIEW_ANY,
            Permission::PRODUCTS_VIEW,
        ]);
    }
}
```

---

## Data Seeder
`database/seeders/ProductSeeder.php`

```php
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
        $clothing    = ProductCategory::where('name', 'Clothing')->first();

        Product::factory()->withPrices(12.50, 29.99)->create([
            'sku'         => 'WIDGET-001',
            'name'        => 'Premium Widget',
            'category_id' => $electronics?->id,
            'description' => 'A premium widget for all your needs.',
        ]);

        Product::factory()->withPrices(5.00, 14.99, 9.99)->create([
            'sku'         => 'TSHIRT-001',
            'name'        => 'Classic T-Shirt',
            'category_id' => $clothing?->id,
        ]);

        // 10 random products for testing
        Product::factory()->count(10)->create();
    }
}
```

---

## DatabaseSeeder additions
`database/seeders/DatabaseSeeder.php` — add after `ProductCategorySeeder`:

```php
$this->call([
    // ... existing ...
    ProductCategorySeeder::class,
    ProductPermissionSeeder::class,
    ProductSeeder::class,
]);
```

---

## Routes
Add inside admin middleware group in `routes/web.php`:

```php
// Products
Route::resource('products', ProductController::class);
Route::post('products/{product}/toggle-active', [ProductController::class, 'toggleActive'])
    ->name('products.toggle-active');
Route::post('products/{product}/restore', [ProductController::class, 'restore'])
    ->name('products.restore')
    ->withTrashed();
```

---

## AppServiceProvider — Policy Registration

```php
use App\Models\Product;
use App\Policies\ProductPolicy;

// in boot():
Gate::policy(Product::class, ProductPolicy::class);
```

## Checklist
- [ ] `ProductPermissionSeeder` — creates 6 permissions, assigns to roles
- [ ] `ProductSeeder` — creates 2 named seed products + 10 random
- [ ] `DatabaseSeeder` updated to call both seeders after ProductCategorySeeder
- [ ] Routes: `Route::resource('products', ...)` inside admin middleware group
- [ ] Routes: `toggle-active` POST route
- [ ] Routes: `restore` POST route with `->withTrashed()`
- [ ] Policy registered in AppServiceProvider
- [ ] `php artisan db:seed --class=ProductPermissionSeeder` works
