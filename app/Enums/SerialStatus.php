<?php

declare(strict_types=1);

namespace App\Enums;

enum SerialStatus: string
{
    case InStock = 'in_stock';
    case Sold = 'sold';
    case Damaged = 'damaged';
    case Missing = 'missing';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In Stock',
            self::Sold => 'Sold',
            self::Damaged => 'Damaged',
            self::Missing => 'Missing',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'green',
            self::Sold => 'blue',
            self::Damaged => 'red',
            self::Missing => 'yellow',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::InStock => 'bg-green-100 text-green-800',
            self::Sold => 'bg-blue-100 text-blue-800',
            self::Damaged => 'bg-red-100 text-red-800',
            self::Missing => 'bg-yellow-100 text-yellow-800',
        };
    }

    /** Returns true when the unit has left the shelf (sold, damaged, or missing). */
    public function isOffShelf(): bool
    {
        return match ($this) {
            self::InStock => false,
            self::Sold, self::Damaged, self::Missing => true,
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
