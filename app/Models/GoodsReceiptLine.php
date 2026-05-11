<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_receipt_id', 'purchase_order_line_id', 'qty_received',
        'qty_passed', 'qty_failed', 'qc_inspected_at', 'qc_inspected_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty_received' => 'decimal:2',
            'qty_passed' => 'integer',
            'qty_failed' => 'integer',
            'qc_inspected_at' => 'datetime',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function qcInspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qc_inspected_by');
    }

    public function qcDone(): bool
    {
        return $this->qty_passed !== null;
    }

    public function qcPassed(): int
    {
        return (int) ($this->qty_passed ?? 0);
    }

    public function qcFailed(): int
    {
        return (int) ($this->qty_failed ?? 0);
    }
}
