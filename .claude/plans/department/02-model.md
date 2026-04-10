# Department Module — Model

File: `app/Models/Department.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepartmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'manager_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForDropdown(Builder $query): Builder
    {
        return $query->active()
                     ->orderBy('name')
                     ->select(['id', 'name', 'code']);
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    public function getActiveMemberCountAttribute(): int
    {
        return $this->users()->where('status', 'active')->count();
    }
}
```

## Enum: `App\Enums\DepartmentStatus`

Not strictly needed since `is_active` is boolean, but kept for clarity
if the model ever gains more states.

File: `app/Enums/DepartmentStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum DepartmentStatus: string
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
            self::Inactive => 'gray',
        };
    }
}
```

## Factory

File: `database/factories/DepartmentFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => $this->faker->unique()->company(),
            'code'        => strtoupper($this->faker->unique()->lexify('????')),
            'description' => $this->faker->sentence(),
            'manager_id'  => null,
            'is_active'   => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
```
