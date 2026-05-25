# Order Module — Enums, Models, FormRequests, Services

> **READ [`00-rules.md`](00-rules.md) FIRST.** Column names come from [`01-schema.md`](01-schema.md) — never invent.
> This file describes **behaviour**, not implementation. Implementation comes from spec + pattern files in `skills/references/`.

---

## ASK Triggers (specific to this file)

| # | Trigger | Question |
|---|---------|----------|
| 1 | About to add a method to a Model that is not in the Model Behaviour table below | "Add method `X` to Model `Y`? What is its responsibility?" |
| 2 | About to add a column to `$fillable` not in [`01-schema.md`](01-schema.md) | "`X` is in fillable but not in schema. Add to schema or remove from fillable?" |
| 3 | About to add a validation rule for a request key not in the FormRequest Field Map | "Field `X` not in CreateOrderRequest map — what is its source-of-truth column?" |
| 4 | About to write a service method body without `DB::transaction()` despite multi-table writes | "Method `X` writes to N tables. Wrap in transaction? (yes for multi-table)" |
| 5 | About to throw an `\Exception` instead of `\DomainException` | "Failure is expected business logic — should be `DomainException`. Confirm." |

---

## Section A — Enums

> Files to create: `app/Enums/<Name>.php`. Pattern: PHP 8.1 backed string enum with `label()` method.
> Existing enums (do not recreate): `SerialStatus`, `MovementType`.

### `OrderStatus` (file: `app/Enums/OrderStatus.php`)

| Case | Value | Label |
|------|-------|-------|
| Pending | `pending` | Pending |
| Processing | `processing` | Processing |
| Shipped | `shipped` | Shipped |
| Complete | `complete` | Complete |
| Cancelled | `cancelled` | Cancelled |
| Refunded | `refunded` | Refunded |
| BackOrdered | `back_ordered` | Back Ordered |
| Rts | `rts` | Return to Sender |

### `OrderSource` (file: `app/Enums/OrderSource.php`)

| Case | Value | Label |
|------|-------|-------|
| Online | `online` | Online |
| WalkIn | `walk_in` | Walk-In |
| Phone | `phone` | Phone |

### `PaymentMethod` (file: `app/Enums/PaymentMethod.php`)

| Case | Value | Label |
|------|-------|-------|
| Cash | `cash` | Cash |
| StripeCard | `stripe_card` | Stripe Card |
| StripeTerminal | `stripe_terminal` | Stripe Terminal |
| StripeCheckout | `stripe_checkout` | Stripe Checkout |
| Cheque | `cheque` | Cheque |

### `PaymentStatus` (file: `app/Enums/PaymentStatus.php`)

| Case | Value | Label |
|------|-------|-------|
| Unpaid | `unpaid` | Unpaid |
| Paid | `paid` | Paid |

### `ShipmentStatus` (file: `app/Enums/ShipmentStatus.php`)

| Case | Value | Label |
|------|-------|-------|
| InTransit | `in_transit` | In Transit |
| Delivered | `delivered` | Delivered |
| Returned | `returned` | Returned |

**Reference:** PHP enum pattern in `skills/references/code-style.md`.

---

## Section B — Models

> Files to create: `app/Models/<Name>.php`. All extend `Illuminate\Database\Eloquent\Model`.
> All have `use HasFactory;` (required by factories — see [`04-tests.md`](04-tests.md)).
> Column names come from [`01-schema.md`](01-schema.md). If a column is not there, STOP and ask.

### Model behaviour matrix

| Model | $fillable (source of truth: 01-schema.md) | Casts | Relationships |
|-------|-------------------------------------------|-------|---------------|
| `Order` | All columns from `orders` table EXCEPT `id`, `created_at`, `updated_at`. **Exclude** `core_charges` (not in schema). | `status` → `OrderStatus::class`, `source` → `OrderSource::class`, `subtotal/fees/shipping/grand_total` → `'decimal:2'`, `shipped_at/delivered_at/cancelled_at` → `'datetime'` | `customer` BelongsTo Customer, `creator` BelongsTo User(`created_by`), `lines` HasMany OrderLine, `orderFees` HasMany OrderFee, `payments` HasMany Payment, `shipments` MorphMany Shipment(`shippable`) |
| `OrderLine` | `order_id`, `sku`, `product_name`, `inventory_serial_id`, `unit_price`, `tax_rate`, `tax_amount`, `line_total` | `unit_price/tax_amount/line_total` → `'decimal:2'`, `tax_rate` → `'decimal:4'` | `order` BelongsTo Order, `serial` BelongsTo InventorySerial(`inventory_serial_id`) |
| `OrderFee` | `order_id`, `name`, `amount` | `amount` → `'decimal:2'` | `order` BelongsTo Order |
| `Payment` | All columns from `payments` table EXCEPT `id/timestamps` | `method` → `PaymentMethod::class`, `status` → `PaymentStatus::class`, `amount` → `'decimal:2'`, `cheque_date` → `'date'`, `cash_received_at/paid_at` → `'datetime'` | `payable` MorphTo, `order` BelongsTo Order |
| `Shipment` | All columns from `shipments` table EXCEPT `id/timestamps` | `status` → `ShipmentStatus::class`, `label_cost` → `'decimal:2'`, `shipped_at/returned_at/delivered_at` → `'datetime'` | `shippable` MorphTo, `address` BelongsTo CustomerAddress(`customer_address_id`) |

### Required additions (cross-cutting)

| Where | What | Why |
|-------|------|-----|
| `app/Providers/AppServiceProvider.php::boot()` | Register `Relation::morphMap(['order' => Order::class])` | `Order::shipments()` uses MorphMany; `Shipment::shippable_type` stores `'order'`. Without morph map → `ModelNotFoundException` in `markDelivered()` |
| `app/Models/CustomerAddress.php::$fillable` | Add `'customer_id'`, `'is_default'` | `OrderService::resolveAddress()` mass-assigns both. Without them Eloquent silently drops them and the insert fails. |

**Reference:** [`skills/references/model.md`](../../skills/references/model.md#full-production-model-pattern) · [`skills/references/model.md`](../../skills/references/model.md#fillable--mass-assignment-rules) · [`skills/references/model.md`](../../skills/references/model.md#relations--always-typed).

---

## Section C — FormRequests

> Files: `app/Http/Requests/Order/<Name>.php`. All extend `Illuminate\Foundation\Http\FormRequest`.
> Every FormRequest below has an `authorize()` that delegates to the policy and a `rules()` that returns an array.

---

### C1 — `CreateOrderRequest`

**File:** `app/Http/Requests/Order/CreateOrderRequest.php`
**authorize():** `$this->user()->can('create', Order::class)`

#### Field map (request key → DB column)

| Request key | DB column (01-schema.md) | Notes |
|-------------|--------------------------|-------|
| `customer_id` | `orders.customer_id` | direct |
| `source` | `orders.source` | enum value |
| `shipping_amount` | `orders.shipping` | **renamed at write** — request key has `_amount` suffix; DB column does not |
| `lines[].serial_id` | `order_lines.inventory_serial_id` | renamed at write |
| `lines[].unit_price` | `order_lines.unit_price` | direct |
| `lines[].tax_rate` | `order_lines.tax_rate` | nullable — AvaTax may compute server-side |
| `fees[].name` | `order_fees.name` | one row per item |
| `fees[].amount` | `order_fees.amount` | one row per item |
| `billing_same_as_shipping` | (control flag — not stored) | when true, billing snapshot = shipping snapshot |
| `billing.address_id` | uses existing `customer_addresses.id` to look up snapshot fields | |
| `billing.first_name … billing.country` | `orders.billing_first_name … billing_country` | 10-column snapshot block |
| `shipping.address_id` | uses existing `customer_addresses.id` to look up snapshot fields | |
| `shipping.first_name … shipping.country` | `orders.shipping_first_name … shipping_country` | 10-column snapshot block |

Any key NOT in this table is **forbidden** — do not validate, do not pass to service.

#### Rules

| Key | Rules |
|-----|-------|
| `customer_id` | `required` `integer` `exists:customers,id` |
| `source` | `required` `string` `Rule::enum(OrderSource::class)` |
| `shipping_amount` | `required` `numeric` `min:0` |
| `lines` | `required` `array` `min:1` |
| `lines.*.serial_id` | `required` `integer` `exists:inventory_serials,id` `distinct` |
| `lines.*.unit_price` | `required` `numeric` `min:0` |
| `lines.*.tax_rate` | `nullable` `numeric` `min:0` |
| `fees` | `nullable` `array` |
| `fees.*.name` | `required_with:fees` `string` `max:100` |
| `fees.*.amount` | `required_with:fees` `numeric` `min:0` |
| `billing_same_as_shipping` | `nullable` `boolean` |
| `billing.address_id` | `nullable` `integer` `exists:customer_addresses,id` |
| `billing.first_name` | `nullable` `string` `max:100` |
| `billing.last_name` | `nullable` `string` `max:100` |
| `billing.email` | `nullable` `email` `max:255` |
| `billing.phone` | `nullable` `string` `max:30` |
| `billing.line1` | `nullable` `string` `max:255` |
| `billing.line2` | `nullable` `string` `max:255` |
| `billing.city` | `nullable` `string` `max:100` |
| `billing.state` | `nullable` `string` `max:10` |
| `billing.postal_code` | `nullable` `string` `max:20` |
| `billing.country` | `nullable` `string` `size:2` |
| `shipping.address_id` | `nullable` `integer` `exists:customer_addresses,id` |
| `shipping.first_name` | `nullable` `string` `max:100` |
| `shipping.last_name` | `nullable` `string` `max:100` |
| `shipping.email` | `nullable` `email` `max:255` |
| `shipping.phone` | `nullable` `string` `max:30` |
| `shipping.line1` | `nullable` `string` `max:255` |
| `shipping.line2` | `nullable` `string` `max:255` |
| `shipping.city` | `nullable` `string` `max:100` |
| `shipping.state` | `nullable` `string` `max:10` |
| `shipping.postal_code` | `nullable` `string` `max:20` |
| `shipping.country` | `nullable` `string` `size:2` |

#### prepareForValidation()
Cast `customer_id` to int. No other normalisation.

---

### C2 — `RecordCashPaymentRequest`

**File:** `app/Http/Requests/Order/RecordCashPaymentRequest.php`
**authorize():** `$this->user()->can('pay', $this->route('order'))`

| Key | Rules |
|-----|-------|
| `amount` | `required` `numeric` `min:0.01` |
| `cash_received_at` | `required` `date` |

---

### C3 — `ShipOrderRequest`

**File:** `app/Http/Requests/Order/ShipOrderRequest.php`
**authorize():** `$this->user()->can('ship', $this->route('order'))`

| Key | Rules |
|-----|-------|
| `carrier` | `required` `string` `max:100` |
| `tracking` | `required` `string` `max:100` |
| `label_cost` | `required` `numeric` `min:0` |
| `shipped_at` | `required` `date` |

---

### C4 — `DeliverOrderRequest`

**File:** `app/Http/Requests/Order/DeliverOrderRequest.php`
**authorize():** `$this->user()->can('deliver', $this->route('order'))`

| Key | Rules |
|-----|-------|
| `delivered_at` | `required` `date` |

---

### C5 — `UpdateOrderRequest`
See [`05-update-cancel-delete.md`](05-update-cancel-delete.md) §4.

**Reference:** [`skills/references/form-request.md`](../../skills/references/form-request.md#anatomy-of-a-formrequest) · [`skills/references/form-request.md`](../../skills/references/form-request.md#validation-rules--full-reference) · feedback memory `feedback_form_request_patterns.md`.

---

## Section D — OrderService

**File:** `app/Services/OrderService.php`
Constructor-injected via Laravel container — no manual `new` ever.

> Every public method that writes to more than one table is wrapped in `DB::transaction()`.
> Every business-rule guard (e.g. status check) lives **inside** the transaction (TOCTOU).
> Every expected business failure throws `\DomainException` — controller catches and flashes to user.

---

### D1 — `paginate(array $filters): LengthAwarePaginator`

**Inputs:** `$filters = ['search' => string|null, 'status' => string|null]`
**Reads:** `orders` with eager-loaded `customer`, `lines`.
**Returns:** Length-aware paginator (20 per page) with `withQueryString()`.

#### Filter behaviour

| Filter | Behaviour |
|--------|-----------|
| `status` empty/missing | No filter |
| `status` non-empty | `where('status', $status)` |
| `search` empty/missing | No filter |
| `search` non-empty | `where('number', 'like', "%X%")` OR `whereHas('customer', ...)` matching name |

Ordered: `latest()` (by `created_at DESC`).

---

### D2 — `create(array $data, User $authActor): Order`

**Wrapping:** `DB::transaction(...)` — whole body.

#### Step list (ordered)

| # | Step | Notes |
|---|------|-------|
| 1 | Resolve shipping address | `resolveAddress($data['customer_id'], $data['shipping'] ?? [])` returns `?CustomerAddress` |
| 2 | Resolve billing address | If `billing_same_as_shipping = true`, reuse shipping. Else `resolveAddress(... $data['billing'] ?? [])` |
| 3 | Build line rows | `buildLines($data['lines'])` — see D7 — applies tax rule |
| 4 | Compute subtotal | `array_sum(array_column($lineRows, 'line_total'))` — tax already inside |
| 5 | Compute fee total | `array_sum(array_column($data['fees'] ?? [], 'amount'))` |
| 6 | Compute shipping | `(float) $data['shipping_amount']` — note rename |
| 7 | Generate order number | `nextOrderNumber()` — see D6 |
| 8 | Insert `orders` row | All 10 billing_* + 10 shipping_* columns written via snapshot helpers — even when address is null (helpers null-safe) |
| 9 | Insert `order_lines` rows | One per built row, all with `order_id` |
| 10 | Insert `order_fees` rows | One per `$data['fees']` item |
| 11 | Return the created `Order` | Not `fresh()` — caller decides |

#### Required column values on the new `orders` row

| Column | Value |
|--------|-------|
| `number` | from `nextOrderNumber()` |
| `customer_id` | `$data['customer_id']` |
| `source` | `$data['source']` |
| `status` | `OrderStatus::Pending` |
| `payment_status` | `'unpaid'` |
| `created_by` | `$authActor->id` |
| `subtotal` | sum of line totals (tax-inclusive) |
| `fees` | sum of fee amounts |
| `shipping` | `(float) $data['shipping_amount']` |
| `grand_total` | `subtotal + fees + shipping` — **no separate tax addition** |
| `currency` | `'USD'` |
| `billing_*` (10 cols) | from `billingSnapshot($billingAddr)` — null if no address |
| `shipping_*` (10 cols) | from `shippingSnapshot($shippingAddr)` — null if no address |

#### Errors

| Trigger | Exception | Reason |
|---------|-----------|--------|
| Customer not found | `ModelNotFoundException` | bubbles to 404 at controller |
| Serial assigned to two lines | DB unique violation on `order_lines.inventory_serial_id` | DB-level oversell guard |
| AvaTax failure (when integrated) | `\DomainException` thrown by `AvaTaxService` | Caught and flashed at controller |

---

### D3 — `recordCashPayment(Order $order, array $data, User $authActor): Payment`

**Wrapping:** `DB::transaction(...)` — multi-table write (payments + orders).

#### Step list

| # | Step |
|---|------|
| 1 | Insert `payments` row: `order_id`, `payable_type=Order::class`, `payable_id=$order->id`, `method=PaymentMethod::Cash`, `amount=$data['amount']`, `status=PaymentStatus::Paid`, `created_by=$authActor->id`, `currency='USD'`, `cash_received_at=$data['cash_received_at']` |
| 2 | Update order: `payment_status='paid'`, `status=OrderStatus::Processing` |
| 3 | Return the new `Payment` |

#### Errors

| Trigger | Exception |
|---------|-----------|
| Order already paid | `\DomainException` — "Order already paid." — guard inside transaction |

---

### D4 — `ship(Order $order, array $data, User $authActor): Order`

**Wrapping:** `DB::transaction(...)` — writes shipments, inventory_movements, inventory_serials, orders.

#### Step list

| # | Step |
|---|------|
| 1 | Guard: `$order->status === OrderStatus::Processing` — else `\DomainException` "Only processing orders can ship." |
| 2 | Guard: every `$order->lines` has `inventory_serial_id` set — else `\DomainException` "Back-ordered lines must be fulfilled first." |
| 3 | Resolve shipment address: `resolveShipmentAddressId($order)` — see D8 |
| 4 | Insert `shipments` row: `shippable_type=Order::class`, `shippable_id=$order->id`, `customer_address_id=$addressId`, `direction='outbound'`, `carrier=$data['carrier']`, `tracking=$data['tracking']`, `label_cost=$data['label_cost']`, `status=ShipmentStatus::InTransit`, `created_by=$authActor->id`, `shipped_at=$data['shipped_at']` |
| 5 | For each line: lock serial row (`lockForUpdate()`), insert `inventory_movements` row (`type=MovementType::Sale`, `from_location_id=$serial->inventory_location_id`, `to_location_id=null`, `reference=$order->number`, `user_id=$authActor->id`), then update serial: `status=SerialStatus::Sold`, `inventory_location_id=null` |
| 6 | Update order: `status=OrderStatus::Shipped`, `shipped_at=$data['shipped_at']`, `shipped_by=$authActor->id` |
| 7 | Return the `Order` |

---

### D5 — `markDelivered(Order $order, array $data, User $authActor): Order`

**Wrapping:** `DB::transaction(...)` — writes shipments + orders.

#### Step list

| # | Step |
|---|------|
| 1 | Guard: `$order->status === OrderStatus::Shipped` — else `\DomainException` "Only shipped orders can be marked delivered." |
| 2 | Get latest outbound shipment: `$order->shipments()->where('direction', 'outbound')->latest()->firstOrFail()` — relies on morph map for `'order' => Order::class` |
| 3 | Update shipment: `status=ShipmentStatus::Delivered`, `delivered_at=$data['delivered_at']`, `delivered_by=$authActor->id` |
| 4 | Update order: `delivered_at=$data['delivered_at']`, `delivered_by=$authActor->id` |
| 5 | Return the `Order` |

---

### D6 — `taxPreview(array $payload): array` (AJAX endpoint)

**Wrapping:** None — read-only.
**Inputs:** Same shape as `CreateOrderRequest` (lines + customer_id + shipping address) but partial — used live as the form is built.
**Returns:** `['lines' => [['line_total' => float, 'tax_amount' => float, 'tax_rate' => float], ...], 'subtotal' => float, 'tax_total' => float]`
**Calls:** `AvaTaxService::calculateTax($payload)` — see `app/Services/AvaTaxService.php`.

> Used by `create.blade.php` to display real-time AvaTax preview. Replaces user-editable tax fields with a read-only display.

---

### D7 — Private: `nextOrderNumber(): string`

**Wrapping:** Caller's transaction (already inside `create()`).

| Step |
|------|
| 1. Lock current sequence row: `DB::table('sequences')->where('name', 'orders')->lockForUpdate()->value('value')` |
| 2. Increment: `$next = $current + 1` |
| 3. Update: `DB::table('sequences')->where('name', 'orders')->update(['value' => $next])` |
| 4. Return: `sprintf('ORD-%d-%04d', now()->year, $next)` |

---

### D8 — Private: `buildLines(array $lines): array`

For each line item:

| Step |
|------|
| 1. Load serial with product: `InventorySerial::with('product')->findOrFail($line['serial_id'])` |
| 2. Compute tax: `$taxAmount = round($unitPrice * $taxRate, 2)` |
| 3. Build row with keys: `inventory_serial_id`, `sku` (from `$serial->product->sku ?? ''`), `product_name` (from `$serial->product->name ?? ''`), `unit_price`, `tax_rate`, `tax_amount`, `line_total = round($unitPrice + $taxAmount, 2)` |

---

### D9 — Private: `resolveAddress(int $customerId, array $address): ?CustomerAddress`

| Input | Returns |
|-------|---------|
| `$address['address_id']` set | `CustomerAddress::findOrFail($address['address_id'])` |
| `$address['line1']` set (inline) | New `CustomerAddress::create([...])` with all 10 fields + `customer_id`, `label='Delivery'`, `is_default=false` |
| Neither | `null` |

---

### D10 — Private: `shippingSnapshot(?CustomerAddress $address): array`

Returns array of 10 keys: `shipping_first_name`, `shipping_last_name`, `shipping_email`, `shipping_phone`, `shipping_address_line1`, `shipping_address_line2`, `shipping_city`, `shipping_state`, `shipping_postal_code`, `shipping_country`.

Each value uses null-safe accessor: `$address?->first_name` etc. So when `$address` is null, all 10 keys exist with value `null` — call sites always spread the full set into `Order::create([...])`.

### D11 — Private: `billingSnapshot(?CustomerAddress $address): array`

Same shape as `shippingSnapshot` but with `billing_` prefix on every key.

### D12 — Private: `resolveShipmentAddressId(Order $order): ?int`

If `$order->shipping_address_line1` is null → return null.
Else look up: `CustomerAddress::where('customer_id', $order->customer_id)->where('address_line1', $order->shipping_address_line1)->where('postal_code', $order->shipping_postal_code)->value('id')`.

---

## Section E — AvaTaxService (referenced by D6 + D2)

**File:** `app/Services/AvaTaxService.php` (already exists per memory observation 4501).
**Registered as singleton** in `AppServiceProvider`.

| Method | Inputs | Returns |
|--------|--------|---------|
| `calculateTax(array $payload): array` | Lines + shipping address | `['lines' => [...with tax_rate, tax_amount...], 'tax_total' => float]` |

> Tax `_rate` and `_amount` returned per line must be persisted to `order_lines.tax_rate` / `tax_amount`. Field map already in [`01-schema.md`](01-schema.md).

**Reference:** `config/services.php` for AvaTax config; `.env.example` for required keys.

---

## Section F — Migrations needed (Ex-2)

| # | Migration | Depends on |
|---|-----------|------------|
| 1 | `create_orders_table` | `customers`, `users` |
| 2 | `create_order_lines_table` | `orders`, `inventory_serials` |
| 3 | `create_order_fees_table` | `orders` |
| 4 | `create_payments_table` | `orders`, `users` (via polymorphic) |
| 5 | `create_shipments_table` | (polymorphic on shippable), `customer_addresses`, `users` |
| 6 | `add_orders_to_sequences` | `sequences` — insertOrIgnore row `name='orders', value=0` |

Migration column definitions: refer to [`01-schema.md`](01-schema.md) Tables section. Each migration mirrors that table exactly — no extra columns, no missing columns.

**Reference:** [`skills/references/database.md`](../../skills/references/database.md) · feedback `feedback_laravel_migration_types.md` (no `unsignedDecimal()` — use `decimal()->unsigned()`).

---

**Reference:** [`skills/references/service.md`](../../skills/references/service.md#full-service-pattern) · [`skills/references/service.md`](../../skills/references/service.md#dbtransaction--non-negotiable-for-multi-table-writes) · [`skills/references/service.md`](../../skills/references/service.md#toctou--business-rule-guards-must-be-inside-the-transaction) · [`skills/references/service.md`](../../skills/references/service.md#throw-domainexception-for-expected-business-failures) · feedback `feedback_service_patterns.md`.
