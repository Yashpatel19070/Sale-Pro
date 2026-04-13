<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\AuditLogPermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(AuditLogPermissionSeeder::class);
});

// ── Authorization ──────────────────────────────────────────────────────────

it('denies unauthenticated access to index', function () {
    $this->get(route('audit-log.index'))->assertRedirect(route('login'));
});

it('denies manager from viewing audit log', function () {
    $user = User::factory()->create()->assignRole('manager');
    $this->actingAs($user)->get(route('audit-log.index'))->assertForbidden();
});

it('denies sales from viewing audit log', function () {
    $user = User::factory()->create()->assignRole('sales');
    $this->actingAs($user)->get(route('audit-log.index'))->assertForbidden();
});

it('allows admin to view audit log index', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $this->actingAs($admin)->get(route('audit-log.index'))->assertOk();
});

// ── Index ──────────────────────────────────────────────────────────────────

it('index returns correct view with required data', function () {
    $admin = User::factory()->create()->assignRole('admin');

    activity()->causedBy($admin)->log('test');

    $this->actingAs($admin)
        ->get(route('audit-log.index'))
        ->assertOk()
        ->assertViewIs('audit_log.index')
        ->assertViewHas('activities')
        ->assertViewHas('subjectTypes')
        ->assertViewHas('events')
        ->assertViewHas('causers');
});

it('index filters by event description', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create()->assignRole('admin'));

    activity()->log('created');
    activity()->log('deleted');

    $this->actingAs($admin)
        ->get(route('audit-log.index', ['event' => 'created']))
        ->assertOk()
        ->assertViewHas('activities', fn ($p) => $p->total() === 1);
});

it('index filters by causer_id', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $other = User::factory()->create()->assignRole('admin');

    activity()->causedBy($admin)->log('updated');
    activity()->causedBy($other)->log('updated');

    $this->actingAs($admin)
        ->get(route('audit-log.index', ['causer_id' => $admin->id]))
        ->assertOk()
        ->assertViewHas('activities', fn ($p) => $p->total() === 1);
});

it('index filters by subject type', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create()->assignRole('admin'));
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());

    activity()->performedOn($customer)->log('created');
    activity()->log('login');

    $this->actingAs($admin)
        ->get(route('audit-log.index', ['subject_type' => Customer::class]))
        ->assertOk()
        ->assertViewHas('activities', fn ($p) => $p->total() === 1);
});

it('index filters by log_name', function () {
    $admin = User::factory()->create()->assignRole('admin');

    activity('auth')->log('login');
    activity()->log('created');

    $this->actingAs($admin)
        ->get(route('audit-log.index', ['log_name' => 'auth']))
        ->assertOk()
        ->assertViewHas('activities', fn ($p) => $p->total() === 1);
});

// ── Show ───────────────────────────────────────────────────────────────────

it('admin can view audit log entry detail', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $activity = activity()->causedBy($admin)->log('login');

    $this->actingAs($admin)
        ->get(route('audit-log.show', $activity))
        ->assertOk()
        ->assertViewIs('audit_log.show');
});

it('manager cannot view audit log entry detail', function () {
    $manager = User::factory()->create()->assignRole('manager');
    $activity = activity()->log('login');

    $this->actingAs($manager)
        ->get(route('audit-log.show', $activity))
        ->assertForbidden();
});

// ── No write routes ────────────────────────────────────────────────────────

it('audit-log.create route does not exist', function () {
    expect(Route::has('audit-log.create'))->toBeFalse();
});

it('audit-log.store route does not exist', function () {
    expect(Route::has('audit-log.store'))->toBeFalse();
});
