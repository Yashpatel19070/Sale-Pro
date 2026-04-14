# InventorySerial — Model

## InventorySerial.php

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SerialStatus;
use Database\Factories\InventorySerialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class InventorySerial extends Model
{
    /** @use HasFactory<InventorySerialFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['purchase_price', 'inventory_location_id', 'status'])
            // purchase_price: sensitive cost data — never logged
            // inventory_location_id + status: already captured by InventoryMovement ledger
            ->logOnlyDirty()
            ->useLogName('inventory_serial');
    }

    protected $hidden = ['purchase_price'];

    protected $fillable = [
        'product_id',
        'inventory_location_id',
        'serial_number',
        'purchase_price',
        'received_at',
        'supplier_name',
        'received_by_user_id',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price'         => 'decimal:2',
            'received_at'            => 'date',
            'status'                 => SerialStatus::class,
            'inventory_location_id'  => 'integer',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Current shelf location. Nullable — null when sold, damaged, or missing.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    /**
     * The user who logged the receipt of this unit.
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    /**
     * Movement history for this serial (receive + any subsequent transfers/sales).
     *
     * // Add this relationship AFTER building the inventory-movement module.
     * // If testing inventory-serial in isolation, stub InventoryMovement with a minimal model.
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_serial_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('serial_number', 'like', "%{$term}%")
                ->orWhereHas('product', fn (Builder $p) => $p->where('sku', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                );
        });
    }

    public function scopeWithStatus(Builder $query, SerialStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeAtLocation(Builder $query, int $locationId): Builder
    {
        return $query->where('inventory_location_id', $locationId);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('status', SerialStatus::InStock->value);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isInStock(): bool
    {
        return $this->status === SerialStatus::InStock;
    }

    public function isSold(): bool
    {
        return $this->status === SerialStatus::Sold;
    }

    public function isDamaged(): bool
    {
        return $this->status === SerialStatus::Damaged;
    }

    public function isMissing(): bool
    {
        return $this->status === SerialStatus::Missing;
    }

    /**
     * True when the unit has left the shelf (sold, damaged, or missing).
     */
    public function isOffShelf(): bool
    {
        return $this->status->isOffShelf();
    }
}
```

**File path:** `app/Models/InventorySerial.php`

---

## Factory — InventorySerialFactory.php

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SerialStatus;
use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventorySerial>
 */
class InventorySerialFactory extends Factory
{
    protected $model = InventorySerial::class;

    public function definition(): array
    {
        return [
            'product_id'             => Product::factory(),
            'inventory_location_id'  => InventoryLocation::factory(),
            'serial_number'          => strtoupper($this->faker->bothify('SN-#####-??')),
            'purchase_price'         => $this->faker->randomFloat(2, 1, 999),
            'received_at'            => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'supplier_name'          => $this->faker->optional()->company(),
            'received_by_user_id'    => User::factory(),
            'status'                 => SerialStatus::InStock->value,
            'notes'                  => null,
        ];
    }

    public function inStock(): static
    {
        return $this->state([
            'status' => SerialStatus::InStock->value,
        ]);
    }

    public function sold(): static
    {
        return $this->state([
            'status'                => SerialStatus::Sold->value,
            'inventory_location_id' => null,
        ]);
    }

    public function damaged(): static
    {
        return $this->state([
            'status'                => SerialStatus::Damaged->value,
            'inventory_location_id' => null,
            'notes'                 => 'Damaged on arrival.',
        ]);
    }

    public function missing(): static
    {
        return $this->state([
            'status'                => SerialStatus::Missing->value,
            'inventory_location_id' => null,
            'notes'                 => 'Not found during stock count.',
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(['product_id' => $product->id]);
    }

    public function atLocation(InventoryLocation $location): static
    {
        return $this->state(['inventory_location_id' => $location->id]);
    }

    public function receivedBy(User $user): static
    {
        return $this->state(['received_by_user_id' => $user->id]);
    }
}
```

**File path:** `database/factories/InventorySerialFactory.php`

---

## Modification to Product Model

Add this relationship to `app/Models/Product.php`:

```php
public function serials(): HasMany
{
    return $this->hasMany(InventorySerial::class);
}
```

Also add `use Illuminate\Database\Eloquent\Relations\HasMany;` to the import block if not already present
(it likely already is from the `listings()` relationship).

---

## Notes

- `status` is cast to `SerialStatus` enum — blade templates should use `$serial->status->label()` and `$serial->status->badgeClasses()`.
- `purchase_price` is cast to `decimal:2` — same pattern as Product. Hidden from activity log (sensitive cost data).
- `received_at` is cast to `date` — renders with `->format('M d, Y')` in views.
- `movements()` relationship assumes `InventoryMovement` model has an `inventory_serial_id` FK column (added by the inventory-serial module migration, or via a separate migration on the movements table).
- `scopeSearch` uses a sub-query to avoid cross-joins — keeps `whereHas` isolated.
