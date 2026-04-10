<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

// ── Auth guards ────────────────────────────────────────────────────────────

it('redirects guests from user index', function (): void {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

it('denies sales role from viewing user list', function (): void {
    $user = User::factory()->create()->assignRole('sales');

    $this->actingAs($user)->get(route('users.index'))->assertForbidden();
});

// ── Index ──────────────────────────────────────────────────────────────────

it('shows user list for admin', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    User::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertViewIs('users.index')
        ->assertViewHas('users');
});

it('shows user list for manager', function (): void {
    $manager = User::factory()->create()->assignRole('manager');

    $this->actingAs($manager)
        ->get(route('users.index'))
        ->assertOk();
});

it('filters users by search term', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    User::factory()->create(['name' => 'Alice Smith', 'email' => 'alice@test.com']);
    User::factory()->create(['name' => 'Bob Jones', 'email' => 'bob@test.com']);

    $this->actingAs($admin)
        ->get(route('users.index', ['search' => 'Alice']))
        ->assertOk()
        ->assertSee('Alice Smith')
        ->assertDontSee('Bob Jones');
});

it('filters users by status', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $active = User::factory()->create(['status' => UserStatus::Active]);
    $inactive = User::factory()->inactive()->create();

    $this->actingAs($admin)
        ->get(route('users.index', ['status' => 'inactive']))
        ->assertOk()
        ->assertSee($inactive->name)
        ->assertDontSee($active->name);
});

// ── Create / Store ─────────────────────────────────────────────────────────

it('admin can access create form', function (): void {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('users.create'))
        ->assertOk()
        ->assertViewIs('users.create');
});

it('manager cannot access create form', function (): void {
    $manager = User::factory()->create()->assignRole('manager');

    $this->actingAs($manager)
        ->get(route('users.create'))
        ->assertForbidden();
});

it('admin can create a user', function (): void {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'New User',
            'email' => 'newuser@test.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
            'role' => 'sales',
            'status' => 'active',
            'timezone' => 'UTC',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', ['email' => 'newuser@test.com']);
});

it('rejects duplicate email on store', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    User::factory()->create(['email' => 'dup@test.com']);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Dup',
            'email' => 'dup@test.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
            'role' => 'sales',
            'status' => 'active',
            'timezone' => 'UTC',
        ])
        ->assertSessionHasErrors('email');
});

it('rejects mismatched password confirmation', function (): void {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Test',
            'email' => 'test@test.com',
            'password' => 'password1',
            'password_confirmation' => 'different1',
            'role' => 'sales',
            'status' => 'active',
            'timezone' => 'UTC',
        ])
        ->assertSessionHasErrors('password');
});

it('manager cannot create a user', function (): void {
    $manager = User::factory()->create()->assignRole('manager');

    $this->actingAs($manager)
        ->post(route('users.store'), [
            'name' => 'New',
            'email' => 'new@test.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
            'role' => 'sales',
            'status' => 'active',
            'timezone' => 'UTC',
        ])
        ->assertForbidden();
});

// ── Show ───────────────────────────────────────────────────────────────────

it('admin can view any user', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('users.show', $target))
        ->assertOk()
        ->assertViewIs('users.show');
});

it('sales user can view their own profile', function (): void {
    $sales = User::factory()->create()->assignRole('sales');

    $this->actingAs($sales)
        ->get(route('users.show', $sales))
        ->assertOk();
});

it('sales user cannot view another user profile', function (): void {
    $sales = User::factory()->create()->assignRole('sales');
    $other = User::factory()->create();

    $this->actingAs($sales)
        ->get(route('users.show', $other))
        ->assertForbidden();
});

it('manager can view user in same department', function (): void {
    $dept = Department::factory()->create();
    $manager = User::factory()->inDepartment($dept)->create()->assignRole('manager');
    $member = User::factory()->inDepartment($dept)->create();

    $this->actingAs($manager)
        ->get(route('users.show', $member))
        ->assertOk();
});

it('manager cannot view user in different department', function (): void {
    $dept1 = Department::factory()->create();
    $dept2 = Department::factory()->create();
    $manager = User::factory()->inDepartment($dept1)->create()->assignRole('manager');
    $other = User::factory()->inDepartment($dept2)->create();

    $this->actingAs($manager)
        ->get(route('users.show', $other))
        ->assertForbidden();
});

// ── Update ─────────────────────────────────────────────────────────────────

it('admin can update a user', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $target = User::factory()->create(['name' => 'Old Name']);

    $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => 'New Name',
            'email' => $target->email,
            'role' => 'sales',
            'status' => 'active',
            'timezone' => 'UTC',
        ])
        ->assertRedirect(route('users.show', $target));

    expect($target->fresh()->name)->toBe('New Name');
});

it('user can update their own profile', function (): void {
    $user = User::factory()->create(['name' => 'Original'])->assignRole('sales');

    $this->actingAs($user)
        ->put(route('users.update', $user), [
            'name' => 'Updated Self',
            'email' => $user->email,
            'role' => 'sales',
            'status' => 'active',
            'timezone' => 'UTC',
        ])
        ->assertRedirect(route('users.show', $user));

    expect($user->fresh()->name)->toBe('Updated Self');
});

it('manager cannot update another user', function (): void {
    $manager = User::factory()->create()->assignRole('manager');
    $target = User::factory()->create();

    $this->actingAs($manager)
        ->put(route('users.update', $target), [
            'name' => 'Hijacked',
            'email' => $target->email,
            'role' => 'sales',
            'status' => 'active',
            'timezone' => 'UTC',
        ])
        ->assertForbidden();
});

// ── Delete ─────────────────────────────────────────────────────────────────

it('admin can soft-delete a user', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target))
        ->assertRedirect(route('users.index'));

    $this->assertSoftDeleted('users', ['id' => $target->id]);
});

it('admin cannot delete themselves', function (): void {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->delete(route('users.destroy', $admin))
        ->assertForbidden();
});

it('cannot delete a user who manages a department', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $manager = User::factory()->create();
    Department::factory()->create(['manager_id' => $manager->id]);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $manager))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('users', ['id' => $manager->id, 'deleted_at' => null]);
});

it('manager cannot delete a user', function (): void {
    $manager = User::factory()->create()->assignRole('manager');
    $target = User::factory()->create();

    $this->actingAs($manager)
        ->delete(route('users.destroy', $target))
        ->assertForbidden();
});

// ── Change Status ──────────────────────────────────────────────────────────

it('admin can change user status', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $target = User::factory()->create(['status' => UserStatus::Active]);

    $this->actingAs($admin)
        ->post(route('users.change-status', $target), ['status' => 'suspended'])
        ->assertRedirect();

    expect($target->fresh()->status)->toBe(UserStatus::Suspended);
});

it('admin cannot change their own status', function (): void {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('users.change-status', $admin), ['status' => 'inactive'])
        ->assertForbidden();
});

it('manager cannot change user status', function (): void {
    $manager = User::factory()->create()->assignRole('manager');
    $target = User::factory()->create();

    $this->actingAs($manager)
        ->post(route('users.change-status', $target), ['status' => 'inactive'])
        ->assertForbidden();
});

// ── Password Reset ─────────────────────────────────────────────────────────

it('admin can send password reset email', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('users.send-password-reset', $target))
        ->assertRedirect();
});

it('manager cannot send password reset', function (): void {
    $manager = User::factory()->create()->assignRole('manager');
    $target = User::factory()->create();

    $this->actingAs($manager)
        ->post(route('users.send-password-reset', $target))
        ->assertForbidden();
});

// ── Restore ────────────────────────────────────────────────────────────────

it('admin can restore a soft-deleted user', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $target = User::factory()->create();
    $target->delete();

    $this->actingAs($admin)
        ->post(route('users.restore', $target->id))
        ->assertRedirect();

    expect(User::find($target->id))->not->toBeNull();
});

it('manager cannot restore a soft-deleted user', function (): void {
    $manager = User::factory()->create()->assignRole('manager');
    $target = User::factory()->create();
    $target->delete();

    $this->actingAs($manager)
        ->post(route('users.restore', $target->id))
        ->assertForbidden();
});

it('rejects creating a user with the email of a soft-deleted user', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $deleted = User::factory()->create(['email' => 'taken@test.com']);
    $deleted->delete();

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'New User',
            'email' => 'taken@test.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
            'role' => 'sales',
            'status' => 'active',
            'timezone' => 'UTC',
        ])
        ->assertSessionHasErrors('email');
});
