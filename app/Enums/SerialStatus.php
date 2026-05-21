<?php

declare(strict_types=1);

namespace App\Enums;

enum SerialStatus: string
{
    case InStock = 'in_stock';
    case Sold = 'sold';
    case Missing = 'missing';

    // case Damaged        = 'damaged'; // TODO: clarify use case — may overlap with scrapped

    // complaint/replacement workflow
    case Assigned = 'assigned';            // replacement shipped, not yet with customer
    case ExpectedReturn = 'expected_return';      // customer has unit, return requested
    case UnderExamination = 'under_examination';   // unit received at dock, tech examining
    case Scrapped = 'scrapped';             // permanently written off

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In Stock',
            self::Sold => 'Sold',
            self::Missing => 'Missing',
            self::Assigned => 'Assigned',
            self::ExpectedReturn => 'Expected Return',
            self::UnderExamination => 'Under Examination',
            self::Scrapped => 'Scrapped',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'green',
            self::Sold => 'blue',
            self::Missing => 'yellow',
            self::Assigned => 'indigo',
            self::ExpectedReturn => 'orange',
            self::UnderExamination => 'purple',
            self::Scrapped => 'red',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::InStock => 'bg-green-100 text-green-800',
            self::Sold => 'bg-blue-100 text-blue-800',
            self::Missing => 'bg-yellow-100 text-yellow-800',
            self::Assigned => 'bg-indigo-100 text-indigo-800',
            self::ExpectedReturn => 'bg-orange-100 text-orange-800',
            self::UnderExamination => 'bg-purple-100 text-purple-800',
            self::Scrapped => 'bg-red-100 text-red-800',
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
