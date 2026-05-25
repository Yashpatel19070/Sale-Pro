<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'payable_type', 'payable_id', 'method', 'amount',
        'status', 'created_by', 'currency',
        'stripe_terminal_reader_id', 'stripe_payment_intent_id',
        'stripe_charge_id', 'stripe_checkout_session_id',
        'cash_received_at', 'cheque_number', 'cheque_date',
        'paid_at', 'paid_by',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'cheque_date' => 'date',
            'cash_received_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
