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

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company_name',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'status',
    ];

    protected $casts = [
        'status' => CustomerStatus::class,
    ];

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
- `$fillable` lists every column that can be mass-assigned — matches migration exactly
- `status` is cast to `CustomerStatus` enum — access as `$customer->status->label()`
- `scopeByStatus` — filters by a `CustomerStatus` enum case
- `scopeSearch` — searches name, email, and company_name
- No relationships in this module — customers are standalone records
- `HasFactory` is required for tests (factory will be created in 08-tests.md)
