# Portal Foundation — Schema

## Architecture Decision

`customers` table IS the auth table for the portal. No link to `users`.
`users` table is backend staff only (admin / manager / sales).

---

## Migration 1 — Drop `user_id` from customers

**File:** `database/migrations/xxxx_drop_user_id_from_customers_table.php`

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
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->nullOnDelete()
                ->after('id');
        });
    }
};
```

---

## Migration 2 — Add auth columns to customers

**File:** `database/migrations/xxxx_add_auth_columns_to_customers_table.php`

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
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('password')->nullable()->after('email'); // nullable — admin-created customers have no portal password yet
            $table->rememberToken()->after('password');
            $table->timestamp('email_verified_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['password', 'remember_token', 'email_verified_at']);
        });
    }
};
```

---

## Migration 3 — Create `customer_password_resets` table

Separate from `password_reset_tokens` (used by staff).
Needed because staff and customers could share the same email — one table would cause collisions.

**File:** `database/migrations/xxxx_create_customer_password_resets_table.php`

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
        Schema::create('customer_password_resets', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_password_resets');
    }
};
```

---

## Customer Model Changes

**File:** `app/Models/Customer.php`

Customer becomes an Authenticatable model — it IS the portal login.

```php
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $hidden = ['password', 'remember_token'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'company_name',
        'status',
        'email_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'status'            => CustomerStatus::class,
        ];
    }

    // addresses() HasMany relationship stays
    // user() BelongsTo relationship REMOVED
}
```

**Remove from `$fillable`:** `user_id`
**Remove relationship:** `user(): BelongsTo`

---

## User Model Changes

**File:** `app/Models/User.php`

**Remove:**
- `customer(): HasOne` relationship method
- `use Illuminate\Database\Eloquent\Relations\HasOne;` import

---

## config/auth.php Changes

Add customer guard, provider, and password broker:

```php
'guards' => [
    'web'      => ['driver' => 'session', 'provider' => 'users'],
    'customer' => ['driver' => 'session', 'provider' => 'customers'],
],

'providers' => [
    'users'     => ['driver' => 'eloquent', 'model' => App\Models\User::class],
    'customers' => ['driver' => 'eloquent', 'model' => App\Models\Customer::class],
],

'passwords' => [
    'users'     => ['provider' => 'users',     'table' => 'password_reset_tokens',    'expire' => 60],
    'customers' => ['provider' => 'customers', 'table' => 'customer_password_resets', 'expire' => 60],
],
```

---

## CustomerFactory Changes

**File:** `database/factories/CustomerFactory.php`

Add `password` to `definition()`:

```php
'password' => Hash::make('password'),
'email_verified_at' => now(),
```

---

## Notes

- `customers.email` is the only login credential — no dual email confusion
- `customer` guard session is separate from `web` guard session — staff login/logout never affects customer session
- `customer_password_resets` is separate from `password_reset_tokens` — no email collision between tables
- Run: `php artisan migrate`
