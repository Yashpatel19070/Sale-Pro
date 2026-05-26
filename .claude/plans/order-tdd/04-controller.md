# Order Module — Controller + Feature Tests

File: `app/Http/Controllers/OrderController.php`
Test file: `tests/Feature/OrderControllerTest.php`

---

## Controller Actions

### `index(Request $request): View`
```
authorize: viewAny Order
loads: paginated orders with filters (status, source, customer search, date range)
passes to view: $orders, $filters, $statuses, $sources
```

### `create(): View`
```
authorize: create Order
passes to view: $customers (with addresses eager-loaded), $productListings (active, with product eager-loaded), $sources, $paymentMethods
```

### `customerAddresses(Customer $customer): JsonResponse`
```
authorize: viewAny Order (staff with view-orders can call this)
returns JSON: [{id, label, summary, is_default}]
summary = "{first_name} {last_name}, {address_line1}, {city}" — compact one-liner for dropdown display
used by: create/edit form via fetch() when customer is changed
```

### `listingStock(ProductListing $listing): JsonResponse`
```
authorize: viewAny Order
returns JSON: {sku: "PROD-C", stock: [{location: "Warehouse A", qty: 20}, ...]}
stock = in_stock serials grouped by inventory_location, excluding serials already reserved in order_lines
used by: create/edit form via fetch() when a line item product is changed
```

### `store(StoreOrderRequest $request): RedirectResponse`
```
authorize: handled by StoreOrderRequest
calls: $this->service->store($request->validated(), $request->user())
redirects: orders.show with success flash
```

### `show(Order $order): View`
```
authorize: view $order
eager loads: customer, lines.productListing.product, lines.inventorySerial, orderFees, payments.createdBy, createdBy
passes to view: $order
```

### `edit(Order $order): View|RedirectResponse`
```
authorize: view $order  ← uses view (not update) so non-pending orders get redirect, not 403
guard: if status != pending → redirect to orders.show with error
eager loads: lines.productListing.product, lines.inventorySerial, orderFees
passes to view: $order, $productListings (active, with product eager-loaded)
```

### `update(UpdateOrderRequest $request, Order $order): RedirectResponse`
```
authorize: handled by UpdateOrderRequest
calls: $this->service->update($order, $request->validated())
catches: \DomainException → back with error
redirects: orders.show with success flash
```

### `destroy(Order $order): RedirectResponse`
```
authorize: delete $order
calls: $this->service->delete($order)
catches: \DomainException → back with error
redirects: orders.index with success flash
```

### `recordCashPayment(RecordCashPaymentRequest $request, Order $order): RedirectResponse`
```
authorize: handled by RecordCashPaymentRequest
calls: $this->service->recordCashPayment($order, $request->validated(), $request->user())
catches: \DomainException → back with error
redirects: orders.show with success flash
```

### `complete(Request $request, Order $order): RedirectResponse`
```
authorize: complete $order (via $this->authorize)
calls: $this->service->complete($order, $request->user())
catches: \DomainException → back with error
redirects: orders.show with success flash
```

---

## Feature Tests

### Setup (shared across all tests)
```php
// Each test needs:
$admin = User::factory()->create();
$admin->givePermissionTo(['view-orders', 'create-orders', 'manage-orders']);

$customer  = Customer::factory()->create();
$location  = InventoryLocation::factory()->create(['name' => 'Warehouse A']);
$product  = Product::factory()->create();
$listing  = ProductListing::factory()->active()->for($product)->create();
$serial   = InventorySerial::factory()->inStock()->atLocation($location)->forProduct($product)->create();
// Note: use ->atLocation()->forProduct() not ->for() — InventorySerial::location() is named 'location', not 'inventoryLocation'
```

---

### `index`

- `admin_can_view_orders_index`
  - actingAs($admin) → GET /admin/orders
  - assertOk()

- `user_without_permission_cannot_view_orders`
  - user with no permissions → GET /admin/orders
  - assertForbidden()

---

### `create`

- `admin_can_view_create_order_form`
  - actingAs($admin) → GET /admin/orders/create
  - assertOk()

---

### `store`

- `admin_can_create_walkin_cash_order`
  ```
  POST /admin/orders with:
    customer_id, source=walk_in, payment_method=cash,
    shipping=0, lines=[{product_listing_id=$listing->id, unit_price=170, tax_rate=0}],
    fees=[{name=Service Fee, amount=15}]
  ```
  - assertRedirect to orders.show
  - assertDatabaseHas orders: source=walk_in, status=pending, payment_status=unpaid, grand_total=185
  - assertDatabaseHas orders: billing_first_name=null, shipping_first_name=null
  - assertDatabaseHas orders: shipped_at=null, shipped_by=null
  - assertDatabaseHas order_lines: inventory_serial_id=null  (serial assigned at payment, not store)
  - assertDatabaseHas order_fees: name=Service Fee, amount=15
  - assertDatabaseMissing customer_addresses: customer_id=$customer->id

- `store_fails_validation_without_required_fields`
  - POST with empty payload → assertSessionHasErrors(['customer_id', 'source', 'lines'])

---

### `show`

- `admin_can_view_order_show_page`
  - order exists → GET /admin/orders/{order}
  - assertOk()

---

### `edit`

- `admin_can_view_edit_form_when_order_is_pending`
  - pending order → GET /admin/orders/{order}/edit
  - assertOk()

- `edit_redirects_to_show_when_order_is_not_pending`
  - processing order → GET /admin/orders/{order}/edit
  - assertRedirect to orders.show
  - assertSessionHasErrors('error')

---

### `update`

- `admin_can_update_pending_order`
  - pending order → PUT /admin/orders/{order}
  - assertRedirect to orders.show
  - assertDatabaseHas updated fields

- `update_fails_when_order_is_not_pending`
  - processing order → PUT /admin/orders/{order}
  - assertRedirect back with error

---

### `destroy`

- `admin_can_delete_pending_order`
  - pending order → DELETE /admin/orders/{order}
  - assertRedirect to orders.index
  - assertDatabaseMissing orders: id=$order->id

- `destroy_fails_when_order_is_not_pending`
  - processing order → DELETE /admin/orders/{order}
  - assertRedirect back with error
  - assertDatabaseHas orders: id=$order->id (still exists)

---

### `recordCashPayment`

- `admin_can_record_cash_payment`
  ```
  POST /admin/orders/{order}/cash-payment with:
    amount=185.00, cash_received_at=now
  ```
  - assertRedirect to orders.show
  - assertDatabaseHas payments: method=cash, status=paid, order_id=$order->id
  - assertDatabaseHas orders: payment_status=paid, status=processing

- `record_cash_payment_fails_if_already_paid`
  - paid order → POST /admin/orders/{order}/cash-payment
  - assertRedirect back with error
  - assertDatabaseCount payments: 1 (no duplicate)

- `user_without_permission_cannot_record_cash_payment`
  - user with no manage-orders → POST /admin/orders/{order}/cash-payment
  - assertForbidden()

---

### `complete`

- `admin_can_complete_order`
  - processing order → POST /admin/orders/{order}/complete
  - assertRedirect to orders.show
  - assertDatabaseHas orders: status=complete
  - assertDatabaseHas inventory_movements: type=sale, reference=$order->number
  - assertDatabaseHas inventory_serials: status=sold
  - assertDatabaseMissing shipments: shippable_id=$order->id, shippable_type=order

- `complete_fails_if_order_not_in_processing`
  - pending order → POST /admin/orders/{order}/complete
  - assertRedirect back with error
  - assertDatabaseHas orders: status=pending (unchanged)

- `user_without_permission_cannot_complete_order`
  - user with no manage-orders → POST /admin/orders/{order}/complete
  - assertForbidden()
