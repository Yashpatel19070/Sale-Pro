# PO Pipeline Module — Models & Factories

## Model: `PoUnitJob`

```php
<?php
// app/Models/PoUnitJob.php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PipelineStage;
use App\Enums\UnitJobStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PoUnitJob extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'purchase_order_id',
        'po_line_id',
        'inventory_serial_id',
        'pending_serial_number',
        'current_stage',
        'status',
        'assigned_to_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'current_stage' => PipelineStage::class,
            'status'        => UnitJobStatus::class,
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────────

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function poLine(): BelongsTo
    {
        return $this->belongsTo(PoLine::class);
    }

    public function inventorySerial(): BelongsTo
    {
        return $this->belongsTo(InventorySerial::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PoUnitEvent::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────────

    public function scopeAtStage($query, PipelineStage $stage)
    {
        return $query->where('current_stage', $stage->value);
    }

    public function scopeActive($query)
    {
        return $query->where('status', UnitJobStatus::Pending->value);
    }

    // ── Plain Methods ─────────────────────────────────────────────────────────────

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function isAtFinalStage(): bool
    {
        return $this->current_stage->isFinal();
    }

    // ── Audit Log ─────────────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
```

---

## Model: `PoUnitEvent`

```php
<?php
// app/Models/PoUnitEvent.php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PipelineStage;
use App\Enums\UnitEventAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoUnitEvent extends Model
{
    use HasFactory;

    // Immutable — no updated_at, no soft delete
    public $timestamps = false;

    protected $fillable = [
        'po_unit_job_id',
        'stage',
        'action',
        'user_id',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'stage'      => PipelineStage::class,
            'action'     => UnitEventAction::class,
            'created_at' => 'datetime',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────────

    public function job(): BelongsTo
    {
        return $this->belongsTo(PoUnitJob::class, 'po_unit_job_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

---

## Factories

### PoUnitJobFactory

```php
<?php
// database/factories/PoUnitJobFactory.php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PipelineStage;
use App\Enums\UnitJobStatus;
use App\Models\PoLine;
use App\Models\PoUnitJob;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PoUnitJobFactory extends Factory
{
    protected $model = PoUnitJob::class;

    public function definition(): array
    {
        $po = PurchaseOrder::factory()->open()->create();

        return [
            'purchase_order_id'    => $po->id,
            'po_line_id'           => PoLine::factory()->for($po),
            'inventory_serial_id'  => null,
            'pending_serial_number' => null,
            'current_stage'        => PipelineStage::Receive,
            'status'               => UnitJobStatus::Pending,
            'assigned_to_user_id'  => null,
            'notes'                => null,
        ];
    }

    public function atStage(PipelineStage $stage): static
    {
        return $this->state(['current_stage' => $stage]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => UnitJobStatus::InProgress]);
    }

    public function passed(): static
    {
        return $this->state(['status' => UnitJobStatus::Passed]);
    }

    public function failed(): static
    {
        return $this->state(['status' => UnitJobStatus::Failed]);
    }
}
```

### PoUnitEventFactory

```php
<?php
// database/factories/PoUnitEventFactory.php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PipelineStage;
use App\Enums\UnitEventAction;
use App\Models\PoUnitEvent;
use App\Models\PoUnitJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PoUnitEventFactory extends Factory
{
    protected $model = PoUnitEvent::class;

    public function definition(): array
    {
        return [
            'po_unit_job_id' => PoUnitJob::factory(),
            'stage'          => PipelineStage::Receive,
            'action'         => UnitEventAction::Pass,
            'user_id'        => User::factory(),
            'notes'          => null,
            'created_at'     => now(),
        ];
    }

    public function pass(): static
    {
        return $this->state(['action' => UnitEventAction::Pass]);
    }

    public function fail(): static
    {
        return $this->state(['action' => UnitEventAction::Fail]);
    }

    public function skip(): static
    {
        return $this->state(['action' => UnitEventAction::Skip]);
    }
}
```

---

## Implementation Deviations (actual code differs from plan above)

### Spatie Activitylog namespaces
Correct imports for activitylog v5:
- `use Spatie\Activitylog\Models\Concerns\LogsActivity;` (not `Traits\LogsActivity`)
- `use Spatie\Activitylog\Support\LogOptions;` (not `Spatie\Activitylog\LogOptions`)

### `UnitJobStatus` — added `label()` and `badgeColor()`
All other enums have these. Added for UI badge rendering:
```php
public function label(): string
{
    return match ($this) {
        self::Pending    => 'Pending',
        self::InProgress => 'In Progress',
        self::Passed     => 'Passed',
        self::Failed     => 'Failed',
        self::Skipped    => 'Skipped',
    };
}

public function badgeColor(): string
{
    return match ($this) {
        self::Pending    => 'gray',
        self::InProgress => 'blue',
        self::Passed     => 'green',
        self::Failed     => 'red',
        self::Skipped    => 'yellow',
    };
}
```

### `PoUnitJob` scopes — added `Builder` type hints
Plan had untyped `$query` params with no return types. Actual code:
```php
use Illuminate\Database\Eloquent\Builder;

public function scopeAtStage(Builder $query, PipelineStage $stage): Builder
public function scopeActive(Builder $query): Builder
```

### `UnitEventAction` — added `label()` and `badgeColor()`
All other enums have these. Added for Blade badge rendering (replaces 5 raw string comparisons in `pipeline/show.blade.php`):
```php
public function label(): string   // 'Started', 'Passed', 'Failed', 'Skipped', 'Reopened'
public function badgeColor(): string  // 'blue', 'green', 'red', 'purple', 'yellow'
```
