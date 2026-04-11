<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\Role;
use App\Services\RoleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->service = new RoleService;
});

it('syncs permissions onto role', function (): void {
    $role = Role::where('name', 'sales')->first();

    $updated = $this->service->syncPermissions($role, [
        Permission::USERS_VIEW,
        Permission::USERS_EDIT,
    ]);

    expect($updated->hasPermissionTo(Permission::USERS_VIEW))->toBeTrue();
    expect($updated->hasPermissionTo(Permission::USERS_EDIT))->toBeTrue();
});

it('removes permissions not in the new list', function (): void {
    $role = Role::where('name', 'sales')->first();

    // Give sales an extra permission first
    $this->service->syncPermissions($role, [
        Permission::USERS_VIEW,
        Permission::USERS_EDIT,
        Permission::DEPARTMENTS_VIEW,
    ]);

    // Sync to fewer permissions
    $updated = $this->service->syncPermissions($role, [
        Permission::USERS_VIEW,
    ]);

    expect($updated->hasPermissionTo(Permission::USERS_VIEW))->toBeTrue();
    expect($updated->hasPermissionTo(Permission::DEPARTMENTS_VIEW))->toBeFalse();
});

it('returns refreshed role after sync', function (): void {
    $role    = Role::where('name', 'sales')->first();
    $updated = $this->service->syncPermissions($role, [Permission::USERS_VIEW]);

    expect($updated)->toBeInstanceOf(Role::class);
    expect($updated->id)->toBe($role->id);
});
