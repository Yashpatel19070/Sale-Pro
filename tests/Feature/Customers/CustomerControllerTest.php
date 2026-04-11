<?php

declare(strict_types=1);

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

// ── Auth / guest guard ────────────────────────────────────────────────────────

it('redirects guests from customers index', function (): void {
    $this->get(route('customers.index'))->assertRedirect(route('login'));
});

it('redirects guests from customer create page', function (): void {
    $this->get(route('customers.create'))->assertRedirect(route('login'));
});

// ── Index — role visibility ───────────────────────────────────────────────────

it('admin can view customers index', function (): void {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertViewIs('customers.index');
});

it('manager can view customers index', function (): void {
    $dept    = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $dept->id])->assignRole('manager');

    $this->actingAs($manager)
        ->get(route('customers.index'))
        ->assertOk();
});

it('sales can view customers index', function (): void {
    $sales = User::factory()->create()->assignRole('sales');

    $this->actingAs($sales)
        ->get(route('customers.index'))
        ->assertOk();
});

// ── Index — scoped data ───────────────────────────────────────────────────────

it('admin sees all customers on index', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    $dept  = Department::factory()->create();
    $sales = User::factory()->create()->assignRole('sales');

    Customer::factory()->create(['first_name' => 'Alice', 'last_name' => 'One', 'department_id' => $dept->id]);
    Customer::factory()->create(['first_name' => 'Bob',   'last_name' => 'Two',  'assigned_to'   => $sales->id]);

    $this->actingAs($admin)
        ->get(route('customers.index'))
        ->assertSee('Alice')
        ->assertSee('Bob');
});

it('manager sees only dept customers on index', function (): void {
    $dept    = Department::factory()->create();
    $other   = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $dept->id])->assignRole('manager');

    Customer::factory()->create(['first_name' => 'DeptCustomer', 'last_name' => 'A', 'department_id' => $dept->id]);
    Customer::factory()->create(['first_name' => 'OtherCustomer', 'last_name' => 'B', 'department_id' => $other->id]);

    $this->actingAs($manager)
        ->get(route('customers.index'))
        ->assertSee('DeptCustomer')
        ->assertDontSee('OtherCustomer');
});

it('sales sees only assigned customers on index', function (): void {
    $sales  = User::factory()->create()->assignRole('sales');
    $other  = User::factory()->create()->assignRole('sales');

    Customer::factory()->create(['first_name' => 'MyCustomer',    'last_name' => 'A', 'assigned_to' => $sales->id]);
    Customer::factory()->create(['first_name' => 'OtherCustomer', 'last_name' => 'B', 'assigned_to' => $other->id]);

    $this->actingAs($sales)
        ->get(route('customers.index'))
        ->assertSee('MyCustomer')
        ->assertDontSee('OtherCustomer');
});

it('manager without department sees no customers', function (): void {
    $manager  = User::factory()->create(['department_id' => null])->assignRole('manager');
    $dept     = Department::factory()->create();

    Customer::factory()->create(['first_name' => 'Hidden', 'last_name' => 'Customer', 'department_id' => $dept->id]);

    $this->actingAs($manager)
        ->get(route('customers.index'))
        ->assertDontSee('Hidden');
});

// ── Create / Store ────────────────────────────────────────────────────────────

it('admin can access create page', function (): void {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('customers.create'))
        ->assertOk()
        ->assertViewIs('customers.create');
});

it('manager can access create page', function (): void {
    $dept    = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $dept->id])->assignRole('manager');

    $this->actingAs($manager)
        ->get(route('customers.create'))
        ->assertOk();
});

it('sales cannot access create page', function (): void {
    $sales = User::factory()->create()->assignRole('sales');

    $this->actingAs($sales)
        ->get(route('customers.create'))
        ->assertForbidden();
});

it('admin can create a customer', function (): void {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('customers.store'), [
            'first_name' => 'Test',
            'last_name'  => 'Customer',
            'status'     => CustomerStatus::Lead->value,
            'source'     => CustomerSource::Web->value,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('customers', ['first_name' => 'Test', 'last_name' => 'Customer']);
});

it('manager can create a customer', function (): void {
    $dept    = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $dept->id])->assignRole('manager');

    $this->actingAs($manager)
        ->post(route('customers.store'), [
            'first_name' => 'Mgr',
            'last_name'  => 'Created',
            'status'     => CustomerStatus::Lead->value,
            'source'     => CustomerSource::Referral->value,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('customers', ['first_name' => 'Mgr']);
});

it('sales cannot create a customer', function (): void {
    $sales = User::factory()->create()->assignRole('sales');

    $this->actingAs($sales)
        ->post(route('customers.store'), [
            'first_name' => 'Sales',
            'last_name'  => 'Create',
            'status'     => CustomerStatus::Lead->value,
            'source'     => CustomerSource::Web->value,
        ])
        ->assertForbidden();
});

it('store fails validation when first_name missing', function (): void {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('customers.store'), [
            'last_name' => 'NoFirst',
            'status'    => CustomerStatus::Lead->value,
            'source'    => CustomerSource::Web->value,
        ])
        ->assertSessionHasErrors('first_name');
});

it('store fails validation when duplicate email', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    Customer::factory()->create(['email' => 'dup@example.com']);

    $this->actingAs($admin)
        ->post(route('customers.store'), [
            'first_name' => 'Dup',
            'last_name'  => 'Email',
            'email'      => 'dup@example.com',
            'status'     => CustomerStatus::Lead->value,
            'source'     => CustomerSource::Web->value,
        ])
        ->assertSessionHasErrors('email');
});

// ── Show ──────────────────────────────────────────────────────────────────────

it('admin can view any customer', function (): void {
    $admin    = User::factory()->create()->assignRole('admin');
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->get(route('customers.show', $customer))
        ->assertOk()
        ->assertViewIs('customers.show');
});

it('manager can view own-dept customer', function (): void {
    $dept     = Department::factory()->create();
    $manager  = User::factory()->create(['department_id' => $dept->id])->assignRole('manager');
    $customer = Customer::factory()->inDepartment($dept->id)->create();

    $this->actingAs($manager)
        ->get(route('customers.show', $customer))
        ->assertOk();
});

it('manager cannot view out-of-dept customer', function (): void {
    $dept1    = Department::factory()->create();
    $dept2    = Department::factory()->create();
    $manager  = User::factory()->create(['department_id' => $dept1->id])->assignRole('manager');
    $customer = Customer::factory()->inDepartment($dept2->id)->create();

    $this->actingAs($manager)
        ->get(route('customers.show', $customer))
        ->assertForbidden();
});

it('sales can view assigned customer', function (): void {
    $sales    = User::factory()->create()->assignRole('sales');
    $customer = Customer::factory()->assignedTo($sales->id)->create();

    $this->actingAs($sales)
        ->get(route('customers.show', $customer))
        ->assertOk();
});

it('sales cannot view unassigned customer', function (): void {
    $sales    = User::factory()->create()->assignRole('sales');
    $customer = Customer::factory()->create(['assigned_to' => null]);

    $this->actingAs($sales)
        ->get(route('customers.show', $customer))
        ->assertForbidden();
});

// ── Edit / Update ─────────────────────────────────────────────────────────────

it('admin can access edit page for any customer', function (): void {
    $admin    = User::factory()->create()->assignRole('admin');
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->get(route('customers.edit', $customer))
        ->assertOk()
        ->assertViewIs('customers.edit');
});

it('sales can access edit page for assigned customer', function (): void {
    $sales    = User::factory()->create()->assignRole('sales');
    $customer = Customer::factory()->assignedTo($sales->id)->create();

    $this->actingAs($sales)
        ->get(route('customers.edit', $customer))
        ->assertOk();
});

it('sales cannot access edit page for unassigned customer', function (): void {
    $sales    = User::factory()->create()->assignRole('sales');
    $customer = Customer::factory()->create(['assigned_to' => null]);

    $this->actingAs($sales)
        ->get(route('customers.edit', $customer))
        ->assertForbidden();
});

it('admin can update a customer', function (): void {
    $admin    = User::factory()->create()->assignRole('admin');
    $customer = Customer::factory()->create(['first_name' => 'Old']);

    $this->actingAs($admin)
        ->put(route('customers.update', $customer), [
            'first_name' => 'New',
            'last_name'  => $customer->last_name,
            'status'     => $customer->status->value,
            'source'     => $customer->source->value,
        ])
        ->assertRedirect(route('customers.show', $customer));

    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'first_name' => 'New']);
});

it('sales can update assigned customer', function (): void {
    $sales    = User::factory()->create()->assignRole('sales');
    $customer = Customer::factory()->assignedTo($sales->id)->create(['first_name' => 'Old']);

    $this->actingAs($sales)
        ->put(route('customers.update', $customer), [
            'first_name' => 'Updated',
            'last_name'  => $customer->last_name,
            'status'     => $customer->status->value,
            'source'     => $customer->source->value,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'first_name' => 'Updated']);
});

it('sales cannot update unassigned customer', function (): void {
    $sales    = User::factory()->create()->assignRole('sales');
    $customer = Customer::factory()->create(['assigned_to' => null]);

    $this->actingAs($sales)
        ->put(route('customers.update', $customer), [
            'first_name' => 'Hacked',
            'last_name'  => $customer->last_name,
            'status'     => $customer->status->value,
            'source'     => $customer->source->value,
        ])
        ->assertForbidden();
});

it('update ignores own email uniqueness check', function (): void {
    $admin    = User::factory()->create()->assignRole('admin');
    $customer = Customer::factory()->create(['email' => 'same@example.com']);

    $this->actingAs($admin)
        ->put(route('customers.update', $customer), [
            'first_name' => $customer->first_name,
            'last_name'  => $customer->last_name,
            'email'      => 'same@example.com',
            'status'     => $customer->status->value,
            'source'     => $customer->source->value,
        ])
        ->assertRedirect(route('customers.show', $customer));
});

it('update fails validation with invalid status enum', function (): void {
    $admin    = User::factory()->create()->assignRole('admin');
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->put(route('customers.update', $customer), [
            'first_name' => $customer->first_name,
            'last_name'  => $customer->last_name,
            'status'     => 'invalid_status',
            'source'     => $customer->source->value,
        ])
        ->assertSessionHasErrors('status');
});

// ── Delete / Restore ──────────────────────────────────────────────────────────

it('admin can delete a customer', function (): void {
    $admin    = User::factory()->create()->assignRole('admin');
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect(route('customers.index'));
});

it('manager cannot delete a customer', function (): void {
    $dept     = Department::factory()->create();
    $manager  = User::factory()->create(['department_id' => $dept->id])->assignRole('manager');
    $customer = Customer::factory()->inDepartment($dept->id)->create();

    $this->actingAs($manager)
        ->delete(route('customers.destroy', $customer))
        ->assertForbidden();
});

it('sales cannot delete a customer', function (): void {
    $sales    = User::factory()->create()->assignRole('sales');
    $customer = Customer::factory()->assignedTo($sales->id)->create();

    $this->actingAs($sales)
        ->delete(route('customers.destroy', $customer))
        ->assertForbidden();
});

it('delete soft-deletes the customer', function (): void {
    $admin    = User::factory()->create()->assignRole('admin');
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->delete(route('customers.destroy', $customer));

    $this->assertSoftDeleted('customers', ['id' => $customer->id]);
});

it('admin can restore a deleted customer', function (): void {
    $admin    = User::factory()->create()->assignRole('admin');
    $customer = Customer::factory()->create();
    $customer->delete();

    $this->actingAs($admin)
        ->post(route('customers.restore', $customer->id))
        ->assertRedirect();

    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'deleted_at' => null]);
});

it('manager cannot restore a customer', function (): void {
    $dept     = Department::factory()->create();
    $manager  = User::factory()->create(['department_id' => $dept->id])->assignRole('manager');
    $customer = Customer::factory()->inDepartment($dept->id)->create();
    $customer->delete();

    $this->actingAs($manager)
        ->post(route('customers.restore', $customer->id))
        ->assertForbidden();
});

// ── Assign ────────────────────────────────────────────────────────────────────

it('admin can assign customer to sales rep', function (): void {
    $admin    = User::factory()->create()->assignRole('admin');
    $sales    = User::factory()->create()->assignRole('sales');
    $customer = Customer::factory()->create(['assigned_to' => null]);

    $this->actingAs($admin)
        ->post(route('customers.assign', $customer), ['assigned_to' => $sales->id])
        ->assertRedirect();

    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'assigned_to' => $sales->id]);
});

it('manager can assign customer to sales rep', function (): void {
    $dept     = Department::factory()->create();
    $manager  = User::factory()->create(['department_id' => $dept->id])->assignRole('manager');
    $sales    = User::factory()->create()->assignRole('sales');
    $customer = Customer::factory()->inDepartment($dept->id)->create();

    $this->actingAs($manager)
        ->post(route('customers.assign', $customer), ['assigned_to' => $sales->id])
        ->assertRedirect();

    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'assigned_to' => $sales->id]);
});

it('sales cannot assign a customer', function (): void {
    $sales    = User::factory()->create()->assignRole('sales');
    $customer = Customer::factory()->assignedTo($sales->id)->create();

    $this->actingAs($sales)
        ->post(route('customers.assign', $customer), ['assigned_to' => $sales->id])
        ->assertForbidden();
});

it('assign with null clears the assignment', function (): void {
    $admin    = User::factory()->create()->assignRole('admin');
    $sales    = User::factory()->create()->assignRole('sales');
    $customer = Customer::factory()->assignedTo($sales->id)->create();

    $this->actingAs($admin)
        ->post(route('customers.assign', $customer), ['assigned_to' => null])
        ->assertRedirect();

    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'assigned_to' => null]);
});

it('assign rejects non-existent user id', function (): void {
    $admin    = User::factory()->create()->assignRole('admin');
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->post(route('customers.assign', $customer), ['assigned_to' => 99999])
        ->assertSessionHasErrors('assigned_to');
});

// ── Change Status ─────────────────────────────────────────────────────────────

it('admin can change customer status', function (): void {
    $admin    = User::factory()->create()->assignRole('admin');
    $customer = Customer::factory()->lead()->create();

    $this->actingAs($admin)
        ->post(route('customers.change-status', $customer), ['status' => 'active'])
        ->assertRedirect();

    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'status' => 'active']);
});

it('manager can change own-dept customer status', function (): void {
    $dept     = Department::factory()->create();
    $manager  = User::factory()->create(['department_id' => $dept->id])->assignRole('manager');
    $customer = Customer::factory()->inDepartment($dept->id)->lead()->create();

    $this->actingAs($manager)
        ->post(route('customers.change-status', $customer), ['status' => 'prospect'])
        ->assertRedirect();

    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'status' => 'prospect']);
});

it('sales cannot change customer status', function (): void {
    $sales    = User::factory()->create()->assignRole('sales');
    $customer = Customer::factory()->assignedTo($sales->id)->lead()->create();

    $this->actingAs($sales)
        ->post(route('customers.change-status', $customer), ['status' => 'active'])
        ->assertForbidden();
});

it('change-status rejects invalid status value', function (): void {
    $admin    = User::factory()->create()->assignRole('admin');
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->post(route('customers.change-status', $customer), ['status' => 'invalid'])
        ->assertSessionHasErrors('status');
});
