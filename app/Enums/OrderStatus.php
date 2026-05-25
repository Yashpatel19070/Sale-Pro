<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Complete = 'complete';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case BackOrdered = 'back_ordered';
    case Rts = 'rts';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Shipped => 'Shipped',
            self::Complete => 'Complete',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
            self::BackOrdered => 'Back Ordered',
            self::Rts => 'Return to Sender',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Processing => 'blue',
            self::Shipped => 'indigo',
            self::Complete => 'green',
            self::Cancelled => 'red',
            self::Refunded => 'orange',
            self::BackOrdered => 'purple',
            self::Rts => 'rose',
        };
    }
}
