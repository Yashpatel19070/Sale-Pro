# Audit Log Module — Service & Controller

## Service
`app/Services/AuditLogService.php`

Key rules:
- `log_name`, `subject_type`, `event` filters are validated against allowlist constants before being applied to the query — prevents probing arbitrary class names / event strings
- `causers()` lives in the service, not the controller
- `->orderByDesc('id')` as secondary sort ensures deterministic ordering when two records share the same second

```php
// Filter validation — use allowlists, not empty-string checks:
->when(
    isset($filters['log_name']) && in_array($filters['log_name'], ['default', 'auth'], true),
    fn ($q) => $q->where('log_name', $filters['log_name'])
)
->when(
    isset($filters['subject_type']) && array_key_exists($filters['subject_type'], self::SUBJECT_TYPES),
    fn ($q) => $q->where('subject_type', $filters['subject_type'])
)
->when(
    isset($filters['event']) && array_key_exists($filters['event'], self::EVENTS),
    fn ($q) => $q->where('description', $filters['event'])
)
// causer_id and date range still use empty-string guards (no fixed allowlist)
```

---

## Controller
`app/Http/Controllers/AuditLogController.php`

Key rules:
- `$causers` comes from `$this->service->causers()` — not a direct model query in the controller
- `show()` uses `Activity::with('causer')->findOrFail()` — eager load, never `$activity->load()`
- `show()` passes `$subjectTypes` to the view — Blade never references the service class directly
- No FormRequest — GET filter with `$request->only([...])` is acceptable for read-only filter endpoints

```php
public function index(Request $request): View
{
    $this->authorize('viewAny', Activity::class);

    $activities   = $this->service->list(
        $request->only(['log_name', 'subject_type', 'causer_id', 'event', 'date_from', 'date_to'])
    );
    $subjectTypes = AuditLogService::SUBJECT_TYPES;
    $events       = AuditLogService::EVENTS;
    $causers      = $this->service->causers();

    return view('audit_log.index', compact('activities', 'subjectTypes', 'events', 'causers'));
}

public function show(Activity $activity): View
{
    $this->authorize('view', $activity);

    $activity = Activity::with('causer')->findOrFail($activity->getKey());

    return view('audit_log.show', [
        'activity'     => $activity,
        'subjectTypes' => AuditLogService::SUBJECT_TYPES,
    ]);
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
- No FormRequest — GET filter with `$request->only([...])` is acceptable for read-only endpoints
- `$this->authorize()` on every action
- `show()`: eager load via `Activity::with('causer')->findOrFail()` — never `$activity->load()`
- `causers()` in service, not controller — controller never queries models directly
- `show()` passes `$subjectTypes` to view — Blade never calls service class constants directly
