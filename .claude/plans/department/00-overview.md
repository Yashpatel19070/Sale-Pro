# Department Module — Overview

## Purpose
Departments are the organisational units that group users (staff) in the sales application.
Every user belongs to exactly one department. A department optionally has a manager (one of
its own users). This module drives the sales-team hierarchy and is a dependency for the User,
Lead, and reporting modules.

## Business Rules
1. A department **name** and **code** must be unique across the system.
2. `code` is an uppercase short key (e.g. `SALES`, `MKT`, `OPS`) — max 20 chars.
3. A department's **manager** must be an existing, active user.
4. A department can be **soft-deleted** only when it has no active users.
5. Departments are **never hard-deleted** through the UI.
6. `is_active = false` hides the department from assignment dropdowns but retains history.

## Roles & Access
| Action          | admin | manager | sales |
|-----------------|-------|---------|-------|
| view list       | ✓     | ✓       |       |
| view detail     | ✓     | ✓       |       |
| create          | ✓     |         |       |
| update          | ✓     |         |       |
| toggle active   | ✓     |         |       |
| delete (soft)   | ✓     |         |       |
| restore         | ✓     |         |       |

## Module Checklist
- [ ] Migration: `departments`
- [ ] Enum: `App\Enums\DepartmentStatus`
- [ ] Model: `App\Models\Department`
- [ ] Policy: `App\Policies\DepartmentPolicy`
- [ ] FormRequests: `StoreDepartmentRequest`, `UpdateDepartmentRequest`
- [ ] Service: `App\Services\DepartmentService`
- [ ] Controller: `App\Http\Controllers\DepartmentController`
- [ ] Routes: `routes/web.php` resource block
- [ ] Views: `resources/views/departments/` (index, create, edit, show)
- [ ] Seeder: `DepartmentSeeder`
- [ ] Pest feature tests
- [ ] Pest unit tests (service)
- [ ] Permissions seeded in `RoleSeeder`

## Dependency Order
1. `departments` migration (no FK dependencies)
2. Permissions for department
3. `DepartmentSeeder` (creates default departments)
4. User module references `departments.id`
