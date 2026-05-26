# Order Module — Foundation

Build these before writing any tests. Every test depends on them.

---

## Migrations

Run in this order (dependency chain):

```
1. create_orders_table          depends on: customers, users
2. create_order_lines_table     depends on: orders, inventory_serials
3. create_order_fees_table      depends on: orders
4. create_payments_table        depends on: orders (polymorphic)
5. create_order_events_table    depends on: orders, users (append-only audit log)
```

### `orders` key columns
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `number` | string(20) | No | Unique — `ORD-2026-0001` |
| `customer_id` | foreignId | No | FK → customers |
| `source` | string(20) | No | `online / walk_in / phone` |
| `status` | string(30) | No | Default `pending` |
| `payment_status` | string(10) | No | Default `unpaid` |
| `created_by` | foreignId | No | FK → users |
| `subtotal` | decimal(12,2) | No | Default 0.00 |
| `fees` | decimal(12,2) | No | Default 0.00 |
| `shipping` | decimal(12,2) | No | Default 0.00 |
| `grand_total` | decimal(12,2) | No | Default 0.00 |
| `billing_*` | string | Yes | 10 snapshot columns — all nullable |
| `shipping_*` | string | Yes | 10 snapshot columns — all nullable |
| `shipped_at` | timestamp | Yes | NULL for in-store pickup |
| `shipped_by` | foreignId | Yes | FK → users |
| `delivered_at` | timestamp | Yes | |
| `delivered_by` | foreignId | Yes | FK → users |
| `cancelled_at` | timestamp | Yes | Reused for refunded state too |
| `cancelled_by` | foreignId | Yes | FK → users |

### `order_lines` key columns
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `order_id` | foreignId | No | FK → orders, cascade delete |
| `product_listing_id` | foreignId | No | FK → product_listings — reporting link to the listing sold |
| `sku` | string(100) | No | Snapshot from listing→product.sku |
| `product_name` | string(255) | No | Snapshot from listing→product.name |
| `inventory_serial_id` | foreignId | Yes | NULL when back-ordered. Unique when set |
| `unit_price` | decimal(10,2) | No | |
| `tax_rate` | decimal(6,4) | No | Default 0.0000 |
| `tax_amount` | decimal(10,2) | No | Default 0.00 |
| `line_total` | decimal(10,2) | No | unit_price + tax_amount |

### `order_events` key columns
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `order_id` | foreignId | No | FK → orders, cascade delete |
| `event` | string(50) | No | See event enum below |
| `metadata` | json | Yes | Payload per event — NULL for events with no data |
| `created_by` | foreignId | No | FK → users — admin who triggered the transition |
| `created_at` | timestamp | No | Append-only — no `updated_at` column |

**Event values:**
| Event | Triggered by | Metadata |
|-------|-------------|----------|
| `order_placed` | `store()` | `sku`, `product_name`, `grand_total` |
| `payment_received` | `recordCashPayment()` | `method`, `amount`, `subtotal`, `fees`, `shipping` |
| `completed` | `complete()` | `{}` empty |
| `shipped` | `ship()` | `carrier`, `tracking`, `shipped_at` |
| `delivered` | `markDelivered()` | `delivered_at` |
| `cancelled` | `cancel()` | `reason` |
| `back_order_created` | `store()` when serial=NULL | `sku`, `product_name` |
| `serial_assigned` | `assignSerial()` | `serial_number` |

> Only the first 3 events apply to Ex-19. Full event list lives in [order-events.md](../system-design/examples/order-events.md).

---

### `order_fees` key columns
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `order_id` | foreignId | No | FK → orders, cascade delete |
| `name` | string(100) | No | e.g. `Service Fee` |
| `amount` | decimal(10,2) | No | |

### `payments` key columns
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `order_id` | foreignId | No | FK → orders — always set |
| `payable_type` | string | No | Polymorphic |
| `payable_id` | bigint | No | Polymorphic |
| `method` | string(30) | No | `cash / stripe_card / stripe_terminal / ...` |
| `amount` | decimal(12,2) | No | |
| `status` | string(10) | No | `paid / pending / expired` |
| `cash_received_at` | timestamp | Yes | Set for cash payments only |
| `stripe_payment_intent_id` | string | Yes | |
| `stripe_charge_id` | string | Yes | |
| `stripe_terminal_reader_id` | string | Yes | |
| `stripe_checkout_session_id` | string | Yes | |
| `cheque_number` | string | Yes | |
| `cheque_date` | date | Yes | |
| `paid_at` | timestamp | Yes | Set for cheque + stripe_checkout only |
| `paid_by` | foreignId | Yes | FK → users |
| `created_by` | foreignId | No | FK → users |

---

## Enums

### `app/Enums/OrderStatus.php`
| Case | Value | Meaning |
|------|-------|---------|
| `Pending` | `pending` | Created — awaiting payment or serial |
| `BackOrdered` | `back_ordered` | Stock not in — serial not yet assigned |
| `Processing` | `processing` | Payment paid + all serials assigned |
| `Shipped` | `shipped` | Carrier has the package |
| `Complete` | `complete` | In-store pickup — unit handed to customer |
| `Cancelled` | `cancelled` | Cancelled before shipment/pickup |
| `Refunded` | `refunded` | Full refund issued |
| `Rts` | `rts` | Return-to-sender |

### `app/Enums/OrderSource.php`
| Case | Value |
|------|-------|
| `Online` | `online` |
| `WalkIn` | `walk_in` |
| `Phone` | `phone` |

### `app/Enums/PaymentMethod.php`
| Case | Value |
|------|-------|
| `Cash` | `cash` |
| `StripeCard` | `stripe_card` |
| `StripeTerminal` | `stripe_terminal` |
| `StripeCheckout` | `stripe_checkout` |
| `Cheque` | `cheque` |

### `app/Enums/PaymentStatus.php`
| Case | Value |
|------|-------|
| `Unpaid` | `unpaid` |
| `Paid` | `paid` |

---

## Models

### `Order`
```php
// Relationships
belongsTo Customer
belongsTo User (created_by)
belongsTo User (shipped_by)
belongsTo User (cancelled_by)
hasMany OrderLine
hasMany OrderFee
hasMany Payment

// Casts
status        → OrderStatus
source        → OrderSource
payment_status → PaymentStatus
```

### `OrderLine`
```php
belongsTo Order
belongsTo ProductListing          // reporting link — listing sold
belongsTo InventorySerial (nullable — NULL when back-ordered)
```

### `OrderFee`
```php
belongsTo Order
```

### `Payment`
```php
morphTo payable    (order or replacement)
belongsTo Order    (order_id — always set)
belongsTo User     (created_by)

// Casts
method → PaymentMethod
status → PaymentStatus
```

> Morph map required — register in `AppServiceProvider::boot()`:
> `Relation::enforceMorphMap(['order' => Order::class])`
> This stores `"order"` as `payable_type`, not `"App\Models\Order"` — consistent across all system-design examples.

### `OrderEvent`
```php
belongsTo Order
belongsTo User (created_by)

// Cast
metadata → array
```

> No `updated_at` — append-only. Never update or delete rows from this table.

---

## Factories

```
database/factories/OrderFactory.php
database/factories/OrderLineFactory.php
database/factories/OrderFeeFactory.php
database/factories/PaymentFactory.php
database/factories/OrderEventFactory.php
```

Each factory must support states for all status values so tests can set up any scenario:

```php
// OrderFactory states
->pending()
->processing()
->complete()
->walkin()
->cash()
```

---

## Permissions

Add to `app/Enums/Permission.php`:

```php
ViewOrders      = 'view-orders'
CreateOrders    = 'create-orders'
ManageOrders    = 'manage-orders'   // edit, delete, cash payment, complete
```

### `OrderPolicy` — method → permission mapping

| Policy method | Permission required | Extra guard |
|--------------|---------------------|-------------|
| `viewAny` | `ViewOrders` | — |
| `view` | `ViewOrders` | — |
| `create` | `CreateOrders` | — |
| `update` | `ManageOrders` | `status = pending` |
| `delete` | `ManageOrders` | `status = pending` |
| `recordCashPayment` | `ManageOrders` | `payment_status = unpaid` |
| `complete` | `ManageOrders` | `status = processing` |

---

## Routes

Add inside the admin auth middleware group in `web.php`:

```php
Route::resource('orders', OrderController::class);
Route::post('orders/{order}/cash-payment', [OrderController::class, 'recordCashPayment'])
    ->name('orders.cash-payment');
Route::post('orders/{order}/complete', [OrderController::class, 'complete'])
    ->name('orders.complete');
```

Route names produced by resource:
```
orders.index
orders.create
orders.store
orders.show
orders.edit
orders.update
orders.destroy
orders.cash-payment
orders.complete
```
