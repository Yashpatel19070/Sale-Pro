<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

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
            'qty_ordered' => 'integer',
            'qty_received' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

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

    public function isFulfilled(): bool
    {
        return $this->qty_received >= $this->qty_ordered;
    }

    public function remainingQty(): int
    {
        return max(0, $this->qty_ordered - $this->qty_received);
    }

    public function lineTotalFormatted(): string
    {
        return number_format($this->qty_ordered * $this->unit_price, 2);
    }

    public function progressPercent(): int
    {
        if ($this->qty_ordered <= 0) {
            return 0;
        }

        return min(100, (int) round($this->qty_received / $this->qty_ordered * 100));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
