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
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Supplier extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty();
    }

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

    public function scopeByStatus(Builder $query, SupplierStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
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
- `$fillable` must list all 12 writable fields — never use `$guarded = []`
- `casts()` method (not `$casts` property) — Laravel 12 style
- Scopes return `Builder` — enables chaining
- LogOptions namespace: `Spatie\Activitylog\Support\LogOptions` (not `Spatie\Activitylog\LogOptions`)
- `purchaseOrders()` relation added in PO module — do not add stubs here
- Never call `forceDelete()` anywhere in this module
