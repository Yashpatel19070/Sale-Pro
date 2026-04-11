# Code Style & Quality Reference

## Non-Negotiable Rules

```
✅ declare(strict_types=1) on every PHP file
✅ Type hints on every parameter and return type
✅ Run ./vendor/bin/pint before every commit
✅ PHPStan level 8 — zero errors before PR merge
✅ PHPDoc on every public Service method
❌ No dd(), var_dump(), print_r() in committed code
❌ No commented-out dead code
❌ No raw strings where constants/enums exist
```

---

## Strict Types — Every File

```php
<?php

declare(strict_types=1);

namespace App\Services;
```

Every PHP file in `app/` starts with `declare(strict_types=1)`. No exceptions.

---

## Type Hints — Always

```php
// ✅ Full type hints — parameters + return type
public function create(array $data, User $user): Order
public function cancel(Order $order): Order
public function delete(Order $order): void
public function list(): LengthAwarePaginator

// ❌ No type hints — PHPStan will fail
public function create($data, $user)
public function cancel($order)
```

Nullable types:
```php
public function find(int $id): ?Order  // returns Order or null
public function notes(): ?string       // string or null
```

---

## Naming Conventions

| Item | Convention | Example |
|------|-----------|---------|
| Model | PascalCase singular | `Product`, `SalesOrder` |
| Controller | PascalCase + `Controller` | `ProductController` |
| Service | PascalCase + `Service` | `OrderService` |
| Action | PascalCase + `Action` | `PlaceOrderAction` |
| FormRequest | Verb + Model + `Request` | `StoreProductRequest`, `UpdateOrderRequest` |
| Event | PascalCase past tense | `OrderPlaced`, `PaymentReceived` |
| Job | PascalCase present action | `ProcessPayment`, `SendInvoiceEmail` |
| Listener | PascalCase descriptive | `SendOrderConfirmation`, `UpdateInventory` |
| Enum | PascalCase + `Enum` | `OrderStatusEnum`, `ProductStatusEnum` |
| Migration | snake_case verb+table | `create_orders_table` |
| Route name | dot.notation | `orders.index`, `admin.users.destroy` |
| Blade view | snake_case folders | `orders/index.blade.php` |
| Variable | camelCase | `$orderItems`, `$totalCents` |
| Method | camelCase | `placeOrder()`, `cancelOrder()` |
| Boolean method | `is*`, `has*`, `can*` | `isActive()`, `hasStock()`, `canCancel()` |
| Constant | UPPER_SNAKE_CASE | `MAX_RETRY_ATTEMPTS` |

---

## PHPDoc Rules

### Services — every public method gets a PHPDoc block

```php
/**
 * Create a new order and decrement stock.
 * Called by: OrderController@store
 *
 * @param array<string, mixed> $data
 */
public function create(array $data, User $user): Order
```

### Models — scopes and non-obvious relations only

```php
/** Scope: pending orders only. Used in: OrderService, DashboardController */
public function scopePending(Builder $query): Builder

/** @return HasMany<OrderItem> */
public function items(): HasMany
```

### Controllers — no comments needed

```php
// index(), store(), update(), destroy() — method names are self-documenting
// Never add inline comments narrating what the code does
```

### PHPDoc array types — always specify value type

```php
/** @param array<string, mixed> $data */
/** @return array<int, Order> */
/** @return Collection<int, User> */
```

---

## What NOT to Comment

```php
// ❌ Narrating what the code does — noise, goes stale
$user = User::find($id);     // find the user
$user->assignRole('viewer'); // assign viewer role
return redirect()->back();   // redirect back

// ✅ Only comment WHY — non-obvious business reason
$order->update(['status' => OrderStatus::Pending]);
// Reset to pending — payment gateway requires re-confirmation after 24h timeout
```

**Rule:** if a comment says WHAT the code does, delete it. If it says WHY, keep it.

---

## Early Return — No Deep Nesting

```php
// ❌ Deeply nested — hard to read
public function process(Order $order): void
{
    if ($order->status === OrderStatus::Pending) {
        if ($order->items->isNotEmpty()) {
            if ($order->user->isActive()) {
                // actual logic buried 3 levels deep
            }
        }
    }
}

// ✅ Early return — flat, readable
public function process(Order $order): void
{
    if ($order->status !== OrderStatus::Pending) {
        return;
    }

    if ($order->items->isEmpty()) {
        return;
    }

    if (! $order->user->isActive()) {
        return;
    }

    // actual logic at top level
}
```

---

## match over switch — Always

```php
// ❌ switch
switch ($status) {
    case 'pending':
        return 'yellow';
    case 'active':
        return 'green';
    default:
        return 'gray';
}

// ✅ match — exhaustive, no fallthrough, returns value
return match($status) {
    OrderStatus::Pending   => 'yellow',
    OrderStatus::Active    => 'green',
    OrderStatus::Cancelled => 'red',
};
```

---

## Array Style

```php
// ❌ Old style
$items = array('a', 'b', 'c');

// ✅ Short syntax always
$items = ['a', 'b', 'c'];

// ✅ Trailing comma on multiline arrays
$data = [
    'user_id'          => $user->id,
    'shipping_address' => $data['shipping_address'],
    'status'           => OrderStatus::Pending,
];
```

---

## No Magic Numbers or Strings

```php
// ❌ Magic number / string
if ($order->total_cents > 10000) { }
if ($user->role === 'admin') { }

// ✅ Constant or enum
const FREE_SHIPPING_THRESHOLD_CENTS = 10000;
if ($order->total_cents > self::FREE_SHIPPING_THRESHOLD_CENTS) { }
if ($user->hasRole(Role::ADMIN)) { }
```

---

## Import Ordering

Always in this order — PHP core → Laravel → App:

```php
<?php

declare(strict_types=1);

namespace App\Services;

// 1. PHP core
use Throwable;

// 2. Laravel / vendor
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// 3. App
use App\Enums\OrderStatus;
use App\Events\OrderPlaced;
use App\Models\Order;
use App\Models\User;
```

---

## Pre-Commit Checklist

```bash
# 1. Fix code style
./vendor/bin/pint

# 2. Static analysis — must be zero errors
./vendor/bin/phpstan analyse

# 3. Run tests — must all pass
php artisan test

# 4. No debug code
grep -r "dd(\|var_dump(\|print_r(" app/  # must return nothing
```

---

## Quick Reference

```
declare(strict_types=1)    → every file, no exceptions
Type hints                 → every parameter + return type
PHPDoc                     → every public Service method
Pint                       → before every commit
PHPStan level 8            → zero errors, before every PR
match                      → always over switch
Early return               → no deep nesting
Comments                   → WHY only, never WHAT
No dd() / var_dump()       → use Telescope instead
No commented-out code      → delete it
Trailing commas            → multiline arrays always
```
