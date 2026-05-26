# Order Module — Views

All views live under `resources/views/orders/`.
Extend the admin layout. Use Tailwind CSS consistent with the rest of the project.

---

## `orders/index.blade.php`

### Filters (top bar)
| Filter | Input type | Binds to |
|--------|-----------|----------|
| Search | text | order number or customer name |
| Status | select | `OrderStatus` cases |
| Source | select | `OrderSource` cases |
| Date from | date | `created_at` |
| Date to | date | `created_at` |

### Table columns
| Column | Notes |
|--------|-------|
| Order number | Link to show |
| Customer | Customer name |
| Source | Badge — walk_in / phone / online |
| Status | Badge with colour per status |
| Payment | Badge — paid / unpaid |
| Grand total | Right-aligned |
| Created | Date |
| Actions | View button |

### Empty state
Show "No orders found" when paginator is empty.

---

## `orders/create.blade.php`

### Container
`max-w-6xl` — wider than normal to accommodate the line items table.

### Data loaded by controller
- `$customers` — with `addresses` relationship eager-loaded
- `$productListings` — active, with `product` eager-loaded
- `$sources`, `$paymentMethods`
- `window.__orderListings` — JSON: `[{id, name, sku, price}]` — price from `$l->currentPrice()`. Passed via `@php` block then `@json($var)` (not inline — Blade chokes on arrow functions inside `@json()`)
- `window.__orderCustomers` — JSON: `[{id, name, addresses:[{id, label, summary, is_default}]}]` — addresses embedded so no AJAX round trip is needed when customer changes

### Section: Customer & Addresses

**Customer selector**
- Dropdown of all customers (name). Required.
- On change (`onCustomerChange()`) → filters `window.__orderCustomers` in Alpine state → populates address dropdowns instantly (no AJAX). Resets billing and shipping selections.

**Billing Address**
- Dropdown, nullable. Disabled until a customer is selected.
- Options:
  - "— No billing address —" (value = "") when customer selected; "Select a customer first…" when not
  - Each customer address: `{label}: {first_name} {last_name}, {address_line1}, {city}` + " ★" if default
  - "+ Manage addresses →" (value = `"manage"`) → `window.open` to `/admin/customers/{id}/addresses` in new tab, then resets value to ""
- Hidden input `name="billing_address_id"` bound to resolved value (empty when "manage" intercepted)

**Shipping Address**
- Dropdown, nullable. Disabled until a customer is selected.
- Options:
  - "— In-store pickup —" (value = "")
  - "Same as billing" (value = `"same"`) → computed `shippingAddressId` getter returns `billingAddressId` — reactive, updates if billing changes
  - Each customer address (same format)
  - "+ Manage addresses →" (value = `"manage"`) → same new-tab behaviour
- Hidden input `name="shipping_address_id"` bound to `shippingAddressId` computed getter

> Address dropdowns disabled until customer selected. "manage" value is intercepted by `@change` handler and never submitted.

### Section: Order Details
- Source dropdown — required
- Payment method dropdown — required
- Shipping Cost — numeric, min 0, default 0.00, label "Shipping Cost" (not just "Shipping"); bound with `x-model="shipping"` so totals strip updates live

### Section: Line Items

`<table>` inside `overflow-x-auto` wrapper — **not a CSS grid**. Table layout ensures all columns stay on one horizontal row regardless of content width. Add Line button top-right of card header.

**Column headers (thead):**
| Product (w-56) | SKU (w-24) | Stock (w-40) | Unit Price (w-28) | Tax % (w-20) | Tax (w-24) | Subtotal (w-28) | × (w-10) |

**Per row behaviour (`x-for` on `<template>` inside `<tbody>`):**
- **Product** — select from active listings. Required. On change → `onProductChange(line)`:
  1. Immediately sets `line.unit_price = listing.price` and `line.sku = listing.sku` from pre-loaded `window.__orderListings` (no AJAX — instant)
  2. Resets `line.stock = ''` then calls `loadStock(line)` async
- **SKU** — read-only `<span>`, filled by `onProductChange`. Shows "—" until product selected.
- **Stock** — read-only `<span>`. Filled async by `loadStock()` via `GET /admin/orders/listing-stock/{id}`. Format: `"Shelf L1: 5 · Shelf L2: 3"`. Shows "Loading…" while fetching. Shows "Out of stock" in red when all locations have qty 0. Shows "—" before product selected.
- **Unit Price** — number input, auto-filled by `onProductChange`, editable. Required.
- **Tax %** — number input, default 0.
- **Tax** — read-only `<span>`. Calculated live: `unit_price × tax_rate / 100`.
- **Subtotal** — read-only `<span>`, bold. Calculated live: `unit_price × (1 + tax_rate / 100)`.
- **Remove** — × icon button. Disabled (opacity-30) when only one row remains.

> Serial assignment is automatic at store() — no serial picker on the form.

### Section: Fees

Repeatable `flex` rows (Alpine.js). Add Fee button top-right of card header.
- Description — `flex-1` text input. Required.
- Amount — `w-32` number input. Required.
- Remove — × icon button (flex-shrink-0).
- Empty state: "No additional fees." shown when `fees.length === 0`.

### Totals strip (bottom, full-width)
`rounded-xl bg-gray-50` bar: Subtotal · Fees · Shipping → **Total** (bold, larger) + "Create Order" submit button on the right.
All values update live via Alpine.js computed properties (`subtotal`, `feesTotal`, `grandTotal`).

### Submit
"Create Order" → POST /admin/orders

---

## `orders/show.blade.php`

### Header card
- Order number, status badge, source badge
- Customer name (link to customer show)
- Created by, created at
- Grand total

### Billing + Shipping snapshot cards
- Show field values or "Not provided" if NULL
- Side by side — billing left, shipping right

### Order Lines table
| Column | Notes |
|--------|-------|
| Product | `{product_name} · {sku}` — e.g. "Widget Basic · PROD-C" |
| Serial | Clickable link → `/admin/inventory-serials/{id}` when assigned, `—` when null. e.g. `SN-E2E-001` |
| Unit price | |
| Tax rate | |
| Tax amount | |
| Line total | |

### Fees table
| Column |
|--------|
| Name |
| Amount |

### Totals summary
- Subtotal / Fees / Shipping / Grand total

### Payment section
Shows payment row if exists:
- Method, amount, status, cash_received_at, created by

### Action buttons (conditional on status)

| Button | Shown when | Action |
|--------|-----------|--------|
| Record Cash Payment | `payment_status=unpaid` + `method=cash` | POST orders.cash-payment |
| Complete Order | `status=processing` | POST orders.complete |
| Ship Order | `status=processing` (carrier) | POST orders.ship |
| Edit | `status=pending` | Link to orders.edit |
| Delete | `status=pending` | DELETE orders.destroy |

---

## `orders/edit.blade.php`

### Same sections as `create` — with current values pre-filled:
- Order Lines — existing lines with products pre-selected (product_listing_id); serial shown as read-only info beside each line
- Fees — existing fees pre-filled
- Totals — live-calculated

### Not editable on edit form:
- Customer (immutable after creation)
- Source (immutable)
- Payment method (immutable)

### Submit
"Update Order" button → PUT /admin/orders/{order}

Cancel link → back to orders.show
