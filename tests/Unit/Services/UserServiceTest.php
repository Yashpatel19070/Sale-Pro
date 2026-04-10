<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\User;
use App\Services\UserService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->service = new UserService;
});

it('creates a user with hashed password', function (): void {
    $user = $this->service->create([
        'name' => 'John Doe',
        'email' => 'john@test.com',
        'password' => 'password1',
        'role' => 'sales',
        'timezone' => 'UTC',
    ]);

    expect($user->email)->toBe('john@test.com');
    expect(Hash::check('password1', $user->password))->toBeTrue();
});

it('assigns role on create', function (): void {
    $user = $this->service->create([
        'name' => 'Manager User',
        'email' => 'mgr@test.com',
        'password' => 'password1',
        'role' => 'manager',
        'timezone' => 'UTC',
    ]);

    expect($user->hasRole('manager'))->toBeTrue();
});

it('defaults status to active on create', function (): void {
    $user = $this->service->create([
        'name' => 'Active User',
        'email' => 'active@test.com',
        'password' => 'password1',
        'role' => 'sales',
        'timezone' => 'UTC',
    ]);

    expect($user->status)->toBe(UserStatus::Active);
});

it('updates user fields', function (): void {
    $user = User::factory()->create(['name' => 'Old Name']);
    $updated = $this->service->update($user, ['name' => 'New Name']);

    expect($updated->name)->toBe('New Name');
});

it('syncs role on update when acting user is admin', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $user = User::factory()->create()->assignRole('sales');

    Auth::login($admin);

    $this->service->update($user, ['role' => 'manager']);

    expect($user->fresh()->hasRole('manager'))->toBeTrue();
    expect($user->fresh()->hasRole('sales'))->toBeFalse();
});

it('does not sync role on update when acting user is not admin', function (): void {
    $salesUser = User::factory()->create()->assignRole('sales');

    Auth::login($salesUser);

    $this->service->update($salesUser, ['role' => 'admin']);

    expect($salesUser->fresh()->hasRole('admin'))->toBeFalse();
    expect($salesUser->fresh()->hasRole('sales'))->toBeTrue();
});

it('soft deletes a user', function (): void {
    $user = User::factory()->create();

    $this->service->delete($user);

    expect(User::find($user->id))->toBeNull();
    expect(User::withTrashed()->find($user->id))->not->toBeNull();
});

it('throws when deleting user who manages a department', function (): void {
    $user = User::factory()->create();
    Department::factory()->create(['manager_id' => $user->id]);

    expect(fn () => $this->service->delete($user))
        ->toThrow(RuntimeException::class);
});

it('restores a soft-deleted user', function (): void {
    $user = User::factory()->create();
    $user->delete();

    $trashed = User::withTrashed()->findOrFail($user->id);
    $restored = $this->service->restore($trashed);

    expect($restored->deleted_at)->toBeNull();
    expect(User::find($user->id))->not->toBeNull();
});

it('changes user status', function (): void {
    $user = User::factory()->create(['status' => UserStatus::Active]);
    $updated = $this->service->changeStatus($user, UserStatus::Suspended);

    expect($updated->status)->toBe(UserStatus::Suspended);
});

it('updates profile fields only', function (): void {
    $user = User::factory()->create(['name' => 'Original', 'job_title' => 'Developer']);

    $this->service->updateProfile($user, [
        'name' => 'Updated',
        'email' => $user->email,
        'timezone' => 'UTC',
    ]);

    expect($user->fresh()->name)->toBe('Updated');
    expect($user->fresh()->job_title)->toBe('Developer'); // unchanged
});

it('caps perPage at 100 in list', function (): void {
    User::factory()->count(5)->create();

    $paginator = $this->service->list([], 200);

    expect($paginator->perPage())->toBe(100);
});

it('filters list by status', function (): void {
    User::factory()->count(2)->create(['status' => UserStatus::Active]);
    User::factory()->inactive()->create();

    $result = $this->service->list(['status' => 'inactive']);

    expect($result->total())->toBe(1);
});

it('filters list by search on name', function (): void {
    User::factory()->create(['name' => 'Alice Wonder']);
    User::factory()->create(['name' => 'Bob Builder']);

    $result = $this->service->list(['search' => 'Alice']);

    expect($result->total())->toBe(1);
    expect($result->items()[0]->name)->toBe('Alice Wonder');
});
