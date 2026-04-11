# Controller Reference

## Rules — What a Controller Does and Doesn't Do

| ✅ Controller owns | ❌ Never in a Controller |
|-------------------|------------------------|
| Receive HTTP request | Business logic |
| Call FormRequest for validation | DB queries |
| Call Service or Action | `if/else` decisions |
| Return View or RedirectResponse | Calculations |
| Handle service exceptions for HTTP | `$request->validate()` inline |

**One-liner:** receive → delegate → respond. Nothing else.

**The test:** could this method exist in an Artisan command? If no — it has HTTP logic in the wrong place.

---

## Constructor Injection — Always

```php
// ✅ Always inject services via constructor — Laravel resolves from container
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}
}

// ❌ Never instantiate manually
public function store(Request $request): RedirectResponse
{
    $service = new OrderService(); // wrong
}
```

---

## Route Model Binding — Always

```php
// ✅ Always use route model binding — Laravel resolves and 404s automatically
public function show(Order $order): View
{
    $order->load(['user', 'items.product']);
    return view('orders.show', compact('order'));
}

// ❌ Never manually find by ID
public function show(int $id): View
{
    $order = Order::find($id); // wrong — no auto 404, no type safety
}
```

---

## Full CRUD Pattern — Blade

```php
<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}

    // List — service returns paginated result, controller just passes to view
    public function index(): View
    {
        $orders = $this->orders->list();

        return view('orders.index', compact('orders'));
    }

    // Show — route model binding, load relations for the view
    public function show(Order $order): View
    {
        $order->load(['user', 'items.product']);

        return view('orders.show', compact('order'));
    }

    // Create — just return the view, no logic
    public function create(): View
    {
        return view('orders.create');
    }

    // Store — FormRequest validates, service handles all logic
    public function store(CreateOrderRequest $request): RedirectResponse
    {
        $order = $this->orders->create(
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Order placed successfully.');
    }

    // Edit — route model binding, return view
    public function edit(Order $order): View
    {
        return view('orders.edit', compact('order'));
    }

    // Update — FormRequest validates, service handles logic
    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $this->orders->update($order, $request->validated());

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Order updated.');
    }

    // Destroy — service handles soft delete + side effects
    public function destroy(Order $order): RedirectResponse
    {
        $this->orders->delete($order);

        return redirect()
            ->route('orders.index')
            ->with('success', 'Order deleted.');
    }
}
```

---

## Custom Action Routes (Non-CRUD)

```php
// routes/web.php
Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])
    ->middleware('permission:' . Permission::POSTS_EDIT) // replace with your actual permission
    ->name('orders.cancel');

// Controller methods
public function cancel(Order $order): RedirectResponse
{
    $this->orders->cancel($order);

    return redirect()
        ->route('orders.show', $order)
        ->with('success', 'Order cancelled.');
}
```

---

## Handling Service Exceptions

Services throw `\DomainException` for expected business failures.
Controllers catch them and convert to HTTP responses.

```php
public function store(CreateOrderRequest $request): RedirectResponse
{
    try {
        $order = $this->orders->create(
            $request->validated(),
            $request->user()
        );
    } catch (\DomainException $e) {
        // Expected business failure — show message to user
        return back()
            ->withErrors(['error' => $e->getMessage()])
            ->withInput();
    }

    return redirect()
        ->route('orders.show', $order)
        ->with('success', 'Order placed.');
}
```

---

## Admin Controller Pattern

Admin controllers live in `App\Http\Controllers\Admin\` and follow the same rules.
The `admin` middleware on the route group already handles role gating.
Per-action permissions are on individual routes.

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function index(): View
    {
        $users = $this->users->list();

        return view('admin.users.index', compact('users'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($user, $request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->users->delete($user);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted.');
    }
}
```

---

## Quick Reference

```
index()   → service->list() → view
show()    → load relations → view
create()  → return view
store()   → FormRequest → service → redirect with success
edit()    → return view
update()  → FormRequest → service → redirect with success
destroy() → service → redirect with success

Always:
- Constructor inject services
- Route model binding
- FormRequest for every write
- Catch \DomainException, return back()->withErrors()
- Pass $request->validated() to service, never $request->all()
- Pass $request->user() as argument to service, never access inside service
```
