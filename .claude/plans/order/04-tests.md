# Order Module — Tests

> **READ [`00-rules.md`](00-rules.md) FIRST.** Test assertions reference columns from [`01-schema.md`](01-schema.md).
> Test setup uses behaviours from [`02-services.md`](02-services.md). Test HTTP from [`03-controllers.md`](03-controllers.md).

---

## ASK Triggers (specific to tests)

| # | Trigger | Question |
|---|---------|----------|
| 1 | Test asserts on a column not in [`01-schema.md`](01-schema.md) | "Assertion on `X` — typo or new column? Add to schema first." |
| 2 | Test uses a factory state not in §C Factory State table | "Factory state `X()` not defined — add to factory spec first?" |
| 3 | Test acts as a role not in [`03-controllers.md`](03-controllers.md) §2 Permission Matrix | "Role `X` not in matrix — which role to use?" |
| 4 | Test calls a route not in [`03-controllers.md`](03-controllers.md) §1 Routes | "Route `X` not in route table — add to routes first?" |
| 5 | Test mocks `AvaTaxService` without setting fake return | "Test calls `create()` without mocking AvaTax — will fail in CI. Confirm mocking." |

---

## Section A — Stack & conventions

| | |
|--|--|
| Framework | Pest 3 |
| Database | MariaDB (real DB) — `RefreshDatabase` trait per test file |
| Auth | `$this->actingAs($user)` |
| Permissions | Real Spatie — `OrderPermissionSeeder` run in `beforeEach` |
| Mocks | Only `AvaTaxService` is mocked — every other service uses real impl |

**Reference:** [`skills/references/testing.md`](../../skills/references/testing.md#stack) · [`skills/references/testing.md`](../../skills/references/testing.md#rules) · feedback `feedback_eloquent_create_defaults.md` (use `assertDatabaseHas` not in-memory model assertions).

---

## Section B — Setup (every test file)

| Step (in `beforeEach`) | Why |
|------------------------|-----|
| `$this->seed(OrderPermissionSeeder::class)` | Permissions/roles must exist before `givePermissionTo` |
| `DB::table('sequences')->insertOrIgnore(['name' => 'orders', 'value' => 0])` | `nextOrderNumber()` needs the sequence row |
| Mock `AvaTaxService::calculateTax()` → returns lines with `tax_rate=0`, `tax_amount=0` | Avoid hitting AvaTax sandbox in tests |
| (Unit only) `$this->service = app(OrderService::class)` | Resolved through container |
| (Unit only) `$this->actor = User::factory()->create()->assignRole('admin')` | Default actor |

---

## Section C — Factory state spec

> Files: `database/factories/<Name>Factory.php`. Pattern: `Illuminate\Database\Eloquent\Factories\Factory`.
> Each row below describes the factory's `definition()` (default) + named states.

### `OrderFactory`

| State | What it sets / does |
|-------|---------------------|
| (default) | `number=fake unique 'ORD-YYYY-NNNN'`, `customer_id=Customer::factory()`, `source='walk_in'`, `status=OrderStatus::Pending`, `payment_status='unpaid'`, `created_by=User::factory()`, `subtotal=0`, `fees=0`, `shipping=0`, `grand_total=0`, `currency='USD'`. All snapshot cols null. |
| `withLines(int $n)` | After-creating: creates `$n` `OrderLine`s, each with a fresh in-stock `InventorySerial`. Recomputes `subtotal`, `grand_total`. |
| `shipped()` | Sets `status=OrderStatus::Shipped`, `shipped_at=now()`, `shipped_by=User::factory()`. After-creating: creates one outbound `Shipment` in `in_transit` status. **Implies `withLines(1)`** if no lines exist. |
| `cancelled()` | Sets `status=OrderStatus::Cancelled`, `cancelled_at=now()`, `cancelled_by=User::factory()`. |

### `OrderLineFactory`

| State | What it sets |
|-------|--------------|
| (default) | `order_id=Order::factory()`, `sku=fake`, `product_name=fake`, `inventory_serial_id=InventorySerial::factory()->inStock()`, `unit_price=fake decimal(10,2)`, `tax_rate=0`, `tax_amount=0`, `line_total = unit_price` |

### `OrderFeeFactory`

| State | What it sets |
|-------|--------------|
| (default) | `order_id=Order::factory()`, `name='Service Fee'`, `amount=10.00` |

### `PaymentFactory`

| State | What it sets |
|-------|--------------|
| (default) | `order_id=Order::factory()`, `payable_type=Order::class`, `payable_id=order_id`, `method=PaymentMethod::Cash`, `amount=0`, `status=PaymentStatus::Paid`, `created_by=User::factory()`, `currency='USD'`, `cash_received_at=now()` |
| `cash()` | Same as default — explicit alias for readability |

### `ShipmentFactory`

| State | What it sets |
|-------|--------------|
| (default) | `shippable_type=Order::class`, `shippable_id=Order::factory()`, `direction='outbound'`, `carrier='FedEx'`, `tracking=fake unique`, `label_cost=10.00`, `status=ShipmentStatus::InTransit`, `created_by=User::factory()`, `shipped_at=now()` |
| `outbound()` | sets `direction='outbound'` (explicit) |
| `delivered()` | sets `status=ShipmentStatus::Delivered`, `delivered_at=now()`, `delivered_by=User::factory()` |

**Reference:** [`skills/references/testing.md`](../../skills/references/testing.md#factory-usage-in-tests) · feedback `feedback_stub_future_modules.md` (stub future modules if test references them).

---

## Section D — Unit Tests — `tests/Unit/OrderServiceTest.php`

### Helpers (in test file)

| Helper | Returns |
|--------|---------|
| `makeSerial()` | In-stock `InventorySerial` via factory |
| `basePayload($customerId, $serialId, $addressId = 0)` | Array shaped like `CreateOrderRequest` validated output — see [`02-services.md`](02-services.md) §C1 field map |

### Test list — `create()`

| # | Test name | Setup | Action | Asserts |
|---|-----------|-------|--------|---------|
| 1 | creates order with correct totals and status | Customer + serial | `service->create(basePayload(...))` | `$order->status === Pending`, `payment_status === 'unpaid'`, `subtotal === '200.00'`, `fees === '30.00'`, `shipping === '15.00'`, `grand_total === '245.00'` |
| 2 | generates order number in ORD-YYYY-NNNN format | Customer + serial | create | `$order->number` matches `/^ORD-\d{4}-\d{4}$/` |
| 3 | creates order_lines row with correct line_total | Customer + serial | create | `assertDatabaseHas('order_lines', [order_id, inventory_serial_id, unit_price=200, line_total=200])` |
| 4 | creates order_fees row | Customer + serial | create | `assertDatabaseHas('order_fees', [order_id, name='Service Fee', amount=30])` |
| 5 | creates new CustomerAddress when inline address provided | Customer + serial | create with inline address | `assertDatabaseHas('customer_addresses', [customer_id, address_line1='456 Oak Avenue'])` |
| 6 | reuses existing CustomerAddress when address_id provided | Customer + serial + existing address | create with `address_id` | `CustomerAddress::count()` unchanged |
| 7 | copies shipping snapshot onto order from address | Customer + serial | create with inline address | `$order->shipping_first_name === 'Mike'`, `shipping_address_line1 === '456 Oak Avenue'` |
| 8 | leaves billing snapshot null when billing not provided | Customer + serial | create with shipping only | `$order->billing_first_name` is null AND all 9 other billing columns null |
| 9 | copies billing = shipping when billing_same_as_shipping is true | Customer + serial | create with `billing_same_as_shipping=true` + shipping | every billing_* equals matching shipping_* |
| 10 | tax_amount on line = round(unit_price × tax_rate, 2) | Customer + serial, tax_rate=0.08, unit_price=200 | create | `assertDatabaseHas('order_lines', [tax_rate=0.08, tax_amount=16.00, line_total=216.00])` |
| 11 | subtotal includes tax | Customer + serial, tax_rate=0.08, unit_price=200 | create | `$order->subtotal === '216.00'` (tax-inclusive) |
| 12 | grand_total = subtotal + fees + shipping (no separate tax) | Customer + serial, tax=0.08, unit_price=200, fees=30, shipping=15 | create | `$order->grand_total === '261.00'` (216 + 30 + 15) |

### Test list — `recordCashPayment()`

| # | Test name | Setup | Action | Asserts |
|---|-----------|-------|--------|---------|
| 13 | creates payment row with method=cash and status=paid | unpaid order | `recordCashPayment` | returned `Payment` has `method === Cash`, `status === Paid` |
| 14 | updates order to payment_status=paid and status=processing | Pending unpaid order | `recordCashPayment` | `fresh()->payment_status === 'paid'`, `fresh()->status === Processing` |
| 15 | inserts payable_type=Order on payment row | unpaid order | `recordCashPayment` | `assertDatabaseHas('payments', [payable_type=Order::class, payable_id=order.id])` |

### Test list — `ship()`

| # | Test name | Setup | Action | Asserts |
|---|-----------|-------|--------|---------|
| 16 | creates shipment with direction=outbound and status=in_transit | Processing order with 1 line | `ship()` | `assertDatabaseHas('shipments', [shippable_id, direction=outbound, carrier=FedEx, status=in_transit])` |
| 17 | creates InventoryMovement of type=sale for each line | Processing order with 2 lines | `ship()` | for each line: `assertDatabaseHas('inventory_movements', [inventory_serial_id, type=Sale])` |
| 18 | updates serials to status=sold | Processing order with 1 line | `ship()` | `$serial->fresh()->status === SerialStatus::Sold` |
| 19 | updates order to status=shipped with shipped_at and shipped_by | Processing order with 1 line | `ship()` | `fresh()->status === Shipped`, `shipped_by === actor.id` |
| 20 | throws DomainException when shipping a Pending order | Pending order | `ship()` | throws `\DomainException` "Only processing orders can ship." |
| 21 | throws DomainException when any line is back-ordered (no serial) | Processing order, 1 line with `inventory_serial_id=null` | `ship()` | throws `\DomainException` "Back-ordered lines must be fulfilled first." |

### Test list — `markDelivered()`

| # | Test name | Setup | Action | Asserts |
|---|-----------|-------|--------|---------|
| 22 | updates shipment to delivered and sets delivered_at | shipped order (factory state) | `markDelivered()` | shipment `fresh()->status === Delivered`, order `fresh()->delivered_at` not null |
| 23 | throws DomainException when marking non-shipped order | Processing order | `markDelivered()` | throws `\DomainException` "Only shipped orders can be marked delivered." |

### Test list — `taxPreview()`

| # | Test name | Setup | Action | Asserts |
|---|-----------|-------|--------|---------|
| 24 | returns lines with computed tax_rate and tax_amount | mock AvaTax → returns rate 0.08 | `taxPreview(['lines' => [...]])` | result has `lines[0].tax_rate === 0.08`, `lines[0].tax_amount` numeric |

---

## Section E — Feature Tests — `tests/Feature/OrderControllerTest.php`

### Helpers (in test file)

| Helper | Returns |
|--------|---------|
| `adminUser()` | User with all 9 order permissions (per matrix §2) |
| `salesUser()` | User with `orders.viewAny`, `orders.view` only |
| `orderPayload($customerId, $serialId)` | Array shaped like `CreateOrderRequest` body |

### Test list — INDEX

| # | Test name | Actor | Action | Asserts |
|---|-----------|-------|--------|---------|
| 1 | admin can list orders | admin | GET `route('orders.index')` | `assertOk`, `assertViewIs('orders.index')`, `assertViewHas('orders')` |
| 2 | sales can list orders | sales | GET index | `assertOk` |
| 3 | guest is redirected from index | (none) | GET index | `assertRedirect(route('login'))` |
| 4 | index filters by status | admin | GET `index?status=pending` | `$orders->total() === 1` |

### Test list — CREATE (form)

| # | Test name | Actor | Action | Asserts |
|---|-----------|-------|--------|---------|
| 5 | admin can view create form | admin | GET `route('orders.create')` | `assertOk`, `assertViewIs('orders.create')`, view has `customers`, `sources`, `addresses` |
| 6 | sales cannot view create form | sales | GET create | `assertForbidden` |
| 7 | guest is redirected from create | (none) | GET create | `assertRedirect(route('login'))` |

### Test list — STORE

| # | Test name | Actor | Action | Asserts |
|---|-----------|-------|--------|---------|
| 8 | admin can create an order | admin | POST `orders.store` with valid payload | `assertRedirect`, `assertDatabaseHas('orders', [customer_id, status='pending'])`, `assertDatabaseHas('order_lines', [inventory_serial_id])`, `assertDatabaseHas('order_fees', [name='Service Fee'])` |
| 9 | sales cannot store an order | sales | POST store | `assertForbidden` |
| 10 | guest is redirected from store | (none) | POST store | `assertRedirect(route('login'))` |
| 11 | store fails with invalid serial_id | admin | POST store with non-existent serial | `assertSessionHasErrors('lines.0.serial_id')` |
| 12 | store fails without customer_id | admin | POST store omitting customer_id | `assertSessionHasErrors('customer_id')` |

### Test list — SHOW

| # | Test name | Actor | Action | Asserts |
|---|-----------|-------|--------|---------|
| 13 | admin can view order show page | admin | GET `route('orders.show', $order)` | `assertOk`, `assertViewIs('orders.show')`, `assertViewHas('order')` |
| 14 | sales can view order show page | sales | GET show | `assertOk` |
| 15 | guest is redirected from show | (none) | GET show | `assertRedirect(route('login'))` |
| 16 | show displays correct columns (no tax_total) | admin | GET show | response does NOT contain string "tax_total", does NOT contain "fees_total", does NOT contain "shipping_amount" as a label — only renders columns from §6C column-map |
| 17 | show displays subtotal labelled "Subtotal (incl. tax)" | admin | GET show | response contains exactly "Subtotal (incl. tax)" |

### Test list — PAY

| # | Test name | Actor | Action | Asserts |
|---|-----------|-------|--------|---------|
| 18 | admin can record cash payment | admin | POST `orders.pay` | `assertRedirect(route('orders.show', $order))`, `fresh()->payment_status === 'paid'`, `fresh()->status === Processing->value`, `assertDatabaseHas('payments', [order_id, method='cash'])` |
| 19 | sales cannot record payment | sales | POST pay | `assertForbidden` |
| 20 | guest is redirected from pay | (none) | POST pay | `assertRedirect(route('login'))` |

### Test list — SHIP

| # | Test name | Actor | Action | Asserts |
|---|-----------|-------|--------|---------|
| 21 | admin can ship an order | admin | POST `orders.ship` (Processing order with line) | `assertRedirect(route('orders.show', $order))`, `fresh()->status === Shipped->value`, `assertDatabaseHas('shipments', [shippable_id, carrier='FedEx'])` |
| 22 | sales cannot ship | sales | POST ship | `assertForbidden` |
| 23 | guest is redirected from ship | (none) | POST ship | `assertRedirect(route('login'))` |
| 24 | shipping a Pending order shows error flash | admin | POST ship on Pending order | `assertSessionHasErrors('error')` |

### Test list — DELIVER

| # | Test name | Actor | Action | Asserts |
|---|-----------|-------|--------|---------|
| 25 | admin can mark order as delivered | admin | POST `orders.deliver` (shipped order) | `assertRedirect(route('orders.show', $order))`, `fresh()->delivered_at` not null, `assertDatabaseHas('shipments', [shippable_id, status='delivered'])` |
| 26 | sales cannot mark delivered | sales | POST deliver | `assertForbidden` |
| 27 | guest is redirected from deliver | (none) | POST deliver | `assertRedirect(route('login'))` |

### Test list — TAX PREVIEW

| # | Test name | Actor | Action | Asserts |
|---|-----------|-------|--------|---------|
| 28 | admin can fetch tax preview | admin | POST `orders.tax-preview` with line payload | `assertOk`, response JSON has `lines[].tax_rate`, `lines[].tax_amount` |
| 29 | sales cannot fetch tax preview | sales | POST tax-preview | `assertForbidden` |

> Tests for `update`, `cancel`, `destroy` actions are spec'd in [`05-update-cancel-delete.md`](05-update-cancel-delete.md) §8.

---

## Section F — Coverage targets

| Layer | Target |
|-------|--------|
| `OrderService` | 100% method coverage. Every public method ≥ 1 happy path + 1 failure (where applicable). |
| `OrderController` | 100% method coverage. Every action: authorized + unauthorized + guest. |
| `OrderPolicy` | Implicit via feature tests (forbidden actors hit policy). |
| `CreateOrderRequest` | Tested via feature tests for `store` (validation errors). |

---

**Reference:** [`skills/references/testing.md`](../../skills/references/testing.md#feature-test--controller-actions) · [`skills/references/testing.md`](../../skills/references/testing.md#unit-test--service-methods) · [`skills/references/testing.md`](../../skills/references/testing.md#testing-with-permissions) · feedback `feedback_eloquent_create_defaults.md`.
