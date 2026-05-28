# 12 — Views (Blade + Alpine)

> **Layer 5 — Presentation.** Depends on `04-models.md`, `08-avatax.md`, `10-routes.md`, `11-controller.md`.

## Scope

Four Blade views under `resources/views/orders/`:

- `index.blade.php` — orders list with filters
- `create.blade.php` — new order form (customer + addresses + lines + per-line fees + AvaTax)
- `edit.blade.php` — same as create with pre-filled values
- `show.blade.php` — read-only detail page with event timeline + status-conditional action buttons

**Partial-code file** — section structure, Alpine `x-data` shape, and key method signatures defined. Full HTML deferred to implementation.

---

## Decisions LOCKED

| Decision | Rationale |
|----------|-----------|
| Extends `<x-app-layout>` (admin) | Existing admin layout per `CLAUDE.md` |
| Tailwind CSS v3 | Project standard |
| Alpine.js for interactivity | No SPA framework — server-rendered Blade + Alpine islands |
| Per-line fees as a **sub-repeater inside each line row** | Lets fees be added/removed independently per line |
| AvaTax tax computed via debounced (`400ms`) fetch to `POST /admin/orders/calculate-tax` | Per `08-avatax.md` |
| Customer addresses passed inline via `window.__orderCustomers` | No AJAX needed when customer is changed — instant address dropdown update |
| Stock per location fetched via AJAX (`GET /admin/orders/listing-stock/{listing}`) | Real-time stock data |
| Hidden `<input type="hidden">` for `tax_amount` fields | Form-submit value, not user-editable directly |
| Show page action buttons rendered conditionally by `$order->status` | Different actions valid at different statuses (per `06-policy.md`) |
| Edit form pre-fills via Blade `old(...)` + `x-init` Alpine hydration | Server values seed Alpine state |
| Container width `max-w-6xl` for create/edit | Wide enough for line-items table |

---

## File locations

```
resources/views/orders/index.blade.php
resources/views/orders/create.blade.php
resources/views/orders/edit.blade.php
resources/views/orders/show.blade.php
```

---

## `orders/index.blade.php`

### Sections (top to bottom)

1. **Page header** — title "Orders", "+ New Order" button → `orders.create`
2. **Filter bar** — 5 inputs in a single row
3. **Orders table** — columns + paginator
4. **Empty state** — "No orders found" when paginator is empty

### Filter inputs

| Filter | Type | Query param |
|--------|------|------------|
| Search | text | `search` (matches order number or customer name) |
| Status | select | `status` (OrderStatus cases) |
| Source | select | `source` (OrderSource cases) |
| Date from | date | `from` |
| Date to | date | `to` |

### Table columns

| Column | Source | Notes |
|--------|--------|-------|
| Order number | `$order->number` | Link → `orders.show` |
| Customer | `$order->customer->name` | |
| Source | `$order->source->label()` | Badge |
| Status | `$order->status->label()` | Badge with color per status |
| Payment | `$order->payment_status->label()` | Badge |
| Grand total | `$order->grand_total` | Right-aligned, $X.XX |
| Created | `$order->created_at` | Date |
| Actions | — | "View" button → `orders.show` |

### Tests covered
- `admin_can_view_orders_index`
- `sales_can_view_orders_index`

---

## `orders/create.blade.php`

### Container
`max-w-6xl mx-auto` — wider for line-items table.

### Server data (passed by controller)
- `$customers` — collection with `addresses` eager-loaded
- `$productListings` — active listings with `product` eager-loaded
- `$sources` — `OrderSource::cases()`
- `$paymentMethods` — `PaymentMethod::cases()`

### Inlined to JS (via `@json` in `<script>` tags)

```js
window.__orderCustomers = [
  { id, name, tax_exempt, addresses: [{id, label, summary, is_default, address_line1, city, state, postal_code, country}, ...] },
  ...
]

window.__orderListings = [
  { id, name, sku, price },
  ...
]
```

### Alpine `x-data` shape

```js
{
  // Server data (read-only)
  listings: window.__orderListings,
  customers: window.__orderCustomers,

  // Customer + Address state
  customerId: '',
  addresses: [],          // populated when customer selected
  taxExempt: false,
  billingAddressId: '',
  shippingSelection: '',  // '' | 'same' | 'manage' | <address.id>

  // Line items (with per-line fees)
  lines: [
    {
      product_listing_id: '',
      unit_price: 0,
      tax_amount: 0,
      sku: '',
      stock: '',
      stockLoading: false,
      fees: [],           // [{name, amount, tax_amount}]
    }
  ],

  // Order-level
  shipping: 0,
  taxTimer: null,         // debounce handle

  // Computed getters
  get shippingAddressId() { /* resolves 'same' to billingAddressId */ },
  get subtotal()          { /* sum of line_totals */ },
  get feesTotal()         { /* sum of fee_totals across all lines */ },
  get grandTotal()        { /* subtotal + feesTotal + shipping */ },

  lineTotal(line) {  /* unit_price + tax_amount */ },
  feeTotal(fee)   {  /* amount + tax_amount */ },

  // Methods (signatures only)
  onCustomerChange()       { /* set addresses, taxExempt, reset address selects, debounceTax() */ },
  onBillingChange()        { /* intercept "manage", open admin/customers tab, debounceTax() */ },
  onShippingChange()       { /* same intercept, debounceTax() */ },
  onProductChange(line)    { /* set sku + unit_price from listings, loadStock(line), debounceTax() */ },
  async loadStock(line)    { /* fetch /admin/orders/listing-stock/{id}, fill line.stock */ },
  debounceTax()            { /* 400ms timer → fetchAllLineTax() */ },
  async fetchAllLineTax()  { /* POST /admin/orders/calculate-tax with nested lines+fees, write tax_amount back per line + per fee */ },
  addLine()                { /* push new line scaffold */ },
  removeLine(i)            { /* splice; disabled when 1 line left */ },
  addFeeToLine(line)       { /* push {name:'', amount:0, tax_amount:0} */ },
  removeFeeFromLine(line, i) { /* splice */ },
  fmt(v)                   { /* parseFloat → fixed(2) */ }
}
```

### Sections

1. **Customer & Addresses card** — 3-column grid
   - Customer dropdown (required) — binds `customerId`, `@change="onCustomerChange()"`
   - Billing Address dropdown — disabled until customer selected
   - Shipping Address dropdown — same; includes "Same as billing" and "In-store pickup" options
   - **"+ New address" button next to each address dropdown** (`data-testid="new-address-button"`) — disabled when no customer picked. Opens `data-testid="new-address-modal"` overlay with full CustomerAddress form (label, first_name, last_name, address_line1, address_line2, city, state, postal_code, country, phone). On submit, `POST /admin/orders/customer-addresses` (new JSON endpoint on `OrderController`, see `11-controller.md`); on success, response JSON `{ id, label, summary, address_line1, city, state, postal_code, country }` is appended to `addresses[]`, modal closes, the new address auto-selects in whichever dropdown opened it. On validation failure, JSON errors render inside the modal; modal stays open.
   - Hidden `billing_address_id` + `shipping_address_id` inputs

2. **Order Details card** — 3-column grid
   - Source dropdown (required)
   - Payment Method dropdown (required)
   - Shipping Cost input

3. **Line Items card** — **proper HTML `<table data-testid="items-table">`** inside `overflow-x-auto`
   - Add Line button (top-right, above table)
   - `<thead>` columns (in order): `Product`, `Qty`, `Unit Price`, `Tax`, `Stock`, `Subtotal`, `` (remove)
   - `<tbody>` contains one `<tr>` per line + N `<tr class="fee-row" data-testid="fee-row">` immediately after for each fee belonging to that line + one `<tr>` with the "+ Add fee" button colspan=7
   - **Line `<tr>`** uses `<template x-for="(line, i) in lines">`:
     - Product cell: select with `@change="onProductChange(line)"` + SKU shown as small grey text below
     - Qty cell: shows `1` (read-only for ex-19 — one-per-line; later expandable)
     - Unit Price cell: `<input type="number">` + `@input="debounceTax()"`
     - Tax cell: shows `$X.XX` (read-only span bound to `line.tax_amount`) + hidden input for form submission
     - Stock cell: shows location:qty list or "Out of stock" or "—" while loading
     - Subtotal cell: bold `$X.XX` = `lineTotal(line)` (unit_price + tax_amount)
     - Remove cell: `×` button (disabled when 1 line left)
   - **Fee `<tr>`** indented with `└` glyph in Product column:
     - Product cell: `└ {fee.name input}` (text input for fee name)
     - Qty cell: `1`
     - Unit Price cell: `<input type="number">` for fee amount
     - Tax cell: `$X.XX` + hidden input
     - Stock cell: `—`
     - Subtotal cell: `$X.XX` = `feeTotal(fee)`
     - Remove cell: `×` button
   - **Add-fee `<tr>`** under each line's fees: `<td colspan="7"><button>+ Add fee</button></td>`

4. **Totals strip** — full-width gray bar
   - Subtotal · Fees · Shipping · **Total** (bold) · Submit button

### Form-submit shape

```json
{
  "customer_id": 19,
  "source": "walk_in",
  "payment_method": "cash",
  "billing_address_id": null,
  "shipping_address_id": null,
  "shipping": 0,
  "lines": [
    {
      "product_listing_id": 14,
      "unit_price": 200.00,
      "tax_amount": 16.50,
      "fees": [
        {"name": "Programming Fee", "amount": 40.00, "tax_amount": 3.30},
        {"name": "Gas Tuning Fee",  "amount": 25.00, "tax_amount": 2.06}
      ]
    }
  ]
}
```

> Matches `StoreOrderRequest` rules from `09-requests.md`.

### Tests covered
- `admin_can_view_create_form`
- `admin_can_create_walk_in_cash_order_with_per_line_fees` (form submit)

---

## `orders/edit.blade.php`

### Same as `create.blade.php` (table layout) with these differences

| Aspect | Difference |
|--------|------------|
| Form action | `route('orders.update', $order)` + `@method('PUT')` |
| Layout | **Same proper `<table data-testid="items-table">` as create** — fees as indented sub-rows. NOT a separate UI. |
| Alpine `x-data` initialization | **Hydrates ALL state from `$order` via `window.__existingOrder`**; no fields fall back to factory defaults |
| `x-init` | Derives `addresses` array + `taxExempt` from existing customer; recomputes line totals from hydrated data |
| Submit button label | "Save Changes" instead of "Create Order" |

### Hydration data (passed by controller)

```js
window.__orderListings   = @json($listingsData);
window.__orderCustomers  = @json($customersData);
window.__existingOrder   = @json([
    'customer_id'           => $order->customer_id,
    'source'                => $order->source->value,
    'payment_method'        => /* infer or pass current — see controller */,
    'billing_address_id'    => $order->billing_address_id_or_null,
    'shipping_address_id'   => $order->shipping_address_id_or_null,
    'shipping'              => (float) $order->shipping,
    'lines'                 => $order->lines->map(fn($l) => [
        'product_listing_id' => $l->product_listing_id,
        'sku'                => $l->sku,
        'unit_price'         => (float) $l->unit_price,
        'tax_amount'         => (float) $l->tax_amount,
        'stock'              => '',
        'fees'               => $l->lineFees->map(fn($f) => [
            'name'       => $f->name,
            'amount'     => (float) $f->amount,
            'tax_amount' => (float) $f->tax_amount,
        ])->all(),
    ])->all(),
]);
```

### Alpine `x-data` hydration rules

```js
{
  customerId:         window.__existingOrder.customer_id,
  billingAddressId:   window.__existingOrder.billing_address_id || '',
  shippingSelection:  window.__existingOrder.shipping_address_id || '',
  shipping:           window.__existingOrder.shipping,
  lines:              window.__existingOrder.lines.map(l => ({...l})),
  addresses:          [],
  taxExempt:          false,
  // ... methods same as create
}
```

`x-init` runs once on mount:
```js
x-init="
  const cx = customers.find(c => c.id == customerId);
  if (cx) { addresses = cx.addresses; taxExempt = cx.tax_exempt; }
  // also restore each line's stock display
  lines.forEach(l => { if (l.product_listing_id) loadStock(l); });
"
```

> **Bug fixed:** previously fields appeared reset because Alpine init didn't merge server values into state — now every visible field (customer, source, payment method, shipping, billing/shipping address selection, every line, every fee) hydrates from `window.__existingOrder` and shows the persisted value unchanged.

### Tests covered
- `admin_can_edit_pending_order`
- `edit_form_prefills_all_fields_from_existing_order` (new)
- `edit_redirects_to_show_when_order_not_pending` (handled by controller redirect, not view)

---

## `orders/show.blade.php`

### Container
`max-w-6xl mx-auto`

### Server data
- `$order` (with all relations eager-loaded — see `11-controller.md::show`)

### Sections

1. **Header card** — title bar
   - Order number (large), source badge, status badge, payment status badge
   - Customer name (link to customer show)
   - Created by + created at
   - Grand total (right-aligned, large)

2. **Billing snapshot card** + **Shipping snapshot card** (side-by-side)
   - Shows all 10 fields each, or "Not provided" if NULL
   - For ex-19: billing card shows "NPC Sales Pro LLC, Houston TX"; shipping card shows "(in-store pickup)"

3. **Line Items table** — read-only
   - Columns: Product · SKU · Serial · Unit Price · Tax $ · Line Total
   - Serial is a link if assigned, "—" if null
   - Per-line fees rendered inside each line row as inline nested table:
     - Columns: Fee Name · Amount · Tax $ · Fee Total
   - Last column: Line Total (unit + tax + sum of fees + sum of fee tax)

4. **Totals summary** — right-aligned
   - Subtotal · Per-line fees total · Tax total · Shipping · **Grand Total**

5. **Payment section** (conditional — shown if payment exists)
   - Method, amount, status, cash_received_at, created_by

6. **Event timeline** (chronological)
   - Iterate `$order->events` — for each:
     - Event label (from `OrderEvent::label()`)
     - Metadata rendered as description (e.g., "Cash · $286.86")
     - `created_by` user name + time

7. **Action buttons** (conditional on status)

| Button | Shown when | Action |
|--------|-----------|--------|
| Edit | `status === Pending` | Link to `orders.edit` |
| Delete | `status === Pending` | DELETE form to `orders.destroy` |
| Record Cash Payment | `status === Pending` AND `payment_status === Unpaid` | **Opens modal `data-testid="record-payment-modal"`** |
| Mark Complete | `status === Processing` | POST form to `orders.complete` |
| Print Receipt | `status === Complete` | Link to receipt view (per `18-receipts.md`) |

### Record Payment Modal (Alpine-driven)

When admin clicks "Record Cash Payment" button, a modal overlay opens. Modal markup lives at the bottom of `show.blade.php`, controlled by Alpine `x-data="{ payOpen: false }"` on the page root.

| Field | Value | Editable |
|-------|-------|----------|
| Heading | "Record Cash Payment" | n/a |
| Amount due (read-only display) | `$grand_total` | no |
| Amount received | pre-filled to `$grand_total` | yes (number input) |
| Method (read-only display) | "Cash" | no |
| Cashier (read-only display) | `auth()->user()->name` | no |
| Received at (read-only display) | `now()` formatted | no |
| Cancel button | closes modal | n/a |
| Confirm button | submits hidden form to `POST /admin/orders/{order}/cash-payment` | n/a |

The modal POSTs the same payload as before; success redirects back to show page with a flash notice. Failure (amount mismatch etc.) re-renders show with `$errors` visible inside the modal.

After confirmation, the show page reloads and the existing "Payments" section at the bottom of show renders a row with `Cash · $X.XX · Paid · timestamp · cashier`.

### Tests covered
- `admin_can_view_order_show`
- `show_page_has_record_payment_modal_when_unpaid` (new — checks `data-testid="record-payment-modal"` present)
- `show_page_omits_record_payment_modal_when_paid` (new — modal absent after payment recorded)

---

## Dependencies

**Depends on:**
- `04-models.md` — uses `Order`, `OrderLine`, `OrderLineFee`, `OrderEvent`, `Payment` relations
- `08-avatax.md` — `fetchAllLineTax()` POSTs to `POST /admin/orders/calculate-tax`
- `10-routes.md` — `route('orders.X', ...)` for form actions + helper fetches
- `11-controller.md` — receives `$order`, `$customers`, `$productListings` etc.

**Depended on by:**
- `15-tests.md` — feature tests assert view renders + form submission shape
- `18-receipts.md` — "Print Receipt" button links to receipt view

---

## Validation gates

- [ ] Every view file extends `<x-app-layout>`
- [ ] Create + edit form submit shapes match `StoreOrderRequest` / `UpdateOrderRequest` rules
- [ ] Per-line fees sub-repeater visible inside each line row
- [ ] AvaTax fetch is debounced 400ms
- [ ] Customer address dropdowns work without AJAX (`window.__orderCustomers` inlined)
- [ ] Stock fetch happens on product change (real AJAX)
- [ ] Show page renders all per-line fees nested under their line
- [ ] Show page action buttons match `06-policy.md` allowed actions per status
- [ ] Event timeline iterates `$order->events` in chronological order
- [ ] Hidden `<input>`s carry computed `tax_amount` to form submit
- [ ] No `tax_rate` shown anywhere (column dropped per `03-schema.md`)

---

## Cross-check vs Layer 1 + 2 + 3 + 4

| Source | View provides |
|--------|---------------|
| `03-schema.md` `orders` columns | Show page displays all + billing/shipping snapshots |
| `03-schema.md` `order_line_fees` (NEW table) | Sub-repeater UI in create/edit; nested table in show |
| `08-avatax.md` calculate-tax endpoint | `fetchAllLineTax()` consumes it |
| `09-requests.md` payload shape | Form submit matches exactly |
| `11-controller.md` `show` eager loads | View reads from pre-loaded relations (no N+1) |
| `14-events-inventory.md` 3 events | Timeline renders all 3 with metadata |
| `15-tests.md` feature tests | Forms post to expected routes with expected payloads |

No gaps. Every test target has a view that supports it.
