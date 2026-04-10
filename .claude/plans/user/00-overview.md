# User Module — Overview

## Purpose
The User module manages staff accounts in the sales application. It extends the Breeze auth
scaffold (login, register, email verification, password reset) with a full admin-facing CRUD
interface for user management, role assignment, and profile data.

## Scope

### In scope
- Admin creates / edits / suspends / deletes staff users
- Assign roles (admin, manager, sales) and department
- Extended profile fields: phone, avatar, job title, employee ID, status, hired date, timezone
- Self-service profile editing (own profile — name, email, phone, avatar, timezone)
- Admin-triggered password reset (sends password-reset email)
- Soft-delete users; admin can restore
- Audit: created_by / updated_by tracking via model observer

### Out of scope (separate modules later)
- Impersonation (`actingAs`)
- Two-factor authentication
- API tokens

## Business Rules
1. `email` must be unique across the system.
2. A user's `status` is one of: **active**, **inactive**, **suspended**.
3. Only `admin` can create users, assign roles, change status, or delete.
4. A `manager` can view users within their own department.
5. A `sales` user can only view and edit their own profile.
6. Deleting a user soft-deletes — their data (leads, activities) is retained.
7. A user who is the `manager_id` on a department cannot be deleted without first
   removing that assignment.
8. Password is set by the admin on create; the user can change it via profile.

## Roles & Access
| Action                   | admin | manager (own dept) | sales (own) |
|--------------------------|-------|--------------------|-------------|
| List all users           | ✓     |                    |             |
| List own-dept users      | ✓     | ✓                  |             |
| View any user            | ✓     | ✓ (own dept)       |             |
| View own profile         | ✓     | ✓                  | ✓           |
| Create user              | ✓     |                    |             |
| Edit any user            | ✓     |                    |             |
| Edit own profile         | ✓     | ✓                  | ✓           |
| Assign role/department   | ✓     |                    |             |
| Change status            | ✓     |                    |             |
| Delete / restore         | ✓     |                    |             |
| Reset password (admin)   | ✓     |                    |             |

## Module Checklist
- [ ] Migration: alter `users` table (add profile columns)
- [ ] Enum: `App\Enums\UserStatus`
- [ ] Model: update `App\Models\User`
- [ ] Policy: `App\Policies\UserPolicy`
- [ ] FormRequests: `StoreUserRequest`, `UpdateUserRequest`, `UpdateProfileRequest`
- [ ] Service: `App\Services\UserService`
- [ ] Controller: `App\Http\Controllers\UserController`
- [ ] Controller: `App\Http\Controllers\ProfileController` (already exists — update)
- [ ] Routes: resource block + custom actions
- [ ] Views: `resources/views/users/` (index, create, edit, show)
- [ ] Views: update `resources/views/profile/` (Breeze profile views)
- [ ] Avatar upload: stored in `storage/app/public/avatars/`
- [ ] Seeder: update `DatabaseSeeder` to seed a default admin
- [ ] Pest feature tests
- [ ] Pest unit tests (service)

## Dependency
- **Department module must be migrated and seeded first.**
  The `users` table alteration adds `department_id` FK → `departments.id`.
