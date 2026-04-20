<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PoStatus;
use App\Enums\PoType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

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
        'notes',
        'created_by_user_id',
        'cancel_notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => PoType::class,
            'status' => PoStatus::class,
            'skip_tech' => 'boolean',
            'skip_qa' => 'boolean',
            'reopen_count' => 'integer',
            'confirmed_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

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

    public function scopeOfType(Builder $query, PoType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function scopeOfStatus(Builder $query, PoStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
