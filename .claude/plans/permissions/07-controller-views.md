# Permissions Module — RoleController + Views

## Controller

File: `app/Http/Controllers/RoleController.php`

Actions: `index`, `show`, `edit`, `update` (no create/delete — roles are fixed in MVP).

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roleService) {}

    public function index(): View
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        return view('roles.index', compact('roles'));
    }

    public function show(Role $role): View
    {
        $role->load('permissions', 'users');
        return view('roles.show', compact('role'));
    }

    public function edit(Role $role): View
    {
        $role->load('permissions');
        $allPermissions = Permission::orderBy('name')->get()->groupBy(fn ($p) => explode('.', $p->name)[0]);
        return view('roles.edit', compact('role', 'allPermissions'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->roleService->syncPermissions($role, $validated['permissions'] ?? []);

        return redirect()->route('roles.show', $role)
            ->with('success', "Permissions for \"{$role->name}\" updated.");
    }
}
```

## Service

File: `app/Services/RoleService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;

class RoleService
{
    /**
     * Sync the given permission names onto the role.
     * Clears Spatie's permission cache after update.
     *
     * @param  string[]  $permissions
     */
    public function syncPermissions(Role $role, array $permissions): Role
    {
        $role->syncPermissions($permissions);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $role->refresh();
    }
}
```

## Views

### Folder structure
```
resources/views/roles/
├── index.blade.php   — table of all roles with their permission count
├── show.blade.php    — role detail: permissions list + users with this role
└── edit.blade.php    — checkboxes grouped by resource (users.*, departments.*, roles.*)
```

### `index.blade.php` — key elements
- Table: Role name | is_admin | Permissions count | Actions (View, Edit)
- Edit link hidden if `@cannot('roles.manage')`

### `show.blade.php` — key elements
- Role name + flags badge (Admin / Super)
- Permission list grouped by resource prefix
- Users table: name, email, status — who has this role

### `edit.blade.php` — key elements
- `<form method="POST" action="{{ route('roles.update', $role) }}">`
- `@method('PUT')` + `@csrf`
- Checkboxes grouped by resource prefix (users, departments, roles)
- Each checkbox: `name="permissions[]"` value="{{ $permission->name }}"
- Checked if role already has the permission
- Submit button disabled if `@cannot('roles.manage')`
