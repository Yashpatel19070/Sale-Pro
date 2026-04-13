# Audit Log Module — Model Integration

## What to add to every model

Two things per model:
1. Add `LogsActivity` to the `use` traits line
2. Add the `getActivitylogOptions()` method

The options are the same for every model — log all fillable fields, only when they change, skip empty saves.

---

## Standard options block (copy to every model)

> **v5 namespaces** — the trait and LogOptions moved in v5:

```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;  // NOT Traits\LogsActivity
use Spatie\Activitylog\Support\LogOptions;              // NOT Spatie\Activitylog\LogOptions
```

```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()    // log all $fillable fields
        ->logOnlyDirty();  // only record fields that actually changed
    // NOTE: dontSubmitEmptyLogs() does NOT exist in v5 — omit it
    // logEmptyChanges defaults to true in v5 (logs even if no dirty attrs)
}
```

> **attribute_changes vs properties** — in v5, model before/after values are stored in
> the `attribute_changes` column (not `properties`). Manual `.withProperties()` data
> goes into `properties`. Access them as separate fields in Blade/code.

---

## User

`app/Models/User.php`

```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
```

```php
use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logExcept(['password', 'remember_token', 'email_verified_at'])
        ->logOnlyDirty();
}
```

> `password` and `remember_token` are in `$fillable` — must be explicitly excluded via `logExcept`.
> Without this, sensitive tokens appear in the audit log.

---

## Customer

`app/Models/Customer.php`

```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
```

```php
use HasFactory, SoftDeletes, LogsActivity;

public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logOnlyDirty();
}
```

---

## Department

`app/Models/Department.php`

```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
```

```php
use HasFactory, SoftDeletes, LogsActivity;

public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logOnlyDirty();
}
```

---

## Product

`app/Models/Product.php`

```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
```

```php
use HasFactory, SoftDeletes, LogsActivity;

public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logOnlyDirty();
}
```

---

## ProductListing

`app/Models/ProductListing.php`

```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
```

```php
use HasFactory, SoftDeletes, HasSlug, LogsActivity;

public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logOnlyDirty();
}
```

> Note: `slug` is not in `$fillable` so it will not be logged. Only `title`, `visibility`, `is_active` are logged.

---

## ProductCategory

`app/Models/ProductCategory.php`

```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
```

```php
use HasFactory, SoftDeletes, LogsActivity;

public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logOnlyDirty();
}
```

---

## How a logged update looks

```
activity_log row for Customer update:
  log_name:     'default'
  description:  'updated'
  event:        'updated'
  subject_type: 'App\Models\Customer'
  subject_id:   5
  causer_type:  'App\Models\User'
  causer_id:    2
  attribute_changes: {
    "old":        { "status": "lead" },
    "attributes": { "status": "active" }
  }
  properties:   {}   ← only holds manual withProperties() data (e.g. IP)
```

Only the dirty fields appear — not the full model.

---

## Checklist

- [ ] `LogsActivity` trait added to `User`
- [ ] `LogsActivity` trait added to `Customer`
- [ ] `LogsActivity` trait added to `Department`
- [ ] `LogsActivity` trait added to `Product`
- [ ] `LogsActivity` trait added to `ProductListing`
- [ ] `LogsActivity` trait added to `ProductCategory`
- [ ] `getActivitylogOptions()` uses `logFillable()` + `logOnlyDirty()` on all models (no `dontSubmitEmptyLogs` — v5 removed it)
- [ ] `LogOptions` imported from `Spatie\Activitylog\Support\LogOptions` on all models
- [ ] `LogsActivity` imported from `Spatie\Activitylog\Models\Concerns\LogsActivity` on all models
- [ ] User model excludes `password`, `remember_token`, `email_verified_at` via `logExcept`
- [ ] Product model excludes `purchase_price` via `logExcept`
- [ ] Blade views use `$activity->attribute_changes` for before/after values, not `$activity->properties`
