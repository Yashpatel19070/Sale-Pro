# Order System — Database Reference

Full schema, data examples, and all edge cases.
Agreed in brainstorm session before any code was written.

---

## Tables Overview — 12 Tables

| Table | Purpose |
|-------|---------|
| `customers` | Lean identity record — cleaned up |
| `customer_addresses` | Address book per customer |
| `orders` | Original sale |
| `order_lines` | Line items — SKU, serial, price snapshots |
| `order_fees` | Extra fees — service, handling |
| `order_status_history` | Lifecycle audit trail — polymorphic |
| `payments` | Money in — cash, stripe (polymorphic) |
| `shipments` | Every physical shipment leg — in and out (polymorphic) |
| `complaints` | Customer issue report + unit examination — always created first |
| `replacements` | New unit sent out — always linked to a complaint |
| `replacement_lines` | Which serials went out and expected back |
| `refunds` | Money out — full or partial (polymorphic) |

---

## Customer Table Changes (Pre-Order Cleanup)

The existing `customers` table had address fields never used by any controller,
service, or view. Removed before building the order system.

### `customers` — after cleanup

```
customers
├── id
├── user_id          FK → users nullable   — portal login link
├── name
├── email            unique
├── phone
├── company_name     nullable
├── status
├── timestamps
└── soft_deletes
```

Fields removed: `address`, `city`, `state`, `postal_code`, `country`

### Files to update alongside the migration

| File | Change |
|------|--------|
| Migration | New migration: drop 5 columns from customers |
| `Customer` model | Remove dead fields from `$fillable` |
| `StoreCustomerRequest` | Remove address validation rules |
| `UpdateCustomerRequest` | Remove address validation rules |
| Customer create/edit views | Remove address fields from form |
| Customer show view | Remove address display |

---

### `customer_addresses` — new table

```
customer_addresses
├── id
├── customer_id           FK → customers
├── label                 varchar             — "Home", "Office", "Warehouse"
├── name                  varchar             — recipient name at this address
├── address_line1         varchar
├── address_line2         varchar nullable
├── city                  varchar
├── state                 varchar
├── postal_code           varchar
├── country               varchar
├── is_default_billing    boolean default false
├── is_default_shipping   boolean default false
└── timestamps
```

### Address snapshot on orders

Admin picks billing + shipping from customer address book at order time.
Both are snapshotted onto the order — frozen forever.

```
orders address fields:
├── billing_name
├── billing_address_line1
├── billing_address_line2 nullable
├── billing_city
├── billing_state
├── billing_postal_code
├── billing_country
│
├── shipping_name
├── shipping_address_line1
├── shipping_address_line2 nullable
├── shipping_city
├── shipping_state
├── shipping_postal_code
└── shipping_country
```

---

## Tax — AvaTax Integration

Tax calculated per line item. Call API, store result, done.
No jurisdiction breakdown stored locally — AvaTax holds that detail.

### Why per line item

When partial refund issued, tax refund is proportional:
```
Line: Widget Pro  $200.00  tax: $17.50
Refund 20%:
  product refund = $40.00
  tax refund     = $3.50    ← 20% of $17.50
  total refund   = $43.50
```

### Schema fields

`order_lines`:
```
├── tax_rate        decimal(8,4)    — e.g. 0.0875
└── tax_amount      decimal(10,2)   — total tax for this line
```

`orders`:
```
├── tax_amount                decimal(10,2) default 0
├── avatax_transaction_code   varchar nullable
├── avatax_transaction_id     varchar nullable
└── avatax_committed          boolean default false
```

`refunds`:
```
├── avatax_return_transaction_code  varchar nullable
└── avatax_return_committed         boolean default false
```

### .env

```
AVATAX_ACCOUNT_ID=
AVATAX_LICENSE_KEY=
AVATAX_COMPANY_CODE=
AVATAX_TAX_CODE=P0000000
AVATAX_ENVIRONMENT=sandbox|production
```

### Grand total calculation

```
subtotal            = sum of line_totals
discount            = order-level discount
fees_total          = sum of order_fees (service fees only — never shipping)
tax_amount          = sum of line tax_amounts — product tax only (from AvaTax)
shipping_amount     = what customer is charged for shipping (0 if free/baked-in)
shipping_tax_amount = tax on shipping (from AvaTax, 0 if state does not tax shipping)
──────────────────────────────────────────────────────────────────────────────────
grand_total = subtotal - discount + fees_total + tax_amount
            + shipping_amount + shipping_tax_amount
```

---

## Currency

Single-currency design — **USD ($) only**. Final decision, no multi-currency support.

**Why locked to USD:**
- Stripe account, AvaTax integration, and customer base are all US-based
- Adding multi-currency would require: currency columns on every money table, exchange rate snapshots, Stripe multi-currency setup, region-aware report logic
- Not part of the business model

**Configuration (.env):**
```
APP_CURRENCY=USD
APP_CURRENCY_SYMBOL=$
```

**Rule:** All `decimal(10,2)` columns are implicit USD. No `currency_code` column on any table. No multi-currency code paths anywhere.

---

## Product & Inventory Chain

```
product_categories
    └── products
         ├── product_listings  — variants (Blue/XL, Red/M, Standard)
         │    FK: product_id
         │
         └── inventory_serials — physical units
              FK: product_id
              ├── inventory_locations  — where the unit sits
              │    FK: inventory_location_id (nullable)
              │
              └── inventory_movements — immutable movement ledger
```

### How order lines connect

Order lines use `product_listing_id` — not `product_id` directly.
Per plan: "Orders reference ProductListings, not Products directly."

```
Admin picks listing  →  "Widget Pro — Black"  (product_listing_id)
                              ↓
                    product_id resolved via listing
                              ↓
Admin picks serial   →  SN-001234  (inventory_serial_id)
                         currently: in_stock  at Warehouse A
```

Price snapshot uses `product.currentPrice()`:
```
sale_price set   → snapshot sale_price as unit_price
sale_price NULL  → snapshot regular_price as unit_price
Admin override   → unit_price saved as entered, frozen
```

---

## SerialStatus — Four New Values

Current (from code):
```
in_stock        ← available to sell
sold            ← with customer
damaged         ← written off / in rebuild
missing         ← lost
```

Add when building order module:
```
expected_return    ← unit should be coming back, not yet arrived
assigned           ← allocated to replacement, not yet shipped
under_examination  ← arrived at shop, awaiting tech inspection (NOT safe to sell)
scrapped           ← destroyed / written off completely (different from damaged — no rebuild)
```

> **Note on `damaged` vs `scrapped`:**
> `damaged` = broken but kept for repair / rebuild (may come back as stock later).
> `scrapped` = destroyed, gone forever. Two different physical outcomes — keep them separate.

Action: update `app/Enums/SerialStatus.php` + migration to extend the enum.

---

## MovementType — Two New Values + Two New Service Methods

### Current enum (`app/Enums/MovementType.php`)

```
Receive       = 'receive'       ← from supplier via PO/GRN only (goods_receipt_id set)
Transfer      = 'transfer'      ← internal warehouse move
Sale          = 'sale'          ← original order shipped (location → NULL)
Adjustment    = 'adjustment'    ← manual correction or no-fault return-to-customer
```

### Add for order module

```
ReplacementOut = 'replacement_out' ← replacement unit shipped (location → NULL)
ReturnIn       = 'return_in'       ← unit arrives back at shop (NULL → location)
```

### Key rules — no shortcuts

- `receive` = supplier only. Always has `goods_receipt_id`. Never for customer returns.
- `sale` = original order only. Not reused for replacements.
- `replacement_out` = replacement shipment. Serial → `assigned`.
- `return_in` = unit arrives at shop. Serial → `under_examination` (NOT `in_stock` — wait for tech to examine before re-selling).
- `adjustment` = no-fault unit handed back to customer OR manual correction.

### Current service methods (`app/Services/InventoryMovementService.php`)

| Method | Movement | Serial status |
|--------|----------|---------------|
| `receive()` | NULL → location | in_stock |
| `transfer()` | location → location | unchanged |
| `sale()` | location → NULL | sold |
| `adjustment()` | varies | varies |
| `bulkReceive()` | batch NULL → location | in_stock |
| `bulkReceiveFromGrn()` | batch NULL → location (GRN) | in_stock |

### Methods to add

| Method | Movement | Serial status |
|--------|----------|---------------|
| `replacementOut()` | location → NULL | assigned |
| `returnIn()` | NULL → location | under_examination |

Action: add both to `app/Services/InventoryMovementService.php`.

---

## Complaint Flow — Two Paths, Same Table

Complaint is always created first at the moment the customer calls.
It is the paper trail. Everything else links to it.

```
Flow A — examine first, then decide:
  complaint created → customer sends unit in → unit arrives
  → tech examines → bad: create replacement
                 → no fault: return to customer OR keep in stock

Flow B — send replacement immediately, examine when unit returns:
  complaint created → replacement sent same day → [days/weeks pass]
  → old unit arrives back → tech examines → bad: scrap or rebuild (no charge)
                                          → no fault: charge customer (or waive)
```

Status sequence is identical for both flows — only timing differs:
```
reported → unit_in_transit → received → examined → closed
```

---

## Full Schema

---

### `orders`

```
orders
├── id                        bigint PK
├── order_number              varchar unique          — ORD-2026-001
├── customer_id               FK → customers
├── source                    enum                    — walk_in, phone, online, whatsapp
├── status                    enum                    — pending, processing, shipped,
│                                                        delivered, cancelled, rts
│
│   -- Address snapshot
├── billing_name              varchar
├── billing_address_line1     varchar
├── billing_address_line2     varchar nullable
├── billing_city              varchar
├── billing_state             varchar
├── billing_postal_code       varchar
├── billing_country           varchar
│
├── shipping_name             varchar
├── shipping_address_line1    varchar
├── shipping_address_line2    varchar nullable
├── shipping_city             varchar
├── shipping_state            varchar
├── shipping_postal_code      varchar
├── shipping_country          varchar
│
│   -- Financials
├── subtotal                  decimal(10,2)
├── discount_amount           decimal(10,2) default 0
├── fees_total                decimal(10,2)
├── tax_amount                decimal(10,2) default 0
│
│   -- AvaTax
├── avatax_transaction_code   varchar nullable
├── avatax_transaction_id     varchar nullable
├── avatax_committed          boolean default false
│
├── shipping_amount           decimal(10,2) default 0 — what customer is charged for shipping
├── shipping_tax_amount       decimal(10,2) default 0 — tax on shipping (AvaTax, varies by state)
├── grand_total               decimal(10,2)
│
│   -- Payment (controlled denormalization — PaymentService owns this)
├── payment_status            enum                    — unpaid, partial, paid
│
│   -- Lifecycle timestamps + actors
├── shipped_at                timestamp nullable       — denorm from first outbound shipment (ShipmentService owns)
├── shipped_by                FK → users nullable      — admin who marked shipped
├── delivered_at              timestamp nullable       — denorm from first outbound shipment (ShipmentService owns)
├── delivered_by              FK → users nullable      — admin who confirmed delivery
├── cancelled_at              timestamp nullable
├── cancelled_by              FK → users nullable      — admin who cancelled
│
├── internal_notes            text nullable
├── customer_notes            text nullable
├── created_by                FK → users
└── timestamps
```

### `orders.status` lifecycle

| Status | When |
|---|---|
| `pending` | Order created, not yet paid |
| `processing` | Payment received, preparing to ship |
| `shipped` | Package dispatched — in transit to customer |
| `delivered` | Customer confirmed received |
| `cancelled` | Order stopped (pre/during fulfillment) or post-delivery refund issued |
| `rts` | Package returned to sender — carrier could not deliver. Serials auto-returned to `in_stock` via `return_in` inventory_movement. Admin must reship (new outbound shipment → status back to `shipped`) or refund (→ `cancelled`). |

**RTS serial tracking rule:** When `status = rts`, for each serial in `order_lines`:
1. `inventory_movements INSERT (type=return_in, to=Warehouse A, reference=order_number, notes="RTS")`
2. `inventory_serials UPDATE status = in_stock`

On reship → serials go `sold` again via new `sale` movement + `orders.status = shipped`.
On refund → serials stay `in_stock` + `orders.status = cancelled`.

---

### `order_lines`

```
order_lines
├── id
├── order_id                  FK → orders
├── product_listing_id        FK → product_listings nullable
├── sku                       varchar                 — snapshot
├── product_name              varchar                 — snapshot
├── listing_title             varchar                 — snapshot
├── serial_number_id          FK → inventory_serials nullable
├── quantity                  int default 1
├── unit_price                decimal(10,2)           — snapshot of product.currentPrice()
├── discount_amount           decimal(10,2) default 0
├── line_total                decimal(10,2)
├── tax_rate                  decimal(8,4)            — from AvaTax
├── tax_amount                decimal(10,2)           — from AvaTax
└── timestamps
```

---

### `order_fees`

```
order_fees
├── id
├── order_id                  FK → orders
├── name                      varchar                 — "Service Fee", "Handling Fee"
├── amount                    decimal(10,2)
└── timestamps
```

> **Rule:** `order_fees` is for non-shipping charges only — service fees, handling, restocking.
> Shipping always goes on `orders.shipping_amount`.

### How `orders.shipping_amount` works

| Scenario | shipping_amount | Customer invoice |
|---|---|---|
| Shipping baked into product price | $0.00 | No shipping line shown |
| Extra charge for fast / expedited | $30.00 | Shipping: $30 shown |
| Package lost in transit — full refund | = original amount | Refunded via `refunds.shipping_refund_amount` |

`$0.00` means free / baked-in — **not missing data**.
Admin enters `shipping_amount` at order creation. It never changes after that.

---

### `order_status_history`

```
order_status_history
├── id
├── historyable_type          varchar                 — 'order', 'replacement', 'complaint'
├── historyable_id            bigint
├── from_status               varchar nullable
├── to_status                 varchar
├── note                      text nullable
├── changed_by                FK → users
└── created_at
```

---

### `payments`

```
payments
├── id
├── payment_number            varchar unique          — PAY-2026-001
├── order_id                  FK → orders             — always root order (for grouping)
├── payable_type              varchar                 — 'order', 'replacement'
├── payable_id                bigint
├── method                    enum                    — cash, stripe_checkout,
│                                                        stripe_card, stripe_terminal
├── amount                    decimal(10,2)
├── status                    enum                    — pending, paid, failed, expired
│
│   -- Cash
├── cash_received_at          timestamp nullable
│
│   -- Failure tracking
├── failed_at                 timestamp nullable        — set when Stripe rejects or cash payment fails
│
│   -- Stripe (shared across all three stripe methods)
├── stripe_payment_intent_id  varchar nullable
├── stripe_session_id         varchar nullable        — checkout only
├── stripe_terminal_reader_id varchar nullable        — terminal only
├── stripe_charge_id          varchar nullable
├── stripe_receipt_url        varchar nullable
│
├── created_by                FK → users
└── timestamps
```

### `payments.status` lifecycle

| Status | When |
|---|---|
| `pending` | Payment row created — awaiting customer action (checkout link sent, terminal waiting) |
| `paid` | Payment confirmed — Stripe webhook received or cash collected |
| `failed` | Payment attempted but rejected — card declined, Stripe error |
| `expired` | Stripe checkout session timed out before customer paid (Stripe fires `checkout.session.expired` webhook). `stripe_session_id` is now invalid. Admin must generate a new checkout link → new payment row inserted with `status=pending`. |

**`expired` design note:** Each checkout session attempt creates one payment row. If admin sends link 5 times and cx misses all 5, there will be 5 rows with `status=expired`. This is correct — each Stripe session is a distinct auditable event with its own `stripe_session_id`. The payments table is an immutable ledger; never update expired rows.

**Reporting — always count at order level, not row level:**
```sql
-- Abandoned checkout rate (orders, not attempts)
SELECT COUNT(DISTINCT order_id)
FROM payments
WHERE status = 'expired'
  AND order_id NOT IN (SELECT order_id FROM payments WHERE status = 'paid');

-- Orders with 3+ failed checkout attempts (needs follow-up)
SELECT order_id, COUNT(*) AS attempts
FROM payments
WHERE status = 'expired'
GROUP BY order_id
HAVING COUNT(*) >= 3;
```

---

### `shipments`

Every physical movement of a package — in or out.
One row per shipment leg. Polymorphic — covers orders, replacements, and complaints.

```
shipments
├── id
├── shippable_type            varchar                 — 'order', 'replacement', 'complaint'
├── shippable_id              bigint
├── direction                 enum                    — outbound, inbound
├── carrier                   varchar nullable        — FedEx, UPS, USPS
├── tracking_number           varchar nullable
├── label_cost                decimal(10,2) nullable  — what YOU paid for this label
├── status                    enum                    — in_transit, delivered, rts, failed
│                                                        in_transit: dispatched, not yet received
│                                                        delivered:  confirmed received by customer
│                                                        rts:        carrier returned package to us (same tracking, bounced back)
│                                                        failed:     lost / unrecoverable (carrier claim filed)
├── shipped_at                timestamp nullable
├── estimated_delivery        date nullable
├── delivered_at              timestamp nullable
├── rts_received_at           timestamp nullable      — when RTS package physically arrives back at warehouse (NULL unless status=rts)
├── rts_reason                enum nullable           — wrong_address, refused, failed_attempts, other (NULL unless status=rts)
├── created_by                FK → users               — admin who logged the shipment
└── timestamps
```

**Mapping:**

| shippable_type | direction | Scenario |
|---------------|-----------|---------|
| order | outbound | Original order ships to customer |
| order | inbound | Cancelled order — items returned |
| replacement | outbound | Replacement unit ships to customer |
| complaint | inbound | Customer ships faulty unit to us |
| complaint | outbound | We return examined unit to customer (no fault) |

`orders.shipping_amount` = what customer was charged (revenue).
`shipments.label_cost` = what you actually paid (cost).
Difference = shipping margin.

---

### `complaints`

Always created first — at the moment customer calls.
Paper trail: what they said, which unit, when.
Examination result recorded here when unit is physically inspected.

```
complaints
├── id
├── complaint_number          varchar unique          — CMP-2026-001
├── order_id                  FK → orders
├── order_line_id             FK → order_lines        — which product
├── serial_number_id          FK → inventory_serials  — which unit
├── issue_description         text                    — customer's words, captured now
├── status                    enum                    — reported, unit_in_transit,
│                                                        received, examined, closed, withdrawn
├── unit_received_at          timestamp nullable
├── examination_notes         text nullable
├── examination_result        enum nullable           — no_fault_found, internal_issues, damaged_by_customer, shipping_damage
├── unit_outcome              enum nullable           — scrapped, rebuild,
│                                                        back_to_stock, returned_to_customer
├── examined_by               FK → users nullable
├── closed_at                 timestamp nullable       — set when status moves to closed
├── closed_by                 FK → users nullable      — admin who closed the complaint
├── withdrawn_at              timestamp nullable       — set when cx withdraws complaint
├── withdrawn_by              FK → users nullable      — admin who processed the withdrawal
├── created_by                FK → users
└── timestamps
```

### `complaints.examination_result` outcomes

The four values answer **"whose fault is it?"** — which drives **"who pays?"**

| Result | Tech finding | Who pays | Default `unit_outcome` |
|---|---|---|---|
| `no_fault_found` | Works fine, no defect | Customer (or admin waives) | `back_to_stock` or `returned_to_customer` |
| `internal_issues` | Manufacturer defect | You / manufacturer | `scrapped` or `rebuild` |
| `damaged_by_customer` | Physical/water damage, customer fault | Customer | `returned_to_customer` (no replacement) or `scrapped` |
| `shipping_damage` | Damaged by FedEx/UPS in transit | Carrier (insurance claim) | `scrapped` — replace customer free, file claim |

**Rule:** `damaged_by_customer` voids warranty — no free replacement. `shipping_damage` triggers a carrier insurance claim workflow (file with FedEx/UPS to recover cost).

### `complaints.status` lifecycle

| Status | When |
|---|---|
| `reported` | Complaint created — cx called/contacted, issue logged |
| `unit_in_transit` | Cx shipped unit back — tracking confirmed, package on the way |
| `received` | Unit physically arrived at warehouse |
| `examined` | Tech completed inspection — `examination_result` and `unit_outcome` set |
| `closed` | Complaint fully resolved — replacement delivered or refund issued |
| `withdrawn` | Cx changed mind and cancelled complaint before examination. `withdrawn_at` and `withdrawn_by` set. |

**Withdrawal rule:** Complaint can only be withdrawn before `examined`. Once tech has examined the unit, the complaint MUST be closed with a result — withdrawal is no longer allowed. System must enforce this: block `withdrawn` transition if `status = examined`.

**Serial state on withdrawal by stage:**

| Withdrawn at stage | Serial state at withdrawal | Action required |
|---|---|---|
| `reported` | `sold` — still with cx | No movement. Complaint closes. Serial stays `sold`. |
| `unit_in_transit` | `expected_return` — cx already shipped it back | Cannot recall package. Unit arrives → return to cx via outbound shipment → serial stays `sold`. |
| `received` | `under_examination` | Return unit to cx via outbound shipment → serial stays `sold`. |

**Reporting:**
```sql
-- Withdrawal rate per year
SELECT COUNT(*) FROM complaints WHERE status = 'withdrawn';
```

---

### `replacements`

```
replacements
├── id
├── replacement_number        varchar unique          — REP-2026-001
├── order_id                  FK → orders             — always the root order
├── parent_replacement_id     FK → replacements nullable  — NULL = from order
│                                                           SET = chained replacement
├── complaint_id              FK → complaints         — always set, complaint always first
├── type                      enum                    — free, charged
├── charge_amount             decimal(10,2) nullable  — only if type = charged
│
│   -- Payment (controlled denormalization — PaymentService owns this)
├── payment_status            enum nullable           — unpaid, partial, paid (NULL if type=free)
│
├── status                    enum                    — pending, pending_stock, processing, shipped, delivered, cancelled
│
│   -- Lifecycle timestamps + actors
├── shipped_at                timestamp nullable       — denorm from outbound shipment
├── shipped_by                FK → users nullable      — admin who marked shipped
├── delivered_at              timestamp nullable       — denorm from outbound shipment
├── delivered_by              FK → users nullable      — admin who confirmed delivery
├── cancelled_at              timestamp nullable
├── cancelled_by              FK → users nullable      — admin who cancelled
│
├── internal_notes            text nullable
├── created_by                FK → users
└── timestamps
```

### `replacements.status` lifecycle

| Status | When |
|---|---|
| `pending` | Replacement created, stock check not yet done |
| `pending_stock` | Confirmed out of stock — customer waiting. CSR must either: (1) wait for restock, (2) assign an interchange serial (different SKU, same function — detectable when `new_serial.sku ≠ order_line.sku`), or (3) raise a purchase order to source the unit. Offer refund if customer does not want to wait. |
| `processing` | Serial assigned and picked from stock, packing for ship |
| `shipped` | Out the door, in transit |
| `delivered` | Customer received |
| `cancelled` | Aborted before shipping (mind change / reclassified / admin error) |

**Rule:** A `shipped` replacement cannot be `cancelled` — the unit already left. Use a complaint / refund instead.

**Interchange rule:** When CSR assigns a serial whose SKU differs from the original `order_line.sku`, that is an interchange substitution. No extra column needed — the mismatch between `replacement_lines.order_line_id → order_lines.sku` and `replacement_lines.new_serial_number_id → inventory_serials.sku` signals interchange. CSR/tech decides the alternative SKU based on product knowledge. System should surface this mismatch in the UI for admin awareness.

### `replacements.payment_status` lifecycle

| Status | When |
|---|---|
| NULL | `type = free` — no charge, no payment needed |
| `unpaid` | `type = charged`, no payments recorded yet |
| `partial` | `type = charged`, some payments collected, balance still owed |
| `paid` | `type = charged`, sum(payments) ≥ charge_amount |

**Rule:** Mirrors `orders.payment_status` exactly. Derived from `payments` table by PaymentService — never hand-set.

---

### `replacement_lines`

```
replacement_lines
├── id
├── replacement_id            FK → replacements
├── order_line_id             FK → order_lines        — original line being replaced
├── sku                       varchar                 — snapshot
├── product_name              varchar                 — snapshot
├── old_serial_number_id      FK → inventory_serials  — faulty unit
├── new_serial_number_id      FK → inventory_serials nullable  — new unit (assigned when shipped)
└── timestamps
```

No return tracking columns here. Complaint owns the examination and outcome.

---

### `refunds`

```
refunds
├── id
├── refund_number             varchar unique          — REF-2026-001
├── order_id                  FK → orders             — always root (for grouping)
├── refundable_type           varchar                 — 'order', 'replacement'
├── refundable_id             bigint
├── amount                    decimal(10,2)           — total refund
├── shipping_refund_amount    decimal(10,2) default 0 — portion refunded for shipping (0 = not refunded)
├── reason                    text
├── refund_method             enum                    — cash, stripe, bank_transfer
├── stripe_refund_id          varchar nullable
├── avatax_return_transaction_code  varchar nullable
├── avatax_return_committed         boolean default false
├── status                    enum                    — pending, processed, failed
├── failure_reason            text nullable           — populated when status = failed (Stripe error message)
├── processed_at              timestamp nullable
├── failed_at                 timestamp nullable        — set when Stripe rejects the refund
├── created_by                FK → users
└── timestamps
```

### `refunds.status` lifecycle (synchronous, live status)

| Status | When |
|---|---|
| `pending` | Refund created, Stripe API call in flight (a few seconds) |
| `processed` | Stripe succeeded — money back to customer, row LOCKED |
| `failed` | Stripe rejected — `failure_reason` captures why, admin clicks "Try Again" |

**Flow:** Admin clicks Refund → INSERT (pending) → call Stripe → live response in seconds → UPDATE to `processed` or `failed`. No queue, no async retry.

**Retry:** "Try Again" button on a `failed` row re-runs Stripe call. Same row gets updated. Every attempt logged via ActivityLog.

### Refund integrity rules (no loopholes)

1. `processed` rows are **immutable** — no status changes, no amount edits ever.
2. Service must check before INSERT: `SUM(refunds.amount WHERE status='processed') + new_amount ≤ grand_total + SUM(replacements.charge_amount)`. Prevents over-refunding.
3. `failed` attempts do NOT count toward refunded total (only `processed` does).
4. All actions logged to ActivityLog — who, when, Stripe response, full audit trail.

### When to refund shipping

| Situation | shipping_refund_amount |
|---|---|
| Partial product return after delivery | $0 — shipping was rendered |
| Order cancelled before it shipped | = orders.shipping_amount |
| Package lost in transit | = orders.shipping_amount |
| Fast shipping paid — item never arrived | = orders.shipping_amount |

**Lost in transit:** complaint created → serial → `missing` → no return shipment row → refund with `shipping_refund_amount = orders.shipping_amount`. OR create replacement (free, your cost) — no refund row.

---

## Required Indexes

Polymorphic + high-traffic columns need explicit indexes for fast queries.

### Polymorphic composite indexes
| Table | Index |
|---|---|
| `order_status_history` | `(historyable_type, historyable_id)` |
| `payments` | `(payable_type, payable_id)` |
| `shipments` | `(shippable_type, shippable_id)` |
| `refunds` | `(refundable_type, refundable_id)` |

### Status + date indexes (for date-range admin reports)
| Table | Index |
|---|---|
| `orders` | `(status, created_at)`, `(payment_status)`, `(cancelled_at)`, `(delivered_at)` |
| `complaints` | `(status, created_at)`, `(serial_number_id)`, `(closed_at)` |
| `replacements` | `(status, created_at)`, `(complaint_id)`, `(parent_replacement_id)` |
| `refunds` | `(status, created_at)`, `(processed_at)` |
| `payments` | `(status, created_at)`, `(failed_at)` |
| `shipments` | `(direction, shipped_at)`, `(delivered_at)` |
| `inventory_serials` | `(status)`, `(product_id, status)` |

### Unique indexes (already implied by `unique` keyword)
- `orders.order_number`, `payments.payment_number`, `refunds.refund_number`, `complaints.complaint_number`, `replacements.replacement_number`

### FK indexes
Laravel auto-creates indexes on every `foreignId()`. All `*_by` actor columns and `created_by` columns are indexed by default — no manual work needed.

---

## ActivityLog Integration (Spatie)

Which models track every field change via the `LogsActivity` trait.

| Model | LogsActivity? | Why |
|---|---|---|
| `Order` | ✅ Yes | Track all order state changes |
| `Replacement` | ✅ Yes | Track replacement lifecycle |
| `Complaint` | ✅ Yes | Track examination + outcome decisions |
| `Refund` | ✅ Yes | Critical financial audit |
| `Payment` | ✅ Yes | Critical financial audit |
| `Shipment` | ✅ Yes | Track tracking number / carrier changes |
| `InventorySerial` | ✅ Yes | Status transitions (in_stock → sold → etc.) |
| `OrderLine` | ❌ No | Line items immutable after order creation; covered by order parent |
| `OrderFee` | ❌ No | Same |
| `ReplacementLine` | ❌ No | Same |
| `OrderStatusHistory` | ❌ No | This table IS the status timeline |
| `InventoryMovement` | ❌ No | Append-only ledger — itself is the audit trail |

**Rule:** Use `order_status_history` for status changes (queryable timeline). Use ActivityLog for non-status field edits (notes, addresses, etc.).

---

## Soft Deletes

Which tables support `SoftDeletes` (deleted_at column).

| Table | Soft Delete? | Reason |
|---|---|---|
| `customers` | ✅ Yes | Customer might be deactivated but data preserved |
| `users` | ✅ Yes | Staff turnover, preserve audit trail |
| `products`, `product_listings` | ✅ Yes | Discontinued products preserved for old orders |
| `inventory_locations` | ✅ Yes | Closed warehouses preserved |
| `inventory_serials` | ❌ No | Status enum handles end-of-life (scrapped/missing) |
| `orders` | ❌ No | Cancel via `status='cancelled'`, never delete |
| `order_lines`, `order_fees` | ❌ No | Immutable line items |
| `payments` | ❌ No | Financial records — never deletable |
| `refunds` | ❌ No | Financial records — never deletable |
| `shipments` | ❌ No | Audit trail — never deletable |
| `complaints` | ❌ No | Use `status='closed'` instead |
| `replacements` | ❌ No | Use `status='cancelled'` instead |
| `inventory_movements` | ❌ No | Append-only immutable ledger |
| `order_status_history` | ❌ No | Append-only |

---

## Relationship Map

```
customers
    │
    └── orders
         │
         ├── order_lines ─────────────────────────────→ inventory_serials
         │    └── FK: order_line_id ──→ complaints ────→ inventory_serials (examined)
         ├── order_fees
         ├── order_status_history (polymorphic)
         ├── payments     (payable:  order)
         ├── shipments    (shippable: order — outbound + inbound)
         ├── refunds      (refundable: order)
         │
         └── replacements ←── complaint_id ─── complaints
              ├── parent_replacement_id ──→ replacements (chain)
              │
              ├── replacement_lines
              │    ├── old_serial ──→ inventory_serials
              │    └── new_serial ──→ inventory_serials
              │
              ├── payments     (payable:  replacement)
              ├── shipments    (shippable: replacement — outbound)
              ├── order_status_history (polymorphic)
              └── refunds      (refundable: replacement)

complaints
    └── shipments (shippable: complaint — inbound + outbound)
```

---

## Financial Logic

### Order payment_status

```
grand_total  = subtotal - discount + fees_total + tax_amount
             + shipping_amount + shipping_tax_amount
paid_total   = SUM(payments.amount WHERE payable_type='order' AND status='paid')

unpaid   → paid_total = 0
partial  → 0 < paid_total < grand_total
paid     → paid_total >= grand_total
```

### Shipping margin

```
shipping_revenue = orders.shipping_amount          ← what customer paid
shipping_cost    = SUM(shipments.label_cost)       ← what you paid
shipping_margin  = shipping_revenue - shipping_cost
```

### Full chain summary

```
chain_charged   = grand_total + SUM(replacements.charge_amount WHERE type='charged')
chain_refunded  = SUM(all refunds in chain)
chain_collected = SUM(all paid payments in chain)
chain_net       = chain_collected - chain_refunded
```

---

## Stripe Payment Flows

### Stripe Checkout (async — webhook)
```
1. Admin generates checkout session → INSERT payments (status: pending, stripe_session_id)
2. Link sent to customer manually
3. Customer pays → Stripe webhook: checkout.session.completed
4. UPDATE payments (status: paid, stripe_charge_id, stripe_receipt_url)
5. UPDATE order/replacement payment_status
```

### Stripe Card (sync)
```
1. Admin enters card via Stripe Elements → Payment Intent created + charged
2. Success → INSERT payments (status: paid, stripe_payment_intent_id, stripe_charge_id)
3. Failure → show error, nothing inserted
```

### Stripe Terminal (sync)
```
1. Admin clicks charge → Terminal Payment Intent created → reader activates
2. Customer taps/swipes → INSERT payments (status: paid, stripe_terminal_reader_id)
```

---

## Serial Status — All Paths

```
in_stock
  │
  ├──[sale]──────────────────────────────────────────→ sold
  │                                                      │
  │                                         [complaint created]
  │                                                      │
  │         Flow A: examine first          Flow B: send replacement first
  │              │                                       │
  │         [unit arrives]              [replacement_out, then unit arrives later]
  │         return_in                   replacement_out → assigned
  │         under_examination           expected_return (old unit, with customer)
  │              │                            │
  │         [examine]                  [return_in, days/weeks later]
  │         bad ──────────────────→ scrapped  under_examination
  │             └──────────────────→ rebuild  (damaged)      │
  │         no_fault ────────────→ sold       [examine]
  │                    (returned  OR in_stock bad ──────────→ scrapped / rebuild
  │                     to cx)   (kept)      no_fault ──────→ charge or waive
  │                                                    sold (returned) or in_stock (kept)
  │
  └──[replacement_out]────────────────────────────────→ assigned
```

---

## Full Data Example — 8 Orders, All Edge Cases

### Scenarios

```
ORD-001  Sarah Johnson    stripe_card      $240   Clean — no issues
ORD-002  Mike Torres      cash             $380   Partial payment outstanding ⚠️
ORD-004  Karen White      cash             $250   Flow A: no fault → returned to customer, no charge
ORD-005  David Park       stripe_terminal  $510   Multi-line: Flow B bad+scrapped / Flow A no fault+returned
ORD-006  Lisa Chen        stripe_card      $240   Flow B: no fault → charged + kept in stock
ORD-007  Emma Davis       stripe_terminal  $240   Flow A: bad → charged replacement upfront + partial refund
ORD-009  Amanda Taylor    stripe_card      $330   Cancelled → full refund → items returned
ORD-010  Chris Martinez   cash             $240   Flow B: old unit NEVER returned — overdue ⚠️
```

---

### `orders`

```
id   number         customer          shipping  grand_total  payment_status  status
─────────────────────────────────────────────────────────────────────────────────────────
1    ORD-2026-001   Sarah Johnson      20.00    240.00       paid            delivered
2    ORD-2026-002   Mike Torres         0.00    380.00       partial         processing   ⚠️
4    ORD-2026-004   Karen White        20.00    250.00       paid            delivered
5    ORD-2026-005   David Park         30.00    510.00       paid            delivered
6    ORD-2026-006   Lisa Chen          20.00    240.00       paid            delivered
7    ORD-2026-007   Emma Davis         20.00    240.00       paid            delivered
9    ORD-2026-009   Amanda Taylor       0.00    330.00       paid            cancelled
10   ORD-2026-010   Chris Martinez     20.00    240.00       paid            delivered
```

---

### `order_lines`

```
id   order  sku     product_name   serial   unit_price  line_total
────────────────────────────────────────────────────────────────────
1    1      PROD-A  Widget Pro     SN-001   200.00      200.00
2    2      PROD-A  Widget Pro     SN-010   200.00      200.00
3    2      PROD-B  Widget Basic   SN-011   150.00      150.00
5    4      PROD-A  Widget Pro     SN-030   200.00      200.00
6    5      PROD-A  Widget Pro     SN-040   200.00      200.00
7    5      PROD-B  Widget Basic   SN-041   150.00      150.00
8    5      PROD-C  Widget Mini    SN-042    80.00       80.00
9    6      PROD-A  Widget Pro     SN-050   200.00      200.00
10   7      PROD-A  Widget Pro     SN-060   200.00      200.00
13   9      PROD-A  Widget Pro     SN-080   200.00      200.00
14   9      PROD-B  Widget Basic   SN-081   100.00      100.00
15   10     PROD-A  Widget Pro     SN-090   200.00      200.00
```

---

### `order_fees`

```
id   order  name            amount
─────────────────────────────────────
1    1      Service Fee     20.00
2    2      Service Fee     30.00
4    4      Service Fee     30.00
5    5      Service Fee     50.00
6    6      Service Fee     20.00
7    7      Service Fee     20.00
9    9      Service Fee     30.00
10   10     Service Fee     20.00
```

Grand total verification (subtotal + service_fees + shipping + shipping_tax = grand_total):
(shipping_tax = $0 in all sample orders — AvaTax not engaged in fixture data)
```
ORD-001: $200 + $20 + $20 + $0 = $240 ✓
ORD-002: $350 + $30 +  $0 + $0 = $380 ✓
ORD-004: $200 + $30 + $20 + $0 = $250 ✓
ORD-005: $430 + $50 + $30 + $0 = $510 ✓
ORD-006: $200 + $20 + $20 + $0 = $240 ✓
ORD-007: $200 + $20 + $20 + $0 = $240 ✓
ORD-009: $300 + $30 +  $0 + $0 = $330 ✓
ORD-010: $200 + $20 + $20 + $0 = $240 ✓
```

---

### `payments`

```
id   order  payable_type  payable_id  method           amount   status   notes
──────────────────────────────────────────────────────────────────────────────────────────────
1    1      order         1           stripe_card      240.00   paid
2    2      order         2           cash             250.00   paid     partial only
3    2      order         2           cash             130.00   pending  ⚠️ balance still owed
5    4      order         4           cash             250.00   paid
6    5      order         5           stripe_terminal  510.00   paid
7    6      order         6           stripe_card      240.00   paid
8    6      replacement   3           stripe_card       80.00   paid     charged after no_fault_found
9    7      order         7           stripe_terminal  240.00   paid
10   7      replacement   4           stripe_terminal   80.00   paid     charged upfront
12   9      order         9           stripe_card      330.00   paid
13   10     order         10          cash             240.00   paid
```

---

### `shipments`

```
id   type         id   dir       carrier  tracking      label_cost  shipped_at            delivered_at          notes
────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
1    order        1    outbound  FedEx    FX-10001      8.50        2026-04-20 09:00      2026-04-22 14:00      ORD-001 → Sarah
2    order        2    outbound  FedEx    FX-10002      12.00       2026-04-21 09:00      2026-04-23 11:00      ORD-002 → Mike
4    order        4    outbound  FedEx    FX-10004      8.50        2026-04-23 09:00      2026-04-25 12:00      ORD-004 → Karen
5    order        5    outbound  FedEx    FX-10005      14.00       2026-04-24 11:00      2026-04-26 15:00      ORD-005 → David
6    order        6    outbound  FedEx    FX-10006      8.50        2026-04-25 09:00      2026-04-27 14:00      ORD-006 → Lisa
7    order        7    outbound  FedEx    FX-10007      8.50        2026-04-26 10:00      2026-04-28 11:00      ORD-007 → Emma
9    order        9    outbound  FedEx    FX-10009      10.00       2026-04-28 10:00      2026-04-30 12:00      ORD-009 → Amanda
10   order        10   outbound  FedEx    FX-10010      8.50        2026-04-29 09:00      2026-05-01 14:00      ORD-010 → Chris

     ── complaint inbound: customer ships unit to us (Flow A) ──
13   complaint    2    inbound   FedEx    FX-20002      7.00        2026-04-30 00:00      2026-05-01 14:00      CMP-002 Karen → us (prepaid label)
14   complaint    4    inbound   FedEx    FX-20004      7.00        2026-05-01 00:00      2026-05-03 09:00      CMP-004 David → us (prepaid label)
15   complaint    6    inbound   UPS      UP-20006      0.00        2026-05-03 00:00      2026-05-04 11:00      CMP-006 Emma → us (her label)

     ── complaint outbound: we return examined unit to customer (no fault) ──
17   complaint    2    outbound  FedEx    FX-30002      8.50        2026-05-02 14:00      2026-05-04 12:00      CMP-002 Karen's unit returned
18   complaint    4    outbound  FedEx    FX-30004      8.50        2026-05-04 10:00      2026-05-06 11:00      CMP-004 David's unit returned

     ── complaint inbound: unit arrives back (Flow B — days/weeks later) ──
19   complaint    3    inbound   UPS      UP-20003      0.00        2026-05-06 00:00      2026-05-08 11:00      CMP-003 David SN-040 (8 days later)
20   complaint    5    inbound   FedEx    FX-20005      7.00        2026-05-07 00:00      2026-05-09 10:00      CMP-005 Lisa SN-050 (9 days later)
22   complaint    9    inbound   UPS      UP-20009      0.00        2026-05-05 00:00      NULL                  CMP-009 Chris SN-090 — NEVER ARRIVED ⚠️

     ── replacement outbound: new unit ships to customer ──
25   replacement  2    outbound  FedEx    FX-40002      8.50        2026-05-01 14:00      2026-05-03 12:00      REP-002 → David (SN-043) Flow B immediate
26   replacement  3    outbound  FedEx    FX-40003      8.50        2026-05-01 11:00      2026-05-03 14:00      REP-003 → Lisa (SN-051) Flow B immediate
27   replacement  4    outbound  FedEx    FX-40004      8.50        2026-05-05 10:00      2026-05-07 12:00      REP-004 → Emma (SN-061)
30   replacement  7    outbound  FedEx    FX-40007      8.50        2026-04-30 14:00      2026-05-02 13:00      REP-007 → Chris (SN-091) Flow B immediate

     ── cancelled order: items returned by customer ──
32   order        9    inbound   UPS      UP-50009      0.00        2026-05-02 00:00      2026-05-04 10:00      ORD-009 Amanda returns both items
```

---

### `complaints`

```
id   number      order  line  serial   flow  issue_description                    status          unit_received_at      result          outcome
──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
2    CMP-002     4      5     SN-030   A     Screen flickering, unusable           closed          2026-05-01 14:00      no_fault_found  returned_to_customer
3    CMP-003     5      6     SN-040   B     Device dead, needs unit urgently      closed          2026-05-08 11:00      internal_issues scrapped
4    CMP-004     5      7     SN-041   A     Widget Basic overheating badly        closed          2026-05-03 09:00      no_fault_found  returned_to_customer
5    CMP-005     6      9     SN-050   B     Device not turning on at all          closed          2026-05-09 10:00      no_fault_found  back_to_stock
6    CMP-006     7      10    SN-060   A     Motor making grinding noise           closed          2026-05-04 11:00      internal_issues scrapped
9    CMP-009     10     15    SN-090   B     Device malfunctioning, urgent         unit_in_transit NULL                  NULL            NULL         ⚠️ 35d overdue
```

**Flow A** (examine before replacement): CMP-002, 004, 006

**Flow B** (replacement sent first, examine when unit returns): CMP-003, 005, 009

**CMP-009** — unit never arrived. 35 days overdue. No examination. Open case. ⚠️

---

### `replacements`

```
id   number         order  parent  complaint  type     charge   pay_status  status
───────────────────────────────────────────────────────────────────────────────────────────
2    REP-2026-002   5      NULL    3          free     NULL     NULL        delivered   CMP-003: David Flow B bad → free
3    REP-2026-003   6      NULL    5          charged  80.00    paid        delivered   CMP-005: Lisa no fault → charged after exam
4    REP-2026-004   7      NULL    6          charged  80.00    paid        delivered   CMP-006: Emma bad → charged upfront
7    REP-2026-007   10     NULL    9          free     NULL     NULL        delivered   CMP-009: Chris Flow B — old unit overdue ⚠️
```

**No replacements for:** CMP-002 (Karen — no fault, unit returned, no replacement needed), CMP-004 (David — no fault, unit returned, no replacement needed).

---

### `replacement_lines`

```
id   rep  order_line  sku     product_name   old_serial  new_serial   note
─────────────────────────────────────────────────────────────────────────────────────────
2    2    6           PROD-A  Widget Pro     SN-040      SN-043       David — REP-002
3    3    9           PROD-A  Widget Pro     SN-050      SN-051       Lisa — REP-003
4    4    10          PROD-A  Widget Pro     SN-060      SN-061       Emma — REP-004
7    7    15          PROD-A  Widget Pro     SN-090      SN-091       Chris — REP-007
```

---

### `refunds`

```
id   number      order  type         payable  amount   ship_refund  method  reason                           status
──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
2    REF-002     7      replacement  4         24.00     0.00       stripe  30% partial — REP-004 Emma        processed
5    REF-005     9      order        9        330.00     0.00       stripe  Full refund — order cancelled     processed
```

---

### `inventory_serials` — Final State

```
serial   sku     status           location      note
──────────────────────────────────────────────────────────────────────────────────────────
SN-001   PROD-A  sold             NULL          with Sarah Johnson — clean ✓
SN-010   PROD-A  sold             NULL          with Mike Torres — partial payment ⚠️
SN-011   PROD-B  sold             NULL          with Mike Torres — partial payment ⚠️
SN-030   PROD-A  sold             NULL          Karen — no fault, returned to customer (Flow A)
SN-040   PROD-A  scrapped         NULL          David — CMP-003 confirmed bad (Flow B)
SN-041   PROD-B  sold             NULL          David — no fault, returned to customer (Flow A)
SN-042   PROD-C  sold             NULL          with David Park — no issues ✓
SN-043   PROD-A  sold             NULL          with David Park — REP-002
SN-050   PROD-A  in_stock         Warehouse A   Lisa — no fault, charged, kept in stock (Flow B)
SN-051   PROD-A  sold             NULL          with Lisa Chen — REP-003
SN-060   PROD-A  scrapped         NULL          Emma — CMP-006 confirmed bad (Flow A)
SN-061   PROD-A  sold             NULL          with Emma Davis — REP-004 (charged)
SN-080   PROD-A  in_stock         Warehouse A   Amanda — cancelled, returned
SN-081   PROD-B  in_stock         Warehouse A   Amanda — cancelled, returned
SN-090   PROD-A  expected_return  NULL          Chris — OVERDUE 35 days, never arrived ⚠️⚠️
SN-091   PROD-A  sold             NULL          with Chris Martinez — REP-007
```

**All 8 serial statuses covered:**
```
sold              → 10 units  (with customers)
in_stock          →  3 units  (SN-050 charged kept, SN-080/081 cancelled)
scrapped          →  2 units  (SN-040, SN-060)
expected_return   →  1 unit   (SN-090 overdue)
damaged           →  0 units
missing           →  0 units  (no lost units in worked example)
assigned          →  0 units  (transient — only while replacement is in transit)
under_examination →  0 units  (transient — only between arrival at shop and tech examination)
```

---

### `inventory_movements` — Full Immutable Ledger

```
id   serial   type             from          to            reference       notes
──────────────────────────────────────────────────────────────────────────────────────────────────────────
     ── All original orders shipped ──
1    SN-001   sale             Warehouse A   NULL          ORD-2026-001
2    SN-010   sale             Warehouse A   NULL          ORD-2026-002
3    SN-011   sale             Warehouse A   NULL          ORD-2026-002
5    SN-030   sale             Warehouse A   NULL          ORD-2026-004
6    SN-040   sale             Warehouse A   NULL          ORD-2026-005
7    SN-041   sale             Warehouse A   NULL          ORD-2026-005
8    SN-042   sale             Warehouse A   NULL          ORD-2026-005
9    SN-050   sale             Warehouse A   NULL          ORD-2026-006
10   SN-060   sale             Warehouse A   NULL          ORD-2026-007
13   SN-080   sale             Warehouse A   NULL          ORD-2026-009
14   SN-081   sale             Warehouse A   NULL          ORD-2026-009
15   SN-090   sale             Warehouse A   NULL          ORD-2026-010
     ──
     ── Flow A: units arriving at shop for examination ──
18   SN-030   return_in        NULL          Warehouse A   CMP-2026-002    Karen sends unit in
19   SN-041   return_in        NULL          Warehouse A   CMP-2026-004    David sends unit in
20   SN-060   return_in        NULL          Warehouse A   CMP-2026-006    Emma sends unit in
     ──
     ── Flow B: replacement ships first (before old unit returns) ──
22   SN-043   replacement_out  Warehouse A   NULL          REP-2026-002    David — REP-002 immediate
23   SN-051   replacement_out  Warehouse A   NULL          REP-2026-003    Lisa  — REP-003 immediate
25   SN-091   replacement_out  Warehouse A   NULL          REP-2026-007    Chris — REP-007 immediate
     ──
     ── Flow B: old units arrive back (days/weeks later) ──
27   SN-040   return_in        NULL          Warehouse A   CMP-2026-003    David SN-040 — 8 days later
28   SN-050   return_in        NULL          Warehouse A   CMP-2026-005    Lisa SN-050 — 9 days later
          ← SN-090 NEVER ARRIVED — no movement record — still expected_return ⚠️
     ──
     ── Replacement units going out after Flow A examination confirms bad ──
32   SN-061   replacement_out  Warehouse A   NULL          REP-2026-004    Emma  — REP-004 (charged)
     ──
     ── No fault found: examined units returned to customer (adjustment) ──
34   SN-030   adjustment       Warehouse A   NULL          CMP-2026-002    Karen — no fault, handed back
35   SN-041   adjustment       Warehouse A   NULL          CMP-2026-004    David — no fault, handed back
     ──
     ── Cancelled order: both items returned by Amanda ──
36   SN-080   return_in        NULL          Warehouse A   ORD-2026-009    Amanda cancelled return
37   SN-081   return_in        NULL          Warehouse A   ORD-2026-009    Amanda cancelled return
```

---

## Worst Case Chain

---

### ORD-010 — Chris Martinez (Overdue Return, Open Case)

```
ORD-2026-010  Chris Martinez  $240  cash  Delivered
│  Widget Pro SN-090 $200 | Service $20 | Shipping $20

Customer calls: "device malfunctioning, urgent"
  CMP-2026-009 created immediately — SN-090, issue captured NOW
  status: reported

Admin decision: send replacement immediately (trust customer, Flow B)
  REP-2026-007 created: free, complaint_id=9
  [shipment #25: SN-091 replacement_out same day]
  SN-091 ships to Chris → SN-090 status: expected_return
  CMP-2026-009 status: unit_in_transit

[35 days pass]
  SN-090 never arrived
  CMP-2026-009 status: unit_in_transit  ← still open, no update
  shipment #22: complaint/9/inbound — tracking UP-20009 — delivered_at NULL ⚠️

Current state:
  CMP-2026-009  unit_in_transit  — examination_result NULL  — open case
  SN-090  expected_return — location NULL — 35 days ⚠️⚠️
  SN-091  sold — with Chris ✓

Admin options at day 35:
  Option A: Write off → serial.status = scrapped, complaint closed (no_fault or bad unknown)
  Option B: Charge Chris for SN-090 → UPDATE replacement type=charged, INSERT payment
  Option C: Escalate — contact customer, give 7-day final notice

Financial:
  ORD-010  $240  paid  Net $240
  REP-007  free — SN-090 still unaccounted for
```

---

## All Edge Cases Covered

| Edge Case | Order |
|-----------|-------|
| Clean order, no issues | ORD-001 |
| Partial payment outstanding | ORD-002 |
| Flow A: no fault → unit returned, no charge | ORD-004, ORD-005 (SN-041) |
| Flow A: bad → charged replacement upfront | ORD-007 |
| Flow B: bad → scrapped, no charge | ORD-005 (SN-040) |
| Flow B: no fault → charged + kept in stock | ORD-006 |
| Flow B: unit NEVER returned — overdue open case | ORD-010 |
| Multi-line, different outcome per line | ORD-005 |
| Partial refund on replacement | ORD-007 |
| Full refund — order cancelled | ORD-009 |
| Cancelled order items returned | ORD-009 |
| Stripe card payment | ORD-001, ORD-006, ORD-009 |
| Stripe terminal payment | ORD-005, ORD-007 |
| Cash payment + partial outstanding | ORD-002, ORD-004, ORD-010 |
| Multiple shipment legs per order | ORD-004, ORD-005 |
| Prepaid return label (label_cost > 0 inbound) | CMP-002, CMP-004 |
| Customer's own label (label_cost = 0 inbound) | CMP-006, CMP-009 |

---

## Business Dashboard — May 2026

```
┌───────────────────────────────────────────────────────────┐
│ ORDERS                                                    │
│   Total Orders            8                               │
│   Delivered               6                               │
│   Cancelled               1   (ORD-009)                   │
│   Processing              1   (ORD-002 partial payment)   │
│                                                           │
│ COMPLAINTS & REPLACEMENTS                                 │
│   Total Complaints        6                               │
│   Closed                  5                               │
│   Open (overdue)          1   (CMP-009 SN-090 35d) ⚠️    │
│   Total Replacements      4                               │
│   Chained                 0                               │
│                                                           │
│ MONEY                                                     │
│   Orders Charged      $2,430.00                           │
│   Replacement Charged   $160.00  (REP-003 $80, REP-004 $80│
│   Total Charged       $2,590.00                           │
│   Partial Outstanding   $130.00  ⚠️ (ORD-002 balance)    │
│   Total Refunded        $354.00                           │
│   Net Collected       $2,106.00                           │
│                                                           │
│ SHIPPING                                                  │
│   Total Shipment Legs    21                               │
│   Outbound               13                               │
│   Inbound                 8                               │
│   Label Cost Spent      $150.50                           │
│   Shipping Charged      $130.00  (orders.shipping_amount) │
│   Shipping Margin      -$ 20.50  (loss — baked-in absorbed)│
│   Pending Inbound         1   (CMP-009 SN-090 overdue)⚠️ │
│                                                           │
│ INVENTORY                                                 │
│   Units Sold             10                               │
│   In Stock (recovered)    3                               │
│   Scrapped                2                               │
│   Expected Return         1   (SN-090) ⚠️                │
└───────────────────────────────────────────────────────────┘
```
