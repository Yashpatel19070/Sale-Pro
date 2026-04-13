# Audit Log Module — Package Setup

## Install

```bash
composer require spatie/laravel-activitylog
php artisan activitylog:table
php artisan migrate
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
```

---

## Migration

The package generates `database/migrations/xxxx_create_activity_log_table.php`.
Do NOT modify it — use it as-is.

### Table: `activity_log`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| log_name | varchar(255) | default `'default'`; use `'auth'` for auth events |
| description | text | human-readable event name: `created`, `updated`, `deleted`, `login`, etc. |
| subject_type | varchar(255) | nullable; fully-qualified model class name e.g. `App\Models\Customer` |
| subject_id | bigint | nullable; model primary key |
| event | varchar(255) | nullable; set automatically by `LogsActivity` trait |
| causer_type | varchar(255) | nullable; `App\Models\User` |
| causer_id | bigint | nullable; authenticated user ID |
| properties | json | nullable; `{ "attributes": {...}, "old": {...} }` |
| batch_uuid | uuid | nullable; groups related log entries |
| created_at / updated_at | timestamp | |

---

## Config: `config/activitylog.php`

After publishing, set:

```php
return [
    /*
     * If set to false, no activities will be saved to the database.
     */
    'enabled' => env('ACTIVITY_LOG_ENABLED', true),

    /*
     * When the clean-command is executed, all recording activities older than
     * the number of days specified here will be deleted.
     */
    'delete_records_older_than_days' => 365,

    /*
     * If no log name is passed to the activity() helper, this log name will
     * be used.
     */
    'default_log_name' => 'default',

    /*
     * If set to true, the subject returns soft deleted models.
     */
    'subject_returns_soft_deleted_models' => true,

    /*
     * The model used to log the activities.
     */
    'activity_model' => \Spatie\Activitylog\Models\Activity::class,

    /*
     * This is the name of the table that will be created by the migration and
     * used by the Activity model shipped with this package.
     */
    'table_name' => env('ACTIVITY_LOG_TABLE', 'activity_log'),

    /*
     * This is the database connection that will be used by the migration and
     * the Activity model shipped with this package.
     */
    'database_connection' => env('ACTIVITY_LOG_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),
];
```

---

## Checklist

- [ ] `composer require spatie/laravel-activitylog` done
- [ ] `php artisan activitylog:table` generated the migration
- [ ] `php artisan migrate` runs clean
- [ ] Config published to `config/activitylog.php`
- [ ] `subject_returns_soft_deleted_models` set to `true`
- [ ] `delete_records_older_than_days` set to `365`
