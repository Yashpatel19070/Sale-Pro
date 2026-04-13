# Audit Log Module — Policy & Permissions

## Permission Constants
Add to `app/Enums/Permission.php`:

```php
// Audit Log
const AUDIT_LOG_VIEW_ANY = 'audit-log.view-any';
const AUDIT_LOG_VIEW     = 'audit-log.view';
```

---

## Policy
`app/Policies/AuditLogPolicy.php`

```php
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
```

---

## Register Policy in AppServiceProvider

`app/Providers/AppServiceProvider.php`

```php
// Add to imports:
use App\Policies\AuditLogPolicy;
use Spatie\Activitylog\Models\Activity;

// Add inside boot():
Gate::policy(Activity::class, AuditLogPolicy::class);
```

---

## Checklist

- [ ] `AUDIT_LOG_VIEW_ANY` and `AUDIT_LOG_VIEW` added to `Permission` enum
- [ ] `AuditLogPolicy` created with `viewAny` and `view` methods
- [ ] Policy registered in `AppServiceProvider::boot()` with `Activity::class` as the model
- [ ] No create/edit/delete policy methods — audit log is read-only
