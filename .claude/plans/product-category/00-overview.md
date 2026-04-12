# ProductCategory Module — Overview

## Purpose
Admin CRUD for product categories. Simple list, create, edit, delete.
No nesting. No parent/child. No extras.
Used as a foreign key by the Products module (coming next).

## Features
| # | Feature |
|---|---------|
| 1 | List categories — paginated table with search + filter by status |
| 2 | View category — detail page |
| 3 | Create category — admin adds category |
| 4 | Edit category — update name, description, is_active |
| 5 | Delete — soft delete only |

## File Map
| File | Path |
|------|------|
| Migration | `database/migrations/xxxx_create_product_categories_table.php` |
| Model | `app/Models/ProductCategory.php` |
| Service | `app/Services/ProductCategoryService.php` |
| Controller | `app/Http/Controllers/ProductCategoryController.php` |
| Store Request | `app/Http/Requests/ProductCategory/StoreProductCategoryRequest.php` |
| Update Request | `app/Http/Requests/ProductCategory/UpdateProductCategoryRequest.php` |
| Policy | `app/Policies/ProductCategoryPolicy.php` |
| View: index | `resources/views/product_categories/index.blade.php` |
| View: show | `resources/views/product_categories/show.blade.php` |
| View: create | `resources/views/product_categories/create.blade.php` |
| View: edit | `resources/views/product_categories/edit.blade.php` |
| View: _form | `resources/views/product_categories/_form.blade.php` |
| Permission Seeder | `database/seeders/ProductCategoryPermissionSeeder.php` |
| Data Seeder | `database/seeders/ProductCategorySeeder.php` |
| Feature Test | `tests/Feature/ProductCategoryControllerTest.php` |
| Unit Test | `tests/Unit/Services/ProductCategoryServiceTest.php` |

## Files to Modify
| File | Change |
|------|--------|
| `app/Enums/Permission.php` | Add 5 PRODUCT_CATEGORIES_* constants |
| `app/Providers/AppServiceProvider.php` | Register ProductCategoryPolicy |
| `routes/web.php` | Add Route::resource inside admin middleware group |
| `database/seeders/DatabaseSeeder.php` | Call ProductCategoryPermissionSeeder + ProductCategorySeeder |

## Implementation Order
1. Schema plan → Migration → `php artisan migrate`
2. Model
3. Service
4. FormRequests (Store + Update)
5. Policy + Permission constants
6. Controller
7. Routes (add to web.php)
8. Views (index → show → create/edit → _form)
9. Seeders → run seeders
10. Tests

## Role Access Matrix
| Permission | Super Admin | Admin | Staff |
|------------|-------------|-------|-------|
| List categories | ✅ | ✅ | ✅ |
| View category | ✅ | ✅ | ✅ |
| Create category | ✅ | ✅ | ❌ |
| Edit category | ✅ | ✅ | ❌ |
| Delete category | ✅ | ✅ | ❌ |

## Key Rules
- `strict_types=1` on every PHP file
- Always `$request->validated()` — never `$request->all()`
- Eager load with `with()` — never lazy load
- Soft delete only — never hard delete
- `$this->authorize()` on every controller action
- Every controller action has a Pest feature test
- Every service method has a Pest unit test
