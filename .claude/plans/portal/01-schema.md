# Customer Portal — Schema

## Change Required
Add `user_id` foreign key to the existing `customers` table.
This links a customer profile to a Laravel `users` account.

---

## Migration — Add user_id to customers

**File:** `database/migrations/xxxx_add_user_id_to_customers_table.php`

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
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('user_id')
                  ->nullable()
                  ->unique()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
```

---

## Updated customers Table

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | bigIncrements | No | Primary key |
| **user_id** | foreignId | **Yes** | **NEW — links to users.id, unique, nullOnDelete** |
| name | string(255) | No | |
| email | string(255) | No | Unique |
| phone | string(20) | No | |
| company_name | string(255) | Yes | |
| address | string(255) | No | |
| city | string(100) | No | |
| state | string(100) | No | |
| postal_code | string(20) | No | |
| country | string(100) | No | |
| status | string | No | active / inactive / blocked |
| created_at | timestamp | Yes | |
| updated_at | timestamp | Yes | |
| deleted_at | timestamp | Yes | Soft delete |

---

## Model Changes Required (after migration)

### Customer model — add to `$fillable` and relationship

```php
// Add to $fillable:
'user_id',

// Add relationship:
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

### User model — add relationship

```php
// Add relationship:
public function customer(): HasOne
{
    return $this->hasOne(Customer::class);
}
```

---

## Notes
- `user_id` is **nullable** — admin-created customers have no user account initially
- `user_id` is **unique** — one user can only be linked to one customer record
- `nullOnDelete` — if the user is deleted, `user_id` is set to null (customer record stays)
- When a customer self-registers, both `User` and `Customer` records are created together in a `DB::transaction`
- After migration: `php artisan migrate`
