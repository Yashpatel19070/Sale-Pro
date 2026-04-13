# Audit Log Module — Service & Controller

## Service
`app/Services/AuditLogService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

class AuditLogService
{
    /**
     * Paginated activity log with filters.
     *
     * @param array{
     *   log_name?: string,
     *   subject_type?: string,
     *   causer_id?: int,
     *   event?: string,
     *   date_from?: string,
     *   date_to?: string
     * } $filters
     */
    public function list(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return Activity::with(['causer'])
            ->when(
                isset($filters['log_name']) && $filters['log_name'] !== '',
                fn ($q) => $q->where('log_name', $filters['log_name'])
            )
            ->when(
                isset($filters['subject_type']) && $filters['subject_type'] !== '',
                fn ($q) => $q->where('subject_type', $filters['subject_type'])
            )
            ->when(
                isset($filters['causer_id']) && $filters['causer_id'] !== '',
                fn ($q) => $q->where('causer_id', $filters['causer_id'])
                             ->where('causer_type', \App\Models\User::class)
            )
            ->when(
                isset($filters['event']) && $filters['event'] !== '',
                fn ($q) => $q->where('description', $filters['event'])
            )
            ->when(
                isset($filters['date_from']) && $filters['date_from'] !== '',
                fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from'])
            )
            ->when(
                isset($filters['date_to']) && $filters['date_to'] !== '',
                fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to'])
            )
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
```

---

## Controller
`app/Http/Controllers/AuditLogController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $service,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Activity::class);

        $activities = $this->service->list(
            $request->only(['log_name', 'subject_type', 'causer_id', 'event', 'date_from', 'date_to'])
        );

        $subjectTypes = \App\Services\AuditLogService::SUBJECT_TYPES;
        $events       = \App\Services\AuditLogService::EVENTS;
        $causers      = \App\Models\User::orderBy('name')->get(['id', 'name']);

        return view('audit_log.index', compact('activities', 'subjectTypes', 'events', 'causers'));
    }

    public function show(Activity $activity): View
    {
        $this->authorize('view', $activity);

        $activity->load('causer');

        return view('audit_log.show', compact('activity'));
    }
}
```

---

## Service Constants (add to AuditLogService)

Add these public constants to `AuditLogService` for use in views and filters:

```php
/**
 * Human-readable labels for subject_type filter dropdown.
 *
 * @var array<string, string>
 */
public const SUBJECT_TYPES = [
    \App\Models\User::class            => 'User',
    \App\Models\Customer::class        => 'Customer',
    \App\Models\Department::class      => 'Department',
    \App\Models\Product::class         => 'Product',
    \App\Models\ProductListing::class  => 'Product Listing',
    \App\Models\ProductCategory::class => 'Product Category',
];

/**
 * All possible event descriptions for the event filter dropdown.
 *
 * @var array<string, string>
 */
public const EVENTS = [
    'created'      => 'Created',
    'updated'      => 'Updated',
    'deleted'      => 'Deleted',
    'restored'     => 'Restored',
    'login'        => 'Login',
    'logout'       => 'Logout',
    'login-failed' => 'Login Failed',
];
```

---

## Method Summary

| Method | Description |
|--------|-------------|
| `list(filters, perPage)` | Paginated activity log; filter by log_name/subject_type/causer/event/date range |

| Action | Route | Description |
|--------|-------|-------------|
| `index` | `GET /admin/audit-log` | Paginated log with filter bar |
| `show` | `GET /admin/audit-log/{activity}` | Full detail — old/new values |

---

## Key Rules

- No write actions — controller is read-only
- No FormRequest — no user input to validate (filters use `$request->only()`)
- `$this->authorize()` on every action
- Eager load `causer` — never lazy load
- `subject` not eager loaded on index (too varied) — loaded only on show via `$activity->subject`
