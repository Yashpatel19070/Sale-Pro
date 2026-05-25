<?php

declare(strict_types=1);

namespace App\Enums;

enum MovementType: string
{
    case Receive = 'receive';                  // PO receiving — NULL → location (new stock from supplier)
    case Transfer = 'transfer';               // internal move — location → location
    case Sale = 'sale';                       // sold to customer — location → NULL
    case Adjustment = 'adjustment';           // write-off, back to stock, or scrap — see inventory_movements docs
    case ReturnIn = 'return_in';              // customer return — NULL → Receiving Area
    case ReplacementOut = 'replacement_out';  // replacement shipped — location → NULL (separate from Sale for reporting)
    case BackOrderFill = 'back_order_fill';   // back-order serial assignment — links to order_line_id
    case CoreReceive = 'core_receive';      // customer core intake — NULL → TECH-BENCH

    public function label(): string
    {
        return match ($this) {
            self::Receive => 'Received',
            self::Transfer => 'Transferred',
            self::Sale => 'Sold',
            self::Adjustment => 'Adjustment',
            self::ReturnIn => 'Customer Return',
            self::ReplacementOut => 'Replacement Shipped',
            self::BackOrderFill => 'Back Order Fill',
            self::CoreReceive => 'Core Received',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Receive => 'green',
            self::Transfer => 'blue',
            self::Sale => 'purple',
            self::Adjustment => 'yellow',
            self::ReturnIn => 'orange',
            self::ReplacementOut => 'indigo',
            self::BackOrderFill => 'teal',
            self::CoreReceive => 'cyan',
        };
    }
}
