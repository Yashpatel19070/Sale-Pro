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
    use LogsActivity;
    use SoftDeletes;

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
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default => $query,
        };
    }
}
