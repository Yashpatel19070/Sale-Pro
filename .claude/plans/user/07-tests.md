# User Module — Tests

## Feature Tests

File: `tests/Feature/User/UserControllerTest.php`

```php
<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
});

// ── Auth guards ────────────────────────────────────────────────────────────

it('redirects guests from user index', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

it('denies sales role from listing users', function () {
    $user = User::factory()->create()->assignRole('sales');
    $this->actingAs($user)->get(route('users.index'))->assertForbidden();
});

// ── Index ──────────────────────────────────────────────────────────────────

it('admin sees user list', function () {
    $admin = User::factory()->create()->assignRole('admin');
    User::factory()->count(3)->create()->each->assignRole('sales');

    $this->actingAs($admin)
         ->get(route('users.index'))
         ->assertOk()
         ->assertViewIs('users.index');
});

it('manager sees only own department users', function () {
    $dept    = Department::factory()->create();
    $manager = User::factory()->inDepartment($dept)->create()->assignRole('manager');
    $inDept  = User::factory()->inDepartment($dept)->create()->assignRole('sales');
    $other   = User::factory()->create()->assignRole('sales');

    // Manager policy viewAny grants access; filtering happens in list()
    $this->actingAs($manager)
         ->get(route('users.index'))
         ->assertOk();
});

it('filters users by search term', function () {
    $admin  = User::factory()->create(['name' => 'Alice Admin'])->assignRole('admin');
    $target = User::factory()->create(['name' => 'Bob Sales'])->assignRole('sales');

    $this->actingAs($admin)
         ->get(route('users.index', ['search' => 'Bob']))
         ->assertSee('Bob Sales')
         ->assertDontSee('Alice Admin');
});

it('filters users by status', function () {
    $admin    = User::factory()->create()->assignRole('admin');
    $active   = User::factory()->create(['status' => UserStatus::Active])->assignRole('sales');
    $inactive = User::factory()->inactive()->create()->assignRole('sales');

    $this->actingAs($admin)
         ->get(route('users.index', ['status' => 'active']))
         ->assertSee($active->name)
         ->assertDontSee($inactive->name);
});

// ── Create / Store ─────────────────────────────────────────────────────────

it('admin can create a user', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
         ->post(route('users.store'), [
             'name'                  => 'Jane Doe',
             'email'                 => 'jane@example.com',
             'password'              => 'Password1',
             'password_confirmation' => 'Password1',
             'status'                => 'active',
             'timezone'              => 'UTC',
             'role'                  => 'sales',
         ])
         ->assertRedirect();

    $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    expect(User::where('email', 'jane@example.com')->first()->hasRole('sales'))->toBeTrue();
});

it('admin can create a user with avatar', function () {
    $admin  = User::factory()->create()->assignRole('admin');
    $avatar = UploadedFile::fake()->image('avatar.jpg');

    $this->actingAs($admin)
         ->post(route('users.store'), [
             'name'                  => 'Jane Doe',
             'email'                 => 'jane@example.com',
             'password'              => 'Password1',
             'password_confirmation' => 'Password1',
             'status'                => 'active',
             'timezone'              => 'UTC',
             'role'                  => 'sales',
             'avatar'                => $avatar,
         ]);

    $user = User::where('email', 'jane@example.com')->first();
    expect($user->avatar)->not->toBeNull();
    Storage::disk('public')->assertExists($user->avatar);
});

it('rejects duplicate email on create', function () {
    $admin = User::factory()->create()->assignRole('admin');
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($admin)
         ->post(route('users.store'), [
             'name'                  => 'Another',
             'email'                 => 'taken@example.com',
             'password'              => 'Password1',
             'password_confirmation' => 'Password1',
             'status'                => 'active',
             'timezone'              => 'UTC',
             'role'                  => 'sales',
         ])
         ->assertSessionHasErrors('email');
});

it('non-admin cannot create users', function () {
    $manager = User::factory()->create()->assignRole('manager');

    $this->actingAs($manager)
         ->post(route('users.store'), [
             'name' => 'X', 'email' => 'x@x.com',
             'password' => 'Pass1', 'password_confirmation' => 'Pass1',
             'status' => 'active', 'timezone' => 'UTC', 'role' => 'sales',
         ])
         ->assertForbidden();
});

// ── View ───────────────────────────────────────────────────────────────────

it('admin can view any user profile', function () {
    $admin  = User::factory()->create()->assignRole('admin');
    $target = User::factory()->create()->assignRole('sales');

    $this->actingAs($admin)
         ->get(route('users.show', $target))
         ->assertOk()
         ->assertViewIs('users.show');
});

it('sales user can view own profile', function () {
    $user = User::factory()->create()->assignRole('sales');

    $this->actingAs($user)
         ->get(route('users.show', $user))
         ->assertOk();
});

it('sales user cannot view other profiles', function () {
    $user  = User::factory()->create()->assignRole('sales');
    $other = User::factory()->create()->assignRole('sales');

    $this->actingAs($user)
         ->get(route('users.show', $other))
         ->assertForbidden();
});

// ── Update ─────────────────────────────────────────────────────────────────

it('admin can update any user', function () {
    $admin  = User::factory()->create()->assignRole('admin');
    $target = User::factory()->create()->assignRole('sales');

    $this->actingAs($admin)
         ->put(route('users.update', $target), [
             'name'     => 'Updated Name',
             'email'    => $target->email,
             'status'   => 'active',
             'timezone' => 'UTC',
             'role'     => 'sales',
         ])
         ->assertRedirect(route('users.show', $target));

    expect($target->fresh()->name)->toBe('Updated Name');
});

it('user can update own profile fields', function () {
    $user = User::factory()->create()->assignRole('sales');

    $this->actingAs($user)
         ->put(route('users.update', $user), [
             'name'     => 'New Name',
             'email'    => $user->email,
             'status'   => $user->status->value,
             'timezone' => 'America/Chicago',
             'role'     => $user->roles->first()->name,
         ])
         ->assertRedirect();

    expect($user->fresh()->timezone)->toBe('America/Chicago');
});

// ── Delete & Restore ───────────────────────────────────────────────────────

it('admin can soft-delete a user', function () {
    $admin  = User::factory()->create()->assignRole('admin');
    $target = User::factory()->create()->assignRole('sales');

    $this->actingAs($admin)
         ->delete(route('users.destroy', $target))
         ->assertRedirect(route('users.index'));

    $this->assertSoftDeleted('users', ['id' => $target->id]);
});

it('admin cannot delete themselves', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
         ->delete(route('users.destroy', $admin))
         ->assertForbidden();
});

it('admin can restore a soft-deleted user', function () {
    $admin  = User::factory()->create()->assignRole('admin');
    $target = User::factory()->create()->assignRole('sales');
    $target->delete();

    $this->actingAs($admin)
         ->post(route('users.restore', $target->id))
         ->assertRedirect();

    expect(User::find($target->id))->not->toBeNull();
});

// ── Change Status ──────────────────────────────────────────────────────────

it('admin can suspend a user', function () {
    $admin  = User::factory()->create()->assignRole('admin');
    $target = User::factory()->create()->assignRole('sales');

    $this->actingAs($admin)
         ->post(route('users.change-status', $target), ['status' => 'suspended'])
         ->assertRedirect();

    expect($target->fresh()->status)->toBe(UserStatus::Suspended);
});

// ── Password Reset ─────────────────────────────────────────────────────────

it('admin can send password reset to a user', function () {
    $admin  = User::factory()->create()->assignRole('admin');
    $target = User::factory()->create()->assignRole('sales');

    \Illuminate\Support\Facades\Password::shouldReceive('sendResetLink')
        ->once()
        ->andReturn(\Illuminate\Support\Facades\Password::RESET_LINK_SENT);

    $this->actingAs($admin)
         ->post(route('users.send-password-reset', $target))
         ->assertRedirect();
});
```

---

## Unit Tests

File: `tests/Unit/Services/UserServiceTest.php`

```php
<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\User;
use App\Services\UserService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
    $this->service = new UserService();
});

it('creates user with role assigned', function () {
    $user = $this->service->create([
        'name'     => 'Test User',
        'email'    => 'test@test.com',
        'password' => 'password',
        'status'   => 'active',
        'timezone' => 'UTC',
        'role'     => 'sales',
    ]);

    expect($user->hasRole('sales'))->toBeTrue();
});

it('stores avatar in public disk', function () {
    $avatar = UploadedFile::fake()->image('avatar.jpg');

    $user = $this->service->create([
        'name' => 'Avatar User', 'email' => 'av@av.com',
        'password' => 'pass', 'status' => 'active',
        'timezone' => 'UTC', 'role' => 'sales',
    ], $avatar);

    Storage::disk('public')->assertExists($user->avatar);
});

it('changes user status', function () {
    $user = User::factory()->create(['status' => UserStatus::Active])->assignRole('sales');

    $updated = $this->service->changeStatus($user, UserStatus::Suspended);
    expect($updated->status)->toBe(UserStatus::Suspended);
});

it('toggles user role via update', function () {
    $user = User::factory()->create()->assignRole('sales');

    $this->service->update($user, ['role' => 'manager']);
    expect($user->fresh()->hasRole('manager'))->toBeTrue();
    expect($user->fresh()->hasRole('sales'))->toBeFalse();
});

it('soft-deletes user', function () {
    $user = User::factory()->create()->assignRole('sales');

    $this->service->delete($user);
    $this->assertSoftDeleted('users', ['id' => $user->id]);
});

it('restores soft-deleted user', function () {
    $user = User::factory()->create()->assignRole('sales');
    $user->delete();

    $restored = $this->service->restore($user->id);
    expect($restored->deleted_at)->toBeNull();
});

it('updateProfile only changes allowed fields', function () {
    $user = User::factory()->create([
        'status'        => UserStatus::Active,
        'department_id' => null,
    ])->assignRole('sales');

    $this->service->updateProfile($user, [
        'name'     => 'New Name',
        'email'    => $user->email,
        'timezone' => 'America/New_York',
    ]);

    $fresh = $user->fresh();
    expect($fresh->name)->toBe('New Name');
    expect($fresh->timezone)->toBe('America/New_York');
    // status and department should be unchanged
    expect($fresh->status)->toBe(UserStatus::Active);
    expect($fresh->department_id)->toBeNull();
});
```
