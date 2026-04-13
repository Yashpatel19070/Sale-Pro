<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AUDIT_LOG_VIEW_ANY);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can(Permission::AUDIT_LOG_VIEW);
    }
}
