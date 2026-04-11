# Portal Foundation — Schema

## Change Required
Add `user_id` to `customers` table — links a customer profile to a portal login account.

---

## Migration

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

## Customer Model Changes

**File:** `app/Models/Customer.php`

Add `user_id` to `$fillable` and add the relationship:

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Add to $fillable:
'user_id',

// Add relationship method:
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

---

## User Model Changes

**File:** `app/Models/User.php`

Two changes required:

**1. Implement MustVerifyEmail** — required for email verification to work:
```php
use Illuminate\Contracts\Auth\MustVerifyEmail;

// Change class signature to:
class User extends Authenticatable implements MustVerifyEmail
```
Check if this is already present before adding — do NOT add it twice.

**2. Add relationship:**
```php
use Illuminate\Database\Eloquent\Relations\HasOne;

public function customer(): HasOne
{
    return $this->hasOne(Customer::class);
}
```

---

## Notes
- `user_id` is nullable — admin-created customers have no portal account
- `user_id` is unique — one user can only link to one customer record
- `nullOnDelete` — deleting a user sets `user_id` to null, customer record is kept
- Run: `php artisan migrate`
