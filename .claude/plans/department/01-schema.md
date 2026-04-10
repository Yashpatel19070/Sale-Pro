# Department Module — Database Schema

## Migration: `create_departments_table`

File: `database/migrations/YYYY_MM_DD_HHMMSS_create_departments_table.php`

```php
Schema::create('departments', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100)->unique();
    $table->string('code', 20)->unique();          // e.g. SALES, MKT, OPS
    $table->text('description')->nullable();
    $table->foreignId('manager_id')
          ->nullable()
          ->constrained('users')
          ->nullOnDelete();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();
});
```

## Column Notes

| Column       | Type                   | Notes                                          |
|--------------|------------------------|------------------------------------------------|
| id           | bigint unsigned PK     | auto-increment                                 |
| name         | varchar(100) UNIQUE    | "Sales Team", "Marketing", …                  |
| code         | varchar(20) UNIQUE     | uppercase slug: SALES, MKT, OPS, FIN, HR      |
| description  | text NULL              | free-form description                          |
| manager_id   | bigint unsigned NULL FK| → users.id; set null on user delete            |
| is_active    | boolean                | false = archived, excluded from dropdowns      |
| created_at   | timestamp              |                                                |
| updated_at   | timestamp              |                                                |
| deleted_at   | timestamp NULL         | soft-delete sentinel                           |

## Indexes
- PRIMARY KEY (`id`)
- UNIQUE (`name`)
- UNIQUE (`code`)
- INDEX (`manager_id`) — via foreignId
- INDEX (`is_active`) — filter active departments efficiently
- INDEX (`deleted_at`) — soft-delete scope

## Seed Data (DepartmentSeeder)

| name              | code   | description                        |
|-------------------|--------|------------------------------------|
| Sales             | SALES  | Outbound and inbound sales team    |
| Marketing         | MKT    | Lead generation and brand          |
| Customer Support  | CS     | Post-sale customer success         |
| Finance           | FIN    | Billing, invoicing, reporting      |
| Operations        | OPS    | Internal ops and administration    |
| Human Resources   | HR     | Hiring, onboarding, compliance     |
| Management        | MGMT   | Executive / management staff       |

## Rollback

```php
Schema::dropIfExists('departments');
```

> **Note**: Run this migration BEFORE the users migration that adds `department_id`.
> The `manager_id` FK is safe to add here because the `users` table already exists
> (created by the Breeze scaffold migration).
