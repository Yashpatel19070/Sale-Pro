# Error Handling & Logging Reference

## The Flow

```
Service throws \DomainException
  → Controller catches it → shows user-friendly message back to form

Unexpected exceptions bubble up automatically
  → Laravel renders 403 / 404 / 500 Blade view
  → withExceptions() logs it
```

---

## Exception Types — Which to Use When

| Exception | Thrown by | Caught by | Means |
|-----------|-----------|-----------|-------|
| `\DomainException` | Service | Controller | Expected business failure — show to user |
| `\Illuminate\Auth\Access\AuthorizationException` | Gate/FormRequest | Laravel auto | 403 |
| `\Illuminate\Database\Eloquent\ModelNotFoundException` | `findOrFail()` | Laravel auto | 404 |
| `\Illuminate\Validation\ValidationException` | FormRequest | Laravel auto | 422 |
| `\RuntimeException` | Anywhere | Laravel auto | 500 — log it |

**Rule:** only catch `\DomainException` in controllers. Let everything else bubble — Laravel handles it.

---

## Controller — Catch DomainException, Let Others Bubble

```php
public function store(CreateOrderRequest $request): RedirectResponse
{
    try {
        $order = $this->orders->create($request->validated(), $request->user());
    } catch (\DomainException $e) {
        return back()->withErrors(['error' => $e->getMessage()])->withInput();
    }

    return redirect()->route('orders.show', $order)->with('success', 'Order placed.');
}
```

---

## `withExceptions()` — Laravel 12

No more `app/Exceptions/Handler.php`. Log everything in `bootstrap/app.php`:

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {

    // Log all unexpected exceptions
    $exceptions->report(function (\Throwable $e) {
        Log::channel('app')->error($e->getMessage(), [
            'exception' => get_class($e),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => $e->getTraceAsString(),
        ]);
    });

})
```

---

## Logging — Rules & Levels

### Log levels

```php
Log::channel('orders')->debug('Debug info', [...]);    // local dev only
Log::channel('orders')->info('Order placed', [...]);   // significant events
Log::channel('orders')->warning('Stock low', [...]);   // unexpected but recoverable
Log::channel('orders')->error('Order failed', [...]);  // needs attention
Log::channel('orders')->critical('DB down', [...]);    // wake someone up
```

### Always log with context — never bare strings

```php
// ❌
Log::error('Order failed');

// ✅
Log::channel('orders')->error('Order creation failed', [
    'user_id' => $user->id,
    'error'   => $e->getMessage(),
]);
```

---

## Per-Feature Log Channels — One Log File Per Module

**Rule: every feature gets its own log channel. Never mix everything into `laravel.log`.**

```
storage/logs/
├── laravel.log     // framework errors only
├── orders.log      // everything order-related
├── payments.log    // keep 90 days for auditing
├── auth.log        // login attempts, failures
└── users.log       // role changes, deactivation
```

### `config/logging.php`

```php
'default' => env('LOG_CHANNEL', 'daily'),

'channels' => [

    'daily' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/laravel.log'),
        'level'  => env('LOG_LEVEL', 'debug'),
        'days'   => 14,
    ],

    'orders' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/orders.log'),
        'level'  => 'debug',
        'days'   => 30,
    ],

    'payments' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/payments.log'),
        'level'  => 'debug',
        'days'   => 90,
    ],

    'auth' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/auth.log'),
        'level'  => 'debug',
        'days'   => 30,
    ],

    'users' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/users.log'),
        'level'  => 'debug',
        'days'   => 30,
    ],

    'slack' => [
        'driver'   => 'slack',
        'url'      => env('LOG_SLACK_WEBHOOK_URL'),
        'username' => 'Laravel',
        'emoji'    => ':boom:',
        'level'    => 'critical',
    ],
],
```

### Inject logger once per service

```php
class OrderService
{
    private LoggerInterface $log;

    public function __construct()
    {
        $this->log = Log::channel('orders');
    }

    public function create(array $data, User $user): Order
    {
        $order = DB::transaction(function () use ($data, $user) {
            // ...
            return $order;
        });

        $this->log->info('Order placed', [
            'order_id' => $order->id,
            'user_id'  => $user->id,
        ]);

        OrderPlaced::dispatch($order->load('items'));

        return $order;
    }
}
```

### Log level per environment

```bash
LOG_LEVEL=debug    # local
LOG_LEVEL=warning  # staging
LOG_LEVEL=error    # production
```

---

## Quick Reference

```
DomainException        → catch in controller → back()->withErrors()
Everything else        → bubble up → Laravel auto 403 / 404 / 500
withExceptions()       → log all Throwable with context

Per-feature channels:
  Log::channel('orders')   → orders.log
  Log::channel('payments') → payments.log  (90 days)
  Log::channel('auth')     → auth.log
  Log::channel('users')    → users.log

Rules:
  One log file per feature — never mix into laravel.log
  Always log with context array — never bare strings
  Never log DomainExceptions — they are expected
  Never log in controllers
  LOG_LEVEL=error in production
```
