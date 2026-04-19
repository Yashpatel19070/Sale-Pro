# Purchase Order Module — Models & Factories

## Model: `PurchaseOrder`

```php
<?php
// app/Models/PurchaseOrder.php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PoStatus;
use App\Enums\PoType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PurchaseOrder extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'po_number',
        'type',
        'parent_po_id',
        'supplier_id',
        'status',
        'skip_tech',
        'skip_qa',
        'reopen_count',
        'reopened_at',
        'notes',
        'created_by_user_id',
        'confirmed_at',
        'closed_at',
        'cancelled_at',
        'cancel_notes',
    ];

    protected function casts(): array
    {
        return [
            'type'         => PoType::class,
            'status'       => PoStatus::class,
            'skip_tech'    => 'boolean',
            'skip_qa'      => 'boolean',
            'reopen_count' => 'integer',
            'confirmed_at' => 'datetime',
            'closed_at'    => 'datetime',
            'cancelled_at' => 'datetime',
            'reopened_at'  => 'datetime',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────────

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function parentPo(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'parent_po_id');
    }

    public function returnPos(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'parent_po_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PoLine::class);
    }

    public function unitJobs(): HasMany
    {
        return $this->hasMany(PoUnitJob::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────────

    public function scopeOfType($query, PoType $type)
    {
        return $query->where('type', $type->value);
    }

    public function scopeOfStatus($query, PoStatus $status)
    {
        return $query->where('status', $status->value);
    }

    // ── Plain Methods ─────────────────────────────────────────────────────────────

    public function isEditable(): bool
    {
        return $this->status === PoStatus::Draft;
    }

    public function canReceive(): bool
    {
        return in_array($this->status, [PoStatus::Open, PoStatus::Partial], true);
    }

    public function isClosed(): bool
    {
        return $this->status === PoStatus::Closed;
    }

    public function isCancelled(): bool
    {
        return $this->status === PoStatus::Cancelled;
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

## Enum: `PoStatus`

```php
<?php
// app/Enums/PoStatus.php

declare(strict_types=1);

namespace App\Enums;

enum PoStatus: string
{
    case Draft     = 'draft';
    case Open      = 'open';
    case Partial   = 'partial';
    case Closed    = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Draft     => 'Draft',
            self::Open      => 'Open',
            self::Partial   => 'Partial',
            self::Closed    => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeColor(): string
    {
        return match($this) {
            self::Draft     => 'gray',
            self::Open      => 'blue',
            self::Partial   => 'yellow',
            self::Closed    => 'green',
            self::Cancelled => 'red',
        };
    }
}
```

---

## Enum: `PoType`

```php
<?php
// app/Enums/PoType.php

declare(strict_types=1);

namespace App\Enums;

enum PoType: string
{
    case Purchase = 'purchase';
    case Return   = 'return';

    public function label(): string
    {
        return match($this) {
            self::Purchase => 'Purchase',
            self::Return   => 'Return',
        };
    }
}
```

---

## Model: `PoLine`

```php
<?php
// app/Models/PoLine.php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PoLine extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'qty_ordered',
        'qty_received',
        'unit_price',
        'snapshot_stock',
        'snapshot_inbound',
    ];

    protected function casts(): array
    {
        return [
            'qty_ordered'  => 'integer',
            'qty_received' => 'integer',
            'unit_price'   => 'decimal:2',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────────

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unitJobs(): HasMany
    {
        return $this->hasMany(PoUnitJob::class);
    }

    // ── Plain Methods ─────────────────────────────────────────────────────────────

    public function isFulfilled(): bool
    {
        return $this->qty_received >= $this->qty_ordered;
    }

    public function remainingQty(): int
    {
        return max(0, $this->qty_ordered - $this->qty_received);
    }

    public function lineTotal(): string
    {
        return number_format($this->qty_ordered * $this->unit_price, 2);
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

## Factories

### PurchaseOrderFactory

```php
<?php
// database/factories/PurchaseOrderFactory.php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PoStatus;
use App\Enums\PoType;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'po_number'          => sprintf('PO-%d-%s', now()->year, $this->faker->unique()->numerify('####')),
            'type'               => PoType::Purchase,
            'parent_po_id'       => null,
            'supplier_id'        => Supplier::factory(),
            'status'             => PoStatus::Draft,
            'skip_tech'          => false,
            'skip_qa'            => false,
            'reopen_count'       => 0,
            'reopened_at'        => null,
            'notes'              => null,
            'created_by_user_id' => User::factory(),
            'confirmed_at'       => null,
            'closed_at'          => null,
            'cancelled_at'       => null,
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => PoStatus::Open, 'confirmed_at' => now()]);
    }

    public function partial(): static
    {
        return $this->state(['status' => PoStatus::Partial, 'confirmed_at' => now()]);
    }

    public function closed(): static
    {
        return $this->state(['status' => PoStatus::Closed, 'confirmed_at' => now(), 'closed_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => PoStatus::Cancelled, 'cancelled_at' => now()]);
    }

    public function returnType(): static
    {
        return $this->state(['type' => PoType::Return]);
    }
}
```

### PoLineFactory

```php
<?php
// database/factories/PoLineFactory.php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PoLine;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class PoLineFactory extends Factory
{
    protected $model = PoLine::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id'        => Product::factory(),
            'qty_ordered'       => $this->faker->numberBetween(1, 50),
            'qty_received'      => 0,
            'unit_price'        => $this->faker->randomFloat(2, 10, 5000),
        ];
    }

    public function fulfilled(): static
    {
        return $this->state(fn (array $attrs) => ['qty_received' => $attrs['qty_ordered']]);
    }
}
```
