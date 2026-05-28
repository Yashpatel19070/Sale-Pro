<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderSource: string
{
    case WalkIn = 'walk_in';

    public function label(): string
    {
        return match ($this) {
            self::WalkIn => 'Walk-in',
        };
    }
}
