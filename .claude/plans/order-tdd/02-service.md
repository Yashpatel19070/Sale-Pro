# Order Module — Service + Unit Tests

File: `app/Services/OrderService.php`
Test file: `tests/Unit/OrderServiceTest.php`

---

## Service Methods

| Method | Visibility | Signature |
|--------|-----------|-----------|
| `paginate` | public | `paginate(array $filters = []): LengthAwarePaginator` |
| `store` | public | `store(array $data, User $createdBy): Order` |
| `update` | public | `update(Order $order, array $data): Order` |
| `delete` | public | `delete(Order $order): void` |
| `recordCashPayment` | public | `recordCashPayment(Order $order, array $data, User $createdBy): Payment` |
| `complete` | public | `complete(Order $order, User $completedBy): Order` |
| `generateNumber` | private | `generateNumber(): string` |
| `recalculateTotals` | private | `recalculateTotals(Order $order): void` |
| `advanceToProcessingIfReady` | private | `advanceToProcessingIfReady(Order $order): void` |
| `assignSerialsToLines` | private | `assignSerialsToLines(Order $order): void` |
| `recordSaleMovements` | private | `recordSaleMovements(Order $order, User $by): void` — loads `customer`, passes `"Order placed by {customer.name}"` as notes to `movementService->recordSale()` |
| `resolveBillingSnapshot` | private | `resolveBillingSnapshot(?int $addressId): array` |
| `resolveShippingSnapshot` | private | `resolveShippingSnapshot(?int $addressId): array` |

> **PHP type gotcha:** Laravel's `validated()` returns address IDs as strings even when the `integer` validation rule passes. Always cast with `(int)` before passing to `?int` typed private methods — PHP 8 strict types will not coerce `"4"` → `4` across a call boundary. Pattern: `isset($data['billing_address_id']) ? (int) $data['billing_address_id'] : null`

---

## Unit Tests

### `paginate()`

- `it_returns_paginated_orders`
  - create 3 orders → `paginate()` returns `LengthAwarePaginator` with 3 results

- `it_filters_by_status`
  - create 1 pending + 1 processing order
  - `paginate(['status' => 'pending'])` → returns 1 result with `status=pending`

- `it_filters_by_source`
  - create 1 walk_in + 1 online order
  - `paginate(['source' => 'walk_in'])` → returns 1 result with `source=walk_in`

---

### `generateNumber()`

- `it_generates_order_number_in_correct_format`
  - first call → `ORD-{year}-0001`
- `it_increments_order_number_on_each_call`
  - second call → `ORD-{year}-0002`

---

### `store()`

**Order header:**
- `it_creates_order_with_walk_in_source`
  - asserts `orders.source = walk_in`
  - asserts `orders.status = pending`
  - asserts `orders.payment_status = unpaid`
  - asserts `orders.created_by = $createdBy->id`

- `it_sets_billing_snapshot_to_null_for_cash_payment`
  - asserts all `billing_*` columns are NULL

- `it_sets_shipping_snapshot_to_null_for_instore_pickup`
  - asserts all `shipping_*` columns are NULL

- `it_sets_shipped_at_and_shipped_by_to_null`
  - asserts `orders.shipped_at = NULL`
  - asserts `orders.shipped_by = NULL`

**Totals:**
- `it_calculates_subtotal_fees_and_grand_total_correctly`
  - unit_price=170, fee=15, shipping=0
  - asserts `subtotal=170.00`, `fees=15.00`, `shipping=0.00`, `grand_total=185.00`

**Order line:**
- `it_creates_order_line_with_null_serial_on_store`
  - asserts `order_lines.inventory_serial_id` = NULL — serial is assigned at payment, not at order creation
- `it_snapshots_sku_and_product_name_on_order_line`
  - asserts `order_lines.sku` = `listing→product.sku` ("PROD-C")
  - asserts `order_lines.product_name` = `listing→product.name` ("Widget Basic")
  - asserts `order_lines.product_listing_id` = `$listing->id`

**Order fee:**
- `it_creates_order_fee_row`
  - asserts `order_fees.name = Service Fee`, `order_fees.amount = 15.00`

**Inventory — store() does NOT touch serials:**
- `it_does_not_create_inventory_movement_on_store`
  - asserts `inventory_movements` has zero rows after store()
- `it_does_not_change_serial_status_on_store`
  - asserts `inventory_serials.status` stays `in_stock` after store()

**Order events:**
- `it_records_order_placed_event`
  - asserts `order_events` row: `event=order_placed`
  - metadata contains `sku=PROD-C`, `product_name=Widget Basic`, `grand_total=185.00`
  - `created_by` = acting user id

**Transaction:**
- `it_rolls_back_order_if_line_creation_fails`
  - force line creation to throw → asserts no `orders` row persists
  - asserts no `order_events` row persists

---

### `update()`

- `it_updates_order_when_status_is_pending`
  - asserts updated fields are saved
- `it_throws_when_order_is_not_pending`
  - order with `status=processing` → throws `\DomainException`

---

### `delete()`

- `it_deletes_order_when_status_is_pending`
  - asserts order no longer exists
- `it_throws_when_order_is_not_pending`
  - order with `status=processing` → throws `\DomainException`

---

### `recordCashPayment()`

> **Rule:** Only a full payment (amount >= grand_total) triggers serial assignment and inventory movement.
> Partial payment records the payment as `partial` and leaves inventory untouched.
> `PaymentStatus::Partial` is a valid enum case alongside `Unpaid` and `Paid`.

**Full payment (amount >= grand_total):**
- `it_creates_cash_payment_row`
  - asserts `payments.method = cash`
  - asserts `payments.status = paid`
  - asserts `payments.cash_received_at` is set to approximately now() — not null
  - asserts `payments.amount = 185.00`
  - asserts `payments.created_by = $createdBy->id`
  - asserts `payments.order_id = $order->id`

- `it_sets_order_payment_status_to_paid`
  - asserts `order.payment_status = paid`

- `it_assigns_serial_on_full_cash_payment`
  - after store(): line has `inventory_serial_id = null`
  - after recordCashPayment() with full amount: line has serial assigned
  - serial locked with `lockForUpdate` + `whereNotIn` subquery to exclude already-reserved serials

- `it_creates_sale_inventory_movement_on_full_payment`
  - asserts `inventory_movements.type = sale`
  - asserts `inventory_movements.from_location_id` = serial's location id
  - asserts `inventory_movements.to_location_id = NULL`
  - asserts `inventory_movements.reference = order.number`

- `it_marks_serial_as_sold_on_full_payment`
  - asserts `inventory_serials.status = sold`

- `it_advances_order_to_processing_on_full_payment`
  - full payment → order status = processing

**Partial payment (amount < grand_total):**
- `it_records_partial_payment_with_partial_status`
  - amount=100, grand_total=185 → `payments.status = partial`
  - asserts `order.payment_status = partial`

- `it_does_not_assign_serial_on_partial_payment`
  - partial payment → `order_lines.inventory_serial_id` stays null

- `it_does_not_create_inventory_movement_on_partial_payment`
  - partial payment → `inventory_movements` has zero rows

- `it_does_not_change_serial_status_on_partial_payment`
  - partial payment → `inventory_serials.status` stays `in_stock`

- `it_does_not_advance_order_status_on_partial_payment`
  - partial payment → `order.status` stays `pending`

**Guards:**
- `it_throws_when_no_serial_available_on_full_payment`
  - no in-stock serials exist → throws `\DomainException`

- `it_throws_if_serial_not_in_stock_on_full_payment`
  - only sold serial exists → throws `\DomainException`

- `it_throws_if_order_already_paid`
  - order with `payment_status = paid` → throws `\DomainException`

**Order events:**
- `it_records_payment_received_event`
  - asserts `order_events` row: `event=payment_received`
  - metadata contains `method=cash`, `amount=185.00`, `subtotal=170.00`, `fees=15.00`, `shipping=0.00`
  - `created_by` = acting user id

---

### `complete()`

> Inventory is already moved at full payment. complete() only closes the order.

- `it_sets_order_status_to_complete`
  - asserts `order.status = complete`

- `it_does_not_create_shipment_row`
  - asserts `shipments` table has zero rows for this order

- `it_throws_if_order_is_not_processing`
  - order with `status = pending` → throws `\DomainException`

- `it_does_not_touch_inventory_on_complete`
  - asserts `inventory_movements` count unchanged after complete()
  - asserts `inventory_serials.status` unchanged after complete()

**Order events:**
- `it_records_completed_event`
  - asserts `order_events` row: `event=completed`, `metadata={}`, `created_by` = acting user id

**Full inventory lifecycle across all three steps (integration):**
- `it_shows_correct_serial_status_at_each_stage`
  - after store()                        → serial = null,    status = in_stock, movements = 0
  - after recordCashPayment() full amt   → serial assigned,  status = sold,     movements = 1
  - after complete()                     → order = complete, no inventory change
- `it_shows_correct_order_events_at_each_stage`
  - after store()              → 1 event: `order_placed`
  - after recordCashPayment()  → 2 events: `order_placed`, `payment_received`
  - after complete()           → 3 events: `order_placed`, `payment_received`, `completed`

**CSR tracking:**
- `created_by` on `orders` table = admin user who placed the order
- `created_by` on `payments` table = admin user who recorded the payment
- Both shown on `orders/show.blade.php` — already implemented in view and controller
