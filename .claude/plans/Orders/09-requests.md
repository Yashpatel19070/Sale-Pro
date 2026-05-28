# 09 — FormRequests

> **Layer 4 — Behavior.** Depends on `01-enums.md`, `03-schema.md`, `04-models.md`, `06-policy.md`, `15-tests.md`.

## Scope

Three FormRequest classes for the Orders module:

- `StoreOrderRequest` — POST `/admin/orders` payload
- `UpdateOrderRequest` — PUT `/admin/orders/{order}` payload
- `RecordCashPaymentRequest` — POST `/admin/orders/{order}/cash-payment` payload

Each FormRequest defines:
- `authorize()` — delegates to `OrderPolicy` via `$this->user()->can(...)`
- `rules()` — validation rule array (the full implementation)

**Partial-code file** — rule arrays are the full spec. Other method bodies omitted.

---

## Decisions LOCKED

| Decision | Rationale |
|----------|-----------|
| `authorize()` delegates to `OrderPolicy` via `$user->can('action', ...)` | Single source of truth for auth (per `06-policy.md`) |
| Validation rules use `Rule::enum()` for enum-cast columns | Type-safe, matches `01-enums.md` cases |
| `tax_amount` is `required + numeric + min:0` — NOT cross-checked vs AvaTax | AvaTax helper pre-fills it; service trusts the value |
| `unit_price`/`tax_amount`/`fee.amount`/`fee.tax_amount` all `min:0` (zero allowed) | Per `00-overview.md` decision — fees with `amount = 0` are allowed (waived fees) |
| `lines` array `required + min:1` | An order with zero lines has no purpose |
| `lines.*.fees` array `nullable` | Lines without fees are valid (simple cash sale) |
| `billing_address_id`/`shipping_address_id` `nullable` | Service decides snapshot source (shop address for cash, customer address for card, NULL for pickup) |
| `amount === grand_total` check NOT in `RecordCashPaymentRequest` | Service does this check — request can't read the order's `grand_total` |
| All 3 requests use `declare(strict_types=1)` | Project convention |
| FormRequest namespace `App\Http\Requests\Order` | Matches existing project structure |

---

## File locations

```
app/Http/Requests/Order/StoreOrderRequest.php
app/Http/Requests/Order/UpdateOrderRequest.php
app/Http/Requests/Order/RecordCashPaymentRequest.php
```

---

## `StoreOrderRequest`

### `authorize()`
```php
return $this->user()->can('create', Order::class);
```

### `rules()` — full rule array
```php
return [
    'customer_id'         => ['required', 'integer', 'exists:customers,id'],
    'source'              => ['required', Rule::enum(OrderSource::class)],
    'payment_method'      => ['required', Rule::enum(PaymentMethod::class)],
    'billing_address_id'  => ['nullable', 'integer', Rule::exists('customer_addresses', 'id')->where('customer_id', $this->input('customer_id'))],
    'shipping_address_id' => ['nullable', 'integer', Rule::exists('customer_addresses', 'id')->where('customer_id', $this->input('customer_id'))],
    'shipping'            => ['nullable', 'numeric', 'min:0'],

    'lines'                          => ['required', 'array', 'min:1'],
    'lines.*.product_listing_id'     => ['required', 'integer', Rule::exists('product_listings', 'id')->where('is_active', true)],
    'lines.*.unit_price'             => ['required', 'numeric', 'min:0'],
    'lines.*.tax_amount'             => ['required', 'numeric', 'min:0'],

    'lines.*.fees'                   => ['nullable', 'array'],
    'lines.*.fees.*.name'            => ['required_with:lines.*.fees', 'string', 'max:100'],
    'lines.*.fees.*.amount'          => ['required_with:lines.*.fees', 'numeric', 'min:0'],
    'lines.*.fees.*.tax_amount'      => ['required_with:lines.*.fees', 'numeric', 'min:0'],
];
```

### Edge cases (each maps to a test in `15-tests.md`)
- Empty payload → `customer_id, source, payment_method, lines` all required → `store_fails_validation_when_customer_id_missing`
- Empty `lines[]` array → fails `required + min:1` → `store_fails_validation_when_lines_array_empty`
- Missing `fee.name` when fee item present → fails `required_with` → `store_fails_validation_when_fee_name_missing`
- Inactive product listing → fails `exists` check
- Negative `unit_price` or `fee.amount` → fails `min:0`

### Tests covered
All 5 tests under `## store` (feature) in `15-tests.md` that assert validation behavior.

---

## `UpdateOrderRequest`

### `authorize()`
```php
return $this->user()->can('update', $this->route('order'));
```

> Policy enforces `status === Pending` per `06-policy.md`. Request only checks permission + valid payload shape.

### `rules()` — full rule array
**Same as `StoreOrderRequest`.** All fields editable on a pending order (per `00-overview.md`: customer, source, payment_method, addresses, shipping, lines all editable while pending).

```php
return [
    'customer_id'         => ['required', 'integer', 'exists:customers,id'],
    'source'              => ['required', Rule::enum(OrderSource::class)],
    'payment_method'      => ['required', Rule::enum(PaymentMethod::class)],
    'billing_address_id'  => ['nullable', 'integer', Rule::exists('customer_addresses', 'id')->where('customer_id', $this->input('customer_id'))],
    'shipping_address_id' => ['nullable', 'integer', Rule::exists('customer_addresses', 'id')->where('customer_id', $this->input('customer_id'))],
    'shipping'            => ['nullable', 'numeric', 'min:0'],

    'lines'                          => ['required', 'array', 'min:1'],
    'lines.*.product_listing_id'     => ['required', 'integer', Rule::exists('product_listings', 'id')->where('is_active', true)],
    'lines.*.unit_price'             => ['required', 'numeric', 'min:0'],
    'lines.*.tax_amount'             => ['required', 'numeric', 'min:0'],

    'lines.*.fees'                   => ['nullable', 'array'],
    'lines.*.fees.*.name'            => ['required_with:lines.*.fees', 'string', 'max:100'],
    'lines.*.fees.*.amount'          => ['required_with:lines.*.fees', 'numeric', 'min:0'],
    'lines.*.fees.*.tax_amount'      => ['required_with:lines.*.fees', 'numeric', 'min:0'],
];
```

### Edge cases
- Same as `StoreOrderRequest`
- Order not pending → 403 (caught by policy, not request)

---

## `RecordCashPaymentRequest`

### `authorize()`
```php
return $this->user()->can('recordCashPayment', $this->route('order'));
```

> Policy enforces `status === Pending && payment_status === Unpaid` per `06-policy.md`.

### `rules()` — full rule array
```php
return [
    'amount' => ['required', 'numeric', 'min:0.01'],
];
```

> `min:0.01` (not `min:0`) because a "$0 payment" is meaningless.
>
> `amount === order.grand_total` check is done by `OrderService::recordCashPayment()` — throws `DomainException` if mismatch. Request can't see `grand_total`, so this check lives in service.

### Edge cases
- Missing `amount` → 422 validation error
- Negative or zero amount → 422 validation error
- Amount mismatch → caught by service → `DomainException` → controller catches → redirect back with error → `record_cash_payment_fails_when_amount_does_not_match_grand_total`

---

## `CalculateTaxRequest` — POST /admin/orders/calculate-tax

### `authorize()`
```php
return $this->user()->can('viewAny', Order::class);
```

### `rules()`
```php
return [
    'customer_id'              => ['required', 'integer', 'exists:customers,id'],
    'shipping_address'         => ['nullable', 'array'],
    'shipping_address.address_line1' => ['required_with:shipping_address', 'string', 'max:255'],
    'shipping_address.city'    => ['required_with:shipping_address', 'string', 'max:100'],
    'shipping_address.state'   => ['required_with:shipping_address', 'string', 'max:10'],
    'shipping_address.postal_code' => ['required_with:shipping_address', 'string', 'max:20'],
    'shipping_address.country' => ['required_with:shipping_address', 'string', 'size:2'],

    'lines'                       => ['required', 'array', 'min:1', 'max:50'],
    'lines.*.unit_price'          => ['required', 'numeric', 'min:0'],
    'lines.*.sku'                 => ['required', 'string', 'max:64'],
    'lines.*.fees'                => ['nullable', 'array', 'max:10'],
    'lines.*.fees.*.name'         => ['required_with:lines.*.fees', 'string', 'max:100'],
    'lines.*.fees.*.amount'      => ['required_with:lines.*.fees', 'numeric', 'min:0'],
];
```

> Replaces direct `$request->input()` calls in `OrderController::calculateTax`. Enforces shape, max array sizes (50 lines, 10 fees per line), and customer existence before the controller passes data to AvaTax.

### Edge cases
- Missing `customer_id` → 422
- Malformed `shipping_address` → 422
- Empty `lines` array → 422

---

## Validation rule reference (per ex-19 line)

| Field | Rule chain | ex-19 example value | ex-19 line |
|-------|-----------|---------------------|-----------|
| `customer_id` | required, integer, exists | `19` (Rachel) | 73 |
| `source` | required, Rule::enum | `walk_in` | 73 |
| `payment_method` | required, Rule::enum | `cash` | 124 |
| `billing_address_id` | nullable, integer, exists | `null` (cash → shop fills) | 77 |
| `shipping_address_id` | nullable, integer, exists | `null` (pickup) | 80 |
| `shipping` | nullable, numeric, min:0 | `0.00` | 73 |
| `lines` | required, array, min:1 | 1 line array | 89 |
| `lines.*.product_listing_id` | required, integer, exists active | `14` | 89 |
| `lines.*.unit_price` | required, numeric, min:0 | `200.00` | 89 |
| `lines.*.tax_amount` | required, numeric, min:0 | `16.50` | 89 |
| `lines.*.fees` | nullable, array | 2 fees array | 97-99 |
| `lines.*.fees.*.name` | required_with, string, max:100 | `Programming Fee`, `Gas Tuning Fee` | 97-99 |
| `lines.*.fees.*.amount` | required_with, numeric, min:0 | `40.00`, `25.00` | 97-99 |
| `lines.*.fees.*.tax_amount` | required_with, numeric, min:0 | `3.30`, `2.06` | 97-99 |

---

## Dependencies

**Depends on:**
- `01-enums.md` — `OrderSource`, `PaymentMethod` for `Rule::enum`
- `04-models.md` — `Order::class` for `can('create', Order::class)`
- `06-policy.md` — `OrderPolicy` actions used in `authorize()`
- Existing tables: `customers`, `customer_addresses`, `product_listings`

**Depended on by:**
- `11-controller.md` — controller type-hints these requests as method arguments
- `15-tests.md` — feature tests trigger validation failures
- `12-views.md` — Alpine form submits payload matching these rules

---

## Validation gates

- [ ] Every column in `03-schema.md` that's user-supplied has a rule
- [ ] Every rule chain matches the column's type/nullability/range
- [ ] `authorize()` delegates to `OrderPolicy` (no inline auth logic)
- [ ] `Rule::enum()` used for `source`, `payment_method`
- [ ] `Rule::exists()` used for FK columns
- [ ] Nested `lines.*.fees.*` validation works with Laravel array validation
- [ ] No business logic in request beyond rule definitions
- [ ] `amount === grand_total` check is NOT here (deferred to service)
- [ ] All 3 files use `declare(strict_types=1);`
- [ ] All 3 files use `namespace App\Http\Requests\Order`

---

## Cross-check vs Layer 1 + 2 + 3

| Source | Request validates |
|--------|-------------------|
| `01-enums.md` `OrderSource::WalkIn` | `Rule::enum(OrderSource::class)` accepts `walk_in` |
| `01-enums.md` `PaymentMethod::Cash` | `Rule::enum(PaymentMethod::class)` accepts `cash` |
| `03-schema.md` `customer_id` FK | `exists:customers,id` |
| `03-schema.md` `product_listing_id` FK + active | `Rule::exists('product_listings','id')->where('is_active', true)` |
| `03-schema.md` `customer_addresses` FK | `exists:customer_addresses,id` |
| `06-policy.md` `create` permission | `can('create', Order::class)` |
| `06-policy.md` `update` permission + status guard | `can('update', $order)` — policy handles status |
| `06-policy.md` `recordCashPayment` permission + status guard | `can('recordCashPayment', $order)` — policy handles status |
| `07-service.md` `recordCashPayment` amount check | Service throws `DomainException` (not request validation) |
| `15-tests.md` `store_fails_validation_when_lines_array_empty` | `'lines' => ['required', 'array', 'min:1']` |
| `15-tests.md` `store_fails_validation_when_customer_id_missing` | `'customer_id' => ['required', ...]` |
| `15-tests.md` `store_fails_validation_when_fee_name_missing` | `'lines.*.fees.*.name' => ['required_with:lines.*.fees', ...]` |
| `15-tests.md` ex-19 payload shape | All payload keys have matching rules |

No gaps. Every validation test in `15-tests.md` has a matching rule.
