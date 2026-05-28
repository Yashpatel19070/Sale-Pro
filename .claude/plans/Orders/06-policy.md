# 06 — Policy

> **Layer 3 — Models.** Depends on `02-permissions.md`, `04-models.md`, `15-tests.md`.

## Scope

Defines `OrderPolicy` — 7 gate methods matching the 7 controller actions. Each method enforces:

1. **Permission check** (Spatie slug from `02-permissions.md`)
2. **Status guard** (only certain order statuses allow certain actions)

**Contract-only file.** Method signatures and behavior described in tables. No method bodies — tests in `15-tests.md` drive the actual implementation.

---

## Decisions LOCKED

| Decision | Rationale |
|----------|-----------|
| One policy method per controller action | 1:1 mapping makes authorization obvious |
| Permission check uses Spatie `$user->can('orders.X')` | Matches `02-permissions.md` slug convention |
| Status guard checked inside the method body | Centralizes "can this action run?" in policy, not service or controller |
| `viewAny` and `create` are class-level (no `$order` argument) | Laravel convention |
| Other methods receive `Order $order` | Status check happens against the specific order |
| Policy returns `bool` (not `Response`) | Simple binary decision — controller maps to 403 via `$this->authorize(...)` |
| Policy is registered in `AppServiceProvider::$policies` via `Gate::policy()` | Laravel convention |
| Status guards mirror `14-events-inventory.md` state machine | Single source of truth for valid transitions |

---

## File location

```
app/Policies/OrderPolicy.php
```

Registration: `AppServiceProvider::boot()` adds `Gate::policy(Order::class, OrderPolicy::class);`

---

## Policy method contracts

### `viewAny(User $user): bool`

| Aspect | Value |
|--------|-------|
| Permission | `orders.viewAny` |
| Status guard | None — index page just lists orders |
| Returns | `true` if user can view the orders list |
| Used by | `OrderController::index` |

---

### `view(User $user, Order $order): bool`

| Aspect | Value |
|--------|-------|
| Permission | `orders.view` |
| Status guard | None — any order status is viewable |
| Returns | `true` if user can view this specific order |
| Used by | `OrderController::show`, `edit` (uses view to allow redirect-instead-of-403 for non-pending) |

> **Note:** `edit` action calls `$this->authorize('view', $order)` (not `update`) — so non-pending orders return a friendly redirect to `show`, not a 403. The `update` permission is checked when the form is actually submitted.

---

### `create(User $user): bool`

| Aspect | Value |
|--------|-------|
| Permission | `orders.create` |
| Status guard | None — class-level check |
| Returns | `true` if user can create new orders |
| Used by | `OrderController::create`, `store` |

---

### `update(User $user, Order $order): bool`

| Aspect | Value |
|--------|-------|
| Permission | `orders.update` |
| Status guard | `$order->status === OrderStatus::Pending` |
| Returns | `true` only if BOTH permission and status check pass |
| Used by | `OrderController::update` |

> **Both must be true.** If user has the permission but order is `processing` or `complete` → return `false` → controller returns 403.

---

### `delete(User $user, Order $order): bool`

| Aspect | Value |
|--------|-------|
| Permission | `orders.delete` |
| Status guard | `$order->status === OrderStatus::Pending` |
| Returns | `true` only if BOTH permission and status check pass |
| Used by | `OrderController::destroy` |

> **Hard delete.** Per `02-permissions.md`, only pending orders are deletable (no money moved, no inventory touched). `sales` role has the status check moot — they lack the permission entirely.

---

### `recordCashPayment(User $user, Order $order): bool`

| Aspect | Value |
|--------|-------|
| Permission | `orders.recordPayment` |
| Status guard | `$order->status === OrderStatus::Pending && $order->payment_status === PaymentStatus::Unpaid` |
| Returns | `true` only if all 3 conditions pass |
| Used by | `OrderController::recordCashPayment` |

> **Triple check:** permission + order pending + not already paid. Prevents double-payment race.

---

### `complete(User $user, Order $order): bool`

| Aspect | Value |
|--------|-------|
| Permission | `orders.complete` |
| Status guard | `$order->status === OrderStatus::Processing` |
| Returns | `true` only if BOTH permission and status check pass |
| Used by | `OrderController::complete` |

> Only `processing` orders can be completed. A `pending` order must first be paid (transitioning it to `processing`). A `complete` order can't be re-completed.

---

## Summary table — permission × status guard

| Action | Permission | Status guard | ex-19 line |
|--------|-----------|--------------|-----------|
| `viewAny` | `orders.viewAny` | — | — (index listing) |
| `view` | `orders.view` | — | — (any order) |
| `create` | `orders.create` | — | line 20 (admin creates order) |
| `update` | `orders.update` | `pending` only | — |
| `delete` | `orders.delete` | `pending` only | — |
| `recordCashPayment` | `orders.recordPayment` | `pending` + `unpaid` only | line 34 (cash payment recorded) |
| `complete` | `orders.complete` | `processing` only | line 45 (handover) |

---

## How the policy interacts with the service

The **service** also throws `DomainException` for invalid transitions (per `14-events-inventory.md` and `15-tests.md`). The policy is the **first defense** (returns `false` → 403); the service is the **last defense** (throws if somehow reached invalid state).

| Layer | Bad request path |
|-------|------------------|
| User clicks "Complete" on pending order | Policy returns `false` → 403 (request never reaches controller logic) |
| API request with stale order state | Policy returns `false` → 403 |
| Internal job calls `OrderService::complete($pendingOrder)` directly | Service throws `DomainException` (policy not invoked) |

Both layers exist for defense-in-depth.

---

## AppServiceProvider registration

`AppServiceProvider::boot()` already has the morph map (per `03-schema.md`). Add the policy registration in the same `boot()` method:

```php
use App\Models\Order;
use App\Policies\OrderPolicy;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    // ... existing morph map ...

    Gate::policy(Order::class, OrderPolicy::class);
}
```

---

## Dependencies

**Depends on:**
- `02-permissions.md` — 7 permission slugs
- `04-models.md` — receives `Order` model instances
- `01-enums.md` — checks against `OrderStatus::Pending`, `Processing`, etc.
- Existing: Spatie Permission package, `User` model

**Depended on by:**
- `11-controller.md` — controller calls `$this->authorize('action', $order)` on every action
- `15-tests.md` — feature tests assert 403 for users without permission OR wrong status

---

## Validation gates

- [ ] Every controller action in `11-controller.md` has a matching policy method
- [ ] Every policy method checks exactly one permission slug from `02-permissions.md`
- [ ] Status guards match `14-events-inventory.md` state machine
- [ ] `viewAny` and `create` are class-level (no `$order` argument)
- [ ] `view`, `update`, `delete`, `recordCashPayment`, `complete` all receive `Order $order`
- [ ] Policy returns `bool`, not `Response`
- [ ] Registered in `AppServiceProvider::boot()` via `Gate::policy()`
- [ ] No method bodies in this plan file (tests drive implementation)

---

## Cross-check vs Layer 1 + Layer 2

| Source | Policy assertion |
|--------|------------------|
| `02-permissions.md` — 7 permissions | 7 policy methods, each checking one permission |
| `02-permissions.md` — `sales` lacks `orders.delete` | `delete` policy returns `false` for sales user (handled by permission check) |
| `14-events-inventory.md` — `pending → processing` on payment | `recordCashPayment` policy requires `pending` |
| `14-events-inventory.md` — `processing → complete` on handover | `complete` policy requires `processing` |
| `14-events-inventory.md` — only pending orders delete | `delete` policy requires `pending` |
| `15-tests.md` — `user_without_orders_*_cannot_*` tests | Permission check returns `false` |
| `15-tests.md` — `edit_redirects_to_show_when_order_not_pending` | `edit` action uses `view` policy (not `update`) — allows redirect |
| `15-tests.md` — `sales_cannot_destroy_order` | Sales user lacks `orders.delete` permission → policy returns `false` |
| `15-tests.md` — `record_cash_payment_fails_when_order_already_paid` | Policy `recordCashPayment` checks `payment_status === Unpaid` |
| `15-tests.md` — `complete_fails_when_order_not_processing` | Policy `complete` checks `status === Processing` |

All test cases mapped to policy methods. No gaps.
