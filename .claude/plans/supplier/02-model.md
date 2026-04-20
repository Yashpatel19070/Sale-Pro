# Supplier Module — Model

**File:** `app/Models/Supplier.php`

---

## Full Model Code

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupplierStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'contact_name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'payment_terms',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupplierStatus::class,
        ];
    }

    // Relations — will be added when PO module is built
    // public function purchaseOrders(): HasMany
    // {
    //     return $this->hasMany(PurchaseOrder::class);
    // }

    public function scopeByStatus(Builder $query, SupplierStatus $status): void
    {
        $query->where('status', $status->value);
    }

    public function scopeSearch(Builder $query, string $term): void
    {
        $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('contact_name', 'like', "%{$term}%");
        });
    }
}
```

---

## Enum: `app/Enums/SupplierStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum SupplierStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match($this) {
            self::Active   => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Active   => 'green',
            self::Inactive => 'yellow',
        };
    }
}
```

---

## Factory: `database/factories/SupplierFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SupplierStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'          => fake()->company(),
            'contact_name'  => fake()->optional()->name(),
            'email'         => fake()->unique()->companyEmail(),
            'phone'         => fake()->numerify('###-###-####'),
            'address'       => fake()->optional()->streetAddress(),
            'city'          => fake()->optional()->city(),
            'state'         => fake()->optional()->state(),
            'postal_code'   => fake()->optional()->postcode(),
            'country'       => fake()->optional()->country(),
            'payment_terms' => fake()->optional()->randomElement(['Net 30', 'Net 60', 'COD', 'Net 15']),
            'notes'         => fake()->optional()->sentence(),
            'status'        => SupplierStatus::Active->value,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => SupplierStatus::Inactive->value]);
    }
}
```

---

## Rules
- `$fillable` must list all 11 writable fields — never use `$guarded = []`
- `casts()` method (not `$casts` property) — Laravel 12 style
- Scopes use `void` return type and accept typed params
- Relations will be added in PO module — `purchaseOrders()` stub commented out as reminder
- Never call `forceDelete()` anywhere in this module
