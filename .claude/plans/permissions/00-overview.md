# Permissions Module — Overview

## What This Module Does

Implements the full Spatie Laravel Permission system per the `references/permissions-spatie.md`
and `references/middleware.md` skill files. Provides:

- DB-driven role flags (`is_admin`, `is_super`) — no hardcoded role names in middleware
- Named permissions seeded and assigned to roles
- Four custom middleware classes with zero N+1 on permission checks
- Gate bypass for superadmin
- Roles management UI (index, show, edit) gated by `roles.manage` permission

## Roles for This Project

| Role | `is_admin` | `is_super` | Access |
|------|:----------:|:----------:|--------|
| `admin` | ✅ | ❌ | Full CRUD on users + departments + roles.view |
| `manager` | ❌ | ❌ | View users in own dept, view departments |
| `sales` | ❌ | ❌ | View + edit own profile only |

No `superadmin` role in MVP. `EnsureSuperAdmin` middleware is implemented for future use.

## Permission Matrix

| Permission | admin | manager | sales |
|------------|:-----:|:-------:|:-----:|
| `users.view-any` | ✅ | ✅ | ❌ |
| `users.view` | ✅ | ✅ | ✅ (self) |
| `users.create` | ✅ | ❌ | ❌ |
| `users.edit` | ✅ | ❌ | ✅ (self) |
| `users.delete` | ✅ | ❌ | ❌ |
| `users.restore` | ✅ | ❌ | ❌ |
| `users.change-status` | ✅ | ❌ | ❌ |
| `users.reset-password` | ✅ | ❌ | ❌ |
| `departments.view-any` | ✅ | ✅ | ❌ |
| `departments.view` | ✅ | ✅ | ❌ |
| `departments.create` | ✅ | ❌ | ❌ |
| `departments.edit` | ✅ | ❌ | ❌ |
| `departments.delete` | ✅ | ❌ | ❌ |
| `departments.restore` | ✅ | ❌ | ❌ |
| `roles.view` | ✅ | ❌ | ❌ |
| `roles.manage` | ✅ | ❌ | ❌ |

## Build Order

1. `01-migration.md` — add `is_admin`, `is_super` to `roles` table
2. `02-middleware.md` — 4 custom middleware classes
3. `03-bootstrap.md` — register aliases in `bootstrap/app.php`
4. `04-routes.md` — update route stacks, add roles routes
5. `05-seeder.md` — update Permission enum + RoleSeeder
6. `06-gate.md` — Gate::before() superadmin bypass in AppServiceProvider
7. `07-controller-views.md` — RoleController + role views
8. `08-tests.md` — feature + unit tests
