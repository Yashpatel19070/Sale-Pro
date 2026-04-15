<?php

declare(strict_types=1);

namespace App\Enums;

enum MovementType: string
{
    case Receive = 'receive';     // NULL → location (new stock arrives)
    case Transfer = 'transfer';    // location → location (shelf move)
    case Sale = 'sale';        // location → NULL (shipped to customer)
    case Adjustment = 'adjustment';  // status change: damaged or missing

    public function label(): string
    {
        return match ($this) {
            self::Receive => 'Received',
            self::Transfer => 'Transferred',
            self::Sale => 'Sold',
            self::Adjustment => 'Adjustment',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Receive => 'green',
            self::Transfer => 'blue',
            self::Sale => 'purple',
            self::Adjustment => 'yellow',
        };
    }
}
