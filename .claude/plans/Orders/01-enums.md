# 01 — Enums

> **Layer 1 — Foundation.** No dependencies on other Orders plan files.

## Scope

Defines the PHP enums used by ex-19:

- `OrderStatus` — `Pending, Processing, Complete`
- `OrderSource` — `WalkIn`
- `PaymentMethod` — `Cash`
- `PaymentStatus` — `Unpaid, Paid`
- `OrderEvent` — `OrderPlaced, PaymentReceived, Completed`

---

## Decisions LOCKED

| Decision | Rationale | ex-19 line |
|----------|-----------|-----------|
| All enums are PHP 8.2 `string`-backed | DB stores readable strings (greppable logs, easy seed data) | 73 |
| Each enum has a `label()` method | Single source of truth for UI display | — |
| Transitions enforced in service, not enum | Service layer is the boundary; enum stays pure data | — (see `07-service.md`) |

---

## File locations

```
app/Enums/OrderStatus.php
app/Enums/OrderSource.php
app/Enums/PaymentMethod.php
app/Enums/PaymentStatus.php
app/Enums/OrderEvent.php
```

---

## `OrderStatus`

```php
<?php
declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Pending    = 'pending';     // order created, awaiting payment
    case Processing = 'processing';  // payment received, work in progress (or awaiting handover)
    case Complete   = 'complete';    // unit handed over, transaction closed

    public function label(): string
    {
        return match ($this) {
            self::Pending    => 'Pending',
            self::Processing => 'Processing',
            self::Complete   => 'Complete',
        };
    }
}
```

**ex-19 refs:** line 73 (`orders.status`), lines 182–186 (status timeline).

**Edge cases:**
- New orders default to `Pending`
- `Complete` is terminal — `OrderService` throws `DomainException` on any transition out of it
- Allowed transitions: `Pending → Processing` (on payment), `Processing → Complete` (on handover)

---

## `OrderSource`

```php
<?php
declare(strict_types=1);

namespace App\Enums;

enum OrderSource: string
{
    case WalkIn = 'walk_in';  // customer physically at the counter

    public function label(): string
    {
        return match ($this) {
            self::WalkIn => 'Walk-in',
        };
    }
}
```

**ex-19 ref:** line 73 (`orders.source = 'walk_in'`).

**Edge cases:** none — only one case exists.

---

## `PaymentMethod`

```php
<?php
declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';  // physical cash collected at counter

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
        };
    }
}
```

**ex-19 ref:** line 124 (`payments.method = 'cash'`).

**Edge cases:**
- Cash settles immediately — no async/pending state for payments

---

## `PaymentStatus`

```php
<?php
declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';  // order created, no payment yet
    case Paid   = 'paid';    // full amount collected

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::Paid   => 'Paid',
        };
    }
}
```

**ex-19 refs:** line 73 (`payment_status = 'paid'`), line 182 (`unpaid`), line 183 (transitions to `paid`).

**Edge cases:**
- Orders default to `Unpaid` on creation
- Transitions: `Unpaid → Paid` (on full cash payment recorded)
- Partial payments not in scope — orders are either fully paid or unpaid

---

## `OrderEvent`

```php
<?php
declare(strict_types=1);

namespace App\Enums;

enum OrderEvent: string
{
    case OrderPlaced     = 'order_placed';      // order row + lines + fees inserted
    case PaymentReceived = 'payment_received';  // cash payment recorded
    case Completed       = 'completed';         // unit handed over, order closed

    public function label(): string
    {
        return match ($this) {
            self::OrderPlaced     => 'Order placed',
            self::PaymentReceived => 'Payment received',
            self::Completed       => 'Order completed',
        };
    }
}
```

**ex-19 refs:** line 155 (`order_placed`), line 156 (`payment_received`), line 157 (`completed`), lines 165-175 (rendered timeline).

**Edge cases:**
- Events are append-only — never updated, never deleted
- `order_events.metadata` is JSON — shape varies per event (defined in `14-events-inventory.md`)
- Sequence enforced by `OrderService`: `OrderPlaced → PaymentReceived → Completed`
- Each event fires exactly once per order

---

## Dependencies

**Depends on:** nothing.

**Depended on by:**
- `03-schema.md` — column casts
- `04-models.md` — model `casts()` arrays
- `05-factories.md` — factory states
- `07-service.md` — transitions, validations
- `09-requests.md` — `Rule::enum(...)` validators
- `12-views.md` — `->label()` calls
- `14-events-inventory.md` — event taxonomy
- `15-tests.md` — assertions

---

## Validation gates

- [ ] Every ex-19 status/source/method/event value has a defined case
- [ ] Every enum has a `label()` method covering all its cases
- [ ] String values are snake_case (DB convention)
- [ ] No transitions defined inside enum classes (kept pure data)
- [ ] PHP namespace `App\Enums` matches project convention
- [ ] `declare(strict_types=1)` on every enum file

---

## Cross-check vs ex-19

| ex-19 value | Enum case | Line |
|-------------|-----------|------|
| `walk_in` | `OrderSource::WalkIn` | 73 |
| `pending` | `OrderStatus::Pending` | 182 |
| `processing` | `OrderStatus::Processing` | 183 |
| `complete` | `OrderStatus::Complete` | 73, 185 |
| `unpaid` | `PaymentStatus::Unpaid` | 182 |
| `paid` | `PaymentStatus::Paid` | 73, 183 |
| `cash` | `PaymentMethod::Cash` | 124 |
| `order_placed` | `OrderEvent::OrderPlaced` | 155 |
| `payment_received` | `OrderEvent::PaymentReceived` | 156 |
| `completed` | `OrderEvent::Completed` | 157 |

All 10 ex-19 enum values mapped.
