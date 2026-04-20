<?php

declare(strict_types=1);

namespace App\Enums;

enum UnitEventAction: string
{
    case Start = 'start';
    case Pass = 'pass';
    case Fail = 'fail';
    case Skip = 'skip';
    case Reopen = 'reopen';

    public function label(): string
    {
        return match ($this) {
            self::Start => 'Started',
            self::Pass => 'Passed',
            self::Fail => 'Failed',
            self::Skip => 'Skipped',
            self::Reopen => 'Reopened',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Start => 'blue',
            self::Pass => 'green',
            self::Fail => 'red',
            self::Skip => 'purple',
            self::Reopen => 'yellow',
        };
    }
}
