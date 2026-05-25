<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLine extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'order_id', 'sku', 'product_name', 'inventory_serial_id',
        'unit_price', 'tax_rate', 'tax_amount', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(InventorySerial::class, 'inventory_serial_id');
    }
}
