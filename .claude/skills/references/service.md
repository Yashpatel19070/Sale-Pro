# Service Reference

## Rules — What a Service Does and Doesn't Do

| ✅ Service owns | ❌ Never in a Service |
|----------------|----------------------|
| ALL business logic | `$request` object |
| Multi-step DB writes | `response()` / `redirect()` |
| `DB::transaction()` | `abort()` |
| Business rule validation | `$request->validated()` |
| Firing events (after transaction) | HTTP status codes |
| Coordinating multiple models | View rendering |

**One-liner:** all business logic lives here. Services are HTTP-agnostic — they work identically from a controller, a job, a command, or a test.

**The test:** can you call this service method from an Artisan command without changing it? If yes — it's correct. If no — HTTP concerns leaked in.

---

## Signature Rules

```php
// ✅ Accept models, not IDs
public function cancel(Order $order): Order { }

// ❌ Never accept raw IDs — service shouldn't query what the controller already has
public function cancel(int $orderId): Order { }

// ✅ Accept validated array + typed models
public function create(array $data, User $user): Order { }

// ✅ Always return the model — controller needs it for redirect/response
public function update(Order $order, array $data): Order { }

// ✅ Void only for true fire-and-forget deletes
public function delete(Order $order): void { }
```

---

## DB::transaction() — Non-Negotiable for Multi-Table Writes

**Every service method that writes to more than one table MUST use `DB::transaction()`.**
If any step fails, everything rolls back. Database stays consistent.

```php
// ✅ Multi-table write — always in a transaction
public function create(array $data, User $user): Order
{
    $order = DB::transaction(function () use ($data, $user) {
        $order = Order::create([
            'user_id'          => $user->id,
            'shipping_address' => $data['shipping_address'],
            'status'           => OrderStatus::Pending,
            'notes'            => $data['notes'] ?? null,
        ]);

        foreach ($data['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);

            throw_if(
                $product->stock < $item['quantity'],
                \DomainException::class,
                "Insufficient stock for '{$product->name}'"
            );

            $order->items()->create([
                'product_id'       => $product->id,
                'quantity'         => $item['quantity'],
                'unit_price_cents' => $product->price_cents,
            ]);

            $product->decrement('stock', $item['quantity']);
        }

        return $order;
    });

    // ✅ Fire events AFTER transaction — never inside
    OrderPlaced::dispatch($order->load('items'));

    return $order;
}
```

---

## Fire Events AFTER Transaction — Never Inside

```php
// ❌ Event inside transaction — job runs even if transaction rolls back
DB::transaction(function () use ($order) {
    $order->update([...]);
    OrderPlaced::dispatch($order); // wrong — fires before commit
});

// ✅ Event after transaction — only fires if everything committed
$order = DB::transaction(function () use ($data) {
    return Order::create([...]);
});

OrderPlaced::dispatch($order); // correct — transaction already committed
```

---

## Throw DomainException for Expected Business Failures

```php
// ✅ DomainException for expected failures the controller can catch and show
public function cancel(Order $order): Order
{
    throw_if(
        $order->status === OrderStatus::Shipped,
        \DomainException::class,
        'Cannot cancel a shipped order.'
    );

    throw_if(
        $order->status === OrderStatus::Cancelled,
        \DomainException::class,
        'Order is already cancelled.'
    );

    $order->update(['status' => OrderStatus::Cancelled]);
    OrderCancelled::dispatch($order);

    return $order;
}

// ❌ Never abort() in a service — HTTP concern
public function cancel(Order $order): Order
{
    if ($order->status === OrderStatus::Shipped) {
        abort(422, 'Cannot cancel.'); // wrong — service is not HTTP
    }
}
```

---

## Full Service Pattern

```php
<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Events\OrderCancelled;
use App\Events\OrderPlaced;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    // Create — multi-table, always transaction
    public function create(array $data, User $user): Order
    {
        $order = DB::transaction(function () use ($data, $user) {
            $order = Order::create([
                'user_id'          => $user->id,
                'shipping_address' => $data['shipping_address'],
                'status'           => OrderStatus::Pending,
                'notes'            => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                throw_if(
                    $product->stock < $item['quantity'],
                    \DomainException::class,
                    "Insufficient stock for '{$product->name}'"
                );

                $order->items()->create([
                    'product_id'       => $product->id,
                    'quantity'         => $item['quantity'],
                    'unit_price_cents' => $product->price_cents,
                ]);

                $product->decrement('stock', $item['quantity']);
            }

            return $order;
        });

        OrderPlaced::dispatch($order->load('items'));

        return $order;
    }

    // List — query lives in service, controller stays clean
    public function list(): LengthAwarePaginator
    {
        return Order::with(['user', 'items'])
            ->latest()
            ->paginate(20);
    }

    // Update — single table write, no transaction needed
    public function update(Order $order, array $data): Order
    {
        $order->update($data);
        return $order->fresh();
    }

    // Cancel — business rules + status change
    public function cancel(Order $order): Order
    {
        throw_if(
            $order->status === OrderStatus::Shipped,
            \DomainException::class,
            'Cannot cancel a shipped order.'
        );

        throw_if(
            $order->status === OrderStatus::Cancelled,
            \DomainException::class,
            'Order is already cancelled.'
        );

        $order->update(['status' => OrderStatus::Cancelled]);
        OrderCancelled::dispatch($order);

        return $order;
    }

    // Delete — soft delete, no business rules needed
    public function delete(Order $order): void
    {
        $order->delete();
    }
}
```

---

## Service vs Action — When to Use Which

| Situation | Use |
|-----------|-----|
| Multiple related operations on one domain | Service |
| Single self-contained operation | Action |
| Logic reused across controllers, jobs, commands | Service |
| One-off feature that doesn't fit a service cleanly | Action |

```php
// Action — single operation, injected directly into controller method
class PlaceOrderAction
{
    public function execute(array $data, User $user): Order
    {
        $order = DB::transaction(function () use ($data, $user) {
            // ... same rules as service
            return $order;
        });

        OrderPlaced::dispatch($order->load('items'));
        return $order;
    }
}

// Controller — method injection, not constructor
public function store(CreateOrderRequest $request, PlaceOrderAction $action): RedirectResponse
{
    $order = $action->execute($request->validated(), $request->user());
    return redirect()->route('orders.show', $order)->with('success', 'Order placed.');
}
```

---

## Calling a Service from a Job

This is why services must be HTTP-agnostic — they work identically here:

```php
class ProcessOrderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    public function handle(OrderService $service): void
    {
        // Same service, same method — no changes needed
        $service->cancel($this->order);
    }
}
```

---

## Quick Reference

```
- All business logic lives in the service — no exceptions
- Multi-table writes → always DB::transaction()
- Fire events AFTER transaction, never inside
- Accept models not IDs — (Order $order) not (int $orderId)
- Accept $user as argument — never access $request inside service
- Throw \DomainException for expected failures
- Return the model — controller needs it
- Service is HTTP-agnostic — works from controller, job, command, test
```
