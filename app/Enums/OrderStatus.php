<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case BackOrdered = 'back_ordered';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Complete = 'complete';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Rts = 'rts';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::BackOrdered => 'Back Ordered',
            self::Processing => 'Processing',
            self::Shipped => 'Shipped',
            self::Complete => 'Complete',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
            self::Rts => 'Return to Sender',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::BackOrdered => 'orange',
            self::Processing => 'blue',
            self::Shipped => 'indigo',
            self::Complete => 'green',
            self::Cancelled => 'red',
            self::Refunded => 'purple',
            self::Rts => 'pink',
        };
    }
}
