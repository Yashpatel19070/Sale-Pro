<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case OnTheWay = 'on_the_way';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case QualityCheck = 'quality_check';
    case Invoiced = 'invoiced';
    case Returning = 'returning';
    case Returned = 'returned';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Rejected => 'Rejected',
            self::Approved => 'Approved',
            self::OnTheWay => 'On The Way',
            self::PartiallyReceived => 'Partially Received',
            self::Received => 'Received',
            self::QualityCheck => 'Quality Check',
            self::Invoiced => 'Invoiced',
            self::Returning => 'Returning',
            self::Returned => 'Returned',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'yellow',
            self::Rejected => 'red',
            self::Approved => 'blue',
            self::OnTheWay => 'indigo',
            self::PartiallyReceived => 'orange',
            self::Received => 'teal',
            self::QualityCheck => 'purple',
            self::Invoiced => 'cyan',
            self::Returning => 'pink',
            self::Returned => 'rose',
            self::Closed => 'green',
            self::Cancelled => 'red',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }
}
