<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

// ── Auth guards ────────────────────────────────────────────────────────────

it('redirects guests from department index', function (): void {
    $this->get(route('departments.index'))->assertRedirect(route('login'));
});

it('denies sales role from viewing departments', function (): void {
    $user = User::factory()->create()->assignRole('sales');

    $this->actingAs($user)->get(route('departments.index'))->assertForbidden();
});

// ── Index ──────────────────────────────────────────────────────────────────

it('shows department list for admin', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    Department::factory()->count(3)->create();

    $this->actingAs($admin)
         ->get(route('departments.index'))
         ->assertOk()
         ->assertViewIs('departments.index')
         ->assertViewHas('departments');
});

it('shows department list for manager', function (): void {
    $manager = User::factory()->create()->assignRole('manager');
    Department::factory()->count(2)->create();

    $this->actingAs($manager)
         ->get(route('departments.index'))
         ->assertOk();
});

it('filters departments by search term', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    Department::factory()->create(['name' => 'Sales Team', 'code' => 'SALES']);
    Department::factory()->create(['name' => 'Marketing',  'code' => 'MKT']);

    $this->actingAs($admin)
         ->get(route('departments.index', ['search' => 'Sales']))
         ->assertOk()
         ->assertSee('Sales Team')
         ->assertDontSee('Marketing');
});

// ── Create / Store ─────────────────────────────────────────────────────────

it('admin can access create form', function (): void {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
         ->get(route('departments.create'))
         ->assertOk()
         ->assertViewIs('departments.create');
});

it('admin can create a department', function (): void {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
         ->post(route('departments.store'), [
             'name'      => 'Sales',
             'code'      => 'SALES',
             'is_active' => 1,
         ])
         ->assertRedirect();

    $this->assertDatabaseHas('departments', ['code' => 'SALES', 'name' => 'Sales']);
});

it('stores code in uppercase', function (): void {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
         ->post(route('departments.store'), [
             'name' => 'Finance',
             'code' => 'fin',
         ]);

    $this->assertDatabaseHas('departments', ['code' => 'FIN']);
});

it('rejects duplicate department name', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    Department::factory()->create(['name' => 'Sales', 'code' => 'SALES']);

    $this->actingAs($admin)
         ->post(route('departments.store'), ['name' => 'Sales', 'code' => 'MKT'])
         ->assertSessionHasErrors('name');
});

it('rejects duplicate department code', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    Department::factory()->create(['name' => 'Sales', 'code' => 'SALES']);

    $this->actingAs($admin)
         ->post(route('departments.store'), ['name' => 'Sales New', 'code' => 'SALES'])
         ->assertSessionHasErrors('code');
});

it('rejects non-alpha code', function (): void {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
         ->post(route('departments.store'), ['name' => 'Test', 'code' => 'SAL-ES'])
         ->assertSessionHasErrors('code');
});

it('manager role cannot create a department', function (): void {
    $manager = User::factory()->create()->assignRole('manager');

    $this->actingAs($manager)
         ->post(route('departments.store'), ['name' => 'Test', 'code' => 'TST'])
         ->assertForbidden();
});

// ── Show ───────────────────────────────────────────────────────────────────

it('admin can view a department', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $dept  = Department::factory()->create();

    $this->actingAs($admin)
         ->get(route('departments.show', $dept))
         ->assertOk()
         ->assertViewIs('departments.show');
});

// ── Update ─────────────────────────────────────────────────────────────────

it('admin can update a department', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $dept  = Department::factory()->create(['name' => 'Old Name', 'code' => 'OLD']);

    $this->actingAs($admin)
         ->put(route('departments.update', $dept), [
             'name' => 'New Name',
             'code' => 'OLD',
         ])
         ->assertRedirect(route('departments.show', $dept));

    expect($dept->fresh()->name)->toBe('New Name');
});

// ── Delete ─────────────────────────────────────────────────────────────────

it('admin can soft-delete an empty department', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $dept  = Department::factory()->create();

    $this->actingAs($admin)
         ->delete(route('departments.destroy', $dept))
         ->assertRedirect(route('departments.index'));

    $this->assertSoftDeleted('departments', ['id' => $dept->id]);
});

it('cannot delete a department with active users', function (): void {
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

it('admin can deactivate a department', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $dept  = Department::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
         ->post(route('departments.toggle-active', $dept));

    expect($dept->fresh()->is_active)->toBeFalse();
});

it('admin can reactivate a department', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $dept  = Department::factory()->create(['is_active' => false]);

    $this->actingAs($admin)
         ->post(route('departments.toggle-active', $dept));

    expect($dept->fresh()->is_active)->toBeTrue();
});

// ── Manager cannot mutate ──────────────────────────────────────────────────

it('manager cannot delete a department', function (): void {
    $manager = User::factory()->create()->assignRole('manager');
    $dept    = Department::factory()->create();

    $this->actingAs($manager)
         ->delete(route('departments.destroy', $dept))
         ->assertForbidden();
});

it('manager cannot toggle department active state', function (): void {
    $manager = User::factory()->create()->assignRole('manager');
    $dept    = Department::factory()->create();

    $this->actingAs($manager)
         ->post(route('departments.toggle-active', $dept))
         ->assertForbidden();
});

// ── Restore ────────────────────────────────────────────────────────────────

it('admin can restore a soft-deleted department', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $dept  = Department::factory()->create();
    $dept->delete();

    $this->actingAs($admin)
         ->post(route('departments.restore', $dept->id))
         ->assertRedirect();

    expect(Department::find($dept->id))->not->toBeNull();
});

it('manager cannot restore a soft-deleted department', function (): void {
    $manager = User::factory()->create()->assignRole('manager');
    $dept    = Department::factory()->create();
    $dept->delete();

    $this->actingAs($manager)
         ->post(route('departments.restore', $dept->id))
         ->assertForbidden();
});
