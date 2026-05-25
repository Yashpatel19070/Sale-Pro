# TDD-Order — View Spec

> Column map and Alpine rules for every order view.
> Column names MUST match `01-schema.md` exactly.
> No code blocks. Rules and constraints only.

---

## Global rules (all views)

- All views extend `layouts.app`
- All views use Tailwind CSS v3
- All monetary values formatted with 2 decimal places: `number_format($value, 2)`
- All dates formatted: `$order->created_at->format('M j, Y g:i A')`
- All enum labels use `->label()` method on enum, never raw DB value
- PHP→Alpine data transfer: use `<script>window.__var = @json(...);</script>` NEVER `x-data="{ foo: @json(...) }"`
- Every `@can` / `@cannot` maps to a policy method in `02-permissions.md`
- Every column reference must appear in `01-schema.md`

---

## Column reference map (view → schema)

| Use in view | Column/property | Source table |
|---|---|---|
| `$order->number` | `number` | orders |
| `$order->status` | `status` (enum) | orders |
| `$order->status->label()` | enum label | — |
| `$order->status->badgeColor()` | badge CSS | — |
| `$order->source->label()` | `source` enum label | orders |
| `$order->payment_status` | `payment_status` | orders |
| `$order->subtotal` | `subtotal` | orders |
| `$order->fees` | `fees` | orders |
| `$order->shipping` | `shipping` | orders |
| `$order->grand_total` | `grand_total` | orders |
| `$order->customer->name` | via relationship | customers |
| `$order->created_at` | `created_at` | orders |
| `$line->sku` | `sku` | order_lines |
| `$line->product_name` | `product_name` | order_lines |
| `$line->unit_price` | `unit_price` | order_lines |
| `$line->tax_rate` | `tax_rate` | order_lines |
| `$line->tax_amount` | `tax_amount` | order_lines |
| `$line->line_total` | `line_total` | order_lines |
| `$fee->name` | `name` | order_fees |
| `$fee->amount` | `amount` | order_fees |

### Prohibited aliases

See `01-schema.md` — Prohibited aliases section. Same rules apply in all views.

---

## `orders.index`

**Variables:** `$orders` (paginator), `$statuses` (enum cases), `$filters` (array)

**Table columns:**
1. Order # (`$order->number`)
2. Customer (`$order->customer->name`)
3. Status (badge using `$order->status->label()` + `$order->status->badgeColor()`)
4. Payment (`$order->payment_status`)
5. Grand Total (`$order->grand_total` formatted)
6. Date (`$order->created_at->format('M j, Y')`)
7. Actions (View link → `orders.show`)

**Filter bar:**
- Text search input (`name="search"`, `value="{{ $filters['search'] ?? '' }}"`)
- Status dropdown (`name="status"`, selected = `$filters['status'] ?? ''`, options from `$statuses`)
- Submit button

**Pagination:** `$orders->links()` with `$filters` appended

---

## `orders.show`

**Variables:** `$order` (with eager loads: customer, lines.serial.product, orderFees, payments, shipments)

**Header section:**
- Order number + status badge + source label (`$order->source->label()`)
- Action buttons (conditional):
  - Edit button → `route('orders.edit', $order)`: visible when `@can('update', $order)` AND `$order->status === OrderStatus::Pending`
  - Pay button → `route('orders.pay', $order)`: visible when `@can('pay', $order)` AND status = pending
  - Ship button → `route('orders.ship', $order)`: visible when `@can('ship', $order)` AND status = processing
  - Deliver button → visible when `@can('deliver', $order)` AND status = shipped
  - Cancel button → visible when `@can('cancel', $order)` AND status in [pending, processing] — requires Alpine confirm
  - Delete button → visible when `@can('delete', $order)` AND status = cancelled — requires Alpine confirm + `@method('DELETE')`

**Order lines table:**
- Columns: SKU, Product Name, Unit Price, Tax Rate (%), Tax Amount, Line Total
- `$line->tax_rate` displayed as percentage: `number_format($line->tax_rate * 100, 2) . '%'`
- Subtotal row label: "Subtotal (incl. tax)" → value: `$order->subtotal`
- Fees rows: each `$fee->name` → `$fee->amount`
- Fees total row: "Fees" → `$order->fees`
- Shipping row: "Shipping" → `$order->shipping`
- Grand Total row: "Grand Total" → `$order->grand_total`
- **NO separate tax row** — tax is included in subtotal

**Customer card:**
- `$order->customer->name`, `$order->customer->email`, `$order->customer->phone`

**Shipping address card** (only if `$order->shipping_address_line1` is not null):
- `$order->shipping_first_name $order->shipping_last_name`
- `$order->shipping_address_line1`
- `$order->shipping_address_line2` (if not null)
- `$order->shipping_city, $order->shipping_state $order->shipping_postal_code`
- `$order->shipping_country`

**Billing address card** (only if `$order->billing_address_line1` is not null):
- Same structure with `billing_*` columns

**Order line columns — use snapshots, not relationships:**
- `$line->sku` — NOT `$line->serial->product->sku`
- `$line->product_name` — NOT `$line->serial->product->name`
- `$line->serial->serial_number` — serial number display only (relationship OK for display)
- `$line->tax_rate` × 100 → display as `number_format($line->tax_rate * 100, 2) . '%'`

**Totals section — correct labels and columns:**
- Row 1 label: "Subtotal (incl. tax)" → `$order->subtotal`
- Row 2 label: "Fees" → `$order->fees` — NOT `$order->fees_total`
- Row 3 label: "Shipping" → `$order->shipping` — NOT `$order->shipping_amount`
- Row 4 label: "Grand Total" → `$order->grand_total`

**Status comparisons in Blade — always use enum, never string:**
- CORRECT: `$order->status === \App\Enums\OrderStatus::Processing`
- WRONG: `$order->status->value === 'processing'`

**Payments section — table + inline pay form:**
- Table columns: Method, Amount, Status, Received At
- `$payment->method->label()` — NOT raw method
- `$payment->status->label()` — NOT raw status
- `$payment->cash_received_at?->format('M d, Y') ?? '—'`
- Inline pay form — visible when `$order->payment_status === 'unpaid'` AND `@can('pay', $order)`:
  - `name="amount"` — default: `$order->grand_total`
  - `name="cash_received_at"` — default: `now()->format('Y-m-d')`
  - POST to `route('orders.pay', $order)`

**Shipments section — table + inline forms:**
- Table columns: Carrier, Tracking, Status, Shipped At, Delivered At
- `$shipment->status->label()` — NOT raw status
- `$shipment->shipped_at?->format('M d, Y') ?? '—'`
- `$shipment->delivered_at?->format('M d, Y') ?? '—'`

- Inline ship form — visible when `$order->status === OrderStatus::Processing` AND `@can('ship', $order)`:
  - `name="carrier"` — required text input
  - `name="tracking"` — required text input
  - `name="label_cost"` — required numeric input, step=0.01
  - `name="shipped_at"` — required date input, default `now()->format('Y-m-d')`
  - POST to `route('orders.ship', $order)`

- Inline deliver form — visible when `$order->status === OrderStatus::Shipped` AND `!$order->delivered_at` AND `@can('deliver', $order)`:
  - `name="delivered_at"` — required date input, default `now()->format('Y-m-d')`
  - POST to `route('orders.deliver', $order)`

---

## `orders.create`

**Variables:** `$customers`, `$sources`, `$addresses` (grouped by customer_id)

**Window globals (in `<script>` block before form):**
- `window.__orderCustomers` = `@json($customers)`
- `window.__orderAddresses` = `@json($addresses)`

**Alpine state (in `x-data` on the `<form>`):**
- `customerId` — bound to customer select, default `old('customer_id')`
- `customers` — from `window.__orderCustomers`
- `addressesAll` — from `window.__orderAddresses`
- `lines` — array of line objects (see structure below)
- `fees` — array of fee objects `{ name, amount }`
- `shippingAmount` — from `old('shipping_amount', 0)`
- `shippingType` — `'saved'` | `'new'` | `'none'` (default: `'saved'`)
- `selectedShippingId` — null
- `billingType` — `'same'` | `'saved'` | `'new'` | `'none'` (default: `'none'`)
- `selectedBillingId` — null

**Alpine computed properties:**
- `selectedCustomer` — `customers.find(c => c.id == customerId) || null`
- `customerAddresses` — `addressesAll[customerId] || []`
- `subtotal` — sum of `line.unit_price` (excl. tax — tax displayed separately in create form)
- `taxTotal` — sum of `line.tax_amount`
- `feesTotal` — sum of `fee.amount`
- `grandTotal` — `subtotal + taxTotal + feesTotal + parseFloat(shippingAmount || 0)`
- `lineTotal(line)` — `parseFloat(line.unit_price) + parseFloat(line.tax_amount)`

**`billing_same_as_shipping` hidden — always in DOM, before the grid:**
- `name="billing_same_as_shipping"` `:value="billingType === 'same' ? '1' : ''"`

**Left column — Line items:**

Line object structure: `{ listing_id, product_id, location_id, serial_id, sku, unit_price, tax_rate, tax_amount, availableLocations, availableSerials }`

Serial selection is 3-step AJAX:
1. Product/listing — Tom Select (`x-ts` directive), AJAX to `/admin/product-listings/search?q=...` returning `{id, label, product_id, sku, price}`. On select: sets `product_id`, `sku`, `unit_price`; fetches locations via `/admin/inventory-locations/search?product_id=...`
2. Location — `<select>` populated from `line.availableLocations`. On change: clears serial, fetches serials via `/admin/inventory-serials/search?product_id=...&location_id=...`
3. Serial — `<select>` populated from `line.availableSerials`. On change: calls `refreshTax()`

Hidden inputs inside `<template x-for>` (submit to server, not visible inputs):
- `name="lines[${index}][serial_id]"` `:value="line.serial_id"`
- `name="lines[${index}][unit_price]"` `:value="line.unit_price"`
- `name="lines[${index}][tax_rate]"` `:value="line.tax_rate"`

Tax preview AJAX (`refreshTax()`):
- Only fires when at least one line has a `serial_id`
- POST to `route('orders.taxPreview')`, header `X-CSRF-TOKEN: {{ csrf_token() }}`
- Body: `{ lines: [{ index: i, serial_id: int|null, unit_price: float }], shipping: { address_id: int } | {} }`
- Response: `{ "0": { tax_rate: 0.0825, tax_amount: 8.25 }, "1": {...} }` — string-keyed by line index
- On response: update `lines[i].tax_rate` and `lines[i].tax_amount` for each key

**Right sidebar — Customer:**
- `<select name="customer_id">` Tom Select; on change: reset `selectedShippingId`, `selectedBillingId`
- Customer info card (Alpine): shown when `selectedCustomer` not null — name, email, phone from Alpine state
- `<select name="source">` with `old('source')` pre-selection

**Right sidebar — Shipping address panel:**
`shippingType` options: `'saved'` | `'new'` | `'none'`
- `'saved'` → address picker list from `customerAddresses`; hidden `name="shipping[address_id]"` `:value="selectedShippingId"`
- `'new'` → inline fields: `name="shipping[first_name]"`, `name="shipping[last_name]"`, `name="shipping[email]"`, `name="shipping[phone]"`, `name="shipping[line1]"`, `name="shipping[line2]"`, `name="shipping[city]"`, `name="shipping[state]"`, `name="shipping[postal_code]"`, `name="shipping[country]"` (default `'US'`, maxlength 2); old() uses dot notation: `old('shipping.first_name')`
- `'none'` → informational text, no inputs

**Right sidebar — Billing address panel:**
`billingType` options: `'same'` | `'saved'` | `'new'` | `'none'` (default: `'none'`)
- `'same'` → informational text; `billing_same_as_shipping` hidden sends `'1'`
- `'saved'` → address picker list; hidden `name="billing[address_id]"` `:value="selectedBillingId"`
- `'new'` → same inline fields as shipping with `billing[...]` prefix; `old('billing.first_name')`
- `'none'` → informational text; billing snapshot stays NULL

**Right sidebar — Fees:**
- `name="fees[${fi}][name]"` — text input, x-model bound
- `name="fees[${fi}][amount]"` — number input, min=0, step=0.01, x-model bound

**Right sidebar — Order totals:**
- `name="shipping_amount"` top-level input (NOT nested inside `shipping[...]`), x-model `shippingAmount`
- Totals display — 5 rows (Alpine computed):
  - "Subtotal" → `subtotal` (excl. tax — unit prices only)
  - "Tax" → `taxTotal` (from AvaTax response)
  - "Fees" → `feesTotal`
  - "Shipping" → `shippingAmount`
  - "Grand Total" → `grandTotal`
- Note: stored DB `subtotal` = `sum(line_total)` = `sum(unit_price + tax_amount)` incl. tax. Create form splits them live. Show/edit display stored value as "Subtotal (incl. tax)".

**Form submission:** POST to `route('orders.store')`

---

## `orders.edit`

**Variables:** `$order` (with eager loads: customer, lines.serial.product, orderFees), `$sources`, `$addresses` (grouped by customer_id)

**Window globals (in `<script>` block before form):**
- `window.__orderAddresses` = `@json($addresses)`
- `window.__orderFees` = `@json($order->orderFees->map(fn($f) => ['name' => $f->name, 'amount' => (float) $f->amount]))`
- `window.__orderSubtotal` = `{{ (float) $order->subtotal }}`
- `window.__orderShipping` = `{{ (float) old('shipping_amount', $order->shipping) }}`

**Alpine state (in `x-data` on the `<form>` — no @json in attribute):**
- `customerId: '{{ $order->customer_id }}'`
- `addressesAll: window.__orderAddresses`
- `fees: window.__orderFees`
- `subtotal: window.__orderSubtotal`
- `shippingAmount: window.__orderShipping`
- `shippingType: "{{ old('shipping_type', $order->shipping_address_line1 ? 'new' : 'none') }}"` — options: `'saved'` | `'new'` | `'none'`
- `selectedShippingId: null`
- `billingType: "{{ old('billing_type', $order->billing_address_line1 ? 'new' : 'none') }}"` — options: `'same'` | `'saved'` | `'new'` | `'none'`
- `selectedBillingId: null`

Note: old() keys are `'shipping_type'` / `'billing_type'` — match radio `name` attributes, not Alpine variable names.

**Alpine computed properties:**
- `customerAddresses` — `addressesAll[customerId] || []`
- `feesTotal` — sum of `fee.amount`
- `grandTotal` — `subtotal + feesTotal + parseFloat(shippingAmount || 0)`

**`billing_same_as_shipping` hidden — always in DOM:**
- `name="billing_same_as_shipping"` `:value="billingType === 'same' ? '1' : ''"`

**Left column — Read-only line items (lines NOT editable):**
- `@forelse($order->lines as $line)` display only — no edit/remove controls
- Use snapshot columns: `$line->sku`, `$line->product_name` — NOT `$line->serial->product->sku`
- Serial display via relationship OK: `$line->serial->serial_number ?? '—'`

**Left column — Editable fees:**
- `name="fees[${fi}][name]"` — text input, x-model bound
- `name="fees[${fi}][amount]"` — number input, min=0, step=0.01, x-model bound

**Right sidebar — Read-only customer card (no input):**
- `$order->customer->name`, `$order->customer->email`, `$order->customer->phone`

**Right sidebar — Editable source:**
- `<select name="source">` — pre-selected: `old('source', $order->source->value) === $source->value`

**Right sidebar — Shipping address panel:**
`shippingType` options: `'saved'` | `'new'` | `'none'`
- `'saved'` → address picker from `customerAddresses`; hidden `name="shipping[address_id]"` `:value="selectedShippingId"`
- `'new'` → inline fields with snapshot pre-fill: `old('shipping.first_name', $order->shipping_first_name)`, `name="shipping[first_name]"` etc.
- `'none'` → no inputs

**Right sidebar — Billing address panel:**
`billingType` options: `'same'` | `'saved'` | `'new'` | `'none'`
- `'same'` → informational text; `billing_same_as_shipping` sends `'1'`
- `'saved'` → address picker; hidden `name="billing[address_id]"` `:value="selectedBillingId"`
- `'new'` → inline fields: `old('billing.first_name', $order->billing_first_name)`, `name="billing[first_name]"` etc.
- `'none'` → no inputs

**Right sidebar — Live order totals:**
- `name="shipping_amount"` — top-level input, x-model `shippingAmount`
- "Subtotal (incl. tax)" — static from `window.__orderSubtotal` (no recalc — lines unchanged)
- "Fees" — Alpine `feesTotal`
- "Shipping" — live `shippingAmount`
- "Grand Total" — Alpine `grandTotal`

**Form:** PUT to `route('orders.update', $order)` — `@method('PUT')` hidden input
**Save button:** standard submit

---

## Alpine computed properties required

For edit form:

| Computed name | Formula |
|---|---|
| `customerAddresses` | `addressesAll[customerId] \|\| []` |
| `feesTotal` | sum of `fees[i].amount` |
| `grandTotal` | `subtotal + feesTotal + parseFloat(shippingAmount \|\| 0)` |

For create form:

| Computed name | Formula |
|---|---|
| `selectedCustomer` | `customers.find(c => c.id == customerId) \|\| null` |
| `customerAddresses` | `addressesAll[customerId] \|\| []` |
| `subtotal` | sum of `lines[i].unit_price` (excl. tax) |
| `taxTotal` | sum of `lines[i].tax_amount` |
| `feesTotal` | sum of `fees[i].amount` |
| `grandTotal` | `subtotal + taxTotal + feesTotal + parseFloat(shippingAmount \|\| 0)` |
| `lineTotal(line)` | `parseFloat(line.unit_price) + parseFloat(line.tax_amount)` |

---

## AJAX endpoint contracts (admin-search module)

These endpoints are owned by other controllers. All require the user to be authenticated with `viewAny` policy on the respective model.

### `GET /admin/product-listings/search?q=<string>`
- Returns `[]` if `q` is blank
- Response: array of `{ id, title, sku, product_id, price, label }`
- `label` = `"SKU — title"` (Tom Select display text)
- `price` = sale_price if set, else regular_price (string decimal)

### `GET /admin/inventory-locations/search?product_id=<int>`
- Returns `[]` if `product_id` absent
- Returns only locations that have at least one in-stock serial for that product
- Response: array of `{ id, name }`

### `GET /admin/inventory-serials/search?product_id=<int>&location_id=<int>`
- Returns `[]` if either param absent
- Returns in-stock serials matching both product and location, ordered by `serial_number`
- Response: array of `{ id, serial_number }`

---

## Flash messages

- All success flashes displayed at top of page: `session('success')`
- All error flashes from `$errors->first('error')` displayed prominently
- Both exist in the layout and require no per-view handling

---

## Prohibited patterns (views)

- Never use `$order->fees_total` — use `$order->fees`
- Never use `$order->shipping_amount` — use `$order->shipping`
- Never show a "Tax" row in show/edit totals summary — tax is baked into stored `subtotal`; create form is the exception (shows tax live before subtotal is stored)
- Never use `@json()` inside an HTML attribute value
- Never lazy-load a relationship inside a `@foreach` — all loads via `->load()` in controller
- Never display raw `$order->status->value` — always use `->label()` for display
- Never default `billingType` to `'same'` in the create form — default is `'none'`
