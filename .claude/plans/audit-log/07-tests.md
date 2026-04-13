# Audit Log Module — Tests

## Feature Test
`tests/Feature/AuditLogControllerTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\AuditLogPermissionSeeder;
use Spatie\Activitylog\Models\Activity;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(AuditLogPermissionSeeder::class);
});

// ── Authorization ──────────────────────────────────────────────────────────

it('denies unauthenticated access', function () {
    $this->get(route('audit-log.index'))->assertRedirect(route('login'));
});

it('denies staff from viewing audit log', function () {
    $user = User::factory()->create()->assignRole('staff');
    $this->actingAs($user)->get(route('audit-log.index'))->assertForbidden();
});

it('allows admin to view audit log', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $this->actingAs($admin)->get(route('audit-log.index'))->assertOk();
});

it('allows super-admin to view audit log', function () {
    $superAdmin = User::factory()->create()->assignRole('super-admin');
    $this->actingAs($superAdmin)->get(route('audit-log.index'))->assertOk();
});

// ── Index ──────────────────────────────────────────────────────────────────

it('index lists activity entries paginated', function () {
    $admin = User::factory()->create()->assignRole('admin');

    activity()->causedBy($admin)->log('test entry');

    $this->actingAs($admin)
        ->get(route('audit-log.index'))
        ->assertOk()
        ->assertViewIs('audit_log.index')
        ->assertViewHas('activities');
});

it('index filters by event description', function () {
    $admin = User::factory()->create()->assignRole('admin');

    activity()->log('created');
    activity()->log('deleted');

    $this->actingAs($admin)
        ->get(route('audit-log.index', ['event' => 'created']))
        ->assertOk()
        ->assertViewHas('activities', fn ($p) => $p->total() === 1);
});

it('index filters by causer', function () {
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
    $admin    = User::factory()->create()->assignRole('admin');
    $customer = Customer::factory()->create();

    activity()->performedOn($customer)->log('created');
    activity()->log('login');

    $this->actingAs($admin)
        ->get(route('audit-log.index', ['subject_type' => Customer::class]))
        ->assertOk()
        ->assertViewHas('activities', fn ($p) => $p->total() === 1);
});

// ── Show ───────────────────────────────────────────────────────────────────

it('admin can view audit log entry detail', function () {
    $admin    = User::factory()->create()->assignRole('admin');
    $activity = activity()->causedBy($admin)->log('login');

    $this->actingAs($admin)
        ->get(route('audit-log.show', $activity))
        ->assertOk()
        ->assertViewIs('audit_log.show');
});

it('staff cannot view audit log entry detail', function () {
    $staff    = User::factory()->create()->assignRole('staff');
    $activity = activity()->log('login');

    $this->actingAs($staff)
        ->get(route('audit-log.show', $activity))
        ->assertForbidden();
});

// ── No write routes ────────────────────────────────────────────────────────

it('audit log has no create route', function () {
    expect(Route::has('audit-log.create'))->toBeFalse();
});

it('audit log has no store route', function () {
    expect(Route::has('audit-log.store'))->toBeFalse();
});
```

---

## Unit Test
`tests/Unit/Services/AuditLogServiceTest.php`

```php
<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogService;
use Spatie\Activitylog\Models\Activity;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->service = new AuditLogService();
});

it('list returns all activities when no filters', function () {
    activity()->log('created');
    activity()->log('updated');

    $result = $this->service->list();

    expect($result->total())->toBe(2);
});

it('list filters by event description', function () {
    activity()->log('created');
    activity()->log('deleted');

    $result = $this->service->list(['event' => 'created']);

    expect($result->total())->toBe(1);
    expect($result->first()->description)->toBe('created');
});

it('list filters by log name', function () {
    activity('auth')->log('login');
    activity()->log('created');

    $result = $this->service->list(['log_name' => 'auth']);

    expect($result->total())->toBe(1);
});

it('list filters by subject type', function () {
    $customer = Customer::factory()->create();

    activity()->performedOn($customer)->log('created');
    activity()->log('login');

    $result = $this->service->list(['subject_type' => Customer::class]);

    expect($result->total())->toBe(1);
});

it('list filters by causer id', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    activity()->causedBy($userA)->log('updated');
    activity()->causedBy($userB)->log('updated');

    $result = $this->service->list(['causer_id' => $userA->id]);

    expect($result->total())->toBe(1);
});

it('list orders newest first', function () {
    activity()->log('first');
    activity()->log('second');

    $result = $this->service->list();

    expect($result->first()->description)->toBe('second');
});

it('list ignores empty string filters', function () {
    activity()->log('login');
    activity()->log('created');

    $result = $this->service->list(['event' => '', 'log_name' => '']);

    expect($result->total())->toBe(2);
});
```

---

## Checklist

- [ ] Feature: unauthenticated → redirected to login
- [ ] Feature: staff → 403 forbidden
- [ ] Feature: admin + super-admin → 200 OK
- [ ] Feature: filter by event description
- [ ] Feature: filter by causer
- [ ] Feature: filter by subject type
- [ ] Feature: show detail — admin can view
- [ ] Feature: show detail — staff forbidden
- [ ] Feature: no create/store routes exist
- [ ] Unit: list returns all with no filters
- [ ] Unit: filter by event, log_name, subject_type, causer_id
- [ ] Unit: ordered newest first
- [ ] Unit: empty string filters ignored
