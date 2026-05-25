# TDD-Order — Service Spec

> Rules for every method in `App\Services\OrderService`.
> No code blocks. Rules and constraints only.
> Column names MUST match `01-schema.md` exactly.

---

## Class constraints

- Constructor receives `AvaTaxService $avatax` and `InventoryMovementService $inventoryMovements`
- `$avatax` used only in `create()` and `taxPreview()`
- `$inventoryMovements` used only in `ship()`
- All multi-table writes run inside `DB::transaction()`
- Business rule violations throw `\DomainException` with a user-readable message
- Status checks inside transactions use `lockForUpdate()` on the fresh model
- Return type is always the model (or `void` for delete)

---

## `paginate(array $filters): LengthAwarePaginator`

**Eager loads:** `['customer', 'lines']`
**Filters:**
- `search` — LIKE match on `orders.number` OR `customers.name` (join required)
- `status` — exact match on `orders.status`
**Pagination:** 20 per page
**Order:** `orders.created_at DESC`

---

## `create(array $data, User $user): Order`

**Input keys (from CreateOrderRequest validated output):**
- `customer_id`, `source`, `shipping` (optional array with `address_id` or inline fields), `lines` (array), `fees` (optional array)

**Steps — inside `DB::transaction()`:**
1. Generate `number` via `nextOrderNumber()` (private): reads `sequences` table row `name='orders'` with `lockForUpdate()`, increments value by 1, returns `sprintf('ORD-%d-%04d', now()->year, $next)` — global monotonic counter, never resets daily
2. Resolve shipping address via `resolveAddress($data['shipping'] ?? [], $data['customer_id'])` → `?CustomerAddress`
3. Build snapshot arrays via `shippingSnapshot(?CustomerAddress)` → all `shipping_*` columns; `billingSnapshot(?CustomerAddress)` → all `billing_*` columns (always NULL for this module — cash only)
4. Call `$this->avatax->calculateTax($data['lines'], $shippingAddr)` → returns array keyed by line index with `tax_rate` and `tax_amount`
5. Call `buildLines($data['lines'], $taxData)` → returns array of line arrays
6. Calculate `subtotal` = sum of all `line_total` values from built lines
7. Calculate `fees` = sum of `$data['fees'][*]['amount']` (0 if no fees)
8. `Order::create([...])` with: `number`, `customer_id`, `source`, `status = OrderStatus::Pending`, `payment_status = 'unpaid'`, `created_by = $user->id`, `subtotal`, `fees`, `core_charges = 0`, `shipping = (float) $data['shipping_amount']` — top-level key, NOT nested under shipping array, `grand_total = subtotal + fees + shipping`, `currency = 'USD'`, all snapshot columns
9. Insert `order_lines` rows: `$order->lines()->createMany($builtLines)`
10. Insert `order_fees` rows if any fees provided: `$order->orderFees()->createMany($data['fees'])`
11. Return `$order`

**`buildLines(array $lines, array $taxData): array`**
- Each line must have: `serial_id` (nullable), `unit_price`
- Lookup `InventorySerial` by `serial_id` if provided → get `sku`, `product_name` from `$serial->product`
- `tax_rate` = `$taxData[$i]['tax_rate'] ?? 0`
- `tax_amount` = `$taxData[$i]['tax_amount'] ?? 0`
- `line_total` = `unit_price + tax_amount`
- Returns array with keys: `inventory_serial_id`, `sku`, `product_name`, `unit_price`, `tax_rate`, `tax_amount`, `line_total`

**`resolveAddress(array $data, int $customerId): ?CustomerAddress`**
- If `address_id` present → return `CustomerAddress::find($data['address_id'])` (`customerId` unused)
- Else if inline fields present (`line1` key required to trigger) → `CustomerAddress::create(['customer_id' => $customerId, 'label' => 'Delivery', 'first_name' => ..., all inline fields])` — `label` hardcoded to `'Delivery'`
- Else → return `null`

**Callers:**
- `create()` → passes `$data['customer_id']`
- `update()` → passes `$fresh->customer_id`

**`shippingSnapshot(?CustomerAddress $address): array`**
- Returns array of all `shipping_*` column values
- All values use `$address?->field_name` (null-safe — returns null if address is null)
- Keys: `shipping_first_name`, `shipping_last_name`, `shipping_email`, `shipping_phone`, `shipping_address_line1`, `shipping_address_line2`, `shipping_city`, `shipping_state`, `shipping_postal_code`, `shipping_country`

**`billingSnapshot(?CustomerAddress $address): array`**
- Same structure as shippingSnapshot but with `billing_*` keys
- `create()` always passes `null` → all billing columns NULL (cash-only module, no billing capture at creation)
- `update()` passes `$shippingAddr` when `billing_same_as_shipping` is truthy, or the result of `resolveAddress($customerId, $data['billing'] ?? [])` otherwise — both paths go through this helper (may still be null if no billing address resolved)

---

## `update(Order $order, array $data, User $user): Order`

**Input keys (from UpdateOrderRequest validated output):**
- `source`, `shipping` (optional), `fees` (optional array), all `billing_*` and `shipping_*` nullable fields

**Business rule:** Only `pending` status orders can be updated. Any other status throws `\DomainException('Order cannot be updated in its current status.')`.

**Steps — inside `DB::transaction()`:**
1. `$fresh = Order::lockForUpdate()->find($order->id)` — re-fetch with lock
2. Throw `\DomainException` if `$fresh->status !== OrderStatus::Pending`
3. Resolve new shipping address via `resolveAddress($data['shipping'] ?? [], $fresh->customer_id)`
4. Rebuild shipping snapshot via `shippingSnapshot($shippingAddr)`
5. Rebuild billing snapshot — two cases:
   - If `$data['billing_same_as_shipping']` is truthy → `$billingAddr = $shippingAddr`
   - Otherwise → `$billingAddr = resolveAddress($fresh->customer_id, $data['billing'] ?? [])` (may be null)
   - Always call `billingSnapshot($billingAddr)` — never map flat keys directly
6. Delete existing fees: `$fresh->orderFees()->delete()`
7. Recreate fees from `$data['fees'] ?? []` — if absent or empty, fees are deleted and not replaced; `fees` column becomes `0.00`
8. Recalculate `fees` = sum of new fees amounts (0 if no fees provided)
9. `shipping` = `(float) ($data['shipping_amount'] ?? $fresh->shipping)`
10. `grand_total` = `$fresh->subtotal + fees + shipping` (**subtotal unchanged** — lines are not editable via update)
11. `$fresh->update([source, shipping, fees, grand_total, ...shippingSnapshot, ...billingSnapshot])`
12. Return `$fresh->fresh()`

**Constraint:** Lines are NOT editable via update. `subtotal` is recalculated only from `$fresh->subtotal` (no line re-creation).

---

## `recordCashPayment(Order $order, array $data, User $user): Order`

**Business rule:** Only `pending` orders can receive cash payment. Throws `\DomainException` otherwise.

**Steps — inside `DB::transaction()`:**
1. Lock and re-fetch order
2. Throw if status !== Pending
3. Create `Payment` record: `order_id`, `payable_type='order'`, `payable_id=$order->id`, `method=PaymentMethod::Cash`, `amount=$data['amount']`, `status=PaymentStatus::Paid`, `currency='USD'`, `cash_received_at=$data['cash_received_at']`, `created_by=$user->id`
4. Update order: `payment_status='paid'`, `status=OrderStatus::Processing`
5. Return `$order->fresh()`

---

## `ship(Order $order, array $data, User $user): Order`

**Business rule:** Only `processing` orders can be shipped. Throws `\DomainException` otherwise.

**Steps — inside `DB::transaction()`:**
1. Lock and re-fetch
2. Throw if status !== Processing
3. Create `Shipment` record: `shippable_type='order'`, `shippable_id=$order->id`, `customer_address_id` (from `resolveShipmentAddressId($order)` — nullable int), `direction='outbound'`, `carrier=$data['carrier'] ?? null`, `tracking=$data['tracking'] ?? null`, `label_cost=$data['label_cost'] ?? null`, `status=ShipmentStatus::InTransit`, `created_by=$user->id`, `shipped_at=$data['shipped_at']`
4. For each order line — inventory side effects (inside same transaction):
   - Load: `InventorySerial::with('location')->lockForUpdate()->findOrFail($line->inventory_serial_id)`
   - Call: `$this->inventoryMovements->sale($serial, $serial->location, $user, reference=$order->number)`
   - This single call: creates `InventoryMovement` (type=Sale), sets serial `status=Sold`, sets serial `inventory_location_id=null`
   - `assertSerialInStockAt` inside `sale()` throws `DomainException` if serial not in-stock or wrong location
5. Update order: `status=OrderStatus::Shipped`, `shipped_at=$data['shipped_at']`, `shipped_by=$user->id`
6. Return `$order->fresh()`

**`resolveShipmentAddressId(Order $order): ?int`** (private)
- If `$order->shipping_address_line1` is null → return `null` (no-shipping / pickup orders)
- Else → `CustomerAddress::where('customer_id', $order->customer_id)->where('address_line1', $order->shipping_address_line1)->where('postal_code', $order->shipping_postal_code)->value('id')`
- Returns `null` if snapshot address has no matching saved `CustomerAddress` row (new-address orders)
- Used only by `ship()` to populate `shipments.customer_address_id`

---

## `markDelivered(Order $order, array $data, User $user): Order`

**Business rule:** Only `shipped` orders can be marked delivered. Throws `\DomainException` otherwise.

**Steps — inside `DB::transaction()`:**
1. Throw if `$order->status !== OrderStatus::Shipped`
2. Find outbound shipment: `$order->shipments()->where('direction', 'outbound')->latest()->firstOrFail()`
3. Update shipment: `status=ShipmentStatus::Delivered`, `delivered_at=$data['delivered_at']`, `delivered_by=$user->id`
4. Update order: `delivered_at=$data['delivered_at']`, `delivered_by=$user->id` — **status stays `shipped`**
5. Return `$order->fresh()`

**CRITICAL:** `orders.status` does NOT change. Only `delivered_at` and `delivered_by` are set on the order.

---

## `cancel(Order $order, User $user): Order`

**Business rule:** Only `pending` or `processing` orders can be cancelled. Throws `\DomainException` otherwise.

**Steps — no transaction needed (single order update):**
1. Throw if `$order->status` is not in `[OrderStatus::Pending, OrderStatus::Processing]`
2. Update order: `status=OrderStatus::Cancelled`, `cancelled_at=now()`, `cancelled_by=$user->id`
3. Return `$order->fresh()`

**Payment note:** Cancelling a `processing` order does NOT reverse the recorded payment. Payments are preserved as accounting records. Manual refund is out of scope for this module.

---

## `delete(Order $order): void`

**Business rule:** Only `cancelled` orders can be deleted. Throws `\DomainException` otherwise.

**Steps — inside `DB::transaction()`:**
1. Throw if `$order->status !== OrderStatus::Cancelled`
2. `$order->orderFees()->delete()`
3. `$order->lines()->delete()`
4. `$order->delete()`
5. **Do NOT delete payments** — they are an accounting record

**Return type:** `void`

---

## `taxPreview(array $lines, array $shipping): array`

**Purpose:** AJAX endpoint for live tax calculation during order creation.

**Steps:**
1. Resolve shipping address from `$shipping` array (same `resolveAddress` logic)
2. Call `$this->avatax->calculateTax($lines, $shippingAddr)`
3. Return raw result from AvaTax — do not wrap or transform

---

## Prohibited patterns

- Never call `$order->save()` directly — always use `$order->update([...])`
- Never read `$order->fees` to mean the relationship — `$order->fees` is the decimal column; `$order->orderFees()` is the relationship
- Never add tax to `grand_total` — `subtotal` already includes tax
- Never change `status` in `markDelivered()` — only set `delivered_at` and `delivered_by`
- Never delete payments in `delete()`
