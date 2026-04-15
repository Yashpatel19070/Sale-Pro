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
            'purchase_price' => 'decimal:2',
            'received_at' => 'date',
            'status' => SerialStatus::class,
            'inventory_location_id' => 'integer',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Current shelf location. Nullable — null when sold, damaged, or missing. */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    /** The user who logged the receipt of this unit. */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    /** All movement records for this serial, in chronological order. */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_serial_id')->orderBy('created_at');
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

    /** True when the unit has left the shelf (sold, damaged, or missing). */
    public function isOffShelf(): bool
    {
        return $this->status->isOffShelf();
    }
}
