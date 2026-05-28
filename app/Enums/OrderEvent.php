<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderEvent: string
{
    case OrderPlaced = 'order_placed';
    case PaymentReceived = 'payment_received';
    case Completed = 'completed';
    case ComplaintOpened = 'complaint_opened';
    case ComplaintClosed = 'complaint_closed';
    case ComplaintWithdrawn = 'complaint_withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::OrderPlaced => 'Order placed',
            self::PaymentReceived => 'Payment received',
            self::Completed => 'Order completed',
            self::ComplaintOpened => 'Complaint opened',
            self::ComplaintClosed => 'Complaint closed',
            self::ComplaintWithdrawn => 'Complaint withdrawn',
        };
    }
}
