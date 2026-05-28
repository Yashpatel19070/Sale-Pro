<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
        };
    }
}
