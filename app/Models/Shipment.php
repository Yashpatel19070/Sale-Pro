<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'shippable_type', 'shippable_id', 'customer_address_id', 'direction',
        'carrier', 'tracking', 'label_cost', 'status', 'created_by',
        'shipped_at', 'returned_at', 'delivered_at', 'delivered_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'label_cost' => 'decimal:2',
            'shipped_at' => 'datetime',
            'returned_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function shippable(): MorphTo
    {
        return $this->morphTo();
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }
}
