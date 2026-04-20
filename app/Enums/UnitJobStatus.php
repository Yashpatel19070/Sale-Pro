<?php

declare(strict_types=1);

namespace App\Enums;

enum UnitJobStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Passed = 'passed';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Passed, self::Failed, self::Skipped], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Passed => 'Passed',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::InProgress => 'blue',
            self::Passed => 'green',
            self::Failed => 'red',
            self::Skipped => 'yellow',
        };
    }
}
