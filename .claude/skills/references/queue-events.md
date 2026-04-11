# Queue, Jobs & Event Design Reference

## Decision: Job vs Event vs Notification

| Use Case | Use |
|----------|-----|
| Single async task (resize image, send email) | Job |
| Something happened, multiple things should react | Event + Listeners |
| User-facing alert (email, SMS, push, DB) | Notification |
| Scheduled recurring work | Scheduled Job via `schedule()` |

## Job Pattern
Laravel 12 — no more manual trait listing. Use `implements ShouldQueue` and let the framework handle the rest via the `Queueable` trait only:

```php
<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessOrderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [30, 60, 120]; // exponential backoff

    public function __construct(private readonly Order $order) {}

    public function handle(OrderService $service): void
    {
        $service->process($this->order);
    }

    public function failed(Throwable $e): void
    {
        Log::error("Order processing failed: {$this->order->id}", ['error' => $e->getMessage()]);
    }
}

// Dispatch
ProcessOrderJob::dispatch($order)->onQueue('orders');
ProcessOrderJob::dispatch($order)->delay(now()->addMinutes(5));
```

> **Laravel 12 change**: The `Queueable` trait is now `Illuminate\Foundation\Queue\Queueable` and bundles `Dispatchable`, `InteractsWithQueue`, and `SerializesModels` — no need to import them separately.

## Event + Listener Pattern
Laravel 12 — event-to-listener mapping is auto-discovered via `#[ListensTo]` attribute. No `EventServiceProvider` `$listen` array needed:

```php
// Event
<?php

namespace App\Events;

class OrderPlaced
{
    public function __construct(public readonly Order $order) {}
}

// Listener — auto-discovered via attribute
<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Events\Attributes\AsListener;

#[AsListener(OrderPlaced::class)]
class SendOrderConfirmationEmail implements ShouldQueue
{
    use Queueable;

    public string $queue = 'notifications';

    public function handle(OrderPlaced $event): void
    {
        Mail::to($event->order->user)->send(new OrderConfirmationMail($event->order));
    }
}

// Sync listener (no ShouldQueue)
#[AsListener(OrderPlaced::class)]
class AuditOrderListener
{
    public function handle(OrderPlaced $event): void
    {
        // runs synchronously
    }
}

// Fire the event
OrderPlaced::dispatch($order);
```

## Queue Names & Priority

| Queue | Purpose | Priority |
|-------|---------|----------|
| `critical` | Payments, auth | Highest |
| `orders` | Order processing | High |
| `notifications` | Email, SMS | Medium |
| `default` | General async | Normal |
| `low` | Reports, exports | Low |

## Retry & Timeout Strategy
```php
public int $tries = 3;
public int $timeout = 120;
public int $maxExceptions = 2;
public array $backoff = [30, 60, 120];

// Prevent duplicate processing — add import at top of job class:
// use Illuminate\Queue\Middleware\WithoutOverlapping;
public function middleware(): array
{
    return [new WithoutOverlapping($this->order->id)];
}
```

## Failed Jobs
- Run `php artisan make:queue-failed-table` to create the `failed_jobs` table
- Monitor via `php artisan queue:failed`
- Retry: `php artisan queue:retry all` (use carefully)
- Hook alerts in `AppServiceProvider::boot()`:

```php
Queue::failing(function (JobFailed $event) {
    // Slack, PagerDuty, etc.
});
```

## Scheduled Jobs
Laravel 12 — define schedules directly in `routes/console.php`, no need for a `Kernel.php`:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::job(new CleanupExpiredOrdersJob)->daily();
Schedule::command('reports:generate')->weeklyOn(1, '8:00');
```

## Job/Event Map Table Template
| Trigger | Type | Class | Queue | Listeners / Notes |
|---------|------|-------|-------|-------------------|
| Order placed | Event | `OrderPlaced` | — | `SendConfirmationEmail`, `UpdateInventory` |
| Payment received | Job | `ProcessPaymentJob` | `critical` | 3 retries, 60s timeout |
| User registered | Event | `UserRegistered` | — | `SendWelcomeEmail` (async), `CreateDefaultSettings` (sync) |
| Low stock detected | Job | `NotifyLowStockJob` | `low` | Nightly batch, no retry needed |
