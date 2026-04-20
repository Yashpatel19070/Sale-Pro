<?php

declare(strict_types=1);

namespace App\Enums;

enum PoType: string
{
    case Purchase = 'purchase';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Return => 'Return',
        };
    }
}
