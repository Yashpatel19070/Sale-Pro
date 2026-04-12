# ProductCategory Module — Seeder

## Files
- `database/seeders/ProductCategorySeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

---

## ProductCategorySeeder

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Electronics',      'description' => 'Gadgets, devices, and accessories'],
            ['name' => 'Clothing',          'description' => 'Apparel, footwear, and fashion'],
            ['name' => 'Home & Garden',     'description' => 'Furniture, decor, and outdoor items'],
            ['name' => 'Sports',            'description' => 'Equipment, activewear, and fitness gear'],
            ['name' => 'Books',             'description' => 'Print books, e-books, and educational material'],
            ['name' => 'Food & Beverage',   'description' => 'Consumables, snacks, and drinks'],
            ['name' => 'Software',          'description' => 'Applications, licenses, and digital tools'],
            ['name' => 'Services',          'description' => 'Professional and consulting services'],
        ];

        foreach ($categories as $data) {
            ProductCategory::firstOrCreate(
                ['name' => $data['name']],
                [...$data, 'is_active' => true],
            );
        }
    }
}
```

## Wire into DatabaseSeeder

```php
$this->call([
    RoleSeeder::class,
    DepartmentSeeder::class,
    CustomerRoleSeeder::class,
    CustomerPermissionSeeder::class,
    ProductCategoryPermissionSeeder::class,  // ← add
    CustomerSeeder::class,
    ProductCategorySeeder::class,            // ← add after permissions
]);
```

## Checklist
- [ ] `ProductCategorySeeder` created with 8 sample categories
- [ ] Uses `firstOrCreate` keyed on name (idempotent)
- [ ] `ProductCategoryPermissionSeeder` called before `ProductCategorySeeder` in DatabaseSeeder
- [ ] `php artisan db:seed --class=ProductCategorySeeder` runs without error
- [ ] Re-running seeder does not create duplicates
