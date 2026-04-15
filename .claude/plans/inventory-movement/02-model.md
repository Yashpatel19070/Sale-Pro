# InventoryMovement Module — Model

## InventoryMovement Model

```php
<?php
// app/Models/InventoryMovement.php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory;

    // NOTE: LogsActivity is intentionally NOT used on this model.
    // InventoryMovement IS the audit trail — each row is itself an audit entry.
    // Adding LogsActivity would create a duplicate log for every movement recorded.
    // Use the movement ledger directly for stock history and audit purposes.

    // ── NO SoftDeletes — movements are immutable by design ──────────────────

    // ── Mass Assignment ──────────────────────────────────────────────────────

    protected $fillable = [
        'inventory_serial_id',
        'type',
        'from_location_id',
        'to_location_id',
        'purchase_price',
        'reference',
        'notes',
        'user_id',
    ];

    protected $hidden = ['purchase_price'];

    // ── Casts ────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'type'           => MovementType::class,
            'purchase_price' => 'decimal:2',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /**
     * The serial number this movement tracks.
     */
    public function serial(): BelongsTo
    {
        return $this->belongsTo(InventorySerial::class, 'inventory_serial_id');
    }

    /**
     * Source location — null for receive (arrived from outside) or
     * for adjustments with no defined source.
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    /**
     * Destination location — null for sale (left warehouse) or
     * for adjustments with no defined destination.
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    /**
     * Staff member who recorded this movement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Local Scopes ─────────────────────────────────────────────────────────

    /**
     * Filter by movement type.
     */
    public function scopeOfType(Builder $q, MovementType $type): Builder
    {
        return $q->where('type', $type->value);
    }

    /**
     * Filter movements for a specific serial.
     */
    public function scopeForSerial(Builder $q, InventorySerial $serial): Builder
    {
        return $q->where('inventory_serial_id', $serial->id);
    }

    /**
     * Filter movements involving a specific location (as source or destination).
     */
    public function scopeAtLocation(Builder $q, InventoryLocation $location): Builder
    {
        return $q->where(function (Builder $inner) use ($location): void {
            $inner->where('from_location_id', $location->id)
                  ->orWhere('to_location_id', $location->id);
        });
    }

    /**
     * Filter movements within a date range.
     */
    public function scopeBetweenDates(Builder $q, string $from, string $to): Builder
    {
        return $q->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
    }

    // ── Plain Methods ────────────────────────────────────────────────────────

    /**
     * Human-readable summary of the movement direction.
     * Example: "Shelf L1 → Shelf L99", "NULL → Shelf L1", "Shelf L99 → NULL"
     */
    public function directionLabel(): string
    {
        $from = $this->fromLocation?->code ?? 'External';
        $to   = $this->toLocation?->code   ?? 'External';

        return "{$from} → {$to}";
    }
}
```

---

## Factory

```php
<?php
// database/factories/InventoryMovementFactory.php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        return [
            'inventory_serial_id' => InventorySerial::factory(),
            'type'                => MovementType::Transfer,
            'from_location_id'    => InventoryLocation::factory(),
            'to_location_id'      => InventoryLocation::factory(),
            'purchase_price'      => null,
            'reference'           => null,
            'notes'               => null,
            'user_id'             => User::factory(),
        ];
    }

    /**
     * Receive movement — null from, location to, purchase price set.
     */
    public function receive(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'             => MovementType::Receive,
            'from_location_id' => null,
            'to_location_id'   => InventoryLocation::factory(),
            'purchase_price'   => $this->faker->randomFloat(2, 10, 500),
        ]);
    }

    /**
     * Transfer movement — location to location.
     */
    public function transfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'             => MovementType::Transfer,
            'from_location_id' => InventoryLocation::factory(),
            'to_location_id'   => InventoryLocation::factory(),
            'purchase_price'   => null,
        ]);
    }

    /**
     * Sale movement — location from, null to.
     */
    public function sale(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'             => MovementType::Sale,
            'from_location_id' => InventoryLocation::factory(),
            'to_location_id'   => null,
            'purchase_price'   => null,
            'reference'        => 'ORD-' . $this->faker->numerify('####'),
        ]);
    }

    /**
     * Adjustment movement — both nullable, status reason in notes.
     */
    public function adjustment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'             => MovementType::Adjustment,
            'from_location_id' => null,
            'to_location_id'   => null,
            'purchase_price'   => null,
            'notes'            => $this->faker->sentence(),
        ]);
    }
}
```

---

## Relationship to Add on InventorySerial

```php
// app/Models/InventorySerial.php — add this method

/**
 * All movement records for this serial, in chronological order.
 */
public function movements(): HasMany
{
    return $this->hasMany(InventoryMovement::class)->orderBy('created_at');
}
```

---

## Key Rules

- No `SoftDeletes` trait — intentional and permanent
- `purchase_price` cast as `decimal:2` (not float — avoids floating-point rounding)
- `purchase_price` is in `$hidden` — never serialized to JSON (cost data is internal)
- All relations have explicit typed return types
- `scopeAtLocation()` uses an OR subquery (both from and to) correctly wrapped to avoid
  AND/OR precedence bugs with other chained scopes
- `directionLabel()` is a plain method — no accessor overhead, called only when needed
