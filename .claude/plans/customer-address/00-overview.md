# Customer Address Module — Overview

## Purpose

Manage physical addresses for customers. Sub-resource of the customer module.
Admin creates, edits, and removes addresses per customer. One address per customer can be marked as default.
Addresses are referenced live by shipments (FK) and copied as immutable snapshots into orders at creation.

---

## Features

### Admin
| # | Feature |
|---|---------|
| 1 | List addresses — table of all addresses for a customer with default badge |
| 2 | Create address — admin adds a new address for a customer |
| 3 | Edit address — update all fields including toggling default |
| 4 | Set default — dedicated action to mark one address as the default |
| 5 | Delete — soft delete only (hard delete blocked by `shipments.customer_address_id` FK) |

### Portal (Customer Self-Service) ✅ Done
| # | Feature |
|---|---------|
| 6 | List own addresses — customer sees their own addresses at `/addresses` |
| 7 | Add address — customer adds new address via portal |
| 8 | Edit address — customer edits their own address |
| 9 | Set default — customer marks one as default |
| 10 | Delete — customer deletes non-default address |

---

## Portal Security Model

- No Spatie policy — `CustomerAddressPolicy` uses `User` (web guard). Portal uses `customer` guard.
- Ownership enforced via scoped query: `$customer->addresses()->findOrFail($address->id)` — returns **404** (not 403) if address belongs to another customer. No data leaked.
- `customer_id` never accepted from request input — set via relationship in service.
- All portal routes inside `auth:customer` + `verified` + `customer.active` middleware.

---

## Portal Routes

| Method | URL | Name | Action |
|--------|-----|------|--------|
| GET | /addresses | `portal.addresses.index` | `index` |
| GET | /addresses/create | `portal.addresses.create` | `create` |
| POST | /addresses | `portal.addresses.store` | `store` |
| GET | /addresses/{address}/edit | `portal.addresses.edit` | `edit` |
| PUT | /addresses/{address} | `portal.addresses.update` | `update` |
| DELETE | /addresses/{address} | `portal.addresses.destroy` | `destroy` |
| PATCH | /addresses/{address}/default | `portal.addresses.setDefault` | `setDefault` |

---

## Portal Files

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Portal/CustomerAddressController.php` | Portal address controller |
| `resources/views/portal/addresses/index.blade.php` | List view |
| `resources/views/portal/addresses/create.blade.php` | Create form |
| `resources/views/portal/addresses/edit.blade.php` | Edit form |
| `resources/views/portal/profile/show.blade.php` | Add addresses card |
| `resources/views/portal/layouts/app.blade.php` | Add Addresses nav link |
| `tests/Feature/Portal/PortalAddressControllerTest.php` | Feature tests |

---

## File Map

| File | Path |
|------|------|
| Migration | `database/migrations/xxxx_create_customer_addresses_table.php` |
| Model | `app/Models/CustomerAddress.php` |
| Service | `app/Services/CustomerAddressService.php` |
| Controller | `app/Http/Controllers/CustomerAddressController.php` |
| Store Request | `app/Http/Requests/CustomerAddress/StoreCustomerAddressRequest.php` |
| Update Request | `app/Http/Requests/CustomerAddress/UpdateCustomerAddressRequest.php` |
| Policy | `app/Policies/CustomerAddressPolicy.php` |
| View: index | `resources/views/customer-addresses/index.blade.php` |
| View: create | `resources/views/customer-addresses/create.blade.php` |
| View: edit | `resources/views/customer-addresses/edit.blade.php` |
| Permission constants | `app/Enums/Permission.php` (append constants) |
| Permission Seeder | `database/seeders/CustomerAddressPermissionSeeder.php` |
| Data Seeder | `database/seeders/CustomerAddressSeeder.php` |
| Factory | `database/factories/CustomerAddressFactory.php` |
| Feature Test | `tests/Feature/CustomerAddressControllerTest.php` |
| Unit Test | `tests/Unit/CustomerAddressServiceTest.php` |

---

## Routes

All nested inside the existing `customers.` prefix group in `routes/web.php`.

| Method | URL | Route Name | Controller Action |
|--------|-----|------------|-------------------|
| GET | /admin/customers/{customer}/addresses | `customer-addresses.index` | `index` |
| GET | /admin/customers/{customer}/addresses/create | `customer-addresses.create` | `create` |
| POST | /admin/customers/{customer}/addresses | `customer-addresses.store` | `store` |
| GET | /admin/customers/{customer}/addresses/{address}/edit | `customer-addresses.edit` | `edit` |
| PUT | /admin/customers/{customer}/addresses/{address} | `customer-addresses.update` | `update` |
| DELETE | /admin/customers/{customer}/addresses/{address} | `customer-addresses.destroy` | `destroy` |
| PATCH | /admin/customers/{customer}/addresses/{address}/default | `customer-addresses.setDefault` | `setDefault` |

```php
// Add inside the existing customers.* prefix group in web.php:
Route::prefix('/{customer}/addresses')->name('customer-addresses.')->group(function () {
    Route::get('/',                          [CustomerAddressController::class, 'index'])->name('index');
    Route::get('/create',                    [CustomerAddressController::class, 'create'])->name('create');
    Route::post('/',                         [CustomerAddressController::class, 'store'])->name('store');
    Route::get('/{address}/edit',            [CustomerAddressController::class, 'edit'])->name('edit');
    Route::put('/{address}',                 [CustomerAddressController::class, 'update'])->name('update');
    Route::delete('/{address}',              [CustomerAddressController::class, 'destroy'])->name('destroy');
    Route::patch('/{address}/default',       [CustomerAddressController::class, 'setDefault'])->name('setDefault');
});
```

---

## Implementation Order

1. Migration → `php artisan migrate`
2. Model (`CustomerAddress`)
3. Service (`CustomerAddressService`)
4. FormRequests (`StoreCustomerAddressRequest`, `UpdateCustomerAddressRequest`)
5. Policy (`CustomerAddressPolicy`)
6. Controller (`CustomerAddressController`)
7. Routes (add to `web.php` inside customers prefix group)
8. Views (`index` → `create` → `edit`)
9. Permission constants (add to `Permission.php`)
10. Permission Seeder → `php artisan db:seed --class=CustomerAddressPermissionSeeder`
11. Tests (factory → feature → unit)

---

## Role Access Matrix

| Permission | admin | manager | sales |
|------------|:-----:|:-------:|:-----:|
| List addresses (`viewAny`) | ✅ | ✅ | ✅ |
| View single address (`view`) | ✅ | ✅ | ✅ |
| Create address (`create`) | ✅ | ✅ | ❌ |
| Edit address (`update`) | ✅ | ✅ | ❌ |
| Delete address (`delete`) | ✅ | ❌ | ❌ |
| Set default (`setDefault`) | ✅ | ✅ | ❌ |

> `manager` cannot delete — protects FK integrity. Only admin can soft-delete.

---

## Key Rules (NEVER break these)

- `strict_types=1` on every PHP file
- Always `$request->validated()` — never `$request->all()`
- `DB::transaction()` required in `store()`, `update()`, `setDefault()` — all three touch `is_default` on multiple rows
- Soft delete only — `forceDelete()` is never called (shipments FK blocks hard delete)
- Only one `is_default = true` per customer — enforced in service layer, NOT DB constraint
- Policy must verify `$address->customer_id === $customer->id` on every address-scoped action
- `$this->authorize()` on every controller action — no exceptions
- Every controller action has a Pest feature test
- Every service method has a Pest unit test

---

## Implementation Checklist

### Migration & Schema
- [ ] `create_customer_addresses_table` migration created
- [ ] All columns present per `01-schema.md`: id, customer_id, label, first_name, last_name, email, phone, address_line1, address_line2, city, state, postal_code, country, is_default, timestamps, deleted_at
- [ ] `customer_id` FK → `customers.id` with cascade delete
- [ ] Composite index `(customer_id, is_default)` added
- [ ] `is_default` default `false`, `country` default `'US'`
- [ ] `php artisan migrate` runs without error

### Model
- [ ] `HasFactory` and `SoftDeletes` traits
- [ ] `$fillable` matches all assignable columns
- [ ] `is_default` cast to `'boolean'`
- [ ] `belongsTo Customer` relationship defined
- [ ] `scopeDefault()` scope for `where('is_default', true)`

### Service
- [ ] `list(Customer)` returns collection ordered default-first, then by label
- [ ] `store(Customer, array)` wraps in `DB::transaction` — unsets other defaults when `is_default = true`
- [ ] `update(CustomerAddress, array)` wraps in `DB::transaction` — unsets others when promoting to default
- [ ] `setDefault(CustomerAddress)` wraps in `DB::transaction` — unsets all others first
- [ ] `delete(CustomerAddress)` soft deletes — never `forceDelete()`

### FormRequests
- [ ] `StoreCustomerAddressRequest` — all fields validated, `prepareForValidation` normalizes `is_default` checkbox
- [ ] `UpdateCustomerAddressRequest` — same rules as Store (no unique constraints to ignore)
- [ ] Both return `authorize(): true`

### Policy
- [ ] 6 methods: `viewAny`, `view`, `create`, `update`, `delete`, `setDefault`
- [ ] Each checks `$user->can(Permission::CUSTOMER_ADDRESSES_*)` constant
- [ ] `view`, `update`, `delete`, `setDefault` also check `$address->customer_id === $customer->id`

### Controller
- [ ] 7 actions: `index`, `create`, `store`, `edit`, `update`, `destroy`, `setDefault`
- [ ] Every action calls `$this->authorize()` with correct arguments
- [ ] Route model binding for both `{customer}` and `{address}`
- [ ] All redirects to `customer-addresses.index` with `$customer`

### Views
- [ ] `index.blade.php` — address table, default badge, Set Default button, Edit, Delete
- [ ] `create.blade.php` — all fields, `is_default` checkbox, validation errors
- [ ] `edit.blade.php` — all fields pre-filled with `old('field', $address->field)`, `is_default` checkbox

### Permissions
- [ ] 6 constants added to `app/Enums/Permission.php`
- [ ] `CustomerAddressPermissionSeeder` created and registered in `DatabaseSeeder`
- [ ] Correct permissions per role (see Role Access Matrix above)
- [ ] `php artisan db:seed --class=CustomerAddressPermissionSeeder` runs without error

### Tests
- [ ] `CustomerAddressFactory` generates valid address data
- [ ] Feature tests: admin happy path for all 7 actions
- [ ] Feature tests: manager forbidden from delete
- [ ] Feature tests: sales forbidden from create, edit, update, delete, setDefault
- [ ] Feature tests: guest redirected to login
- [ ] Feature tests: validation errors on missing required fields
- [ ] Feature tests: `setDefault` correctly flips `is_default` flag
- [ ] Unit tests: all 5 service methods covered
- [ ] `php artisan test --filter CustomerAddress` — all pass
