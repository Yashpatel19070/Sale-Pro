<?php

declare(strict_types=1);

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Department;
use App\Models\User;
use App\Services\CustomerService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->service = new CustomerService;
    $this->admin   = User::factory()->create()->assignRole('admin');
});

// ── list() — role scoping ─────────────────────────────────────────────────────

it('admin list returns all customers', function (): void {
    $dept  = Department::factory()->create();
    $sales = User::factory()->create()->assignRole('sales');

    Customer::factory()->create(['department_id' => $dept->id]);
    Customer::factory()->create(['assigned_to' => $sales->id]);
    Customer::factory()->create();

    $result = $this->service->list($this->admin);

    expect($result->total())->toBe(3);
});

it('manager list scoped to own department', function (): void {
    $dept    = Department::factory()->create();
    $other   = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $dept->id])->assignRole('manager');

    Customer::factory()->create(['department_id' => $dept->id]);
    Customer::factory()->create(['department_id' => $dept->id]);
    Customer::factory()->create(['department_id' => $other->id]);

    $result = $this->service->list($manager);

    expect($result->total())->toBe(2);
});

it('manager without department returns assigned-only customers', function (): void {
    $manager = User::factory()->create(['department_id' => null])->assignRole('manager');

    Customer::factory()->create(['assigned_to' => $manager->id]);
    Customer::factory()->create(['assigned_to' => null]);

    $result = $this->service->list($manager);

    expect($result->total())->toBe(1);
});

it('sales list scoped to assigned customers only', function (): void {
    $sales = User::factory()->create()->assignRole('sales');
    $other = User::factory()->create()->assignRole('sales');

    Customer::factory()->create(['assigned_to' => $sales->id]);
    Customer::factory()->create(['assigned_to' => $sales->id]);
    Customer::factory()->create(['assigned_to' => $other->id]);

    $result = $this->service->list($sales);

    expect($result->total())->toBe(2);
});

it('admin with manager role uses admin scope (priority check)', function (): void {
    $user = User::factory()->create()->assignRole('admin');
    $user->assignRole('manager'); // also has manager role

    Customer::factory()->count(3)->create();

    $result = $this->service->list($user);

    expect($result->total())->toBe(3);
});

// ── list() — filters ──────────────────────────────────────────────────────────

it('list filters by search term on name', function (): void {
    Customer::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith']);
    Customer::factory()->create(['first_name' => 'Bob',   'last_name' => 'Jones']);

    $result = $this->service->list($this->admin, ['search' => 'Alice']);

    expect($result->total())->toBe(1);
    expect($result->items()[0]->first_name)->toBe('Alice');
});

it('list filters by search term on email', function (): void {
    Customer::factory()->create(['email' => 'find@example.com']);
    Customer::factory()->create(['email' => 'other@example.com']);

    $result = $this->service->list($this->admin, ['search' => 'find@']);

    expect($result->total())->toBe(1);
});

it('list filters by search term on company_name', function (): void {
    Customer::factory()->create(['company_name' => 'Acme Corp']);
    Customer::factory()->create(['company_name' => 'Other Inc']);

    $result = $this->service->list($this->admin, ['search' => 'Acme']);

    expect($result->total())->toBe(1);
});

it('list filters by status', function (): void {
    Customer::factory()->lead()->create();
    Customer::factory()->lead()->create();
    Customer::factory()->active()->create();

    $result = $this->service->list($this->admin, ['status' => 'lead']);

    expect($result->total())->toBe(2);
});

it('list filters by source', function (): void {
    Customer::factory()->create(['source' => CustomerSource::Web]);
    Customer::factory()->create(['source' => CustomerSource::Web]);
    Customer::factory()->create(['source' => CustomerSource::Referral]);

    $result = $this->service->list($this->admin, ['source' => 'web']);

    expect($result->total())->toBe(2);
});

it('list filters by assigned_to', function (): void {
    $sales = User::factory()->create()->assignRole('sales');
    $other = User::factory()->create()->assignRole('sales');

    Customer::factory()->create(['assigned_to' => $sales->id]);
    Customer::factory()->create(['assigned_to' => $other->id]);

    $result = $this->service->list($this->admin, ['assigned_to' => $sales->id]);

    expect($result->total())->toBe(1);
});

// ── create() ──────────────────────────────────────────────────────────────────

it('creates a customer record', function (): void {
    $customer = $this->service->create([
        'first_name' => 'Test',
        'last_name'  => 'Customer',
        'status'     => CustomerStatus::Lead,
        'source'     => CustomerSource::Web,
    ]);

    expect($customer)->toBeInstanceOf(Customer::class);
    expect($customer->first_name)->toBe('Test');
    $this->assertDatabaseHas('customers', ['first_name' => 'Test']);
});

it('observer stamps created_by from authenticated user', function (): void {
    Auth::login($this->admin);

    $customer = $this->service->create([
        'first_name' => 'Created',
        'last_name'  => 'ByAdmin',
        'status'     => CustomerStatus::Lead,
        'source'     => CustomerSource::Other,
    ]);

    expect($customer->created_by)->toBe($this->admin->id);
});

it('observer stamps updated_by from authenticated user', function (): void {
    Auth::login($this->admin);

    $customer = $this->service->create([
        'first_name' => 'Updated',
        'last_name'  => 'ByAdmin',
        'status'     => CustomerStatus::Lead,
        'source'     => CustomerSource::Other,
    ]);

    expect($customer->updated_by)->toBe($this->admin->id);
});

// ── update() ──────────────────────────────────────────────────────────────────

it('updates customer fields', function (): void {
    $customer = Customer::factory()->create(['first_name' => 'Old']);

    $updated = $this->service->update($customer, ['first_name' => 'New']);

    expect($updated->first_name)->toBe('New');
});

it('observer stamps updated_by on update', function (): void {
    Auth::login($this->admin);
    $customer = Customer::factory()->create();

    $updated = $this->service->update($customer, ['first_name' => 'Changed']);

    expect($updated->updated_by)->toBe($this->admin->id);
});

it('returns refreshed customer after update', function (): void {
    $customer = Customer::factory()->create(['first_name' => 'Before']);

    $result = $this->service->update($customer, ['first_name' => 'After']);

    expect($result)->toBeInstanceOf(Customer::class);
    expect($result->first_name)->toBe('After');
});

// ── changeStatus() ────────────────────────────────────────────────────────────

it('transitions status to prospect', function (): void {
    $customer = Customer::factory()->lead()->create();

    $result = $this->service->changeStatus($customer, CustomerStatus::Prospect);

    expect($result->status)->toBe(CustomerStatus::Prospect);
});

it('transitions status to active', function (): void {
    $customer = Customer::factory()->lead()->create();

    $result = $this->service->changeStatus($customer, CustomerStatus::Active);

    expect($result->status)->toBe(CustomerStatus::Active);
});

it('transitions status to churned', function (): void {
    $customer = Customer::factory()->active()->create();

    $result = $this->service->changeStatus($customer, CustomerStatus::Churned);

    expect($result->status)->toBe(CustomerStatus::Churned);
});

it('observer stamps updated_by on status change', function (): void {
    Auth::login($this->admin);
    $customer = Customer::factory()->create();

    $result = $this->service->changeStatus($customer, CustomerStatus::Active);

    expect($result->updated_by)->toBe($this->admin->id);
});

// ── assign() ──────────────────────────────────────────────────────────────────

it('assigns customer to a user', function (): void {
    $sales    = User::factory()->create()->assignRole('sales');
    $customer = Customer::factory()->create(['assigned_to' => null]);

    $result = $this->service->assign($customer, $sales->id);

    expect($result->assigned_to)->toBe($sales->id);
});

it('clears assignment when null passed', function (): void {
    $sales    = User::factory()->create()->assignRole('sales');
    $customer = Customer::factory()->assignedTo($sales->id)->create();

    $result = $this->service->assign($customer, null);

    expect($result->assigned_to)->toBeNull();
});

it('observer stamps updated_by on assign', function (): void {
    Auth::login($this->admin);
    $sales    = User::factory()->create()->assignRole('sales');
    $customer = Customer::factory()->create();

    $result = $this->service->assign($customer, $sales->id);

    expect($result->updated_by)->toBe($this->admin->id);
});

// ── delete() / restore() ──────────────────────────────────────────────────────

it('soft-deletes the customer', function (): void {
    $customer = Customer::factory()->create();

    $this->service->delete($customer);

    $this->assertSoftDeleted('customers', ['id' => $customer->id]);
});

it('deleted customer not found in normal queries', function (): void {
    $customer = Customer::factory()->create();
    $id       = $customer->id;

    $this->service->delete($customer);

    expect(Customer::find($id))->toBeNull();
});

it('restores a soft-deleted customer', function (): void {
    $customer = Customer::factory()->create();
    $customer->delete();

    $result = $this->service->restore($customer);

    expect($result->deleted_at)->toBeNull();
});

it('restored customer appears in normal queries', function (): void {
    $customer = Customer::factory()->create();
    $id       = $customer->id;
    $customer->delete();

    $this->service->restore($customer);

    expect(Customer::find($id))->not->toBeNull();
});
