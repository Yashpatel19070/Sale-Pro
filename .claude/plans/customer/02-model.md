# Customer Module — Enum + Model

---

## 1. CustomerStatus Enum

**File:** `app/Enums/CustomerStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomerStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
    case Blocked  = 'blocked';

    public function label(): string
    {
        return match($this) {
            self::Active   => 'Active',
            self::Inactive => 'Inactive',
            self::Blocked  => 'Blocked',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Active   => 'green',
            self::Inactive => 'yellow',
            self::Blocked  => 'red',
        };
    }
}
```

### Notes
- `label()` — used in Blade views to display readable status text
- `color()` — used in Blade views to display Tailwind badge color
- Do NOT add more cases without updating this plan

---

## 2. Customer Model

**File:** `app/Models/Customer.php`

Customer IS the portal auth model — it extends `Authenticatable`, implements `MustVerifyEmail`.
No linked `User` row. No Spatie role.

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerStatus;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable implements MustVerifyEmail
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'company_name',
        'status',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'status'            => CustomerStatus::class,
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // --- Relationships ---

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    // --- Scopes ---

    public function scopeByStatus(Builder $query, CustomerStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('company_name', 'like', "%{$term}%");
        });
    }
}
```

### Notes

- Extends `Illuminate\Foundation\Auth\User as Authenticatable` — NOT `Model`
- Implements `MustVerifyEmail` — portal registration sends verification email
- `Notifiable` trait — required for password reset + email verify notifications
- `$hidden` — `password` and `remember_token` excluded from serialization
- `$casts` — `password` cast as `'hashed'` (Laravel 10+ auto-hashes on assignment), `email_verified_at` as datetime
- `addresses()` — hasMany(CustomerAddress::class), added when customer-address module was built
- `user()` relationship **DELETED** — Customer is standalone; no linked User
- `scopeByStatus` — filters by a `CustomerStatus` enum case
- `scopeSearch` — searches name, email, and company_name
- `HasFactory` is required for tests

### Auth Columns (from migration)

These columns are added by `portal-foundation/01-schema.md`:

| Column | Type | Notes |
|--------|------|-------|
| `password` | varchar(255), nullable | null = no portal account set up yet |
| `remember_token` | varchar(100), nullable | standard Laravel auth |
| `email_verified_at` | timestamp, nullable | null = unverified |
