# Supplier Module — Schema

## Table: `suppliers`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| name | string(255) | No | — | Company/supplier name, required |
| contact_name | string(255) | Yes | null | Contact person at supplier |
| email | string(255) | No | — | Unique business email |
| phone | string(20) | No | — | Required |
| address | string(255) | Yes | null | Street address |
| city | string(100) | Yes | null | |
| state | string(100) | Yes | null | |
| postal_code | string(20) | Yes | null | |
| country | string(100) | Yes | null | |
| payment_terms | string(100) | Yes | null | e.g. "Net 30", "Net 60", "COD" |
| notes | text | Yes | null | Internal notes about the supplier |
| status | string | No | 'active' | Enum: active / inactive |
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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone', 20);
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('payment_terms', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
```

## After Creating Migration
Run:
```bash
php artisan migrate
```

## Notes
- `email` has a unique index — duplicate emails rejected at DB level
- `status` is stored as plain string — cast to `SupplierStatus` enum in the Model
- `deleted_at` enables soft delete — records never permanently removed
- Address fields are nullable — suppliers may only have email + phone initially
- `payment_terms` is a free-text field; no enum — values are business-defined (Net 30, Net 60, COD, etc.)
- No foreign keys in this table — PO module will reference `suppliers.id` in its own migration
