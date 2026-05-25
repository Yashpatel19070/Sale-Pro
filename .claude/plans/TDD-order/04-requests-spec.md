# TDD-Order — FormRequest Spec

> Rules for every FormRequest in `App\Http\Requests\Order\`.
> No code blocks. Rules and constraints only.

---

## `CreateOrderRequest`

**Class:** `App\Http\Requests\Order\CreateOrderRequest`
**authorize():** `return $this->user()->can('create', Order::class);`
**`prepareForValidation()`:** merges `customer_id` cast to int — `$this->merge(['customer_id' => (int) $this->customer_id])`

### Validation rules

| Field | Rules |
|---|---|
| `customer_id` | required, integer, exists:customers,id |
| `source` | required, string, Rule::enum(OrderSource::class) |
| `shipping_amount` | required, numeric, min:0 |
| `lines` | required, array, min:1 |
| `lines.*.serial_id` | required, integer, exists:inventory_serials,id, distinct |
| `lines.*.unit_price` | required, numeric, min:0 |
| `lines.*.tax_rate` | nullable, numeric, min:0 |
| `fees` | nullable, array |
| `fees.*.name` | required_with:fees, string, max:100 |
| `fees.*.amount` | required_with:fees, numeric, min:0 |
| `billing_same_as_shipping` | nullable, boolean |
| `billing.address_id` | nullable, integer, exists:customer_addresses,id |
| `billing.first_name` | nullable, string, max:100 |
| `billing.last_name` | nullable, string, max:100 |
| `billing.email` | nullable, email, max:255 |
| `billing.phone` | nullable, string, max:30 |
| `billing.line1` | nullable, string, max:255 |
| `billing.line2` | nullable, string, max:255 |
| `billing.city` | nullable, string, max:100 |
| `billing.state` | nullable, string, max:10 |
| `billing.postal_code` | nullable, string, max:20 |
| `billing.country` | nullable, string, size:2 |
| `shipping.address_id` | nullable, integer, exists:customer_addresses,id |
| `shipping.first_name` | nullable, string, max:100 |
| `shipping.last_name` | nullable, string, max:100 |
| `shipping.email` | nullable, email, max:255 |
| `shipping.phone` | nullable, string, max:30 |
| `shipping.line1` | nullable, string, max:255 |
| `shipping.line2` | nullable, string, max:255 |
| `shipping.city` | nullable, string, max:100 |
| `shipping.state` | nullable, string, max:10 |
| `shipping.postal_code` | nullable, string, max:20 |
| `shipping.country` | nullable, string, size:2 |

**Notes:**
- `lines.*.serial_id` is `required` — backorders out of scope for this module
- `distinct` on serial_id prevents same unit on two lines in one request; DB `unique` constraint prevents same unit across all orders
- `shipping_amount` is a top-level key — NOT nested inside `shipping` array

---

## `UpdateOrderRequest`

**Class:** `App\Http\Requests\Order\UpdateOrderRequest`
**authorize():** `return $this->user()->can('update', $this->route('order'));`
**No `prepareForValidation()`.**
**No `customer_id` — customer cannot change after creation.**

### Validation rules

| Field | Rules |
|---|---|
| `source` | required, string, Rule::enum(OrderSource::class) |
| `shipping_amount` | required, numeric, min:0 |
| `fees` | nullable, array |
| `fees.*.name` | required_with:fees, string, max:100 |
| `fees.*.amount` | required_with:fees, numeric, min:0 |
| `billing_same_as_shipping` | nullable, boolean |
| `billing.address_id` | nullable, integer, exists:customer_addresses,id |
| `billing.first_name` | nullable, string, max:100 |
| `billing.last_name` | nullable, string, max:100 |
| `billing.email` | nullable, email, max:255 |
| `billing.phone` | nullable, string, max:30 |
| `billing.line1` | nullable, string, max:255 |
| `billing.line2` | nullable, string, max:255 |
| `billing.city` | nullable, string, max:100 |
| `billing.state` | nullable, string, max:10 |
| `billing.postal_code` | nullable, string, max:20 |
| `billing.country` | nullable, string, size:2 |
| `shipping.address_id` | nullable, integer, exists:customer_addresses,id |
| `shipping.first_name` | nullable, string, max:100 |
| `shipping.last_name` | nullable, string, max:100 |
| `shipping.email` | nullable, email, max:255 |
| `shipping.phone` | nullable, string, max:30 |
| `shipping.line1` | nullable, string, max:255 |
| `shipping.line2` | nullable, string, max:255 |
| `shipping.city` | nullable, string, max:100 |
| `shipping.state` | nullable, string, max:10 |
| `shipping.postal_code` | nullable, string, max:20 |
| `shipping.country` | nullable, string, size:2 |

**Notes:**
- `shipping_amount` top-level key — same as create
- All `billing.*` and `shipping.*` are nested arrays (same structure as create)

---

## `RecordCashPaymentRequest`

**Class:** `App\Http\Requests\Order\RecordCashPaymentRequest`
**authorize():** `return $this->user()->can('pay', $this->route('order'));`

### Validation rules

| Field | Rules |
|---|---|
| `amount` | required, numeric, min:0.01 |
| `cash_received_at` | required, date |

---

## `ShipOrderRequest`

**Class:** `App\Http\Requests\Order\ShipOrderRequest`
**authorize():** `return $this->user()->can('ship', $this->route('order'));`

### Validation rules

| Field | Rules |
|---|---|
| `shipped_at` | required, date |
| `carrier` | required, string, max:100 |
| `tracking` | required, string, max:100 |
| `label_cost` | required, numeric, min:0 |

---

## `DeliverOrderRequest`

**Class:** `App\Http\Requests\Order\DeliverOrderRequest`
**authorize():** `return $this->user()->can('deliver', $this->route('order'));`

### Validation rules

| Field | Rules |
|---|---|
| `delivered_at` | required, date |

---

## Prohibited patterns

- Never use `$request->all()` — always `$request->validated()`
- Never put business logic in `authorize()` — only permission check
- Never trust the HTTP body for IDs used in authorization — use `$this->route('order')` for the bound model
- Never add `customer_id` to `UpdateOrderRequest` — customer is immutable after creation
