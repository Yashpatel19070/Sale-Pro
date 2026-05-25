<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case StripeCard = 'stripe_card';
    case StripeTerminal = 'stripe_terminal';
    case StripeCheckout = 'stripe_checkout';
    case Cheque = 'cheque';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::StripeCard => 'Stripe Card',
            self::StripeTerminal => 'Stripe Terminal',
            self::StripeCheckout => 'Stripe Checkout',
            self::Cheque => 'Cheque',
        };
    }
}
