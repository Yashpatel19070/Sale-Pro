<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id', 'product_id', 'description',
        'qty_ordered', 'qty_received', 'qty_on_hand_snapshot',
        'unit_cost', 'tax_rate', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:2',
            'qty_received' => 'decimal:2',
            'qty_on_hand_snapshot' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
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

    public function goodsReceiptLines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    public function remainingQty(): float
    {
        return max(0, (float) $this->qty_ordered - (float) $this->qty_received);
    }

    public function isFullyReceived(): bool
    {
        return (float) $this->qty_received >= (float) $this->qty_ordered;
    }
}
