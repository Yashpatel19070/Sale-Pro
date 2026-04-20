# PO Pipeline Module — Policy

## PoUnitJobPolicy

```php
<?php
// app/Policies/PoUnitJobPolicy.php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\PipelineStage;
use App\Enums\UnitJobStatus;
use App\Models\PoUnitJob;
use App\Models\User;

class PoUnitJobPolicy
{
    /**
     * Can create a job (receive a unit) — procurement department.
     */
    public function createJob(User $user): bool
    {
        return $user->can(Permission::PIPELINE_RECEIVE);
    }

    /**
     * Can claim (start) a unit job. Job must be pending AND user must have the stage permission.
     * The InProgress + assigned_to enforcement is in PipelineService::start() inside a transaction.
     */
    public function start(User $user, PoUnitJob $job): bool
    {
        return $job->status === UnitJobStatus::Pending
            && $this->hasStagePermission($user, $job->current_stage);
    }

    /**
     * Can pass a unit at its current stage.
     * User must have the permission for that specific stage.
     * Service also enforces: job must be in_progress + assigned to this user.
     */
    public function pass(User $user, PoUnitJob $job): bool
    {
        return $this->hasStagePermission($user, $job->current_stage);
    }

    /**
     * Can fail a unit at its current stage.
     * User must have the permission for that specific stage.
     * Service also enforces: job must be in_progress + assigned to this user.
     */
    public function fail(User $user, PoUnitJob $job): bool
    {
        return $this->hasStagePermission($user, $job->current_stage);
    }

    /**
     * Can view any unit job — managers and above.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PIPELINE_VIEW_ANY);
    }

    /**
     * Can view a specific unit job.
     */
    public function view(User $user, PoUnitJob $job): bool
    {
        return $user->can(Permission::PIPELINE_VIEW_ANY)
            || $this->hasStagePermission($user, $job->current_stage);
    }

    private function hasStagePermission(User $user, PipelineStage $stage): bool
    {
        return match($stage) {
            PipelineStage::Receive      => $user->can(Permission::PIPELINE_RECEIVE),
            PipelineStage::Visual       => $user->can(Permission::PIPELINE_VISUAL),
            PipelineStage::SerialAssign => $user->can(Permission::PIPELINE_SERIAL_ASSIGN),
            PipelineStage::Tech         => $user->can(Permission::PIPELINE_TECH),
            PipelineStage::Qa           => $user->can(Permission::PIPELINE_QA),
            PipelineStage::Shelf        => $user->can(Permission::PIPELINE_SHELF),
        };
    }
}
```

---

## Permission Constants to Add

```php
// app/Enums/Permission.php — add these constants

const PIPELINE_VIEW_ANY      = 'pipeline.viewAny';
const PIPELINE_RECEIVE       = 'pipeline.receive';
const PIPELINE_VISUAL        = 'pipeline.visual';
const PIPELINE_SERIAL_ASSIGN = 'pipeline.serial_assign';
const PIPELINE_TECH          = 'pipeline.tech';
const PIPELINE_QA            = 'pipeline.qa';
const PIPELINE_SHELF         = 'pipeline.shelf';
```

---

## Register in AppServiceProvider

```php
Gate::policy(\App\Models\PoUnitJob::class, \App\Policies\PoUnitJobPolicy::class);
```

---

## Permission Seeder

```php
<?php
// database/seeders/PipelinePermissionSeeder.php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role;

class PipelinePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = [
            Permission::PIPELINE_VIEW_ANY,
            Permission::PIPELINE_RECEIVE,
            Permission::PIPELINE_VISUAL,
            Permission::PIPELINE_SERIAL_ASSIGN,
            Permission::PIPELINE_TECH,
            Permission::PIPELINE_QA,
            Permission::PIPELINE_SHELF,
        ];

        foreach ($allPermissions as $permission) {
            SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin  = Role::findByName('super-admin');
        $admin       = Role::findByName('admin');
        $manager     = Role::findByName('manager');
        $procurement = Role::findByName('procurement');
        $warehouse   = Role::findByName('warehouse');
        $tech        = Role::findByName('tech');
        $qa          = Role::findByName('qa');

        // Super Admin, Admin, Manager — view all + no action stages
        foreach ([$superAdmin, $admin, $manager] as $role) {
            $role->givePermissionTo([Permission::PIPELINE_VIEW_ANY]);
        }

        // Procurement — receive stage
        $procurement->givePermissionTo([Permission::PIPELINE_VIEW_ANY, Permission::PIPELINE_RECEIVE]);

        // Warehouse — visual, serial_assign, shelf stages
        $warehouse->givePermissionTo([
            Permission::PIPELINE_VIEW_ANY,
            Permission::PIPELINE_VISUAL,
            Permission::PIPELINE_SERIAL_ASSIGN,
            Permission::PIPELINE_SHELF,
        ]);

        // Tech — tech stage
        $tech->givePermissionTo([Permission::PIPELINE_VIEW_ANY, Permission::PIPELINE_TECH]);

        // QA — qa stage
        $qa->givePermissionTo([Permission::PIPELINE_VIEW_ANY, Permission::PIPELINE_QA]);
    }
}
```

---

## Stage → Role Map

| Stage | Role(s) that can act |
|-------|---------------------|
| `receive` | procurement |
| `visual` | warehouse |
| `serial_assign` | warehouse |
| `tech` | tech |
| `qa` | qa |
| `shelf` | warehouse |

---

## Notes

- `start` policy checks two conditions: job is pending AND user has stage permission. The actual in_progress enforcement is inside `PipelineService::start()` using a transaction-scoped TOCTOU guard.
- `pass` and `fail` policy checks stage permission only. The service adds the additional guard: job must be `in_progress` AND `assigned_to_user_id === $user->id`. Policy = "can this role act here?", service = "is this the right time and worker?".
- Managers and above have `PIPELINE_VIEW_ANY` for oversight but cannot pass/fail/start units at stages (they manage, they don't process).
- To give a manager ability to intervene at any stage (emergency), grant them all stage permissions individually — this is a future decision, not built in now.
- `view` policy allows viewing if the user has `viewAny` OR has permission for the job's current stage — workers can see their own queue items.

---

## Implementation Deviations (actual code differs from plan above)

### `pass()` and `fail()` — added `InProgress` status + `assigned_to_user_id` ownership check
Plan had stage-permission check only. Actual code adds:
```php
$job->status === UnitJobStatus::InProgress
    && $job->assigned_to_user_id === $user->id
    && $this->hasStagePermission($user, $job->current_stage)
```
**Why:** Policy should return 403 before the service throws DomainException. Returning a flash error for an ownership violation is weaker than a proper 403. Now wrong-worker and unclaimed-job attempts get 403 at policy level, not a flash error.
