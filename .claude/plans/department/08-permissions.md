# Department Module — Permissions

## Strategy

The Department module uses **Spatie Laravel Permission** roles (not granular permissions)
because department management is admin-only. The policy maps roles directly.

## Role Matrix

| Policy method | admin | manager | sales |
|---------------|-------|---------|-------|
| viewAny       | ✓     | ✓       |       |
| view          | ✓     | ✓       |       |
| create        | ✓     |         |       |
| update        | ✓     |         |       |
| delete        | ✓     |         |       |
| restore       | ✓     |         |       |

## RoleSeeder Update

Add to `database/seeders/RoleSeeder.php`:

```php
// Roles already exist from init: admin, manager, sales
// No additional Spatie permissions needed for Department —
// the DepartmentPolicy uses hasRole() checks directly.
```

> If you later want granular permissions (e.g., a super-manager who can create
> departments), replace the `hasRole()` calls in `DepartmentPolicy` with
> `hasPermissionTo()` and seed those permissions in `RoleSeeder`.

## Navigation

Show the "Departments" nav link only to admin and manager roles:

```blade
@if(auth()->user()->hasAnyRole(['admin', 'manager']))
    <x-nav-link :href="route('departments.index')"
                :active="request()->routeIs('departments.*')">
        Departments
    </x-nav-link>
@endif
```
