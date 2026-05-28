# 11 — Controller (OrderController)

> **Layer 4 — Behavior.** Depends on `07-service.md`, `08-avatax.md`, `09-requests.md`, `10-routes.md`, `06-policy.md`, `15-tests.md`.

## Scope

`OrderController` — 12 action methods (7 RESTful + 2 custom actions + 3 helpers).

**Contract-only file** — method signatures + behavior tables. No method bodies; tests in `15-tests.md` drive the implementation.

---

## Decisions LOCKED

| Decision | Rationale |
|----------|-----------|
| Constructor injects `OrderService` + `AvaTaxService` | DI, testable |
| Every action calls `$this->authorize(...)` BEFORE service | Authorization first per `06-policy.md` |
| Service-throwing `DomainException` is caught → `back()->withErrors(['error' => ...])` | Friendly 422-style redirect (not 500) |
| Helper actions return `JsonResponse` | Frontend Alpine `fetch()` consumes JSON |
| All other actions return `View` or `RedirectResponse` | Server-rendered Blade UI |
| FormRequests (`StoreOrderRequest`, etc.) injected by type-hint — Laravel auto-validates | Standard Laravel pattern |
| Show / edit eager-load all relations needed by view in ONE query | Prevents N+1 |
| `edit` uses `view` policy (not `update`) so non-pending orders redirect to show with a flash error instead of 403 | Friendly UX |
| `index` action filters via query params (`?status=, ?source=, ?search=, ?from=, ?to=`) — passed to `service->paginate()` | Filter logic in service, not controller |
| Route model binding resolves `{order}`, `{customer}`, `{listing}` automatically | Type-hint = auto-bind |
| Helpers do NOT delegate to `OrderService` — they have their own logic (small, query-only) | Service is for order lifecycle, not lookups |

---

## File location

```
app/Http/Controllers/OrderController.php
```

---

## Constructor

```
__construct(
    private readonly OrderService $service,
    private readonly AvaTaxService $avatax,
)
```

---

## RESTful actions (7)

### `index(Request $request): View`

| Aspect | Value |
|--------|-------|
| Authorize | `viewAny` on `Order::class` |
| Inputs | Query params: `search`, `status`, `source`, `from`, `to` |
| Service call | `$this->service->paginate($request->only(['search','status','source','from','to']))` |
| View data | `orders` (paginator), `filters`, `statuses` (OrderStatus cases), `sources` (OrderSource cases) |
| Returns | `view('orders.index', [...])` |
| Tests covered | `admin_can_view_orders_index`, `sales_can_view_orders_index`, `user_without_orders_viewAny_cannot_view_index` |

---

### `create(): View`

| Aspect | Value |
|--------|-------|
| Authorize | `create` on `Order::class` |
| View data | `customers` (with `addresses` eager-loaded), `productListings` (active, with `product`), `sources`, `paymentMethods` |
| Returns | `view('orders.create', [...])` |
| Tests covered | `admin_can_view_create_form`, `user_without_orders_create_cannot_view_create_form` |

---

### `store(StoreOrderRequest $request): RedirectResponse`

| Aspect | Value |
|--------|-------|
| Authorize | Handled by `StoreOrderRequest::authorize()` (delegates to `OrderPolicy::create`) |
| Service call | `$order = $this->service->store($request->validated(), $request->user())` |
| Exception handling | `\DomainException` → `back()->withErrors(['error' => $e->getMessage()])->withInput()` |
| Redirects | `route('orders.show', $order)` with success flash |
| Tests covered | `admin_can_create_walk_in_cash_order_with_per_line_fees`, all `store_fails_validation_*`, `user_without_orders_create_cannot_post_store` |
| ex-19 ref | lines 20-32 (admin creates order at counter) |

---

### `show(Order $order): View`

| Aspect | Value |
|--------|-------|
| Authorize | `view` on `$order` |
| Eager loads | `customer`, `lines.productListing.product`, `lines.inventorySerial`, `lines.lineFees`, `events.createdBy`, `payments.createdBy`, `createdBy` |
| View data | `order` (loaded) |
| Returns | `view('orders.show', compact('order'))` |
| Tests covered | `admin_can_view_order_show`, `user_without_orders_view_cannot_view_show` |

---

### `edit(Order $order): View|RedirectResponse`

| Aspect | Value |
|--------|-------|
| Authorize | `view` on `$order` (NOT `update` — see decision above) |
| Guard | If `$order->status !== Pending` → `redirect()->route('orders.show', $order)->withErrors(['error' => 'Only pending orders can be edited.'])` |
| Eager loads | `customer.addresses`, `lines.productListing.product`, `lines.lineFees`, `payments` (needed for Alpine hydration of current payment_method) |
| View data | `order`, `customers` (with `addresses`), `productListings` (active), `sources`, `paymentMethods` |
| Returns | `view('orders.edit', [...])` |
| Tests covered | `admin_can_edit_pending_order`, `edit_redirects_to_show_when_order_not_pending`, `user_without_orders_update_cannot_view_edit` |

---

### `update(UpdateOrderRequest $request, Order $order): RedirectResponse`

| Aspect | Value |
|--------|-------|
| Authorize | Handled by `UpdateOrderRequest::authorize()` (delegates to `OrderPolicy::update`) |
| Service call | `$this->service->update($order, $request->validated())` |
| Exception handling | `\DomainException` → `back()->withErrors(['error' => $e->getMessage()])->withInput()` |
| Redirects | `route('orders.show', $order)` with success flash |
| Tests covered | `admin_can_update_pending_order`, `update_fails_when_order_not_pending`, `user_without_orders_update_cannot_post_update` |

---

### `destroy(Order $order): RedirectResponse`

| Aspect | Value |
|--------|-------|
| Authorize | `delete` on `$order` |
| Service call | `$this->service->delete($order)` |
| Exception handling | `\DomainException` → `back()->withErrors(['error' => $e->getMessage()])` |
| Redirects | `route('orders.index')` with success flash |
| Tests covered | `admin_can_hard_delete_pending_order`, `destroy_fails_when_order_not_pending`, `sales_cannot_destroy_order`, `user_without_orders_delete_cannot_destroy` |

---

## Custom action methods (2)

### `recordCashPayment(RecordCashPaymentRequest $request, Order $order): RedirectResponse`

| Aspect | Value |
|--------|-------|
| Authorize | Handled by `RecordCashPaymentRequest::authorize()` (delegates to `OrderPolicy::recordCashPayment`) |
| Service call | `$this->service->recordCashPayment($order, $request->validated(), $request->user())` |
| Exception handling | `\DomainException` → `back()->withErrors(['error' => $e->getMessage()])->withInput()` |
| Redirects | `route('orders.show', $order)` with success flash |
| Tests covered | `admin_can_record_cash_payment`, `record_cash_payment_fails_when_order_already_paid`, `record_cash_payment_fails_when_amount_does_not_match_grand_total`, `user_without_orders_recordPayment_cannot_record_payment` |
| ex-19 ref | line 34 (Rachel pays $286.86 cash) |

---

### `complete(Request $request, Order $order): RedirectResponse`

| Aspect | Value |
|--------|-------|
| Authorize | `complete` on `$order` (via `$this->authorize`) |
| Service call | `$this->service->complete($order, $request->user())` |
| Exception handling | `\DomainException` → `back()->withErrors(['error' => $e->getMessage()])` |
| Redirects | `route('orders.show', $order)` with success flash |
| Tests covered | `admin_can_complete_order`, `complete_fails_when_order_not_processing`, `user_without_orders_complete_cannot_complete` |
| ex-19 ref | line 46-51 (staff hands unit to Rachel) |

---

## Helper methods (3, JSON responses)

### `customerAddresses(Customer $customer): JsonResponse`

| Aspect | Value |
|--------|-------|
| Authorize | `viewAny` on `Order::class` **AND** `view` on the resolved `$customer` (defense in depth — prevents PII enumeration across customers) |
| Loads | `$customer->load('addresses')` — eager loaded to avoid lazy-load violation |
| Returns | JSON array: `[{id, label, summary, is_default}]` where `summary = "{first_name} {last_name}, {address_line1}, {city}"` |
| Used by | Create/edit form when customer is changed |

---

### `listingStock(ProductListing $listing): JsonResponse`

| Aspect | Value |
|--------|-------|
| Authorize | `viewAny` on `Order::class` |
| Query | In-stock `inventory_serials` for `$listing->product`, grouped by `inventory_location`, excluding serials already allocated to other `order_lines` |
| Returns | JSON: `{sku: "ECM-2024", stock: [{location: "Warehouse A", qty: 5}, ...]}` |
| Used by | Create/edit form when product is changed in a line |

---

### `calculateTax(CalculateTaxRequest $request): JsonResponse`

| Aspect | Value |
|--------|-------|
| Authorize | `viewAny` on `Order::class` |
| Inputs | JSON body: `customer_id`, `shipping_address` (or null for pickup), `lines[]` (with `unit_price`, `sku`, `fees[]`) |
| Logic | Per `08-avatax.md`: short-circuit if customer `tax_exempt`, resolve shipTo, flatten lines+fees → AvaTax → map response back |
| Returns | JSON: `{lines: [{tax_amount, fees: [{tax_amount}, ...]}, ...]}` |
| Used by | Create/edit form's `fetchAllLineTax()` (debounced 400ms) |
| ex-19 ref | line 28-30 (AvaTax computes tax for unit + Programming Fee + Gas Tuning Fee) |

---

### `storeCustomerAddress(StoreCustomerAddressRequest $request): JsonResponse`

| Aspect | Value |
|--------|-------|
| Route | `POST /admin/orders/customer-addresses` → name `orders.customer-addresses.store` |
| Authorize | **`create` on `CustomerAddress::class` for the resolved `$customer`** (per `CustomerAddressPolicy::create`). Both `orders.create` AND `customers.addresses.create` permission required. |
| Inputs | JSON body: `customer_id` + all CustomerAddress fields (label, first_name, last_name, address_line1, address_line2, city, state, postal_code, country, phone). `customer_id` is validated `exists:customers,id`. |
| FormRequest | `StoreCustomerAddressFromOrderRequest` — `authorize()` calls `$this->user()->can('create', [CustomerAddress::class, Customer::find($this->input('customer_id'))])` |
| Logic | Delegate to existing `CustomerAddressService::store($customer, $request->validated())` (no duplication) |
| Returns | `201` with JSON: `{id, label, summary, address_line1, city, state, postal_code, country, is_default}` |
| Used by | Create-order form's "+ New address" modal (per `12-views.md`) |

---

## Method signatures (overview — for the implementation file)

```
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $service, private readonly AvaTaxService $avatax) {}

    // Helpers
    public function customerAddresses(Customer $customer): JsonResponse
    public function listingStock(ProductListing $listing): JsonResponse
    public function calculateTax(Request $request): JsonResponse

    // RESTful
    public function index(Request $request): View
    public function create(): View
    public function store(StoreOrderRequest $request): RedirectResponse
    public function show(Order $order): View
    public function edit(Order $order): View|RedirectResponse
    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    public function destroy(Order $order): RedirectResponse

    // Custom actions
    public function recordCashPayment(RecordCashPaymentRequest $request, Order $order): RedirectResponse
    public function complete(Request $request, Order $order): RedirectResponse
}
```

> **Body left to implementation.** Tests drive the actual code.

---

## Dependencies

**Depends on:**
- `06-policy.md` — `OrderPolicy` for `authorize` calls
- `07-service.md` — `OrderService` injected, all public methods called
- `08-avatax.md` — `calculateTax` action implements the helper flow
- `09-requests.md` — 3 FormRequest classes type-hinted
- `10-routes.md` — routes map to action methods
- Existing: `AvaTaxService`, `Customer`, `ProductListing`, `InventorySerial`, `InventoryLocation`

**Depended on by:**
- `12-views.md` — views read `$order, $customers, $productListings` etc. from controller
- `15-tests.md` — every feature test exercises a controller action

---

## Validation gates

- [ ] Every route in `10-routes.md` has a matching controller method
- [ ] Every action calls `$this->authorize(...)` or has it handled by FormRequest
- [ ] All RESTful methods follow Laravel naming (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`)
- [ ] All `RedirectResponse` actions handle `\DomainException` from service
- [ ] All helper methods return `JsonResponse`
- [ ] `show` and `edit` eager-load all relations needed by view (no N+1)
- [ ] `edit` uses `view` policy (allows non-pending redirect)
- [ ] `update`/`destroy`/`recordCashPayment`/`complete` actions delegate to service for status guards
- [ ] No business logic in controller — all flows through service
- [ ] Constructor uses `private readonly` DI
- [ ] `declare(strict_types=1);` at top

---

## Cross-check vs Layer 1 + 2 + 3 + 4

| Source | Controller asserts |
|--------|--------------------|
| `02-permissions.md` 7 permissions | 7 RESTful + 2 custom actions, each authorized |
| `06-policy.md` 7 policy methods | Each action calls or relies on a policy method |
| `07-service.md` 5 public service methods | Each is called by exactly one controller action |
| `08-avatax.md` calculateTax helper | `OrderController::calculateTax` action implements it |
| `09-requests.md` 3 FormRequest classes | Each type-hinted in matching action signature |
| `10-routes.md` 12 routes | Each maps to a controller method |
| `15-tests.md` 25 feature tests | Each exercises an action method |

No gaps. Every action exists for a real route + real test + real service call.
