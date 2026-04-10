# User Module — Database Schema

## Migration: Alter `users` Table

File: `database/migrations/YYYY_MM_DD_HHMMSS_add_profile_columns_to_users_table.php`

> Run **after** the `create_departments_table` migration.

```php
Schema::table('users', function (Blueprint $table) {
    // Profile
    $table->string('phone', 30)->nullable()->after('email');
    $table->string('avatar', 255)->nullable()->after('phone');     // relative storage path
    $table->string('job_title', 100)->nullable()->after('avatar');
    $table->string('employee_id', 50)->nullable()->unique()->after('job_title');

    // Org
    $table->foreignId('department_id')
          ->nullable()
          ->after('employee_id')
          ->constrained('departments')
          ->nullOnDelete();

    // Status & dates
    $table->enum('status', ['active', 'inactive', 'suspended'])
          ->default('active')
          ->after('department_id');
    $table->date('hired_at')->nullable()->after('status');

    // Locale
    $table->string('timezone', 50)->default('UTC')->after('hired_at');

    // Audit
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

    // Soft delete
    $table->softDeletes();
});
```

## Full `users` Column Set (after migration)

| Column            | Type                                 | Notes                               |
|-------------------|--------------------------------------|-------------------------------------|
| id                | bigint unsigned PK                   |                                     |
| name              | varchar(255)                         | display name                        |
| email             | varchar(255) UNIQUE                  |                                     |
| email_verified_at | timestamp NULL                       |                                     |
| password          | varchar(255)                         | bcrypt hashed                       |
| remember_token    | varchar(100) NULL                    |                                     |
| phone             | varchar(30) NULL                     |                                     |
| avatar            | varchar(255) NULL                    | storage-relative path               |
| job_title         | varchar(100) NULL                    |                                     |
| employee_id       | varchar(50) NULL UNIQUE              | HR identifier                       |
| department_id     | bigint unsigned NULL FK              | → departments.id                    |
| status            | enum(active,inactive,suspended)      | default: active                     |
| hired_at          | date NULL                            |                                     |
| timezone          | varchar(50)                          | default: UTC                        |
| created_by        | bigint unsigned NULL FK              | → users.id                          |
| updated_by        | bigint unsigned NULL FK              | → users.id                          |
| created_at        | timestamp                            |                                     |
| updated_at        | timestamp                            |                                     |
| deleted_at        | timestamp NULL                       | soft-delete sentinel                |

## Indexes

- PRIMARY KEY (`id`)
- UNIQUE (`email`)
- UNIQUE (`employee_id`) — allows NULL
- INDEX (`department_id`)
- INDEX (`status`)
- INDEX (`deleted_at`)

## Rollback

```php
Schema::table('users', function (Blueprint $table) {
    $table->dropForeign(['department_id']);
    $table->dropForeign(['created_by']);
    $table->dropForeign(['updated_by']);
    $table->dropColumn([
        'phone', 'avatar', 'job_title', 'employee_id',
        'department_id', 'status', 'hired_at', 'timezone',
        'created_by', 'updated_by', 'deleted_at',
    ]);
});
```

## Default Admin Seeder

In `DatabaseSeeder.php`:

```php
$admin = User::firstOrCreate(
    ['email' => 'admin@salepro.local'],
    [
        'name'     => 'System Admin',
        'password' => Hash::make('password'),
        'status'   => 'active',
        'timezone' => 'UTC',
    ]
);
$admin->assignRole('admin');
```
