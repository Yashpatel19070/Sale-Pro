# Supplier Module — Overview

## Purpose
Manage product suppliers. Admin/Manager can create, view, edit, deactivate suppliers.
Suppliers link to Purchase Orders — soft delete only, guard blocks delete when active POs exist.

## Features
| # | Feature |
|---|---------|
| 1 | List suppliers — paginated table with search + filter by status |
| 2 | View supplier — full profile detail page |
| 3 | Create supplier — admin/manager adds supplier manually |
| 4 | Edit supplier — update all fields |
| 5 | Delete / Deactivate — soft delete only; blocked if supplier has POs |
| 6 | Status management — change status (Active / Inactive) |
| 7 | Role-based permissions — Super Admin and Admin full, Manager create/edit/status, Sales view only |

## File Map
| File | Path |
|------|------|
| Migration | `database/migrations/xxxx_create_suppliers_table.php` |
| Enum | `app/Enums/SupplierStatus.php` |
| Model | `app/Models/Supplier.php` |
| Service | `app/Services/SupplierService.php` |
| Controller | `app/Http/Controllers/SupplierController.php` |
| Store Request | `app/Http/Requests/Supplier/StoreSupplierRequest.php` |
| Update Request | `app/Http/Requests/Supplier/UpdateSupplierRequest.php` |
| Change Status Request | `app/Http/Requests/Supplier/ChangeSupplierStatusRequest.php` |
| Policy | `app/Policies/SupplierPolicy.php` |
| View: index | `resources/views/suppliers/index.blade.php` |
| View: show | `resources/views/suppliers/show.blade.php` |
| View: create | `resources/views/suppliers/create.blade.php` |
| View: edit | `resources/views/suppliers/edit.blade.php` |
| Permission Seeder | `database/seeders/SupplierPermissionSeeder.php` |
| Feature Test | `tests/Feature/SupplierControllerTest.php` |
| Unit Test | `tests/Unit/SupplierServiceTest.php` |

## Implementation Order
1. Migration → run migrate
2. Enum
3. Model + Factory
4. Service
5. FormRequests (all 3)
6. Policy
7. Controller
8. Routes (add to web.php)
9. Views (index → show → create → edit)
10. Permission Seeder → run seeder
11. Tests

## Role Access Matrix
| Permission | Super Admin | Admin | Manager | Sales |
|------------|-------------|-------|---------|-------|
| List suppliers | ✅ | ✅ | ✅ | ✅ |
| View supplier | ✅ | ✅ | ✅ | ✅ |
| Create supplier | ✅ | ✅ | ✅ | ❌ |
| Edit supplier | ✅ | ✅ | ✅ | ❌ |
| Delete supplier | ✅ | ✅ | ❌ | ❌ |
| Change status | ✅ | ✅ | ✅ | ❌ |

## Key Rules (NEVER break these)
- `strict_types=1` on every PHP file
- Always use `$request->validated()` — never `$request->all()`
- Always eager load with `with()` — never lazy load
- `DB::transaction()` not required for single-table writes in this module
- Soft delete only — never hard delete suppliers
- Guard delete: throw `DomainException` if supplier has any purchase orders
- Policy gates on every controller action via `$this->authorize()`
- Every controller action must have a Pest feature test
- Every service method must have a Pest unit test

## Implementation Checklist

Complete every item in order. Do not skip ahead.

### Audit Logging
- [ ] `Supplier` model uses `LogsActivity` trait
- [ ] `getActivitylogOptions()` returns `LogOptions::defaults()->logFillable()->logOnlyDirty()`
- [ ] `Supplier::class => 'Supplier'` added to `AuditLogService::SUBJECT_TYPES`
- [ ] Verify: create a supplier → check `activity_log` table has a `created` entry

### Migration & Schema
- [ ] `create_suppliers_table` migration created
- [ ] All columns present: id, name, contact_name, email, phone, address, city, state, postal_code, country, payment_terms, notes, status, timestamps, deleted_at
- [ ] `email` has unique index
- [ ] `status` default is `'active'`
- [ ] `php artisan migrate` runs without error

### Enum
- [ ] `SupplierStatus` enum created at `app/Enums/SupplierStatus.php`
- [ ] Has 2 cases: `Active = 'active'`, `Inactive = 'inactive'`
- [ ] Has `label()` method
- [ ] Has `color()` method returning `green`, `yellow`

### Model
- [ ] `Supplier` model uses `HasFactory` and `SoftDeletes`
- [ ] `$fillable` has all 12 fields
- [ ] `status` cast to `SupplierStatus::class`
- [ ] `scopeByStatus(Builder $query, SupplierStatus $status)` defined
- [ ] `scopeSearch(Builder $query, string $term)` defined — searches name, email, contact_name

### Service
- [ ] `paginate(array $filters)` — filters by search + status, 20/page, withQueryString
- [ ] `store(array $data)` — creates Supplier
- [ ] `update(Supplier, array $data)` — updates + returns fresh
- [ ] `changeStatus(Supplier, SupplierStatus)` — updates status + returns fresh
- [ ] `delete(Supplier)` — soft delete; throws DomainException if supplier has POs

### FormRequests
- [ ] `StoreSupplierRequest` — all required fields validated, email unique in suppliers
- [ ] `UpdateSupplierRequest` — email unique ignores current supplier (`Rule::unique()->ignore`)
- [ ] `ChangeSupplierStatusRequest` — status validated with `Rule::enum(SupplierStatus::class)`
- [ ] All 3 requests have `authorize(): bool` delegating to Policy

### Policy
- [ ] `SupplierPolicy` created at `app/Policies/SupplierPolicy.php`
- [ ] 6 methods: viewAny, view, create, update, delete, changeStatus
- [ ] Every method checks `$user->can('suppliers.{action}')`

### Controller
- [ ] All 8 actions present: index, create, store, show, edit, update, destroy, changeStatus
- [ ] Every action calls `$this->authorize()`
- [ ] `store` and `update` use typed FormRequest — NOT plain Request
- [ ] `destroy` catches `DomainException` and redirects back with error
- [ ] All redirects use named routes
- [ ] Flash messages use `with('success', '...')` or `with('error', '...')`

### Routes
- [ ] All 8 routes added to `web.php` under `auth` + `verified` middleware
- [ ] Route names: suppliers.index, suppliers.create, suppliers.store, suppliers.show, suppliers.edit, suppliers.update, suppliers.destroy, suppliers.changeStatus
- [ ] Run `php artisan route:list | grep suppliers` to verify all 8 routes exist

### Views
- [ ] `index.blade.php` — table with search/filter, status badge, paginate, action buttons
- [ ] `show.blade.php` — all fields displayed, change status form, edit/back buttons
- [ ] `create.blade.php` — all fields, `old()` values, validation errors, status select
- [ ] `edit.blade.php` — pre-filled with `old('field', $supplier->field)`, status select
- [ ] Delete button uses POST form with `@method('DELETE')` and confirm dialog
- [ ] Edit/Delete buttons hidden from users without permission (`@can`)
- [ ] Flash message displayed on all views

### Permissions Seeder
- [ ] `SupplierPermissionSeeder` creates 6 permissions with `firstOrCreate`
- [ ] Super Admin gets all 6 permissions
- [ ] Admin gets all 6 permissions
- [ ] Manager gets viewAny, view, create, update, changeStatus (no delete)
- [ ] Sales gets only `suppliers.viewAny` and `suppliers.view`
- [ ] Seeder registered in `DatabaseSeeder`
- [ ] `php artisan db:seed --class=SupplierPermissionSeeder` runs without error

### Tests
- [ ] `SupplierFactory` created with all fields, status uses `->value`
- [ ] `SupplierControllerTest` has `beforeEach` seeding `SupplierPermissionSeeder`
- [ ] Feature tests: admin can do all 6 actions
- [ ] Feature tests: sales is forbidden for create, edit, delete, changeStatus
- [ ] Feature tests: guest redirected to login
- [ ] Feature tests: validation errors on invalid input
- [ ] Feature test: delete blocked when supplier has POs (future — add note, skip for now)
- [ ] Unit tests: all 5 service methods tested
- [ ] `php artisan test --filter SupplierControllerTest` — all pass
- [ ] `php artisan test --filter SupplierServiceTest` — all pass

---

## Routes (add to routes/web.php)

Add the `use` import at the top of web.php alongside other controller imports:
```php
use App\Http\Controllers\SupplierController;
```

Add the route group **inside** the existing admin middleware group (after the customers block).
Middleware is inherited from the parent group (`auth`, `load_perms`, `verified`, `active`).
URLs resolve as `/admin/suppliers/...`.

```php
// Inside: Route::prefix('admin')->group(function () {
//   Inside: Route::middleware(['auth', 'load_perms', 'verified', 'active'])->group(function () {

Route::prefix('suppliers')->name('suppliers.')->group(function () {
    Route::get('/',                    [SupplierController::class, 'index'])->name('index');
    Route::get('/create',              [SupplierController::class, 'create'])->name('create');
    Route::post('/',                   [SupplierController::class, 'store'])->name('store');
    Route::get('/{supplier}',          [SupplierController::class, 'show'])->name('show');
    Route::get('/{supplier}/edit',     [SupplierController::class, 'edit'])->name('edit');
    Route::put('/{supplier}',          [SupplierController::class, 'update'])->name('update');
    Route::delete('/{supplier}',       [SupplierController::class, 'destroy'])->name('destroy');
    Route::patch('/{supplier}/status', [SupplierController::class, 'changeStatus'])->name('changeStatus');
});
```
