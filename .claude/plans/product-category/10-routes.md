# ProductCategory Module — Routes

## File to Modify
`routes/web.php` — inside the admin middleware group

## Add after the Customers routes block:

```php
// Product Categories
Route::resource('product-categories', ProductCategoryController::class);
```

## Import at top of web.php:

```php
use App\Http\Controllers\ProductCategoryController;
```

## Generated Routes

`php artisan route:list --name=product-categories`

| Method | URI | Name | Action |
|--------|-----|------|--------|
| GET | /admin/product-categories | product-categories.index | index |
| GET | /admin/product-categories/create | product-categories.create | create |
| POST | /admin/product-categories | product-categories.store | store |
| GET | /admin/product-categories/{productCategory} | product-categories.show | show |
| GET | /admin/product-categories/{productCategory}/edit | product-categories.edit | edit |
| PUT/PATCH | /admin/product-categories/{productCategory} | product-categories.update | update |
| DELETE | /admin/product-categories/{productCategory} | product-categories.destroy | destroy |

## Route Model Binding
Laravel binds `{productCategory}` (camelCase of route parameter `product-categories/{productCategory}`)
to `ProductCategory` model automatically via implicit binding.

The controller parameter must match: `ProductCategory $productCategory`

## Checklist
- [ ] `Route::resource()` added inside admin `middleware(['auth', 'load_perms', 'verified', 'active'])` group
- [ ] `ProductCategoryController` imported at top of `web.php`
- [ ] `php artisan route:list --name=product-categories` shows all 7 routes
- [ ] Routes resolve under `/admin/product-categories/...`
