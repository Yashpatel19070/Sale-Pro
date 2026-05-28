<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_listing_id',
        'sku',
        'product_name',
        'inventory_serial_id',
        'unit_price',
        'tax_amount',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function productListing(): BelongsTo
    {
        return $this->belongsTo(ProductListing::class);
    }

    public function inventorySerial(): BelongsTo
    {
        return $this->belongsTo(InventorySerial::class);
    }

    public function lineFees(): HasMany
    {
        return $this->hasMany(OrderLineFee::class);
    }
}
