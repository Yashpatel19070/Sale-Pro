# Customer Module — Overview

## Purpose
The Customer module manages external CRM contacts — leads, prospects, and active customers.
Customers are **not** system users: they never log in. All customer data lives in a dedicated
`customers` table, separate from the `users` (staff) table.

## Why Separate Module (not extending User)
- Staff users authenticate; customers never do. Mixing them pollutes the `users` table
  with authentication columns that have no meaning for contacts.
- Spatie roles/permissions apply to staff only.
- Customers have a lifecycle (lead → prospect → active → churned) that is unrelated
  to staff `status` (active / inactive / suspended).
- Independent table enables customer-specific indexes and future portal authentication
  without touching the staff auth system.

## Scope

### In scope
- Full CRUD: create, view, edit, soft-delete, restore
- Customer status lifecycle: **lead → prospect → active → churned**
- Assign a customer to a sales rep (`assigned_to` → users.id)
- Department scoping (optional grouping)
- Source tracking (web, referral, cold call, etc.)
- Address fields (line1, line2, city, state, postcode, country)
- Notes free-text field
- Audit: created_by / updated_by via Observer

### Out of scope (future modules)
- CSV bulk import / export  ← deliberately excluded; add later as a separate feature
- Customer portal login / self-service
- Activity timeline (calls, emails, meetings)
- Deals / opportunities / pipeline
- Documents / attachments
- Email campaign integration
- Customer tags / custom fields

## Business Rules
1. `email` is nullable but must be unique in the `customers` table when provided.
2. `status` is one of: **lead**, **prospect**, **active**, **churned**. Default: **lead**.
3. `assigned_to` points to a staff user. Nullable — unassigned is valid.
4. Only `admin` can delete (soft), restore, or reassign any customer.
5. `manager` can view all customers in their department, create, edit, and assign.
6. `sales` can only view and edit customers assigned to themselves.
7. **Only admin and manager can change customer status.** Sales cannot.
8. **Manager must have a department assigned** (`department_id` required on the User record).
   A manager without a department is treated as having no dept-scoped access to customers.
9. Soft-delete only — history is preserved.
10. On user delete, `assigned_to` is set to NULL (nullOnDelete constraint).
11. **Multi-role priority order**: admin > manager > sales. If a user has admin role, admin
    rules apply. If manager (and not admin), manager rules apply. Etc.

## Roles & Access Matrix
| Action                    | admin | manager (own dept) | sales (assigned only) |
|---------------------------|-------|--------------------|-----------------------|
| List all customers        | ✓     |                    |                       |
| List dept customers       | ✓     | ✓                  |                       |
| List assigned customers   | ✓     | ✓                  | ✓                     |
| View any customer         | ✓     | ✓ (own dept)       | ✓ (assigned)          |
| Create customer           | ✓     | ✓                  |                       |
| Edit customer             | ✓     | ✓ (own dept)       | ✓ (assigned)          |
| Assign / reassign         | ✓     | ✓                  |                       |
| Change status             | ✓     | ✓                  |                       |
| Delete (soft)             | ✓     |                    |                       |
| Restore                   | ✓     |                    |                       |

## Module Checklist
- [ ] Migration: create `customers` table
- [ ] Enum: `App\Enums\CustomerStatus`
- [ ] Enum: `App\Enums\CustomerSource`
- [ ] Model: `App\Models\Customer`
- [ ] Factory: `Database\Factories\CustomerFactory`
- [ ] Observer: `App\Observers\CustomerObserver` (created_by / updated_by audit)
- [ ] Policy: `App\Policies\CustomerPolicy`
- [ ] FormRequests: `StoreCustomerRequest`, `UpdateCustomerRequest`
- [ ] Service: `App\Services\CustomerService`
- [ ] Controller: `App\Http\Controllers\CustomerController`
- [ ] Routes: module block inside shared middleware group (see 04-controller.md)
- [ ] Views: `resources/views/customers/` (index, create, edit, show) — all responsive
- [ ] Permission constants: `App\Enums\Permission` (add customer.* entries)
- [ ] RoleSeeder: add customer permissions to all three roles
- [ ] CustomerSeeder: seed ~5 demo customers for development (see 09-seeder.md)
- [ ] Update DatabaseSeeder: add CustomerSeeder after DepartmentSeeder (see 09-seeder.md)
- [ ] Update navigation.blade.php (desktop + mobile)
- [ ] AppServiceProvider: `Gate::policy(Customer::class, CustomerPolicy::class)`
- [ ] AppServiceProvider: `Customer::observe(CustomerObserver::class)`
- [ ] AppServiceProvider: `Route::bind('trashedCustomer', ...)` — required for restore route (see 02-model.md)
- [ ] Pest feature tests: `tests/Feature/Customers/CustomerControllerTest.php`
- [ ] Pest unit tests: `tests/Unit/Services/CustomerServiceTest.php`

## Dependencies
- Department module must be migrated first (`customers.department_id` FK).
- User module must be migrated first (`customers.assigned_to` FK → `users.id`).
- Permissions module (Spatie) must be seeded with `customer.*` permissions.
