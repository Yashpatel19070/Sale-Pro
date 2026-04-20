<?php

declare(strict_types=1);

namespace App\Enums;

enum PoStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Partial = 'partial';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Open => 'Open',
            self::Partial => 'Partial',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Open => 'blue',
            self::Partial => 'yellow',
            self::Closed => 'green',
            self::Cancelled => 'red',
        };
    }
}
