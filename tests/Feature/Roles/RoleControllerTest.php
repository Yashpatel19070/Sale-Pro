<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

// ── Auth / role guards ─────────────────────────────────────────────────────

it('redirects guests from roles index', function (): void {
    $this->get(route('roles.index'))->assertRedirect(route('login'));
});

it('denies manager from roles index', function (): void {
    $manager = User::factory()->create()->assignRole('manager');

    $this->actingAs($manager)
        ->get(route('roles.index'))
        ->assertForbidden();
});

it('denies sales from roles index', function (): void {
    $sales = User::factory()->create()->assignRole('sales');

    $this->actingAs($sales)
        ->get(route('roles.index'))
        ->assertForbidden();
});

// ── Index ──────────────────────────────────────────────────────────────────

it('admin can view roles index', function (): void {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertViewIs('roles.index')
        ->assertViewHas('roles');
});

// ── Show ───────────────────────────────────────────────────────────────────

it('admin can view role show page', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $role  = Role::where('name', 'manager')->first();

    $this->actingAs($admin)
        ->get(route('roles.show', $role))
        ->assertOk()
        ->assertViewIs('roles.show');
});

// ── Edit ───────────────────────────────────────────────────────────────────

it('admin can view role edit page', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $role  = Role::where('name', 'sales')->first();

    $this->actingAs($admin)
        ->get(route('roles.edit', $role))
        ->assertOk()
        ->assertViewIs('roles.edit')
        ->assertViewHas('allPermissions');
});

// ── Update ─────────────────────────────────────────────────────────────────

it('admin can update role permissions', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $role  = Role::where('name', 'sales')->first();

    $this->actingAs($admin)
        ->put(route('roles.update', $role), [
            'permissions' => [Permission::USERS_VIEW, Permission::USERS_EDIT],
        ])
        ->assertRedirect(route('roles.show', $role))
        ->assertSessionHas('success');

    expect($role->fresh()->hasPermissionTo(Permission::USERS_VIEW))->toBeTrue();
    expect($role->fresh()->hasPermissionTo(Permission::USERS_EDIT))->toBeTrue();
});

it('update accepts empty permissions array', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $role  = Role::where('name', 'sales')->first();

    $this->actingAs($admin)
        ->put(route('roles.update', $role), [])
        ->assertRedirect(route('roles.show', $role));
});

it('update rejects non-existent permission name', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $role  = Role::where('name', 'sales')->first();

    $this->actingAs($admin)
        ->put(route('roles.update', $role), [
            'permissions' => ['fake.permission'],
        ])
        ->assertSessionHasErrors('permissions.0');
});
