# TDD-Order — Test Spec (TDD Contract)

> Every test name and its assertion contract.
> Implementation must satisfy all tests listed here.
> Tests are the ground truth — if code and test disagree, fix the code.

---

## Test files

| File | Type | Count |
|---|---|---|
| `tests/Unit/OrderServiceTest.php` | Unit | ~66 tests |
| `tests/Feature/OrderControllerTest.php` | Feature | ~49 tests |

---

## Unit: `OrderServiceTest`

**Setup:** `RefreshDatabase`, uses `OrderPermissionSeeder`, mocks `AvaTaxService`, creates admin user with all order permissions.

**AvaTax mock default:** Returns `['tax_rate' => 0, 'tax_amount' => 0]` for each line, so `line_total = unit_price`.

---

### Group: paginate

| Test name | Assertion contract |
|---|---|
| `it paginates orders with no filters` | Returns LengthAwarePaginator; items include seeded order |
| `it filters orders by status` | With filter `['status' => 'pending']`, only pending orders returned |
| `it filters orders by search on order number` | With filter `['search' => 'ORD-']`, matching order returned |
| `it filters orders by search on customer name` | With filter `['search' => customer->name]`, matching order returned |

---

### Group: create

| Test name | Assertion contract |
|---|---|
| `it creates an order with correct number format` | Order number matches `/^ORD-\d{4}-\d{4}$/` |
| `it creates order_lines with correct line_total` | `line_total = unit_price + tax_amount` (both 0 with mock = unit_price) |
| `it sets subtotal as sum of line totals` | `$order->subtotal = sum($line->line_total)` |
| `it sets grand_total as subtotal plus fees plus shipping` | `grand_total = subtotal + fees + shipping` |
| `it sets status to pending on create` | `$order->status === OrderStatus::Pending` |
| `it sets payment_status to unpaid on create` | `$order->payment_status === 'unpaid'` |
| `it sets billing snapshot to null for cash orders` | All `billing_*` columns are null when no billing address passed |
| `it sets shipping snapshot from address` | `shipping_first_name = $address->first_name`, etc. |
| `it creates order fees rows` | `$order->orderFees()->count() === count($feeData)` |
| `it creates order with no fees when fees absent` | `$order->fees == 0.00`, `$order->orderFees()->count() === 0` |

---

### Group: update

| Test name | Assertion contract |
|---|---|
| `it updates source on a pending order` | `$order->fresh()->source === OrderSource::Phone` (or chosen source) |
| `it updates shipping snapshot` | `$order->fresh()->shipping_first_name === $newAddress->first_name` |
| `it replaces fees on update` | Old fees deleted; new fees in database; `$order->fresh()->fees === sum(new fees)` |
| `it recalculates grand_total after fee change` | `grand_total = subtotal + new_fees + shipping` |
| `it does not change subtotal on update` | `$order->fresh()->subtotal === $originalSubtotal` |
| `it throws DomainException when updating processing order` | `DomainException` thrown with message containing "cannot be updated" |

---

### Group: recordCashPayment

| Test name | Assertion contract |
|---|---|
| `it records cash payment and sets status to processing` | Payment row created; `$order->fresh()->status === OrderStatus::Processing` |
| `it sets payment_status to paid` | `$order->fresh()->payment_status === 'paid'` |
| `it throws DomainException when order is not pending` | `DomainException` thrown for processing/shipped/cancelled orders |
| `it creates payment with correct amount` | `Payment->amount === $order->grand_total` |

---

### Group: ship

| Test name | Assertion contract |
|---|---|
| `it ships a processing order` | `$order->fresh()->status === OrderStatus::Shipped` |
| `it sets shipped_at from data on ship` | `$order->fresh()->shipped_at->eq($data['shipped_at'])` |
| `it sets shipped_by to acting user` | `$order->fresh()->shipped_by === $user->id` |
| `it creates shipment record on ship` | `assertDatabaseHas('shipments', ['shippable_type' => 'order', 'shippable_id' => $order->id, 'direction' => 'outbound'])` |
| `it creates inventory_movement for each line on ship` | `assertDatabaseHas('inventory_movements', ['inventory_serial_id' => $serial->id, 'type' => 'sale', 'reference' => $order->number])` |
| `it sets serial status to Sold on ship` | `assertDatabaseHas('inventory_serials', ['id' => $serial->id, 'status' => 'sold', 'inventory_location_id' => null])` |
| `it throws DomainException when order is not processing` | `DomainException` thrown for pending/shipped/cancelled |

---

### Group: markDelivered

| Test name | Assertion contract |
|---|---|
| `it sets delivered_at from data on order` | `$order->fresh()->delivered_at->eq($data['delivered_at'])` |
| `it does not change status on delivery` | `$order->fresh()->status === OrderStatus::Shipped` |
| `it sets delivered_by to acting user` | `$order->fresh()->delivered_by === $user->id` |
| `it updates outbound shipment to delivered` | `assertDatabaseHas('shipments', ['shippable_id' => $order->id, 'direction' => 'outbound', 'status' => 'delivered'])` |
| `it throws DomainException when order is not shipped` | `DomainException` thrown |

---

### Group: cancel

| Test name | Assertion contract |
|---|---|
| `it cancels a pending order` | `$order->fresh()->status === OrderStatus::Cancelled` |
| `it cancels a processing order` | `$order->fresh()->status === OrderStatus::Cancelled` |
| `it sets cancelled_at timestamp` | `$order->fresh()->cancelled_at` is not null |
| `it sets cancelled_by to acting user` | `$order->fresh()->cancelled_by === $user->id` |
| `it throws DomainException when cancelling a shipped order` | `DomainException` thrown |

---

### Group: delete

| Test name | Assertion contract |
|---|---|
| `it deletes a cancelled order and its lines and fees` | Order row gone; `order_lines` gone; `order_fees` gone |
| `it preserves payments on delete` | Payment row still exists after order deleted |
| `it throws DomainException when deleting a non-cancelled order` | `DomainException` thrown for pending, processing, shipped |

---

## Feature: `OrderControllerTest`

**Setup:** `RefreshDatabase`, uses `OrderPermissionSeeder`.

**User helpers:**
- `orderAdminUser()` — has all permissions except `orders.delete`
- `orderSuperAdminUser()` — has all permissions including `orders.delete`
- `orderSalesUser()` — has `orders.viewAny` and `orders.view` only

---

### Group: index

| Test name | Expected response |
|---|---|
| `it shows order index to admin` | 200 OK, view contains orders.index |
| `it forbids order index to guest` | 302 redirect to login |
| `it forbids order index when no permission` | 403 Forbidden |

---

### Group: create / store

| Test name | Expected response |
|---|---|
| `it shows create form to admin` | 200 OK |
| `it forbids create form to sales role` | 403 Forbidden |
| `it stores a new order and redirects to show` | 302 redirect to `orders.show`; order in database |
| `it fails store with missing lines` | 302 back with validation errors |
| `it fails store with invalid source` | 302 back with validation errors |

---

### Group: show

| Test name | Expected response |
|---|---|
| `it shows order detail to admin` | 200 OK |
| `it shows order detail to sales user` | 200 OK |
| `it forbids show to guest` | 302 redirect to login |

### Group: show page content

| Test name | Assertion contract |
|---|---|
| `it displays order number on show page` | `assertSee($order->number)` |
| `it displays status label not raw value on show page` | `assertSee($order->status->label())` + `assertDontSee($order->status->value)` |
| `it displays source label on show page` | `assertSee($order->source->label())` |
| `it displays customer name on show page` | `assertSee($order->customer->name)` |
| `it displays subtotal incl tax label on show page` | `assertSee('Subtotal (incl. tax)')` |
| `it displays correct grand total on show page` | `assertSee(number_format($order->grand_total, 2))` |
| `it uses order fees column not fees_total on show page` | `assertSee(number_format($order->fees, 2))` |
| `it uses order shipping column not shipping_amount on show page` | `assertSee(number_format($order->shipping, 2))` |
| `it displays line sku from snapshot on show page` | `assertSee($line->sku)` |
| `it displays payment method label not raw on show page` | `assertSee($payment->method->label())` |
| `it displays payment status label not raw on show page` | `assertSee($payment->status->label())` |
| `it displays shipment status label not raw on show page` | `assertSee($shipment->status->label())` |
| `it shows shipping address when present on show page` | `assertSee($order->shipping_address_line1)` |
| `it hides billing address section when null on show page` | `assertDontSee('Billing Address')` when `billing_address_line1` is null |
| `it shows inline pay form when order is unpaid and user can pay` | `assertSee('Record Payment')` |
| `it shows inline ship form when order is processing and user can ship` | `assertSee('Mark Shipped')` |
| `it shows inline deliver form when order is shipped and not yet delivered` | `assertSee('Mark Delivered')` |
| `it hides pay form after payment recorded` | `assertDontSee('Record Payment')` when `payment_status = paid` |

---

### Group: pay

| Test name | Expected response |
|---|---|
| `it records cash payment and redirects to show` | 302 redirect; order status = processing |
| `it forbids pay to sales role` | 403 Forbidden |
| `it returns error when order is not pending` | 302 back with error message |

---

### Group: ship

| Test name | Expected response |
|---|---|
| `it ships a processing order and redirects to show` | 302 redirect; order status = shipped |
| `it forbids ship to sales role` | 403 Forbidden |

---

### Group: deliver

| Test name | Expected response |
|---|---|
| `it marks order delivered and redirects to show` | 302 redirect; order status still shipped; delivered_at set |
| `it forbids deliver to sales role` | 403 Forbidden |

---

### Group: edit / update

| Test name | Expected response |
|---|---|
| `it shows edit form to admin for pending order` | 200 OK |
| `it forbids edit form to sales role` | 403 Forbidden |
| `it forbids edit form for non-pending order` | 403 Forbidden — policy checks status; processing/shipped/cancelled orders blocked at door |
| `it updates a pending order and redirects to show` | 302 redirect; order source updated in DB |
| `it returns error when updating processing order` | 302 back; `$errors->first('error')` not empty |

---

### Group: cancel

| Test name | Expected response |
|---|---|
| `it cancels a pending order and redirects to show` | 302 redirect; order status = cancelled |
| `it forbids cancel to sales role` | 403 Forbidden |
| `it returns error when cancelling a shipped order` | 302 back with error |

---

### Group: destroy

| Test name | Expected response |
|---|---|
| `it deletes a cancelled order and redirects to index` | 302 redirect to index; order not in DB |
| `it forbids delete to admin role` | 403 Forbidden (admin lacks orders.delete) |
| `it returns error when deleting a non-cancelled order` | 302 back with error |

---

## Assertion contracts

### Status assertions

All status assertions use enum comparison, never string:
- CORRECT: `expect($order->fresh()->status)->toBe(OrderStatus::Shipped)`
- WRONG: `expect($order->fresh()->status)->toBe('shipped')`

### Column assertions

All column assertions use the exact column name from `01-schema.md`:
- `$order->fees` — decimal column
- `$order->shipping` — decimal column
- `$order->grand_total` — decimal column

### Financial assertions

All financial assertions compare decimal values:
- `expect($order->fresh()->grand_total)->toEqual($order->subtotal + $order->fees + $order->shipping)`
- Tax is never asserted separately at order level — it is in `order_lines.tax_amount` and `order_lines.tax_rate`

### Delete assertions

After delete, assert:
- `assertDatabaseMissing('orders', ['id' => $order->id])`
- `assertDatabaseMissing('order_lines', ['order_id' => $order->id])`
- `assertDatabaseMissing('order_fees', ['order_id' => $order->id])`
- `assertDatabaseHas('payments', ['order_id' => $order->id])` ← payments preserved

### DomainException assertions

```
expect(fn() => $service->method($order, ...))->toThrow(\DomainException::class);
```

---

## Test helpers (allowed patterns)

- `orderAdminUser()` — creates user with `orders.*` except delete
- `orderSuperAdminUser()` — creates user with all `orders.*` permissions
- `orderSalesUser()` — creates user with `orders.viewAny` and `orders.view`
- `makeOrder(OrderStatus $status)` — creates order in given status (sets all required timestamps)
- `makeProcessingOrder()` — pending order that had `recordCashPayment` called on it
- `makeShippedOrder()` — processing order that had `ship()` called on it
- `makeCancelledOrder()` — cancelled order for delete tests

---

## Factory rules (spec-derived — not copied from implementation)

Factories must satisfy these invariants. Specific dollar amounts are arbitrary but must be internally consistent.

### `OrderFactory`

**Invariants:**
- `grand_total` MUST equal `subtotal + fees + shipping` (spec formula — never deviate)
- `status` default MUST be `OrderStatus::Pending`
- `payment_status` default MUST be `'unpaid'`
- `number` format MUST match `/^ORD-\d{4}-\d{4}$/` — use random 4-digit int, NOT sequences table

**State: `withLines(int $count = 1)`**
- Creates `$count` in-stock `InventorySerial` records + one `OrderLine` per serial
- Each line `line_total` MUST equal `unit_price + tax_amount`
- Required for any test that calls `ship()`

**State: `shipped()`**
- Applies `withLines(1)` first
- Sets `status = OrderStatus::Shipped`, `shipped_at = now()`
- Creates one `Shipment` record: `shippable_type='order'`, `shippable_id=$order->id`, `direction='outbound'`, `status='in_transit'`, `created_by=$order->created_by`
- Required for `markDelivered()` tests

### `OrderLineFactory`

**Invariants:**
- `line_total` MUST equal `unit_price + tax_amount` (spec formula)
- `tax_rate` default `0.00` — matches AvaTax mock return in unit tests
- `inventory_serial_id` default — in-stock serial (use `InventorySerial::factory()->inStock()`)

### `OrderFeeFactory`

**Invariants:**
- Must have `order_id`, `name` (non-empty string), `amount` (positive decimal)

### `PaymentFactory`

**Invariants:**
- `payable_type` MUST be `'order'`
- `payable_id` MUST resolve to same value as `order_id` (spec: mirrors order_id for direct payments)
- `status` default MUST be `PaymentStatus::Paid`
- `method` default MUST be `PaymentMethod::Cash`

---

## OrderPermissionSeeder

**Permissions created** (all `guard_name = 'web'`):
`orders.viewAny`, `orders.view`, `orders.create`, `orders.update`, `orders.cancel`, `orders.delete`, `orders.pay`, `orders.ship`, `orders.deliver`

**Role assignments** (derived from `02-permissions.md`):
| Role | Permissions |
|---|---|
| `super_admin` | all 9 |
| `admin` | all except `orders.delete` |
| `manager` | all except `orders.delete` |
| `sales` | `orders.viewAny`, `orders.view` only |

Use `Permission::firstOrCreate` and `Role::firstOrCreate` — seeder must be idempotent.
