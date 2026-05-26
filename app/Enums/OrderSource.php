<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderSource: string
{
    case Online = 'online';
    case WalkIn = 'walk_in';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::WalkIn => 'Walk-in',
            self::Phone => 'Phone',
        };
    }
}
