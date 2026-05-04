<?php

declare(strict_types=1);

namespace App\Enums;

enum GoodsReceiptStatus: string
{
    case Draft = 'draft';
    case Complete = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Complete => 'Complete',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Complete => 'green',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-800',
            self::Complete => 'bg-green-100 text-green-800',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
