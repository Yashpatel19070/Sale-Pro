<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'number', 'customer_id', 'source', 'status', 'payment_status',
        'created_by', 'subtotal', 'fees', 'core_charges', 'shipping', 'grand_total', 'currency',
        'billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone',
        'billing_address_line1', 'billing_address_line2', 'billing_city',
        'billing_state', 'billing_postal_code', 'billing_country',
        'shipping_first_name', 'shipping_last_name', 'shipping_email', 'shipping_phone',
        'shipping_address_line1', 'shipping_address_line2', 'shipping_city',
        'shipping_state', 'shipping_postal_code', 'shipping_country',
        'shipped_at', 'shipped_by', 'delivered_at', 'delivered_by',
        'cancelled_at', 'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'source' => OrderSource::class,
            'subtotal' => 'decimal:2',
            'fees' => 'decimal:2',
            'core_charges' => 'decimal:2',
            'shipping' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function orderFees(): HasMany
    {
        return $this->hasMany(OrderFee::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function shipments(): MorphMany
    {
        return $this->morphMany(Shipment::class, 'shippable');
    }
}
