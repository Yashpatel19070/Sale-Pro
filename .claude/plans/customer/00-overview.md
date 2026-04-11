# Customer Module — Overview

## Purpose
Manage e-commerce customers. Admin/Staff can create, view, edit, deactivate customers.
Customers do NOT log in (no portal in this module).

## Features
| # | Feature |
|---|---------|
| 1 | List customers — paginated table with search + filter by status |
| 2 | View customer — full profile detail page |
| 3 | Create customer — admin adds customer manually |
| 4 | Edit customer — update all fields |
| 5 | Delete / Deactivate — soft delete only, no hard delete |
| 6 | Status management — change status (Active / Inactive / Blocked) |
| 7 | Role-based permissions — Super Admin and Admin full access, Staff view only |

## File Map
| File | Path |
|------|------|
| Migration | `database/migrations/xxxx_create_customers_table.php` |
| Enum | `app/Enums/CustomerStatus.php` |
| Model | `app/Models/Customer.php` |
| Service | `app/Services/CustomerService.php` |
| Controller | `app/Http/Controllers/CustomerController.php` |
| Store Request | `app/Http/Requests/StoreCustomerRequest.php` |
| Update Request | `app/Http/Requests/UpdateCustomerRequest.php` |
| Change Status Request | `app/Http/Requests/ChangeCustomerStatusRequest.php` |
| Policy | `app/Policies/CustomerPolicy.php` |
| View: index | `resources/views/customers/index.blade.php` |
| View: show | `resources/views/customers/show.blade.php` |
| View: create | `resources/views/customers/create.blade.php` |
| View: edit | `resources/views/customers/edit.blade.php` |
| Permission Seeder | `database/seeders/CustomerPermissionSeeder.php` |
| Feature Test | `tests/Feature/CustomerControllerTest.php` |
| Unit Test | `tests/Unit/CustomerServiceTest.php` |

## Implementation Order
1. Migration → run migrate
2. Enum
3. Model
4. Service
5. FormRequests (all 3)
6. Policy
7. Controller
8. Routes (add to web.php)
9. Views (index → show → create → edit)
10. Permission Seeder → run seeder
11. Tests

## Role Access Matrix
| Permission | Super Admin | Admin | Staff |
|------------|-------------|-------|-------|
| List customers | ✅ | ✅ | ✅ |
| View customer | ✅ | ✅ | ✅ |
| Create customer | ✅ | ✅ | ❌ |
| Edit customer | ✅ | ✅ | ❌ |
| Delete customer | ✅ | ✅ | ❌ |
| Change status | ✅ | ✅ | ❌ |

## Key Rules (NEVER break these)
- `strict_types=1` on every PHP file
- Always use `$request->validated()` — never `$request->all()`
- Always eager load with `with()` — never lazy load
- `DB::transaction()` only needed if writing to multiple tables (not needed here unless future relations are added)
- Soft delete only — never hard delete customers
- Policy gates on every controller action via `$this->authorize()`
- Every controller action must have a Pest feature test
- Every service method must have a Pest unit test

## Implementation Checklist

Complete every item in order. Do not skip ahead.

### Migration & Schema
- [ ] `create_customers_table` migration created
- [ ] All columns present: id, name, email, phone, company_name, address, city, state, postal_code, country, status, timestamps, deleted_at
- [ ] `email` has unique index
- [ ] `status` default is `'active'`
- [ ] `php artisan migrate` runs without error

### Enum
- [ ] `CustomerStatus` enum created at `app/Enums/CustomerStatus.php`
- [ ] Has 3 cases: `Active = 'active'`, `Inactive = 'inactive'`, `Blocked = 'blocked'`
- [ ] Has `label()` method
- [ ] Has `color()` method returning `green`, `yellow`, `red`

### Model
- [ ] `Customer` model uses `HasFactory` and `SoftDeletes`
- [ ] `$fillable` has all 10 fields (name, email, phone, company_name, address, city, state, postal_code, country, status)
- [ ] `status` cast to `CustomerStatus::class`
- [ ] `scopeByStatus(Builder $query, CustomerStatus $status)` defined
- [ ] `scopeSearch(Builder $query, string $term)` defined — searches name, email, company_name

### Service
- [ ] `paginate(array $filters)` — filters by search + status, 20/page, withQueryString
- [ ] `store(array $data)` — creates Customer
- [ ] `update(Customer, array $data)` — updates + returns fresh
- [ ] `changeStatus(Customer, CustomerStatus)` — updates status + returns fresh
- [ ] `delete(Customer)` — soft delete only

### FormRequests
- [ ] `StoreCustomerRequest` — all 10 fields validated, email unique in customers
- [ ] `UpdateCustomerRequest` — email unique ignores current customer (`Rule::unique()->ignore`)
- [ ] `ChangeCustomerStatusRequest` — status validated with `Rule::enum(CustomerStatus::class)`
- [ ] All 3 requests have `authorize(): bool` returning `true`

### Policy
- [ ] `CustomerPolicy` created at `app/Policies/CustomerPolicy.php`
- [ ] 6 methods: viewAny, view, create, update, delete, changeStatus
- [ ] Every method checks `$user->can('customers.{action}')`
- [ ] Policy registered in `AuthServiceProvider` or auto-discovered

### Controller
- [ ] All 8 actions present: index, create, store, show, edit, update, destroy, changeStatus
- [ ] Every action calls `$this->authorize()`
- [ ] `store` and `update` use typed FormRequest — NOT plain Request
- [ ] All redirects use named routes
- [ ] Flash messages use `with('success', '...')`

### Routes
- [ ] All 8 routes added to `web.php` under `auth` + `verified` middleware
- [ ] Route names: customers.index, customers.create, customers.store, customers.show, customers.edit, customers.update, customers.destroy, customers.changeStatus
- [ ] Run `php artisan route:list | grep customers` to verify all 8 routes exist

### Views
- [ ] `index.blade.php` — table with search/filter form, status badge, paginate, action buttons
- [ ] `show.blade.php` — all fields displayed, change status form, edit/back buttons
- [ ] `create.blade.php` — all 10 fields, `old()` values, validation errors, status select
- [ ] `edit.blade.php` — pre-filled with `old('field', $customer->field)`, status select
- [ ] Delete button uses POST form with `@method('DELETE')` and confirm dialog
- [ ] Edit/Delete buttons hidden from users without permission (`@can`)
- [ ] Flash message displayed on all views

### Permissions Seeder
- [ ] `CustomerPermissionSeeder` creates 6 permissions with `firstOrCreate`
- [ ] Super Admin gets all 6 permissions
- [ ] Admin gets all 6 permissions
- [ ] Staff gets only `customers.viewAny` and `customers.view`
- [ ] Seeder registered in `DatabaseSeeder`
- [ ] `php artisan db:seed --class=CustomerPermissionSeeder` runs without error

### Tests
- [ ] `CustomerFactory` created with all fields, status uses `->value`
- [ ] `CustomerControllerTest` has `beforeEach` seeding `CustomerPermissionSeeder`
- [ ] Feature tests: admin can do all 6 actions
- [ ] Feature tests: staff is forbidden for create, edit, delete, changeStatus
- [ ] Feature tests: guest redirected to login
- [ ] Feature tests: validation errors on invalid input
- [ ] Unit tests: all 5 service methods tested
- [ ] `php artisan test --filter CustomerControllerTest` — all pass
- [ ] `php artisan test --filter CustomerServiceTest` — all pass

---

## Routes (add to routes/web.php)
```php
use App\Http\Controllers\CustomerController;

Route::middleware(['auth', 'verified'])->prefix('customers')->name('customers.')->group(function () {
    Route::get('/',                         [CustomerController::class, 'index'])->name('index');
    Route::get('/create',                   [CustomerController::class, 'create'])->name('create');
    Route::post('/',                        [CustomerController::class, 'store'])->name('store');
    Route::get('/{customer}',               [CustomerController::class, 'show'])->name('show');
    Route::get('/{customer}/edit',          [CustomerController::class, 'edit'])->name('edit');
    Route::put('/{customer}',               [CustomerController::class, 'update'])->name('update');
    Route::delete('/{customer}',            [CustomerController::class, 'destroy'])->name('destroy');
    Route::patch('/{customer}/status',      [CustomerController::class, 'changeStatus'])->name('changeStatus');
});
```
