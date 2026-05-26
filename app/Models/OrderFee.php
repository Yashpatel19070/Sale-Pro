<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrderFeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFee extends Model
{
    /** @use HasFactory<OrderFeeFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'name',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
