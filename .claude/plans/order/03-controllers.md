# Order Module — Routes, Permissions, Policy, Controllers, Views, Seeder

> **READ [`00-rules.md`](00-rules.md) FIRST.** Column names from [`01-schema.md`](01-schema.md). Service signatures from [`02-services.md`](02-services.md).
> No code blocks for new content — implementation comes from spec + pattern files. View fields come from column-source-of-truth maps below.

---

## ASK Triggers (specific to this file)

| # | Trigger | Question |
|---|---------|----------|
| 1 | About to add a route not in §1 Routes table | "Add route `X`? What is its HTTP verb, URL, controller method, name?" |
| 2 | About to render a column in a view that is not in the View Column-Map | "Field `X` not in column-map for `Y.blade.php` — which DB column is its source?" |
| 3 | About to use `@json($x)` inside `x-data="..."` | "Per `00-rules.md` §3 this is forbidden. Use `window.__X = @json($x)` script block before `x-data`. Confirm." |
| 4 | About to add a permission key not in §2 Permission Matrix | "Permission `X` not in matrix — add to matrix and to seeder?" |
| 5 | About to invoke a service method not in [`02-services.md`](02-services.md) §D | "Method `OrderService::X` not in 02-services.md — add to service spec first?" |

---

## Section 1 — Routes

> Add inside the admin `auth` middleware group in `routes/web.php`.
> The order module uses `Route::prefix('orders')->name('orders.')->group(...)` with **explicit** routes (not `Route::resource`).

| Verb | URL | Controller method | Route name | FormRequest |
|------|-----|-------------------|------------|-------------|
| GET  | `/orders`              | `index`   | `orders.index`   | — |
| GET  | `/orders/create`       | `create`  | `orders.create`  | — |
| POST | `/orders`              | `store`   | `orders.store`   | `CreateOrderRequest` |
| GET  | `/orders/{order}`      | `show`    | `orders.show`    | — |
| GET  | `/orders/{order}/edit` | `edit`    | `orders.edit`    | — (see [`05-update-cancel-delete.md`](05-update-cancel-delete.md)) |
| PUT  | `/orders/{order}`      | `update`  | `orders.update`  | `UpdateOrderRequest` |
| DELETE | `/orders/{order}`    | `destroy` | `orders.destroy` | — |
| POST | `/orders/{order}/pay`     | `pay`     | `orders.pay`     | `RecordCashPaymentRequest` |
| POST | `/orders/{order}/ship`    | `ship`    | `orders.ship`    | `ShipOrderRequest` |
| POST | `/orders/{order}/deliver` | `deliver` | `orders.deliver` | `DeliverOrderRequest` |
| POST | `/orders/{order}/cancel`  | `cancel`  | `orders.cancel`  | — |
| POST | `/orders/tax-preview`     | `taxPreview` | `orders.tax-preview` | (AJAX — uses inline validation in controller) |

> **Do NOT switch to `Route::resource()`** — domain actions (`pay`, `ship`, `deliver`, `cancel`, `tax-preview`) cannot be expressed by resource(), and the existing group uses explicit routes.

**Reference:** [`skills/references/controller.md`](../../skills/references/controller.md#route-model-binding--always) · [`skills/references/controller.md`](../../skills/references/controller.md#custom-action-routes-non-crud).

---

## Section 2 — Permission Matrix

| Permission | super_admin | admin | manager | sales |
|------------|:----:|:----:|:----:|:----:|
| `orders.viewAny` | ✅ | ✅ | ✅ | ✅ |
| `orders.view`    | ✅ | ✅ | ✅ | ✅ |
| `orders.create`  | ✅ | ✅ | ✅ | ❌ |
| `orders.update`  | ✅ | ✅ | ✅ | ❌ |
| `orders.cancel`  | ✅ | ✅ | ✅ | ❌ |
| `orders.delete`  | ✅ | ❌ | ❌ | ❌ |
| `orders.pay`     | ✅ | ✅ | ✅ | ❌ |
| `orders.ship`    | ✅ | ✅ | ✅ | ❌ |
| `orders.deliver` | ✅ | ✅ | ✅ | ❌ |

> The "staff" role does not exist in seeded data — use `sales` for forbidden-actor tests (memory: `project_roles.md`).

---

## Section 3 — OrderPermissionSeeder

**File:** `database/seeders/OrderPermissionSeeder.php`

### Behaviour spec

| Step |
|------|
| 1. Forget cached permissions: `app()[PermissionRegistrar::class]->forgetCachedPermissions()` |
| 2. For each permission key in §2 matrix: `Permission::firstOrCreate(['name' => $key, 'guard_name' => 'web'])` |
| 3. Build three permission sets: `$all` (all 9 keys), `$adminPerms` (all except `orders.delete`), `$viewOnly` (`orders.viewAny`, `orders.view`) |
| 4. `Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])->givePermissionTo($all)` |
| 5. `Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])->givePermissionTo($adminPerms)` ← **must exclude `orders.delete`** |
| 6. `Role::where('name', 'manager')->first()?->givePermissionTo($adminPerms)` |
| 7. `Role::where('name', 'sales')->first()?->givePermissionTo($viewOnly)` |

> Use `where()->first()?->` (null-safe) for `manager` and `sales` because those roles are created by a separate `RoleSeeder` — do not `firstOrCreate` them here (would race the RoleSeeder).

### Field map (key → role grant)

| Permission key | Granted to |
|----------------|------------|
| `orders.viewAny` | super_admin, admin, manager, sales |
| `orders.view`    | super_admin, admin, manager, sales |
| `orders.create`  | super_admin, admin, manager |
| `orders.update`  | super_admin, admin, manager |
| `orders.cancel`  | super_admin, admin, manager |
| `orders.delete`  | super_admin **only** |
| `orders.pay`     | super_admin, admin, manager |
| `orders.ship`    | super_admin, admin, manager |
| `orders.deliver` | super_admin, admin, manager |

**Reference:** [`skills/references/permissions-spatie.md`](../../skills/references/permissions-spatie.md).

---

## Section 4 — OrderPolicy

**File:** `app/Policies/OrderPolicy.php`
Register in `app/Providers/AuthServiceProvider.php`: `Order::class => OrderPolicy::class`.

### Method spec

| Method | Signature | Check |
|--------|-----------|-------|
| `viewAny` | `(User $user): bool` | `$user->can('orders.viewAny')` |
| `view`    | `(User $user, Order $order): bool` | `$user->can('orders.view')` |
| `create`  | `(User $user): bool` | `$user->can('orders.create')` |
| `update`  | `(User $user, Order $order): bool` | `$user->can('orders.update')` |
| `cancel`  | `(User $user, Order $order): bool` | `$user->can('orders.cancel')` |
| `delete`  | `(User $user, Order $order): bool` | `$user->can('orders.delete')` |
| `pay`     | `(User $user, Order $order): bool` | `$user->can('orders.pay')` |
| `ship`    | `(User $user, Order $order): bool` | `$user->can('orders.ship')` |
| `deliver` | `(User $user, Order $order): bool` | `$user->can('orders.deliver')` |

> Policy methods are pure permission checks. **Status guards do NOT live in the policy** — they live in the service inside the transaction (TOCTOU rule, per `00-rules.md` §4).

**Reference:** [`skills/references/permissions-spatie.md`](../../skills/references/permissions-spatie.md).

---

## Section 5 — OrderController

**File:** `app/Http/Controllers/OrderController.php`
**Constructor:** `__construct(private readonly OrderService $service) {}` — single dependency, constructor-injected.

### Method spec table

| Method | HTTP | URL | Authorize | FormRequest | Service call | Returns |
|--------|------|-----|-----------|-------------|--------------|---------|
| `index(Request)` | GET | `/orders` | `viewAny`, `Order::class` | — | `service->paginate($request->only(['search','status']))` | `view('orders.index', [orders, statuses, filters])` |
| `create()` | GET | `/orders/create` | `create`, `Order::class` | — | — | `view('orders.create', [customers, addresses, sources])` |
| `store(CreateOrderRequest)` | POST | `/orders` | `create`, `Order::class` | `CreateOrderRequest` | `service->create($request->validated(), $request->user())` | redirect `orders.show` + flash `success` |
| `show(Order)` | GET | `/orders/{order}` | `view`, `$order` | — | — (controller eager-loads `customer`, `lines.serial.product`, `orderFees`, `payments`, `shipments`) | `view('orders.show', compact('order'))` |
| `edit(Order)` | GET | `/orders/{order}/edit` | `update`, `$order` | — | — (controller eager-loads `customer`, `lines.serial.product`, `orderFees`) | `view('orders.edit', [order, sources, addresses])` |
| `update(UpdateOrderRequest, Order)` | PUT | `/orders/{order}` | `update`, `$order` | `UpdateOrderRequest` | `service->update($order, $req->validated(), $req->user())` — wrap in try/catch for `DomainException` | redirect `orders.show` + flash `success` (or back() with error) |
| `destroy(Order)` | DELETE | `/orders/{order}` | `delete`, `$order` | — | `service->delete($order)` — try/catch `DomainException` | redirect `orders.index` + flash `success` |
| `cancel(Request, Order)` | POST | `/orders/{order}/cancel` | `cancel`, `$order` | — | `service->cancel($order, $request->user())` — try/catch `DomainException` | redirect `orders.show` + flash `success` |
| `pay(RecordCashPaymentRequest, Order)` | POST | `/orders/{order}/pay` | `pay`, `$order` | `RecordCashPaymentRequest` | `service->recordCashPayment($order, $req->validated(), $req->user())` | redirect `orders.show` + flash `success` |
| `ship(ShipOrderRequest, Order)` | POST | `/orders/{order}/ship` | `ship`, `$order` | `ShipOrderRequest` | `service->ship($order, $req->validated(), $req->user())` | redirect `orders.show` + flash `success` |
| `deliver(DeliverOrderRequest, Order)` | POST | `/orders/{order}/deliver` | `deliver`, `$order` | `DeliverOrderRequest` | `service->markDelivered($order, $req->validated(), $req->user())` | redirect `orders.show` + flash `success` |
| `taxPreview(Request)` | POST | `/orders/tax-preview` | `create`, `Order::class` | inline validation | `service->taxPreview($request->all())` | `JsonResponse` |

> Every controller method calls `$this->authorize(...)` even when the FormRequest already authorizes — defense in depth.
> Every domain-action method (`update`, `destroy`, `cancel`) catches `\DomainException` and returns `back()->withErrors(['error' => $e->getMessage()])`.

### Data the controller passes to views

| View | Variables | Source |
|------|-----------|--------|
| `orders.index` | `$orders` (paginator), `$statuses` (`OrderStatus::cases()`), `$filters` (search+status) | — |
| `orders.create` | `$customers` (`Customer::byStatus(Active)->latest()->get(['id','name','email','phone'])`), `$addresses` (`CustomerAddress::orderBy('label')->get([...]) ->groupBy('customer_id')`), `$sources` (`OrderSource::cases()`) | — |
| `orders.show` | `$order` (eager-loaded `customer`, `lines.serial.product`, `orderFees`, `payments`, `shipments`) | — |
| `orders.edit` | `$order`, `$sources`, `$addresses` (same shape as create) | — |

> Listings/locations/serials are **NOT** preloaded — fetched via AJAX on demand from the create form.

**Reference:** [`skills/references/controller.md`](../../skills/references/controller.md#full-crud-pattern--blade) · [`skills/references/controller.md`](../../skills/references/controller.md#handling-service-exceptions) · [`skills/references/controller.md`](../../skills/references/controller.md#admin-controller-pattern).

---

## Section 6 — Views

> All views extend `x-layouts.admin`. Folder: `resources/views/orders/`.
> Pattern: [`skills/references/admin-views.md`](../../skills/references/admin-views.md).
> **Per [`00-rules.md`](00-rules.md) §3:** never `@json` in `x-data`; pass server data via `window.__var` script block before Alpine reads it.

---

### 6A — `orders/index.blade.php`

#### Column-source-of-truth map (column → DB)

| Display column | DB column |
|----------------|-----------|
| Number | `orders.number` |
| Customer | `orders.customer.name` (eager-loaded) |
| Source | `orders.source` (rendered via `->label()`) |
| Status | `orders.status` (badge by enum case) |
| Payment | `orders.payment_status` (badge) |
| Total | `orders.grand_total` (formatted currency) |
| Created | `orders.created_at` (formatted date) |

#### UI elements

| Element | Spec |
|---------|------|
| Heading | "Orders" |
| Action button (top-right) | "New Order" — guarded by `@can('create', App\Models\Order::class)`, links to `route('orders.create')` |
| Filter bar | Search text input (name: `search`), status `<select>` (options from `$statuses`) |
| Table | Columns above, rows from `$orders` |
| Pagination | `{{ $orders->links() }}` |

**Reference:** [`skills/references/admin-views.md`](../../skills/references/admin-views.md#index-page--table-with-actions).

---

### 6B — `orders/create.blade.php`

#### Layout (2-column WooCommerce style)

```
LEFT  (lg:col-span-2)            RIGHT sidebar (lg:col-span-1)
─────────────────────────────    ──────────────────────────────
Line Items table                 Customer (Tom Select + info)
+ Add Line                       Source <select>
                                 ───────────────────────────────
Additional Fees                  Billing Address (tabs)
+ Add Fee                        ───────────────────────────────
                                 Shipping Address (tabs)
Shipping                         ───────────────────────────────
[shipping_amount input]          Order Total
                                  Subtotal (incl. tax)
                                  Fees
                                  Shipping
                                  Grand Total
                                 ───────────────────────────────
                                 [Create Order]
```

> The visual is a sketch only — Tailwind grid classes come from the admin layout pattern. Do not hardcode column widths from this sketch.

#### Server data passed via `window.__var` (before any `x-data`)

| Window global | Source | Purpose |
|---------------|--------|---------|
| `window.__orderCustomers` | `@json($customers)` | Tom Select options + info card |
| `window.__orderAddresses` | `@json($addresses)` | Saved-address cards by customer |

#### Form-input → DB column map

| Form input name | DB column (via service) | Notes |
|-----------------|-------------------------|-------|
| `customer_id` | `orders.customer_id` | hidden, set by Tom Select |
| `source` | `orders.source` | `<select>` |
| `shipping_amount` | `orders.shipping` | renamed at service |
| `lines[n][serial_id]` | `order_lines.inventory_serial_id` | hidden, set by 3-step AJAX cascade |
| `lines[n][unit_price]` | `order_lines.unit_price` | editable number |
| `lines[n][tax_rate]` | `order_lines.tax_rate` | **read-only** (populated by AvaTax preview) |
| `fees[n][name]` | `order_fees.name` | text |
| `fees[n][amount]` | `order_fees.amount` | number |
| `billing_same_as_shipping` | (control flag) | hidden, value driven by `billingType === 'same'` |
| `billing[address_id]` | (lookup) | hidden, set by saved-address click |
| `billing[first_name … country]` | `orders.billing_*` (10 cols) | inputs visible only when `billingType === 'new'` |
| `shipping[address_id]` | (lookup) | hidden, set by saved-address click |
| `shipping[first_name … country]` | `orders.shipping_*` (10 cols) | inputs visible only when `shippingType === 'new'` |

#### Alpine state (inline `x-data`)

| Key | Type | Initial value | Notes |
|-----|------|---------------|-------|
| `customer` | object\|null | null | populated by Tom Select onChange |
| `customers` | array | `window.__orderCustomers` | preloaded |
| `addresses` | object | `window.__orderAddresses` | grouped by customer_id |
| `source` | string | `'walk_in'` | default for cash |
| `lines` | array | `[]` | each: `{listingId, locationId, serialId, sku, name, unitPrice, taxRate, taxAmount, availableLocations, availableSerials}` |
| `fees` | array | `[]` | each: `{name, amount}` |
| `shippingAmount` | number | `0` | |
| `billingType` | string | `'none'` | **default `'none'` for cash** (per 00-rules §7.3) — one of `none` / `saved` / `new` |
| `shippingType` | string | `'same'` | one of `same` / `saved` / `new` / `none` |
| `selectedBillingId` | int\|null | null | |
| `selectedShippingId` | int\|null | null | |
| `subtotal` (getter) | number | sum of `lines[].unitPrice + taxAmount` | tax already inside |
| `feesTotal` (getter) | number | sum of `fees[].amount` | |
| `grandTotal` (getter) | number | `subtotal + feesTotal + shippingAmount` | no separate tax |
| `resetAddresses()` | method | clears `selectedBillingId` + `selectedShippingId` | called on customer change |

#### AJAX cascade (line items)

| Step | Trigger | Endpoint | Updates |
|------|---------|----------|---------|
| 1. Pick listing | Tom Select `onItemAdd` | `GET /admin/product-listings/{listing}/locations` | `line.availableLocations` |
| 2. Pick location | `<select>` change | `GET /admin/inventory-locations/{location}/serials?listing={listing}` | `line.availableSerials` |
| 3. Pick serial | `<select>` change | — | sets `line.serialId`, `line.sku`, `line.name` |
| 4. Tax preview | line array change (debounce 300ms) | `POST /orders/tax-preview` (Section 5 controller) | sets `line.taxRate`, `line.taxAmount` per line |

#### Billing tabs (cash default = `'none'`)

| Tab value | Fields shown | What writes to DB |
|-----------|--------------|-------------------|
| `none` | (nothing) | all `orders.billing_*` columns stay NULL |
| `saved` | grid of address cards from `addresses[customer.id]` | `billing[address_id]` → service resolves to snapshot |
| `new` | 10 inputs for `billing[first_name…country]` | service creates `customer_addresses` row + snapshot |

#### Shipping tabs

| Tab value | Fields shown | What writes to DB |
|-----------|--------------|-------------------|
| `same` | (nothing, copies billing) | `billing_same_as_shipping=1`; service copies billing snapshot to shipping columns |
| `saved` | grid of address cards | `shipping[address_id]` → service resolves to snapshot |
| `new` | 10 inputs for `shipping[first_name…country]` | service creates `customer_addresses` row + snapshot |
| `none` | (nothing) | all `orders.shipping_*` columns stay NULL — walk-in pickup |

**Reference:** [`skills/references/admin-views.md`](../../skills/references/admin-views.md#create--edit-form-page) · [`../admin-search/01-plan.md`](../admin-search/01-plan.md) (Tom Select patterns) · feedback `feedback_alpine_inline_xdata.md`.

---

### 6C — `orders/show.blade.php`

#### Header card

| Field | DB column |
|-------|-----------|
| Order number | `orders.number` |
| Status badge | `orders.status` (via enum `->label()`) |
| Payment status badge | `orders.payment_status` |
| Source label | `orders.source` (via enum `->label()`) |
| Created at | `orders.created_at` |

#### Header action buttons (guarded by `@can` + status)

| Button | Condition | Action |
|--------|-----------|--------|
| Edit Order | `@can('update', $order)` + `status === Pending` | link to `route('orders.edit', $order)` |
| Cancel Order | `@can('cancel', $order)` + `status in [Pending, Processing]` | POST `route('orders.cancel', $order)` — Alpine confirm |
| Delete Order | `@can('delete', $order)` + `status === Cancelled` | DELETE `route('orders.destroy', $order)` — Alpine confirm |
| Record Payment (inline form) | `@can('pay', $order)` + `payment_status === unpaid` | POST `route('orders.pay', $order)` |
| Ship (inline form) | `@can('ship', $order)` + `status === Processing` | POST `route('orders.ship', $order)` |
| Mark Delivered (inline form) | `@can('deliver', $order)` + `status === Shipped` + `delivered_at is null` | POST `route('orders.deliver', $order)` |

#### Customer card

| Field | DB column / relation |
|-------|----------------------|
| Name | `$order->customer->name` |
| Email | `$order->customer->email` |
| Phone | `$order->customer->phone` |
| Profile link | `route('customers.show', $order->customer)` |

#### Order lines table

| Column heading | DB column |
|----------------|-----------|
| SKU | `order_lines.sku` |
| Product | `order_lines.product_name` |
| Serial # | `order_lines.serial->serial_number` (via relation) |
| Unit Price | `order_lines.unit_price` |
| Tax Rate | `order_lines.tax_rate` (display as `% `) |
| Tax | `order_lines.tax_amount` |
| Line Total | `order_lines.line_total` |

#### Fees table

| Column | DB column |
|--------|-----------|
| Name | `order_fees.name` |
| Amount | `order_fees.amount` |

#### Totals (right-aligned card)

> **CRITICAL** — see [`00-rules.md`](00-rules.md) §7.2. Use the exact labels and columns below. Tax is already inside subtotal.

| Label | DB column |
|-------|-----------|
| `Subtotal (incl. tax)` | `orders.subtotal` |
| `Fees` | `orders.fees` |
| `Shipping` | `orders.shipping` |
| `Grand Total` | `orders.grand_total` |

> Do **NOT** display a separate `Tax` row in the totals card. Tax is per-line, shown in the line items table only.
> Do **NOT** reference `tax_total`, `tax_amount`, `core_charges`, `fees_total`, or `shipping_amount` — none of these are columns on `orders`.

#### Shipping address card

| Field | DB column |
|-------|-----------|
| Name | `orders.shipping_first_name` + `shipping_last_name` |
| Address line 1 | `orders.shipping_address_line1` |
| Address line 2 | `orders.shipping_address_line2` |
| City, State Postal | `orders.shipping_city`, `shipping_state`, `shipping_postal_code` |
| Country | `orders.shipping_country` |
| Email | `orders.shipping_email` |
| Phone | `orders.shipping_phone` |

Hide the whole card if `shipping_address_line1` is null (walk-in pickup).

#### Billing address card

Same fields as shipping but `billing_*` columns. Hide if `billing_address_line1` is null (cash orders).

#### Payments section

| Column | DB |
|--------|----|
| Method | `payments.method` (via enum `->label()`) |
| Amount | `payments.amount` |
| Status | `payments.status` |
| Received at | `payments.cash_received_at` or `paid_at` |

#### Shipments section

| Column | DB |
|--------|----|
| Carrier | `shipments.carrier` |
| Tracking | `shipments.tracking` |
| Status | `shipments.status` |
| Shipped at | `shipments.shipped_at` |
| Delivered at | `shipments.delivered_at` |

**Reference:** [`skills/references/admin-views.md`](../../skills/references/admin-views.md#page-structure--every-admin-page).

---

### 6D — `orders/edit.blade.php`
See [`05-update-cancel-delete.md`](05-update-cancel-delete.md) §7A.

---

## Section 7 — Navigation

**File:** `resources/views/layouts/navigation.blade.php`
Add the Orders link **after** the Customers nav-link block and **before** the Catalog dropdown.

| Variant | Component | Guard |
|---------|-----------|-------|
| Desktop | `<x-nav-link :href="route('orders.index')" :active="request()->routeIs('orders.*')">` | wrapped in `@can('orders.viewAny')` |
| Mobile | `<x-responsive-nav-link :href="..." :active="...">` | same `@can` guard |

Label: `__('Orders')`.

---

## Section 8 — OrderSeeder (dev/demo)

**File:** `database/seeders/OrderSeeder.php`
**Depends on:** `InventorySerialSeeder` (needs in-stock serials), `OrderPermissionSeeder` (already runs).
**Register in:** `DatabaseSeeder.php` **after** `InventorySerialSeeder::class`.

### Behaviour

| Step |
|------|
| 1. Resolve `$admin` (`User` with role `admin`), `$customer` (random), `$serials` (5 random in-stock) |
| 2. Warn + return if any are missing |
| 3. `DB::table('sequences')->insertOrIgnore(['name' => 'orders', 'value' => 0])` |
| 4. Create demo orders in 3 states using `OrderService` (DI from container): one Pending+unpaid, one Processing+paid (cash), one Shipped (paid + shipped) |
| 5. Print info: `'OrderSeeder: Created N demo orders.'` |

### Demo order payloads (parameters per call — column-source-of-truth aligned)

| Order | source | shipping_amount | lines (1 each) | fees | address |
|-------|--------|-----------------|-----------------|------|---------|
| Pending+unpaid | `walk_in` | 15.00 | serial[0], 199.99 unit_price, 0.08 tax_rate | `Service Fee` $25.00 | empty (walk-in) |
| Processing+paid | `phone` | 12.00 | serial[1], 349.00 unit_price, 0.00 tax_rate | none | empty |
| Shipped | `online` | 9.99 | serial[2], 275.00 unit_price, 0.00 tax_rate | none | inline address (Demo User, Austin TX) |

After each non-pending order: call `recordCashPayment`. For shipped order: also call `ship` with FedEx + random tracking.

**Reference:** existing seeders in `database/seeders/` for structure.

---

**Reference:** [`skills/references/controller.md`](../../skills/references/controller.md) · [`skills/references/admin-views.md`](../../skills/references/admin-views.md) · [`skills/references/permissions-spatie.md`](../../skills/references/permissions-spatie.md).
