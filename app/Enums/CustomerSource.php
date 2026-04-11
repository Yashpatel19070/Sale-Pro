<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomerSource: string
{
    case Web           = 'web';
    case Referral      = 'referral';
    case ColdCall      = 'cold_call';
    case EmailCampaign = 'email_campaign';
    case Social        = 'social';
    case Event         = 'event';
    case Import        = 'import';
    case Other         = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Web           => 'Website',
            self::Referral      => 'Referral',
            self::ColdCall      => 'Cold Call',
            self::EmailCampaign => 'Email Campaign',
            self::Social        => 'Social Media',
            self::Event         => 'Event',
            self::Import        => 'Import',
            self::Other         => 'Other',
        };
    }
}
