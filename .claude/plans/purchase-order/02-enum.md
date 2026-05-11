# Purchase Order Module — Enum

## File: `app/Enums/PurchaseOrderStatus.php`

---

## Cases

| Case | Value | Label | Color |
|------|-------|-------|-------|
| `Draft` | `draft` | Draft | gray |
| `PendingApproval` | `pending_approval` | Pending Approval | yellow |
| `Rejected` | `rejected` | Rejected | red |
| `Approved` | `approved` | Approved | blue |
| `OnTheWay` | `on_the_way` | On The Way | indigo |
| `PartiallyReceived` | `partially_received` | Partially Received | orange |
| `Received` | `received` | Received | teal |
| `QualityCheck` | `quality_check` | Quality Check | purple | GRN complete → full receipt triggers this; requires Pass QC action to advance |
| `Invoiced` | `invoiced` | Invoiced | cyan |
| `Returning` | `returning` | Returning | pink |
| `Returned` | `returned` | Returned | rose |
| `Closed` | `closed` | Closed | green |
| `Cancelled` | `cancelled` | Cancelled | red |

---

## Methods Required

| Method | Return | Purpose |
|--------|--------|---------|
| `label()` | string | Human-readable label for UI |
| `color()` | string | Tailwind color name for badge |

---

## Edit Allowed States

PO is editable only when status is one of:
- `draft`
- `rejected`

Service must guard: throw `DomainException` if edit attempted in any other state.

## GRN Allowed States

GRN can only be created when PO status is:
- `approved`
- `on_the_way`
- `partially_received`

## Invoice Allowed States

Invoice can be created when PO status is:
- `approved`
- `on_the_way`
- `partially_received`
- `received`
- `invoiced` (multi-invoice scenario — second invoice on same PO allowed)

> Note: `quality_check` is NOT allowed — invoice requires QC to pass first (PO must reach `received`).

---

## Status Transition Rules

| From | Action | To |
|------|--------|----|
| `draft` | submit | `pending_approval` |
| `pending_approval` | approve | `approved` |
| `pending_approval` | reject | `rejected` |
| `rejected` | resubmit | `pending_approval` |
| `approved` | mark on_the_way | `on_the_way` |
| `on_the_way` | create GRN (partial qty) | `partially_received` |
| `on_the_way` | create GRN (full qty) | `quality_check` |
| `partially_received` | create GRN (partial remaining) | `partially_received` |
| `partially_received` | create GRN (all remaining qty) | `quality_check` |
| `quality_check` | pass QC | `received` |
| `received` | create invoice | `invoiced` |
| `invoiced` | mark paid | `closed` |
| `received` | initiate return | `returning` |
| `returning` | return complete | `returned` |
| any | cancel | `cancelled` |

---

## Notes
- `cancelled` and `closed` are terminal — no transitions out
- `returned` is terminal
- `quality_check` is the mandatory gate between GRN completion and invoice creation — service must guard `passQualityCheck()` with status check
- Colors match Tailwind CSS v3 color names used in badge components
- Follow `SupplierStatus` pattern exactly for consistency

---

## Implementation

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft             = 'draft';
    case PendingApproval   = 'pending_approval';
    case Rejected          = 'rejected';
    case Approved          = 'approved';
    case OnTheWay          = 'on_the_way';
    case PartiallyReceived = 'partially_received';
    case Received          = 'received';
    case QualityCheck      = 'quality_check';
    case Invoiced          = 'invoiced';
    case Returning         = 'returning';
    case Returned          = 'returned';
    case Closed            = 'closed';
    case Cancelled         = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft             => 'Draft',
            self::PendingApproval   => 'Pending Approval',
            self::Rejected          => 'Rejected',
            self::Approved          => 'Approved',
            self::OnTheWay          => 'On The Way',
            self::PartiallyReceived => 'Partially Received',
            self::Received          => 'Received',
            self::QualityCheck      => 'Quality Check',
            self::Invoiced          => 'Invoiced',
            self::Returning         => 'Returning',
            self::Returned          => 'Returned',
            self::Closed            => 'Closed',
            self::Cancelled         => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft             => 'gray',
            self::PendingApproval   => 'yellow',
            self::Rejected          => 'red',
            self::Approved          => 'blue',
            self::OnTheWay          => 'indigo',
            self::PartiallyReceived => 'orange',
            self::Received          => 'teal',
            self::QualityCheck      => 'purple',
            self::Invoiced          => 'cyan',
            self::Returning         => 'pink',
            self::Returned          => 'rose',
            self::Closed            => 'green',
            self::Cancelled         => 'red',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }
}
```

**File path:** `app/Enums/PurchaseOrderStatus.php`

---

## File: `app/Enums/GoodsReceiptStatus.php`

---

## Cases

| Case | Value | Label | Color |
|------|-------|-------|-------|
| `Draft` | `draft` | Draft | gray |
| `Complete` | `complete` | Complete | green |

---

## Methods Required

| Method | Return | Purpose |
|--------|--------|---------|
| `label()` | string | Human-readable label for UI |
| `color()` | string | Tailwind color name for badge |
| `badgeClasses()` | string | Tailwind badge CSS classes |
| `isEditable()` | bool | True only for `draft` |

---

## Status Transition Rules

| From | Action | To |
|------|--------|----|
| `draft` | complete GRN | `complete` |

`complete` is terminal — no transitions out. Edit only allowed in `draft`.

---

## Implementation

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum GoodsReceiptStatus: string
{
    case Draft    = 'draft';
    case Complete = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::Draft    => 'Draft',
            self::Complete => 'Complete',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft    => 'gray',
            self::Complete => 'green',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft    => 'bg-gray-100 text-gray-800',
            self::Complete => 'bg-green-100 text-green-800',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
```

**File path:** `app/Enums/GoodsReceiptStatus.php`

---

## File: `app/Enums/InvoiceStatus.php`

---

## Cases

| Case | Value | Label | Color |
|------|-------|-------|-------|
| `Pending` | `pending` | Pending | yellow |
| `Approved` | `approved` | Approved | blue |
| `Paid` | `paid` | Paid | green |

---

## Methods Required

| Method | Return | Purpose |
|--------|--------|---------|
| `label()` | string | Human-readable label for UI |
| `color()` | string | Tailwind color name for badge |
| `badgeClasses()` | string | Tailwind badge CSS classes |
| `isTerminal()` | bool | True only for `paid` |

---

## Status Transition Rules

| From | Action | To |
|------|--------|----|
| `pending` | approve | `approved` |
| `approved` | mark paid | `paid` |

`paid` is terminal — no transitions out. Triggers PO status → `closed`.

---

## Implementation

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Paid     = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Pending',
            self::Approved => 'Approved',
            self::Paid     => 'Paid',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending  => 'yellow',
            self::Approved => 'blue',
            self::Paid     => 'green',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending  => 'bg-yellow-100 text-yellow-800',
            self::Approved => 'bg-blue-100 text-blue-800',
            self::Paid     => 'bg-green-100 text-green-800',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Paid;
    }
}
```

**File path:** `app/Enums/InvoiceStatus.php`

---

## Notes
- Both enums follow `SupplierStatus` pattern for consistency
- `GoodsReceiptService::complete()` transitions `draft → complete`
- `InvoiceService::approve()` transitions `pending → approved`
- `InvoiceService::markPaid()` transitions `approved → paid` — also closes PO (`closed` status)
