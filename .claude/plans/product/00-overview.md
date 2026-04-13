# Product Module — Overview

## Purpose
Internal product catalog. Each Product is the master record for a sellable item.
Staff and admins manage SKUs, costs, pricing, and category assignment.
Products are **not** directly visible to customers — they back ProductListings (variations) which are.

## Relationship to Other Modules
```
ProductCategory ←── Product ──→ ProductListing (many)
                                      ↓
                               Order Line Items
```
- A Product **belongs to** one ProductCategory
- A Product **has many** ProductListings (the actual sellable variants / storefront entries)
- Orders reference ProductListings, not Products directly

## Features
| # | Feature |
|---|---------|
| 1 | List products — paginated table, search by name/SKU, filter by category + status |
| 2 | View product — detail page with linked listings count |
| 3 | Create product — admin/staff adds product with SKU, pricing, category |
| 4 | Edit product — update all fields including SKU; SKU change auto-regenerates all listing slugs + creates redirects |
| 5 | Delete product — soft delete; block if active ProductListings exist |
| 6 | Restore product — restore a soft-deleted product |
| 7 | Toggle active — flip is_active without full edit |

## File Map
| File | Path |
|------|------|
| Migration | `database/migrations/xxxx_create_products_table.php` |
| Model | `app/Models/Product.php` |
| Factory | `database/factories/ProductFactory.php` |
| Service | `app/Services/ProductService.php` |
| Controller | `app/Http/Controllers/ProductController.php` |
| Store Request | `app/Http/Requests/Product/StoreProductRequest.php` |
| Update Request | `app/Http/Requests/Product/UpdateProductRequest.php` |
| Policy | `app/Policies/ProductPolicy.php` |
| View: index | `resources/views/products/index.blade.php` |
| View: show | `resources/views/products/show.blade.php` |
| View: create | `resources/views/products/create.blade.php` |
| View: edit | `resources/views/products/edit.blade.php` |
| View: _form | `resources/views/products/_form.blade.php` |
| Permission Seeder | `database/seeders/ProductPermissionSeeder.php` |
| Data Seeder | `database/seeders/ProductSeeder.php` |
| Feature Test | `tests/Feature/ProductControllerTest.php` |
| Unit Test | `tests/Unit/Services/ProductServiceTest.php` |

## Files to Modify
| File | Change |
|------|--------|
| `app/Enums/Permission.php` | Add 6 PRODUCTS_* constants |
| `app/Providers/AppServiceProvider.php` | Register ProductPolicy |
| `routes/web.php` | Add Route::resource + toggleActive + restore inside admin group |
| `database/seeders/DatabaseSeeder.php` | Call ProductPermissionSeeder + ProductSeeder |

## Implementation Order
1. Schema → Migration → `php artisan migrate`
2. Model + Factory
4. Service
5. FormRequests (Store + Update)
6. Policy + Permission constants
7. Controller
8. Routes
9. Views
10. Seeders
11. Tests

## Role Access Matrix
| Permission | Super Admin | Admin | Staff |
|------------|-------------|-------|-------|
| List products | ✅ | ✅ | ✅ |
| View product | ✅ | ✅ | ✅ |
| Create product | ✅ | ✅ | ❌ |
| Edit product | ✅ | ✅ | ❌ |
| Delete product | ✅ | ✅ | ❌ |
| Restore product | ✅ | ✅ | ❌ |

## Key Rules
- `strict_types=1` on every PHP file
- Always `$request->validated()` — never `$request->all()`
- Eager load with `with()` — never lazy load
- Soft delete only — block delete if active listings exist
- `$this->authorize()` on every controller action
- SKU is editable — changing it triggers `ProductListingService::regenerateSlugsForProduct()` which auto-regenerates slugs + creates 301 redirect records for all non-trashed listings (atomic — same transaction)
- Every controller action has a Pest feature test
- Every service method has a Pest unit test
