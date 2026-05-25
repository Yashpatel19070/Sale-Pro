<?php

declare(strict_types=1);

namespace App\Enums;

enum ShipmentStatus: string
{
    case Pending = 'pending';
    case LabelCreated = 'label_created';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Returned = 'returned';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::LabelCreated => 'Label Created',
            self::InTransit => 'In Transit',
            self::Delivered => 'Delivered',
            self::Returned => 'Returned',
            self::Voided => 'Voided',
        };
    }
}
