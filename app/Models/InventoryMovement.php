<?php

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

    // ── NO SoftDeletes — movements are immutable by design ──────────────────────

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

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'purchase_price' => 'decimal:2',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────────

    public function serial(): BelongsTo
    {
        return $this->belongsTo(InventorySerial::class, 'inventory_serial_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Local Scopes ─────────────────────────────────────────────────────────────

    public function scopeOfType(Builder $q, MovementType $type): Builder
    {
        return $q->where('type', $type->value);
    }

    public function scopeForSerial(Builder $q, InventorySerial $serial): Builder
    {
        return $q->where('inventory_serial_id', $serial->id);
    }

    public function scopeAtLocation(Builder $q, InventoryLocation $location): Builder
    {
        return $q->where(function (Builder $inner) use ($location): void {
            $inner->where('from_location_id', $location->id)
                ->orWhere('to_location_id', $location->id);
        });
    }

    public function scopeBetweenDates(Builder $q, string $from, string $to): Builder
    {
        return $q->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
    }

    // ── Plain Methods ─────────────────────────────────────────────────────────────

    /**
     * Human-readable summary of the movement direction.
     * Example: "Shelf L1 → Shelf L99", "External → Shelf L1", "Shelf L99 → External"
     */
    public function directionLabel(): string
    {
        $from = $this->fromLocation?->code ?? 'External';
        $to = $this->toLocation?->code ?? 'External';

        return "{$from} → {$to}";
    }
}
