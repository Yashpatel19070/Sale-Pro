<?php

declare(strict_types=1);

namespace App\Enums;

enum SerialStatus: string
{
    case InStock = 'in_stock';
    case Sold = 'sold';
    case Damaged = 'damaged';
    case Missing = 'missing';

    // complaint/replacement workflow
    case Assigned = 'assigned';
    case ExpectedReturn = 'expected_return';
    case UnderExamination = 'under_examination';
    case Scrapped = 'scrapped';
    case CoreReceived = 'core_received';
    case CoreAccepted = 'core_accepted';
    case CoreRejected = 'core_rejected';
    case CoreInRebuild = 'core_in_rebuild';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In Stock',
            self::Sold => 'Sold',
            self::Damaged => 'Damaged',
            self::Missing => 'Missing',
            self::Assigned => 'Assigned',
            self::ExpectedReturn => 'Expected Return',
            self::UnderExamination => 'Under Examination',
            self::Scrapped => 'Scrapped',
            self::CoreReceived => 'Core Received',
            self::CoreAccepted => 'Core Accepted',
            self::CoreRejected => 'Core Rejected',
            self::CoreInRebuild => 'Core In Rebuild',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'green',
            self::Sold => 'blue',
            self::Damaged => 'red',
            self::Missing => 'yellow',
            self::Assigned => 'indigo',
            self::ExpectedReturn => 'orange',
            self::UnderExamination => 'purple',
            self::Scrapped => 'red',
            self::CoreReceived => 'cyan',
            self::CoreAccepted => 'teal',
            self::CoreRejected => 'rose',
            self::CoreInRebuild => 'amber',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::InStock => 'bg-green-100 text-green-800',
            self::Sold => 'bg-blue-100 text-blue-800',
            self::Damaged => 'bg-red-100 text-red-800',
            self::Missing => 'bg-yellow-100 text-yellow-800',
            self::Assigned => 'bg-indigo-100 text-indigo-800',
            self::ExpectedReturn => 'bg-orange-100 text-orange-800',
            self::UnderExamination => 'bg-purple-100 text-purple-800',
            self::Scrapped => 'bg-red-100 text-red-800',
            self::CoreReceived => 'bg-cyan-100 text-cyan-800',
            self::CoreAccepted => 'bg-teal-100 text-teal-800',
            self::CoreRejected => 'bg-rose-100 text-rose-800',
            self::CoreInRebuild => 'bg-amber-100 text-amber-800',
        };
    }

    /** Returns true when unit is not available for sale. */
    public function isOffShelf(): bool
    {
        return match ($this) {
            self::InStock => false,
            default => true,
        };
    }

    /** Returns all cases as [value => label] for select dropdowns. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
