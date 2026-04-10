# User Module — Permissions

## Strategy

Like the Department module, User management uses **role-based** checks in the Policy.
No granular Spatie permissions are needed initially — the three roles (admin, manager, sales)
map cleanly to the access matrix.

## Role Matrix (from UserPolicy)

| Policy action  | admin | manager (own dept) | sales (own only) |
|----------------|-------|--------------------|------------------|
| viewAny        | ✓     | ✓                  |                  |
| view           | ✓     | ✓                  | ✓ (self only)    |
| create         | ✓     |                    |                  |
| update         | ✓     |                    | ✓ (self only)    |
| delete         | ✓     |                    |                  |
| restore        | ✓     |                    |                  |
| changeStatus   | ✓     |                    |                  |
| resetPassword  | ✓     |                    |                  |

## RoleSeeder — No Changes Needed

The existing `RoleSeeder` already creates `admin`, `manager`, `sales` roles.
No additional Spatie permissions are seeded for the User module.

## Escalation Path

If you later need fine-grained control (e.g., a "HR manager" who can create but not delete),
move from `hasRole()` to `hasPermissionTo()` in the Policy and add permissions like:

```php
// Future granular permissions (not needed for MVP)
'user.view-any'
'user.view'
'user.create'
'user.update'
'user.delete'
'user.restore'
'user.change-status'
'user.reset-password'
```

Seed them in `RoleSeeder` and assign to roles as needed.

## Navigation

Show the "Users" nav link to admin and manager:

```blade
@if(auth()->user()->hasAnyRole(['admin', 'manager']))
    <x-nav-link :href="route('users.index')"
                :active="request()->routeIs('users.*')">
        Users
    </x-nav-link>
@endif
```

Show "My Profile" to all authenticated users:

```blade
<x-nav-link :href="route('profile.edit')"
            :active="request()->routeIs('profile.*')">
    My Profile
</x-nav-link>
```

## Middleware

Protect the `users.*` routes with `auth` and `verified` middleware.
The `profile.*` routes (Breeze) already use `auth`.
