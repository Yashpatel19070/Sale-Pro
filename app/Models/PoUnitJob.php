<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PipelineStage;
use App\Enums\UnitJobStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

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
            'status' => UnitJobStatus::class,
        ];
    }

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

    public function scopeAtStage(Builder $query, PipelineStage $stage): Builder
    {
        return $query->where('current_stage', $stage->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UnitJobStatus::Pending->value);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function isAtFinalStage(): bool
    {
        return $this->current_stage->isFinal();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
