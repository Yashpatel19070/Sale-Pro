# Audit Log Module — Model Integration

## What to add to every model

Two things per model:
1. Add `LogsActivity` to the `use` traits line
2. Add the `getActivitylogOptions()` method

The options are the same for every model — log all fillable fields, only when they change, skip empty saves.

---

## Standard options block (copy to every model)

```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
```

```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()       // log all $fillable fields
        ->logOnlyDirty()      // only record fields that actually changed
        ->dontSubmitEmptyLogs(); // skip saves that change nothing
}
```

---

## User

`app/Models/User.php`

```php
// Add to imports:
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

// Add to use statement:
use HasFactory, Notifiable, LogsActivity;

// Add method:
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs();
}
```

> Note: `password` should NOT be in `$fillable` — it will not be logged. This is correct by design.

---

## Customer

`app/Models/Customer.php`

```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
```

```php
use HasFactory, SoftDeletes, LogsActivity;

public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs();
}
```

---

## Department

`app/Models/Department.php`

```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
```

```php
use HasFactory, SoftDeletes, LogsActivity;

public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs();
}
```

---

## Product

`app/Models/Product.php`

```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
```

```php
use HasFactory, SoftDeletes, LogsActivity;

public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs();
}
```

---

## ProductListing

`app/Models/ProductListing.php`

```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
```

```php
use HasFactory, SoftDeletes, HasSlug, LogsActivity;

public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs();
}
```

> Note: `slug` is not in `$fillable` so it will not be logged. Only `title`, `visibility`, `is_active` are logged.

---

## ProductCategory

`app/Models/ProductCategory.php`

```php
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
```

```php
use HasFactory, SoftDeletes, LogsActivity;

public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs();
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
  properties:   {
    "old":        { "status": "lead" },
    "attributes": { "status": "active" }
  }
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
- [ ] `getActivitylogOptions()` uses `logFillable()` + `logOnlyDirty()` + `dontSubmitEmptyLogs()` on all models
- [ ] `LogOptions` imported on all models
