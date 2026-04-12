# ProductCategory Module — Policy & Permissions

## Policy File
`app/Policies/ProductCategoryPolicy.php`

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ProductCategory;
use App\Models\User;

class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PRODUCT_CATEGORIES_VIEW_ANY);
    }

    public function view(User $user, ProductCategory $category): bool
    {
        return $user->can(Permission::PRODUCT_CATEGORIES_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PRODUCT_CATEGORIES_CREATE);
    }

    public function update(User $user, ProductCategory $category): bool
    {
        return $user->can(Permission::PRODUCT_CATEGORIES_UPDATE);
    }

    public function delete(User $user, ProductCategory $category): bool
    {
        return $user->can(Permission::PRODUCT_CATEGORIES_DELETE);
    }
}
```

---

## Permission Constants
Add to `app/Enums/Permission.php`:

```php
// Product Categories
const PRODUCT_CATEGORIES_VIEW_ANY = 'product_categories.viewAny';
const PRODUCT_CATEGORIES_VIEW     = 'product_categories.view';
const PRODUCT_CATEGORIES_CREATE   = 'product_categories.create';
const PRODUCT_CATEGORIES_UPDATE   = 'product_categories.update';
const PRODUCT_CATEGORIES_DELETE   = 'product_categories.delete';
```

---

## Register Policy
Add to `app/Providers/AppServiceProvider.php` in `boot()`:

```php
Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);
```

Also add import at top:
```php
use App\Policies\ProductCategoryPolicy;
```

---

## Permission Seeder
`database/seeders/ProductCategoryPermissionSeeder.php`

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;

class ProductCategoryPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            Permission::PRODUCT_CATEGORIES_VIEW_ANY,
            Permission::PRODUCT_CATEGORIES_VIEW,
            Permission::PRODUCT_CATEGORIES_CREATE,
            Permission::PRODUCT_CATEGORIES_UPDATE,
            Permission::PRODUCT_CATEGORIES_DELETE,
        ];

        foreach ($permissions as $name) {
            SpatiePermission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'admin')->first();
        $staff = Role::where('name', 'staff')->first();

        $admin?->givePermissionTo($permissions);

        $staff?->givePermissionTo([
            Permission::PRODUCT_CATEGORIES_VIEW_ANY,
            Permission::PRODUCT_CATEGORIES_VIEW,
        ]);
    }
}
```

Wire into `DatabaseSeeder.php`:
```php
$this->call([
    // ... existing seeders ...
    ProductCategoryPermissionSeeder::class,
]);
```

## Checklist
- [ ] 5 Permission constants added to `Permission.php`
- [ ] `ProductCategoryPolicy` created with 5 methods
- [ ] Policy registered in `AppServiceProvider::boot()`
- [ ] `ProductCategoryPermissionSeeder` creates all 5 permissions with `firstOrCreate`
- [ ] Admin role gets all 5 permissions
- [ ] Staff role gets only viewAny + view
- [ ] Seeder wired into `DatabaseSeeder`
- [ ] `php artisan db:seed --class=ProductCategoryPermissionSeeder` runs without error
