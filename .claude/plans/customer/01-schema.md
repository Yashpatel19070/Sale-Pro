# Customer Module — Schema

## Table: `customers`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| name | string(255) | No | — | Full name, required |
| email | string(255) | No | — | Unique |
| phone | string(20) | No | — | Required |
| company_name | string(255) | Yes | null | Optional |
| status | string | No | 'active' | Enum: active / inactive / blocked |
| created_at | timestamp | Yes | — | Auto by Laravel |
| updated_at | timestamp | Yes | — | Auto by Laravel |
| deleted_at | timestamp | Yes | null | Soft delete |

## Migration File — Exact Code

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20);
            $table->string('company_name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
```

## After Creating Migration
Run:
```bash
php artisan migrate
```

## Notes
- `email` has a unique index — duplicate emails are rejected at the DB level
- `status` is stored as a plain string — cast to `CustomerStatus` enum in the Model
- `deleted_at` enables soft delete — records are never permanently removed
- No foreign keys in this table — customers are standalone records
- Address data is managed via the `customer_addresses` table — see customer-address module plan.
