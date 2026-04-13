<?php

declare(strict_types=1);

namespace App\Enums;

enum ListingVisibility: string
{
    case Public = 'public';
    case Private = 'private';
    case Draft = 'draft';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Private => 'Private (admin only)',
            self::Draft => 'Draft',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Public => 'badge-green',
            self::Private => 'badge-yellow',
            self::Draft => 'badge-gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
