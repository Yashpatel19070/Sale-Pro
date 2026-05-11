<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Invoice extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'purchase_order_id', 'invoice_number', 'invoice_date', 'due_date',
        'amount', 'status', 'notes', 'approved_by', 'approved_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
            'status' => InvoiceStatus::class,
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeByStatus(Builder $query, InvoiceStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeByPurchaseOrder(Builder $query, int $poId): Builder
    {
        return $query->where('purchase_order_id', $poId);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->where('status', '!=', InvoiceStatus::Paid->value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
