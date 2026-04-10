# Department Module — Tests

## Feature Tests

File: `tests/Feature/Department/DepartmentControllerTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

// ── Authorization ──────────────────────────────────────────────────────────

it('redirects guests from department index', function () {
    $this->get(route('departments.index'))->assertRedirect(route('login'));
});

it('denies sales role from viewing departments', function () {
    $user = User::factory()->create()->assignRole('sales');
    $this->actingAs($user)->get(route('departments.index'))->assertForbidden();
});

// ── Index ──────────────────────────────────────────────────────────────────

it('shows department list for admin', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Department::factory()->count(3)->create();

    $this->actingAs($admin)
         ->get(route('departments.index'))
         ->assertOk()
         ->assertViewIs('departments.index')
         ->assertViewHas('departments');
});

it('filters departments by search term', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Department::factory()->create(['name' => 'Sales Team', 'code' => 'SALES']);
    Department::factory()->create(['name' => 'Marketing', 'code' => 'MKT']);

    $this->actingAs($admin)
         ->get(route('departments.index', ['search' => 'Sales']))
         ->assertOk()
         ->assertSee('Sales Team')
         ->assertDontSee('Marketing');
});

// ── Create / Store ─────────────────────────────────────────────────────────

it('admin can create a department', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
         ->post(route('departments.store'), [
             'name'      => 'Sales',
             'code'      => 'SALES',
             'is_active' => 1,
         ])
         ->assertRedirect();

    $this->assertDatabaseHas('departments', ['code' => 'SALES']);
});

it('rejects duplicate department name', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Department::factory()->create(['name' => 'Sales', 'code' => 'SALES']);

    $this->actingAs($admin)
         ->post(route('departments.store'), ['name' => 'Sales', 'code' => 'MKT'])
         ->assertSessionHasErrors('name');
});

it('rejects duplicate department code', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Department::factory()->create(['name' => 'Sales', 'code' => 'SALES']);

    $this->actingAs($admin)
         ->post(route('departments.store'), ['name' => 'Sales New', 'code' => 'SALES'])
         ->assertSessionHasErrors('code');
});

it('rejects non-alpha code', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
         ->post(route('departments.store'), ['name' => 'Test', 'code' => 'SAL-ES'])
         ->assertSessionHasErrors('code');
});

it('manager role cannot create a department', function () {
    $manager = User::factory()->create()->assignRole('manager');

    $this->actingAs($manager)
         ->post(route('departments.store'), ['name' => 'Test', 'code' => 'TST'])
         ->assertForbidden();
});

// ── Update ─────────────────────────────────────────────────────────────────

it('admin can update a department', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $dept  = Department::factory()->create(['name' => 'Old Name', 'code' => 'OLD']);

    $this->actingAs($admin)
         ->put(route('departments.update', $dept), [
             'name' => 'New Name', 'code' => 'OLD',
         ])
         ->assertRedirect(route('departments.show', $dept));

    expect($dept->fresh()->name)->toBe('New Name');
});

// ── Delete ─────────────────────────────────────────────────────────────────

it('admin can delete an empty department', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $dept  = Department::factory()->create();

    $this->actingAs($admin)
         ->delete(route('departments.destroy', $dept))
         ->assertRedirect(route('departments.index'));

    $this->assertSoftDeleted('departments', ['id' => $dept->id]);
});

it('cannot delete a department with active users', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $dept  = Department::factory()->create();
    User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);

    $this->actingAs($admin)
         ->delete(route('departments.destroy', $dept))
         ->assertRedirect()
         ->assertSessionHas('error');

    $this->assertDatabaseHas('departments', ['id' => $dept->id, 'deleted_at' => null]);
});

// ── Toggle Active ──────────────────────────────────────────────────────────

it('admin can toggle department active state', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $dept  = Department::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
         ->post(route('departments.toggle-active', $dept));

    expect($dept->fresh()->is_active)->toBeFalse();
});

// ── Restore ────────────────────────────────────────────────────────────────

it('admin can restore a soft-deleted department', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $dept  = Department::factory()->create();
    $dept->delete();

    $this->actingAs($admin)
         ->post(route('departments.restore', $dept->id))
         ->assertRedirect();

    expect(Department::find($dept->id))->not->toBeNull();
});
```

---

## Unit Tests

File: `tests/Unit/Services/DepartmentServiceTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\User;
use App\Services\DepartmentService;
use Database\Seeders\RoleSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->service = new DepartmentService();
});

it('creates a department with uppercased code', function () {
    $dept = $this->service->create(['name' => 'Finance', 'code' => 'fin']);
    expect($dept->code)->toBe('FIN');
});

it('throws when deleting department with active users', function () {
    $dept = Department::factory()->create();
    User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);

    expect(fn () => $this->service->delete($dept))
        ->toThrow(\RuntimeException::class);
});

it('allows deleting department with only inactive users', function () {
    $dept = Department::factory()->create();
    User::factory()->create(['department_id' => $dept->id, 'status' => 'inactive']);

    $this->service->delete($dept);
    $this->assertSoftDeleted('departments', ['id' => $dept->id]);
});

it('toggles active state', function () {
    $dept = Department::factory()->create(['is_active' => true]);
    $updated = $this->service->toggleActive($dept);
    expect($updated->is_active)->toBeFalse();
});

it('restores a soft-deleted department', function () {
    $dept = Department::factory()->create();
    $dept->delete();

    $restored = $this->service->restore($dept->id);
    expect($restored->deleted_at)->toBeNull();
});

it('returns dropdown list of active departments only', function () {
    Department::factory()->create(['is_active' => true]);
    Department::factory()->inactive()->create();

    $list = $this->service->dropdown();
    expect($list)->toHaveCount(1);
});
```
