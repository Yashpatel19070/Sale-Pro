<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['purchase_price'])
            ->logOnlyDirty();
    }

    protected $hidden = ['purchase_price'];

    protected $fillable = [
        'category_id',
        'sku',
        'name',
        'description',
        'purchase_price',
        'regular_price',
        'sale_price',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'regular_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(ProductListing::class);
    }

    public function serials(): HasMany
    {
        return $this->hasMany(InventorySerial::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%");
        });
    }

    public function scopeForDropdown(Builder $query): Builder
    {
        return $query->active()
            ->orderBy('name')
            ->select(['id', 'sku', 'name']);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function hasActiveListings(): bool
    {
        return $this->listings()->where('is_active', true)->exists();
    }

    public function getListingsCountAttribute(): int
    {
        return $this->listings()->count();
    }

    /** Returns the active selling price (sale_price if set, otherwise regular_price). */
    public function currentPrice(): string
    {
        return $this->sale_price ?? $this->regular_price;
    }

    public function isOnSale(): bool
    {
        return $this->sale_price !== null;
    }
}
