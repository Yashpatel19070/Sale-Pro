<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case Active    = 'active';
    case Inactive  = 'inactive';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match($this) {
            self::Active    => 'Active',
            self::Inactive  => 'Inactive',
            self::Suspended => 'Suspended',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Active    => 'badge-green',
            self::Inactive  => 'badge-gray',
            self::Suspended => 'badge-red',
        };
    }
}
