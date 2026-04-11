# Customer Module — Database Schema

## Migration: Create `customers` Table

File: `database/migrations/YYYY_MM_DD_HHMMSS_create_customers_table.php`

> Run **after** `create_departments_table` and after users profile columns migration.

```php
Schema::create('customers', function (Blueprint $table) {
    $table->id();

    // Name
    $table->string('first_name', 100);
    $table->string('last_name', 100);

    // Contact
    $table->string('email', 255)->nullable()->unique();
    $table->string('phone', 30)->nullable();

    // Company
    $table->string('company_name', 255)->nullable();
    $table->string('job_title', 100)->nullable();

    // Lifecycle — string, not enum(): works on MySQL, MariaDB, AND SQLite (test suite)
    // Valid values enforced by Rule::enum() in FormRequests, not at DB level.
    $table->string('status', 20)->default('lead');

    // Source — same reasoning as status
    $table->string('source', 30)->default('other');

    // Assignment
    $table->foreignId('assigned_to')
          ->nullable()
          ->constrained('users')
          ->nullOnDelete();

    // Department scoping
    $table->foreignId('department_id')
          ->nullable()
          ->constrained('departments')
          ->nullOnDelete();

    // Address
    $table->string('address_line1', 255)->nullable();
    $table->string('address_line2', 255)->nullable();
    $table->string('city', 100)->nullable();
    $table->string('state', 100)->nullable();
    $table->string('postcode', 20)->nullable();
    // Nullable — country is part of the optional address block.
    // Default 'Australia' is applied in CustomerService::create(), not at DB level,
    // so an explicit NULL from the form correctly clears the field.
    $table->string('country', 100)->nullable();

    // Notes
    $table->text('notes')->nullable();

    // Audit
    $table->foreignId('created_by')
          ->nullable()
          ->constrained('users')
          ->nullOnDelete();
    $table->foreignId('updated_by')
          ->nullable()
          ->constrained('users')
          ->nullOnDelete();

    $table->timestamps();
    $table->softDeletes();
});
```

## Full Column Set

| Column          | Type                                                          | Notes                          |
|-----------------|---------------------------------------------------------------|--------------------------------|
| id              | bigint unsigned PK                                            |                                |
| first_name      | varchar(100)                                                  | required                       |
| last_name       | varchar(100)                                                  | required                       |
| email           | varchar(255) NULL UNIQUE                                      | unique when present            |
| phone           | varchar(30) NULL                                              |                                |
| company_name    | varchar(255) NULL                                             |                                |
| job_title       | varchar(100) NULL                                             |                                |
| status          | varchar(20)                                                   | default: lead                  |
| source          | varchar(30)                                                   | default: other                 |
| assigned_to     | bigint unsigned NULL FK                                       | → users.id                     |
| department_id   | bigint unsigned NULL FK                                       | → departments.id               |
| address_line1   | varchar(255) NULL                                             |                                |
| address_line2   | varchar(255) NULL                                             |                                |
| city            | varchar(100) NULL                                             |                                |
| state           | varchar(100) NULL                                             |                                |
| postcode        | varchar(20) NULL                                              |                                |
| country         | varchar(100) NULL                                             | default via service            |
| notes           | text NULL                                                     |                                |
| created_by      | bigint unsigned NULL FK                                       | → users.id                     |
| updated_by      | bigint unsigned NULL FK                                       | → users.id                     |
| created_at      | timestamp                                                     |                                |
| updated_at      | timestamp                                                     |                                |
| deleted_at      | timestamp NULL                                                | soft-delete sentinel           |

## Indexes

```php
// Already created by foreignId() helpers:
//   INDEX (assigned_to), INDEX (department_id), INDEX (created_by), INDEX (updated_by)

$table->index('status');
$table->index('source');
$table->index(['last_name', 'first_name']);  // name search
$table->index('deleted_at');
```

## Rollback

```php
Schema::dropIfExists('customers');
```

## Enums

### `App\Enums\CustomerStatus`

```php
enum CustomerStatus: string
{
    case Lead      = 'lead';
    case Prospect  = 'prospect';
    case Active    = 'active';
    case Churned   = 'churned';

    public function label(): string
    {
        return match($this) {
            self::Lead      => 'Lead',
            self::Prospect  => 'Prospect',
            self::Active    => 'Active Customer',
            self::Churned   => 'Churned',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Lead      => 'blue',
            self::Prospect  => 'yellow',
            self::Active    => 'green',
            self::Churned   => 'gray',
        };
    }
}
```

### `App\Enums\CustomerSource`

```php
enum CustomerSource: string
{
    case Web            = 'web';
    case Referral       = 'referral';
    case ColdCall       = 'cold_call';
    case EmailCampaign  = 'email_campaign';
    case Social         = 'social';
    case Event          = 'event';
    case Import         = 'import';
    case Other          = 'other';

    public function label(): string
    {
        return match($this) {
            self::Web           => 'Website',
            self::Referral      => 'Referral',
            self::ColdCall      => 'Cold Call',
            self::EmailCampaign => 'Email Campaign',
            self::Social        => 'Social Media',
            self::Event         => 'Event',
            self::Import        => 'Import',
            self::Other         => 'Other',
        };
    }
}
```
