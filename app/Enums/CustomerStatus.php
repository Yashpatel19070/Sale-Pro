<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomerStatus: string
{
    case Lead     = 'lead';
    case Prospect = 'prospect';
    case Active   = 'active';
    case Churned  = 'churned';

    public function label(): string
    {
        return match ($this) {
            self::Lead     => 'Lead',
            self::Prospect => 'Prospect',
            self::Active   => 'Active Customer',
            self::Churned  => 'Churned',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Lead     => 'blue',
            self::Prospect => 'yellow',
            self::Active   => 'green',
            self::Churned  => 'gray',
        };
    }
}
