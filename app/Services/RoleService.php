<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;

class RoleService
{
    /**
     * Sync the given permission names onto the role and clear Spatie's cache.
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
