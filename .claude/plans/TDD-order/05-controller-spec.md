# TDD-Order — Controller Spec

> Rules for every action in `App\Http\Controllers\OrderController`.
> No code blocks. Rules and constraints only.

---

## Class constraints

- `declare(strict_types=1)` at top
- Constructor: `private readonly OrderService $service`
- Every action calls `$this->authorize(...)` as first statement
- Never call service methods directly with raw `$request` data — always `$request->validated()`
- Catch `\DomainException` in pay, ship, deliver, update, cancel, destroy — redirect back with `withErrors(['error' => $e->getMessage()])`

---

## `index(Request $request): View`

- **Authorize:** `$this->authorize('viewAny', Order::class)`
- **Input:** `$request->only(['search', 'status'])`
- **Calls:** `$this->service->paginate($filters)`
- **Returns:** view `orders.index` with `['orders', 'statuses', 'filters']`
- `statuses` = `OrderStatus::cases()`

---

## `create(): View`

- **Authorize:** `$this->authorize('create', Order::class)`
- **Data loading:**
  - `Customer::byStatus(CustomerStatus::Active)->latest()->get(['id','name','email','phone'])`
  - `CustomerAddress::orderBy('label')->get([...])->groupBy('customer_id')`
- **Returns:** view `orders.create` with `['customers', 'sources', 'addresses']`
- `sources` = `OrderSource::cases()`
- `Customer::byStatus` scope and `CustomerStatus` enum are defined in the customer module — not re-declared here.

---

## `store(CreateOrderRequest $request): RedirectResponse`

- **Authorize:** `$this->authorize('create', Order::class)`
- **Calls:** `$this->service->create($request->validated(), $request->user())`
- **Success redirect:** `route('orders.show', $order)` with flash `'Order created.'`

---

## `show(Order $order): View`

- **Authorize:** `$this->authorize('view', $order)`
- **Eager load:** `$order->load(['customer', 'lines.serial.product', 'orderFees', 'payments', 'shipments'])`
- **Returns:** view `orders.show` with `compact('order')`

---

## `pay(RecordCashPaymentRequest $request, Order $order): RedirectResponse`

- **Authorize:** `$this->authorize('pay', $order)` *(RecordCashPaymentRequest::authorize() also checks — both run)*
- **Calls:** `$this->service->recordCashPayment($order, $request->validated(), $request->user())`
- **Exception handling:** catch `\DomainException` → `back()->withErrors(['error' => $e->getMessage()])`
- **Success redirect:** `route('orders.show', $order)` with flash `'Payment recorded.'`

---

## `ship(ShipOrderRequest $request, Order $order): RedirectResponse`

- **Authorize:** `$this->authorize('ship', $order)`
- **Calls:** `$this->service->ship($order, $request->validated(), $request->user())`
- **Exception handling:** catch `\DomainException` → `back()->withErrors(['error' => $e->getMessage()])`
- **Success redirect:** `route('orders.show', $order)` with flash `'Order shipped.'`

---

## `deliver(DeliverOrderRequest $request, Order $order): RedirectResponse`

- **Authorize:** `$this->authorize('deliver', $order)`
- **Calls:** `$this->service->markDelivered($order, $request->validated(), $request->user())`
- **Exception handling:** catch `\DomainException` → `back()->withErrors(['error' => $e->getMessage()])`
- **Success redirect:** `route('orders.show', $order)` with flash `'Delivery recorded.'`

---

## `edit(Order $order): View`

- **Authorize:** `$this->authorize('update', $order)`
- **Eager load:** `$order->load(['customer', 'lines.serial.product', 'orderFees'])`
- **Data loading:** `CustomerAddress::orderBy('label')->get([...])->groupBy('customer_id')`
- **Returns:** view `orders.edit` with `['order', 'sources', 'addresses']`
- `sources` = `OrderSource::cases()`

---

## `update(UpdateOrderRequest $request, Order $order): RedirectResponse`

- **Authorize:** `$this->authorize('update', $order)`
- **Calls:** `$this->service->update($order, $request->validated(), $request->user())`
- **Exception handling:** catch `\DomainException` → `back()->withErrors(['error' => $e->getMessage()])->withInput()`
- **Success redirect:** `route('orders.show', $order)` with flash `'Order updated.'`

---

## `cancel(Request $request, Order $order): RedirectResponse`

- **Authorize:** `$this->authorize('cancel', $order)`
- **Calls:** `$this->service->cancel($order, $request->user())`
- **Exception handling:** catch `\DomainException` → `back()->withErrors(['error' => $e->getMessage()])`
- **Success redirect:** `route('orders.show', $order)` with flash `'Order cancelled.'`

---

## `destroy(Order $order): RedirectResponse`

- **Authorize:** `$this->authorize('delete', $order)`
- **Calls:** `$this->service->delete($order)`
- **Exception handling:** catch `\DomainException` → `back()->withErrors(['error' => $e->getMessage()])`
- **Success redirect:** `route('orders.index')` with flash `'Order deleted.'`

---

## `taxPreview(Request $request): JsonResponse`

- **Authorize:** `$this->authorize('create', Order::class)`
- **Inline validation** (not FormRequest):
  - `lines` — array
  - `lines.*.serial_id` — nullable, integer
  - `lines.*.unit_price` — nullable, numeric, min:0
  - `shipping` — nullable, array
- **Calls:** `$this->service->taxPreview($data['lines'] ?? [], $data['shipping'] ?? [])`
- **Returns:** `response()->json($result)`

---

## Route definitions (add to existing prefix/group in web.php)

All routes live inside the admin group — nested under the `/admin` prefix with full middleware stack:

```
Route::prefix('admin')->middleware(['auth', 'load_perms', 'verified', 'active'])->group(function () {
    Route::prefix('orders')->name('orders.')->group(function () {
        // routes here
    });
});
```

Full URLs are therefore `/admin/orders`, `/admin/orders/create`, `/admin/orders/{order}`, etc.

| Method | URI | Controller action | Route name |
|---|---|---|---|
| GET | `/admin/orders` | `index` | `orders.index` |
| GET | `/admin/orders/create` | `create` | `orders.create` |
| POST | `/admin/orders` | `store` | `orders.store` |
| GET | `/admin/orders/{order}` | `show` | `orders.show` |
| POST | `/admin/orders/tax-preview` | `taxPreview` | `orders.taxPreview` |
| POST | `/admin/orders/{order}/pay` | `pay` | `orders.pay` |
| POST | `/admin/orders/{order}/ship` | `ship` | `orders.ship` |
| POST | `/admin/orders/{order}/deliver` | `deliver` | `orders.deliver` |
| GET | `/admin/orders/{order}/edit` | `edit` | `orders.edit` |
| PUT | `/admin/orders/{order}` | `update` | `orders.update` |
| POST | `/admin/orders/{order}/cancel` | `cancel` | `orders.cancel` |
| DELETE | `/admin/orders/{order}` | `destroy` | `orders.destroy` |

**Note:** `tax-preview` route must appear BEFORE `{order}` wildcard routes to avoid conflict.

---

## Prohibited patterns

- Never put SQL queries in controller actions — delegate to service
- Never call `$request->all()` — always `$request->validated()`
- Never suppress `\DomainException` — always redirect back with error
- Never redirect to index after update/cancel — always back to show
- Never redirect to show after delete — always to index
