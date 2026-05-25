# TDD-Order — Schema (Single Source of Truth)

> All view templates, service methods, tests, and FormRequests MUST use column names from this file.
> Never invent aliases. Never use a name not listed here.

---

## Table: `orders`

| Column | Type | Nullable | Cast in Model | Notes |
|---|---|---|---|---|
| `id` | bigint unsigned | no | — | PK |
| `number` | varchar(20) | no | string | format: `ORD-YYYY-NNNN` |
| `customer_id` | bigint unsigned | no | — | FK → customers |
| `source` | varchar(20) | no | `OrderSource` enum | see enum table below |
| `status` | varchar(30) | no | `OrderStatus` enum | default `pending` |
| `payment_status` | varchar(10) | no | string | `unpaid` or `paid` |
| `created_by` | bigint unsigned | no | — | FK → users |
| `subtotal` | decimal(12,2) | no | `decimal:2` | sum of line_total (incl. tax) |
| `fees` | decimal(12,2) | no | `decimal:2` | sum of order_fees.amount |
| `core_charges` | decimal(12,2) | no | `decimal:2` | default 0 — future use |
| `shipping` | decimal(12,2) | no | `decimal:2` | shipping cost |
| `grand_total` | decimal(12,2) | no | `decimal:2` | subtotal + fees + shipping |
| `currency` | char(3) | no | string | default `USD` |
| `billing_first_name` | varchar(100) | yes | string | NULL for cash orders |
| `billing_last_name` | varchar(100) | yes | string | |
| `billing_email` | varchar(255) | yes | string | |
| `billing_phone` | varchar(30) | yes | string | |
| `billing_address_line1` | varchar(255) | yes | string | |
| `billing_address_line2` | varchar(255) | yes | string | |
| `billing_city` | varchar(100) | yes | string | |
| `billing_state` | varchar(10) | yes | string | |
| `billing_postal_code` | varchar(20) | yes | string | |
| `billing_country` | char(2) | yes | string | |
| `shipping_first_name` | varchar(100) | yes | string | NULL for pickup orders |
| `shipping_last_name` | varchar(100) | yes | string | |
| `shipping_email` | varchar(255) | yes | string | |
| `shipping_phone` | varchar(30) | yes | string | |
| `shipping_address_line1` | varchar(255) | yes | string | |
| `shipping_address_line2` | varchar(255) | yes | string | |
| `shipping_city` | varchar(100) | yes | string | |
| `shipping_state` | varchar(10) | yes | string | |
| `shipping_postal_code` | varchar(20) | yes | string | |
| `shipping_country` | char(2) | yes | string | |
| `shipped_at` | timestamp | yes | `datetime` | |
| `shipped_by` | bigint unsigned | yes | — | FK → users |
| `delivered_at` | timestamp | yes | `datetime` | set on delivery; status stays `shipped` |
| `delivered_by` | bigint unsigned | yes | — | FK → users |
| `cancelled_at` | timestamp | yes | `datetime` | |
| `cancelled_by` | bigint unsigned | yes | — | FK → users |
| `created_at` | timestamp | yes | `datetime` | |
| `updated_at` | timestamp | yes | `datetime` | |

### Prohibited aliases — NEVER use these in any file

| Wrong | Correct |
|---|---|
| `$order->fees_total` | `$order->fees` |
| `$order->shipping_amount` | `$order->shipping` |
| `$order->tax_total` | does not exist — tax is in subtotal |
| `$order->address` | does not exist — use snapshot columns |

---

## Table: `order_lines`

| Column | Type | Nullable | Cast | Notes |
|---|---|---|---|---|
| `id` | bigint unsigned | no | — | PK |
| `order_id` | bigint unsigned | no | — | FK → orders |
| `sku` | varchar(100) | no | string | snapshot at creation |
| `product_name` | varchar(255) | no | string | snapshot at creation |
| `inventory_serial_id` | bigint unsigned | yes | — | FK → inventory_serials; unique; NULL = backorder |
| `unit_price` | decimal(10,2) | no | `decimal:2` | |
| `tax_rate` | decimal(6,4) | no | `decimal:4` | stored as ratio e.g. `0.0825` |
| `tax_amount` | decimal(10,2) | no | `decimal:2` | |
| `line_total` | decimal(10,2) | no | `decimal:2` | `unit_price + tax_amount` |

**Relationship:** `$line->serial` → `InventorySerial` (via `inventory_serial_id`)
**Relationship:** `$line->serial->product` → `Product`

---

## Table: `order_fees`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | bigint unsigned | no | PK |
| `order_id` | bigint unsigned | no | FK → orders |
| `name` | varchar(100) | no | e.g. "Service Fee" |
| `amount` | decimal(10,2) | no | |

**Relationship on Order model:** `$order->orderFees()` → HasMany (NOT `$order->fees` — that is the decimal column)

---

## Table: `sequences`

Shared counter table — not order-specific, but required for order number generation.

| Column | Type | Notes |
|---|---|---|
| `name` | varchar(50) | PK |
| `value` | bigint unsigned | last used value; next = value + 1 |

**Seed row required:** `INSERT INTO sequences (name, value) VALUES ('orders', 0)`

**Usage in OrderService:** `nextOrderNumber()` reads this row with `lockForUpdate()`, increments, returns `sprintf('ORD-%d-%04d', now()->year, $next)`

---

## Table: `payments`

| Column | Type | Nullable | Cast | Notes |
|---|---|---|---|---|
| `id` | bigint | no | — | PK |
| `order_id` | bigint | no | — | FK → orders (always set) |
| `payable_type` | string | no | — | `order` |
| `payable_id` | bigint | no | — | mirrors order_id for direct payments |
| `method` | string | no | `PaymentMethod` enum | |
| `amount` | decimal(10,2) | no | `decimal:2` | |
| `status` | string | no | `PaymentStatus` enum | |
| `currency` | char(3) | no | string | `USD` |
| `cash_received_at` | timestamp | yes | `datetime` | set for cash payments |
| `created_by` | bigint | no | — | FK → users |

---

## Enum: `OrderStatus`

| PHP case | DB value | Label | Badge color |
|---|---|---|---|
| `Pending` | `pending` | Pending | yellow |
| `Processing` | `processing` | Processing | blue |
| `Shipped` | `shipped` | Shipped | indigo |
| `Complete` | `complete` | Complete | green |
| `Cancelled` | `cancelled` | Cancelled | red |
| `Refunded` | `refunded` | Refunded | orange |
| `BackOrdered` | `back_ordered` | Back Ordered | purple |
| `Rts` | `rts` | Return to Sender | rose |

---

## Enum: `OrderSource`

| PHP case | DB value | Label |
|---|---|---|
| `Online` | `online` | Online |
| `WalkIn` | `walk_in` | Walk-In |
| `Phone` | `phone` | Phone |

---

## Enum: `PaymentMethod`

| PHP case | DB value | Label |
|---|---|---|
| `Cash` | `cash` | Cash |
| `StripeCard` | `stripe_card` | Stripe Card |
| `StripeTerminal` | `stripe_terminal` | Stripe Terminal |
| `StripeCheckout` | `stripe_checkout` | Stripe Checkout |
| `Cheque` | `cheque` | Cheque |

---

## Enum: `ShipmentStatus`

| PHP case | DB value | Label |
|---|---|---|
| `Pending` | `pending` | Pending |
| `LabelCreated` | `label_created` | Label Created |
| `InTransit` | `in_transit` | In Transit |
| `Delivered` | `delivered` | Delivered |
| `Returned` | `returned` | Returned |
| `Voided` | `voided` | Voided |

---

## Enum: `PaymentStatus`

| PHP case | DB value | Label |
|---|---|---|
| `Pending` | `pending` | Pending |
| `Paid` | `paid` | Paid |
| `Expired` | `expired` | Expired |

---

## Model relationships (eager load paths)

| Purpose | `->load(...)` argument |
|---|---|
| Show page | `['customer', 'lines.serial.product', 'orderFees', 'payments', 'shipments']` |
| Edit page | `['customer', 'lines.serial.product', 'orderFees']` |
| Index page | `['customer', 'lines']` (via paginate with()) |

---

## Grand total formula (never deviate)

```
grand_total = subtotal + fees + shipping
```

- `subtotal` already includes tax (tax is in each `line_total`)
- Do NOT add tax separately to grand_total at any level
- Display label: "Subtotal (incl. tax)"

---

## Order model spec

**Class:** `App\Models\Order`

**`$fillable`:** `number`, `customer_id`, `source`, `status`, `payment_status`, `created_by`, `subtotal`, `fees`, `core_charges`, `shipping`, `grand_total`, `currency`, all 10 `billing_*` snapshot columns, all 10 `shipping_*` snapshot columns, `shipped_at`, `shipped_by`, `delivered_at`, `delivered_by`, `cancelled_at`, `cancelled_by`

**`$casts`:**

| Column | Cast |
|---|---|
| `status` | `OrderStatus::class` |
| `source` | `OrderSource::class` |
| `subtotal` | `decimal:2` |
| `fees` | `decimal:2` |
| `core_charges` | `decimal:2` |
| `shipping` | `decimal:2` |
| `grand_total` | `decimal:2` |
| `shipped_at` | `datetime` |
| `delivered_at` | `datetime` |
| `cancelled_at` | `datetime` |

**Relationships:**

| Method | Type | Notes |
|---|---|---|
| `customer()` | `BelongsTo(Customer)` | |
| `creator()` | `BelongsTo(User, 'created_by')` | |
| `lines()` | `HasMany(OrderLine)` | |
| `orderFees()` | `HasMany(OrderFee)` | NOT `fees` — that is the decimal column |
| `payments()` | `HasMany(Payment)` | no FK constraint — payments outlive orders |
| `shipments()` | `MorphMany(Shipment, 'shippable')` | polymorphic on `shippable_type` / `shippable_id` |

---

## OrderLine model spec

**Class:** `App\Models\OrderLine`

**`$timestamps`:** `false` — table has no `created_at`/`updated_at`

**`$fillable`:** `order_id`, `sku`, `product_name`, `inventory_serial_id`, `unit_price`, `tax_rate`, `tax_amount`, `line_total`

**`$casts`:**

| Column | Cast | Reason |
|---|---|---|
| `unit_price` | `decimal:2` | monetary |
| `tax_rate` | `decimal:4` | ratio e.g. `0.0825` |
| `tax_amount` | `decimal:2` | monetary |
| `line_total` | `decimal:2` | monetary |

**Relationships:**

| Method | Type | Notes |
|---|---|---|
| `order()` | `BelongsTo(Order)` | |
| `serial()` | `BelongsTo(InventorySerial, 'inventory_serial_id')` | nullable — NULL = backorder |

---

## OrderFee model spec

**Class:** `App\Models\OrderFee`

**`$timestamps`:** `false` — table has no `created_at`/`updated_at`

**`$fillable`:** `order_id`, `name`, `amount`

**`$casts`:**

| Column | Cast |
|---|---|
| `amount` | `decimal:2` |

**Relationships:** none — accessed only via `$order->orderFees()`

---

## External tables written by OrderService::ship()

Owned by other modules — full schema in their migration files.
Listed here only for the columns OrderService touches.

### `shipments` (migration: 2026_05_23_000020)

| Column | Type | Value written |
|---|---|---|
| `shippable_type` | varchar(30) | `'order'` |
| `shippable_id` | bigint unsigned | `$order->id` |
| `customer_address_id` | bigint, nullable | from `resolveShipmentAddressId()` — see below |
| `direction` | varchar(10) | `'outbound'` |
| `carrier` | varchar(50), nullable | `$data['carrier']` |
| `tracking` | varchar(100), nullable | `$data['tracking']` |
| `label_cost` | decimal(8,2) | `$data['label_cost']` |
| `status` | varchar(20) | `'in_transit'` |
| `created_by` | bigint | `$user->id` |
| `shipped_at` | timestamp, nullable | `$data['shipped_at']` |
| `delivered_at` | timestamp, nullable | set in `markDelivered()` |
| `delivered_by` | bigint, nullable | set in `markDelivered()` |

### `inventory_serials` (migration: 2026_04_14_181000) — columns mutated on ship

| Column | Before | After |
|---|---|---|
| `status` | `in_stock` | `sold` |
| `inventory_location_id` | int | `null` |

### `inventory_movements` (migration: 2026_04_14_182000) — one row created per order line on ship

| Column | Value |
|---|---|
| `inventory_serial_id` | `$serial->id` |
| `type` | `'sale'` |
| `from_location_id` | `$serial->inventory_location_id` (before null-out) |
| `to_location_id` | `null` |
| `reference` | `$order->number` |
| `user_id` | `$user->id` |

### `resolveShipmentAddressId(Order $order): ?int`

Looks up a saved `CustomerAddress` row matching the order's shipping snapshot:
- If `$order->shipping_address_line1` is null → returns `null` (no-shipping orders)
- Else → `CustomerAddress::where('customer_id', ...)->where('address_line1', ...)->where('postal_code', ...)->value('id')`
- Returns `null` if snapshot address was never saved as a `CustomerAddress` row (new-address orders)
