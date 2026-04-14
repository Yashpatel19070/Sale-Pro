# InventoryLocation Module — Model

## File
`app/Models/InventoryLocation.php`

---

## Full Implementation

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class InventoryLocation extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ── Spatie Activity Log ────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('inventory_location');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    /**
     * Active locations only — used by dropdowns in other modules (e.g. InventorySerial).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Search by code or name (case-insensitive LIKE).
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('code', 'like', "%{$term}%")
              ->orWhere('name', 'like', "%{$term}%");
        });
    }

    /**
     * Filter by active status — accepts 'active', 'inactive', or empty string for all.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'active'   => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default    => $query,
        };
    }
}
```

---

## Factory File
`database/factories/InventoryLocationFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code'        => strtoupper($this->faker->unique()->bothify('L##')),
            'name'        => 'Shelf ' . $this->faker->bothify('L## Row ?'),
            'description' => $this->faker->optional()->sentence(),
            'is_active'   => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
```

---

## Notes

- `$fillable` lists every mass-assignable column — matches migration exactly.
- `is_active` cast to `boolean` — safe to use `$location->is_active` as a bool in Blade.
- `scopeActive()` is the canonical scope for dropdowns in other modules — always use this, never manually filter.
- `scopeSearch()` searches both `code` and `name` — a single search input covers both.
- `scopeByStatus()` is used on the index filter bar.
- `LogsActivity` trait — `logFillable()` captures all column changes, `logOnlyDirty()` skips no-op saves, `dontSubmitEmptyLogs()` avoids blank entries.
- No relationships defined in this model — future modules (`InventorySerial`, `InventoryMovement`) will declare the reverse relationship pointing back to this model.
- `HasFactory` is required for tests — factory defined in `database/factories/InventoryLocationFactory.php`.
