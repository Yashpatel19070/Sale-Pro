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
├── billing_zip
├── billing_country
│
├── shipping_name
├── shipping_address_line1
├── shipping_address_line2 nullable
├── shipping_city
├── shipping_state
├── shipping_zip
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
subtotal    = sum of line_totals
discount    = order-level discount
fees_total  = sum of order_fees
tax_amount  = sum of line tax_amounts (from AvaTax)
shipping    = shipping_amount (what customer is charged)
─────────────────────────────────────────────────────
grand_total = subtotal - discount + fees_total + tax_amount + shipping
```

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

## SerialStatus — Two New Values

Current (from code):
```
in_stock        ← available to sell
sold            ← with customer
damaged         ← written off / in rebuild
missing         ← lost
```

Add when building order module:
```
expected_return ← unit should be coming back, not yet arrived
assigned        ← allocated to replacement, not yet shipped
```

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
- `return_in` = unit arrives at shop. Serial → `in_stock`.
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
| `returnIn()` | NULL → location | in_stock |

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
│                                                        delivered, cancelled
│
│   -- Address snapshot
├── billing_name              varchar
├── billing_address_line1     varchar
├── billing_address_line2     varchar nullable
├── billing_city              varchar
├── billing_state             varchar
├── billing_zip               varchar
├── billing_country           varchar
│
├── shipping_name             varchar
├── shipping_address_line1    varchar
├── shipping_address_line2    varchar nullable
├── shipping_city             varchar
├── shipping_state            varchar
├── shipping_zip              varchar
├── shipping_country          varchar
│
│   -- Financials
├── subtotal                  decimal(10,2)
├── discount_amount           decimal(10,2) default 0
├── fees_total                decimal(10,2)
├── tax_amount                decimal(10,2) default 0
├── shipping_amount           decimal(10,2)           — what customer is charged for shipping
├── grand_total               decimal(10,2)
│
│   -- Payment (controlled denormalization — PaymentService owns this)
├── payment_status            enum                    — unpaid, partial, paid
│
├── internal_notes            text nullable
├── customer_notes            text nullable
├── created_by                FK → users
└── timestamps
```

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

---

### `order_status_history`

```
order_status_history
├── id
├── model_type                varchar                 — 'order', 'replacement', 'complaint'
├── model_id                  bigint
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
├── status                    enum                    — pending, paid, failed
│
│   -- Cash
├── cash_received_at          timestamp nullable
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
├── shipped_at                timestamp nullable
├── estimated_delivery        date nullable
├── delivered_at              timestamp nullable
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
│                                                        received, examined, closed
├── unit_received_at          timestamp nullable
├── examination_notes         text nullable
├── examination_result        enum nullable           — bad, no_fault_found
├── unit_outcome              enum nullable           — scrapped, rebuild,
│                                                        back_to_stock, returned_to_customer
├── examined_by               FK → users nullable
├── created_by                FK → users
└── timestamps
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
├── payment_status            enum nullable           — unpaid, paid
│
├── status                    enum                    — pending, processing, shipped, delivered
├── internal_notes            text nullable
├── created_by                FK → users
└── timestamps
```

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
├── amount                    decimal(10,2)
├── reason                    text
├── refund_method             enum                    — cash, stripe, bank_transfer
├── stripe_refund_id          varchar nullable
├── avatax_return_transaction_code  varchar nullable
├── avatax_return_committed         boolean default false
├── status                    enum                    — pending, processed
├── processed_at              timestamp nullable
├── created_by                FK → users
└── timestamps
```

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
grand_total  = subtotal - discount + fees_total + tax_amount + shipping_amount
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
  │         in_stock (at shop)          expected_return (old unit, with customer)
  │              │                            │
  │         [examine]                  [return_in, days/weeks later]
  │         bad ──────────────────→ scrapped  in_stock (at shop)
  │             └──────────────────→ rebuild  (damaged)      │
  │         no_fault ────────────→ sold       [examine]
  │                    (returned  OR in_stock bad ──────────→ scrapped / rebuild
  │                     to cx)   (kept)      no_fault ──────→ charge or waive
  │                                                    sold (returned) or in_stock (kept)
  │
  └──[replacement_out]────────────────────────────────→ assigned
```

---

## Full Data Example — 11 Orders, All Edge Cases

### Scenarios

```
ORD-001  Sarah Johnson    stripe_card      $240   Clean — no issues
ORD-002  Mike Torres      cash             $380   Partial payment outstanding ⚠️
ORD-003  James Brown      cash             $170   Flow A: bad → free replacement → partial refund
ORD-004  Karen White      cash             $250   Flow A: no fault → returned to customer, no charge
ORD-005  David Park       stripe_terminal  $510   Multi-line: Flow B bad+scrapped / Flow A no fault+returned
ORD-006  Lisa Chen        stripe_card      $240   Flow B: no fault → charged + kept in stock (stripe_card pay)
ORD-007  Emma Davis       stripe_terminal  $240   Flow A: bad → charged replacement upfront + partial refund
ORD-008  Robert Wilson    stripe_terminal  $450   Chained: Flow A bad → rep → Flow B rep also bad (rebuild)
ORD-009  Amanda Taylor    stripe_card      $330   Cancelled → full refund → items returned
ORD-010  Chris Martinez   cash             $240   Flow B: old unit NEVER returned — overdue ⚠️
ORD-011  Jessica Lee      stripe_checkout  $240   Flow B: no fault → waived charge → goodwill free rep
```

---

### `orders`

```
id   number         customer          grand_total  payment_status  status
──────────────────────────────────────────────────────────────────────────────
1    ORD-2026-001   Sarah Johnson     240.00       paid            delivered
2    ORD-2026-002   Mike Torres       380.00       partial         processing   ⚠️
3    ORD-2026-003   James Brown       170.00       paid            delivered
4    ORD-2026-004   Karen White       250.00       paid            delivered
5    ORD-2026-005   David Park        510.00       paid            delivered
6    ORD-2026-006   Lisa Chen         240.00       paid            delivered
7    ORD-2026-007   Emma Davis        240.00       paid            delivered
8    ORD-2026-008   Robert Wilson     450.00       paid            delivered
9    ORD-2026-009   Amanda Taylor     330.00       paid            cancelled
10   ORD-2026-010   Chris Martinez    240.00       paid            delivered
11   ORD-2026-011   Jessica Lee       240.00       paid            delivered
```

---

### `order_lines`

```
id   order  sku     product_name   serial   unit_price  line_total
────────────────────────────────────────────────────────────────────
1    1      PROD-A  Widget Pro     SN-001   200.00      200.00
2    2      PROD-A  Widget Pro     SN-010   200.00      200.00
3    2      PROD-B  Widget Basic   SN-011   150.00      150.00
4    3      PROD-B  Widget Basic   SN-020   150.00      150.00
5    4      PROD-A  Widget Pro     SN-030   200.00      200.00
6    5      PROD-A  Widget Pro     SN-040   200.00      200.00
7    5      PROD-B  Widget Basic   SN-041   150.00      150.00
8    5      PROD-C  Widget Mini    SN-042    80.00       80.00
9    6      PROD-A  Widget Pro     SN-050   200.00      200.00
10   7      PROD-A  Widget Pro     SN-060   200.00      200.00
11   8      PROD-A  Widget Pro     SN-070   200.00      200.00
12   8      PROD-B  Widget Basic   SN-071   150.00      150.00
13   9      PROD-A  Widget Pro     SN-080   200.00      200.00
14   9      PROD-B  Widget Basic   SN-081   100.00      100.00
15   10     PROD-A  Widget Pro     SN-090   200.00      200.00
16   11     PROD-A  Widget Pro     SN-100   200.00      200.00
```

---

### `order_fees`

```
id   order  name            amount    id   order  name            amount
──────────────────────────────────────────────────────────────────────────
1    1      Service Fee     20.00     11   6      Shipping        20.00
2    1      Shipping        20.00     12   7      Service Fee     20.00
3    2      Service Fee     30.00     13   7      Shipping        20.00
4    3      Service Fee     20.00     14   8      Service Fee     50.00
5    4      Service Fee     30.00     15   8      Shipping        50.00
6    4      Shipping        20.00     16   9      Service Fee     30.00
7    5      Service Fee     50.00     17   10     Service Fee     20.00
8    5      Shipping        30.00     18   10     Shipping        20.00
9    6      Service Fee     20.00     19   11     Service Fee     20.00
10   6      Shipping        20.00     20   11     Shipping        20.00
```

Grand total verification:
```
ORD-001: $200 + $20 + $20 = $240 ✓       ORD-007: $200 + $20 + $20 = $240 ✓
ORD-002: $200 + $150 + $30 = $380 ✓      ORD-008: $200 + $150 + $50 + $50 = $450 ✓
ORD-003: $150 + $20 = $170 ✓             ORD-009: $200 + $100 + $30 = $330 ✓
ORD-004: $200 + $30 + $20 = $250 ✓       ORD-010: $200 + $20 + $20 = $240 ✓
ORD-005: $200 + $150 + $80 + $50 + $30 = $510 ✓   ORD-011: $200 + $20 + $20 = $240 ✓
ORD-006: $200 + $20 + $20 = $240 ✓
```

---

### `payments`

```
id   order  payable_type  payable_id  method           amount   status   notes
──────────────────────────────────────────────────────────────────────────────────────────────
1    1      order         1           stripe_card      240.00   paid
2    2      order         2           cash             250.00   paid     partial only
3    2      order         2           cash             130.00   pending  ⚠️ balance still owed
4    3      order         3           cash             170.00   paid
5    4      order         4           cash             250.00   paid
6    5      order         5           stripe_terminal  510.00   paid
7    6      order         6           stripe_card      240.00   paid
8    6      replacement   3           stripe_card       80.00   paid     charged after no_fault_found
9    7      order         7           stripe_terminal  240.00   paid
10   7      replacement   4           stripe_terminal   80.00   paid     charged upfront
11   8      order         8           stripe_terminal  450.00   paid
12   9      order         9           stripe_card      330.00   paid
13   10     order         10          cash             240.00   paid
14   11     order         11          stripe_checkout  240.00   paid     async — paid via webhook
```

---

### `shipments`

```
id   type         id   dir       carrier  tracking      label_cost  shipped_at            delivered_at          notes
────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
1    order        1    outbound  FedEx    FX-10001      8.50        2026-04-20 09:00      2026-04-22 14:00      ORD-001 → Sarah
2    order        2    outbound  FedEx    FX-10002      12.00       2026-04-21 09:00      2026-04-23 11:00      ORD-002 → Mike
3    order        3    outbound  FedEx    FX-10003      8.50        2026-04-22 10:00      2026-04-24 13:00      ORD-003 → James
4    order        4    outbound  FedEx    FX-10004      8.50        2026-04-23 09:00      2026-04-25 12:00      ORD-004 → Karen
5    order        5    outbound  FedEx    FX-10005      14.00       2026-04-24 11:00      2026-04-26 15:00      ORD-005 → David
6    order        6    outbound  FedEx    FX-10006      8.50        2026-04-25 09:00      2026-04-27 14:00      ORD-006 → Lisa
7    order        7    outbound  FedEx    FX-10007      8.50        2026-04-26 10:00      2026-04-28 11:00      ORD-007 → Emma
8    order        8    outbound  FedEx    FX-10008      12.00       2026-04-27 09:00      2026-04-29 13:00      ORD-008 → Robert
9    order        9    outbound  FedEx    FX-10009      10.00       2026-04-28 10:00      2026-04-30 12:00      ORD-009 → Amanda
10   order        10   outbound  FedEx    FX-10010      8.50        2026-04-29 09:00      2026-05-01 14:00      ORD-010 → Chris
11   order        11   outbound  FedEx    FX-10011      8.50        2026-04-30 10:00      2026-05-02 11:00      ORD-011 → Jessica

     ── complaint inbound: customer ships unit to us (Flow A) ──
12   complaint    1    inbound   UPS      UP-20001      0.00        2026-05-01 00:00      2026-05-02 10:00      CMP-001 James → us (his label)
13   complaint    2    inbound   FedEx    FX-20002      7.00        2026-04-30 00:00      2026-05-01 14:00      CMP-002 Karen → us (prepaid label)
14   complaint    4    inbound   FedEx    FX-20004      7.00        2026-05-01 00:00      2026-05-03 09:00      CMP-004 David → us (prepaid label)
15   complaint    6    inbound   UPS      UP-20006      0.00        2026-05-03 00:00      2026-05-04 11:00      CMP-006 Emma → us (her label)
16   complaint    7    inbound   FedEx    FX-20007      7.00        2026-05-04 00:00      2026-05-06 14:00      CMP-007 Robert → us (prepaid label)

     ── complaint outbound: we return examined unit to customer (no fault) ──
17   complaint    2    outbound  FedEx    FX-30002      8.50        2026-05-02 14:00      2026-05-04 12:00      CMP-002 Karen's unit returned
18   complaint    4    outbound  FedEx    FX-30004      8.50        2026-05-04 10:00      2026-05-06 11:00      CMP-004 David's unit returned

     ── complaint inbound: unit arrives back (Flow B — days/weeks later) ──
19   complaint    3    inbound   UPS      UP-20003      0.00        2026-05-06 00:00      2026-05-08 11:00      CMP-003 David SN-040 (8 days later)
20   complaint    5    inbound   FedEx    FX-20005      7.00        2026-05-07 00:00      2026-05-09 10:00      CMP-005 Lisa SN-050 (9 days later)
21   complaint    8    inbound   FedEx    FX-20008      7.00        2026-05-08 00:00      2026-05-10 15:00      CMP-008 Robert SN-072 (10 days later)
22   complaint    9    inbound   UPS      UP-20009      0.00        2026-05-05 00:00      NULL                  CMP-009 Chris SN-090 — NEVER ARRIVED ⚠️
23   complaint    10   inbound   FedEx    FX-20010      7.00        2026-05-08 00:00      2026-05-11 09:00      CMP-010 Jessica SN-100 (7 days later)

     ── replacement outbound: new unit ships to customer ──
24   replacement  1    outbound  FedEx    FX-40001      8.50        2026-05-03 10:00      2026-05-05 13:00      REP-001 → James (SN-021)
25   replacement  2    outbound  FedEx    FX-40002      8.50        2026-05-01 14:00      2026-05-03 12:00      REP-002 → David (SN-043) Flow B immediate
26   replacement  3    outbound  FedEx    FX-40003      8.50        2026-05-01 11:00      2026-05-03 14:00      REP-003 → Lisa (SN-051) Flow B immediate
27   replacement  4    outbound  FedEx    FX-40004      8.50        2026-05-05 10:00      2026-05-07 12:00      REP-004 → Emma (SN-061)
28   replacement  5    outbound  FedEx    FX-40005      8.50        2026-05-01 09:00      2026-05-03 11:00      REP-005 → Robert (SN-072) Flow B immediate
29   replacement  6    outbound  FedEx    FX-40006      8.50        2026-05-11 10:00      2026-05-13 12:00      REP-006 → Robert chained (SN-073)
30   replacement  7    outbound  FedEx    FX-40007      8.50        2026-04-30 14:00      2026-05-02 13:00      REP-007 → Chris (SN-091) Flow B immediate
31   replacement  8    outbound  FedEx    FX-40008      8.50        2026-05-05 10:00      2026-05-07 11:00      REP-008 → Jessica (SN-101) Flow B immediate

     ── cancelled order: items returned by customer ──
32   order        9    inbound   UPS      UP-50009      0.00        2026-05-02 00:00      2026-05-04 10:00      ORD-009 Amanda returns both items
```

---

### `complaints`

```
id   number      order  line  serial   flow  issue_description                    status          unit_received_at      result          outcome
──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
1    CMP-001     3      4     SN-020   A     Unit stopped working completely       closed          2026-05-02 10:00      bad             scrapped
2    CMP-002     4      5     SN-030   A     Screen flickering, unusable           closed          2026-05-01 14:00      no_fault_found  returned_to_customer
3    CMP-003     5      6     SN-040   B     Device dead, needs unit urgently      closed          2026-05-08 11:00      bad             scrapped
4    CMP-004     5      7     SN-041   A     Widget Basic overheating badly        closed          2026-05-03 09:00      no_fault_found  returned_to_customer
5    CMP-005     6      9     SN-050   B     Device not turning on at all          closed          2026-05-09 10:00      no_fault_found  back_to_stock
6    CMP-006     7      10    SN-060   A     Motor making grinding noise           closed          2026-05-04 11:00      bad             scrapped
7    CMP-007     8      11    SN-070   A     Unit completely dead, urgent          closed          2026-05-06 14:00      bad             scrapped
8    CMP-008     8      11    SN-072   B     Replacement unit also failed          closed          2026-05-10 15:00      bad             rebuild
9    CMP-009     10     15    SN-090   B     Device malfunctioning, urgent         unit_in_transit NULL                  NULL            NULL         ⚠️ 35d overdue
10   CMP-010     11     16    SN-100   B     Device not charging at all            closed          2026-05-11 09:00      no_fault_found  back_to_stock
```

**Flow A** (examine before replacement): CMP-001, 002, 004, 006, 007

**Flow B** (replacement sent first, examine when unit returns): CMP-003, 005, 008, 009, 010

**CMP-009** — unit never arrived. 35 days overdue. No examination. Open case. ⚠️

---

### `replacements`

```
id   number         order  parent  complaint  type     charge   pay_status  status
───────────────────────────────────────────────────────────────────────────────────────────
1    REP-2026-001   3      NULL    1          free     NULL     NULL        delivered   CMP-001: James bad unit → free
2    REP-2026-002   5      NULL    3          free     NULL     NULL        delivered   CMP-003: David Flow B bad → free
3    REP-2026-003   6      NULL    5          charged  80.00    paid        delivered   CMP-005: Lisa no fault → charged after exam
4    REP-2026-004   7      NULL    6          charged  80.00    paid        delivered   CMP-006: Emma bad → charged upfront
5    REP-2026-005   8      NULL    7          free     NULL     NULL        delivered   CMP-007: Robert Flow B sent immediately
6    REP-2026-006   8      5       8          free     NULL     NULL        delivered   CMP-008: Robert chained (parent=REP-005) ← Flow B
7    REP-2026-007   10     NULL    9          free     NULL     NULL        delivered   CMP-009: Chris Flow B — old unit overdue ⚠️
8    REP-2026-008   11     NULL    10         free     NULL     NULL        delivered   CMP-010: Jessica no fault → waived, goodwill rep
```

**No replacements for:** CMP-002 (Karen — no fault, unit returned, no replacement needed), CMP-004 (David — no fault, unit returned, no replacement needed).

---

### `replacement_lines`

```
id   rep  order_line  sku     product_name   old_serial  new_serial   note
─────────────────────────────────────────────────────────────────────────────────────────
1    1    4           PROD-B  Widget Basic   SN-020      SN-021       James — REP-001
2    2    6           PROD-A  Widget Pro     SN-040      SN-043       David — REP-002
3    3    9           PROD-A  Widget Pro     SN-050      SN-051       Lisa — REP-003
4    4    10          PROD-A  Widget Pro     SN-060      SN-061       Emma — REP-004
5    5    11          PROD-A  Widget Pro     SN-070      SN-072       Robert — REP-005
6    6    11          PROD-A  Widget Pro     SN-072      SN-073       Robert chained — REP-006
7    7    15          PROD-A  Widget Pro     SN-090      SN-091       Chris — REP-007
8    8    16          PROD-A  Widget Pro     SN-100      SN-101       Jessica — REP-008
```

---

### `refunds`

```
id   number      order  type         payable  amount   method  reason                           status
───────────────────────────────────────────────────────────────────────────────────────────────────────────
1    REF-001     3      order        3         34.00   cash    20% goodwill — James Brown        processed
2    REF-002     7      replacement  4         24.00   stripe  30% partial — REP-004 Emma        processed
3    REF-003     8      order        8         90.00   stripe  20% goodwill — Robert Wilson      processed
4    REF-004     8      replacement  6         30.00   stripe  Partial on REP-006 chained        processed
5    REF-005     9      order        9        330.00   stripe  Full refund — order cancelled     processed
```

---

### `inventory_serials` — Final State

```
serial   sku     status           location      note
──────────────────────────────────────────────────────────────────────────────────────────
SN-001   PROD-A  sold             NULL          with Sarah Johnson — clean ✓
SN-010   PROD-A  sold             NULL          with Mike Torres — partial payment ⚠️
SN-011   PROD-B  sold             NULL          with Mike Torres — partial payment ⚠️
SN-020   PROD-B  scrapped         NULL          James — CMP-001 confirmed bad (Flow A)
SN-021   PROD-B  sold             NULL          with James Brown — REP-001
SN-030   PROD-A  sold             NULL          Karen — no fault, returned to customer (Flow A)
SN-040   PROD-A  scrapped         NULL          David — CMP-003 confirmed bad (Flow B)
SN-041   PROD-B  sold             NULL          David — no fault, returned to customer (Flow A)
SN-042   PROD-C  sold             NULL          with David Park — no issues ✓
SN-043   PROD-A  sold             NULL          with David Park — REP-002
SN-050   PROD-A  in_stock         Warehouse A   Lisa — no fault, charged, kept in stock (Flow B)
SN-051   PROD-A  sold             NULL          with Lisa Chen — REP-003
SN-060   PROD-A  scrapped         NULL          Emma — CMP-006 confirmed bad (Flow A)
SN-061   PROD-A  sold             NULL          with Emma Davis — REP-004 (charged)
SN-070   PROD-A  scrapped         NULL          Robert — CMP-007 confirmed bad (Flow A)
SN-071   PROD-B  sold             NULL          with Robert Wilson — no issues ✓
SN-072   PROD-A  damaged          Warehouse A   Robert — CMP-008 also bad, rebuild ⚠️ (Flow B)
SN-073   PROD-A  sold             NULL          with Robert Wilson — REP-006 chained
SN-080   PROD-A  in_stock         Warehouse A   Amanda — cancelled, returned
SN-081   PROD-B  in_stock         Warehouse A   Amanda — cancelled, returned
SN-090   PROD-A  expected_return  NULL          Chris — OVERDUE 35 days, never arrived ⚠️⚠️
SN-091   PROD-A  sold             NULL          with Chris Martinez — REP-007
SN-100   PROD-A  in_stock         Warehouse A   Jessica — no fault, waived, kept (goodwill) (Flow B)
SN-101   PROD-A  sold             NULL          with Jessica Lee — REP-008 goodwill
```

**All 6 serial statuses covered:**
```
sold             → 14 units  (with customers)
in_stock         →  4 units  (SN-050 charged kept, SN-080/081 cancelled, SN-100 waived kept)
scrapped         →  4 units  (SN-020, SN-040, SN-060, SN-070)
damaged          →  1 unit   (SN-072 in rebuild)
expected_return  →  1 unit   (SN-090 overdue)
assigned         →  0 units  (only exists while replacement is in transit — transient state)
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
4    SN-020   sale             Warehouse A   NULL          ORD-2026-003
5    SN-030   sale             Warehouse A   NULL          ORD-2026-004
6    SN-040   sale             Warehouse A   NULL          ORD-2026-005
7    SN-041   sale             Warehouse A   NULL          ORD-2026-005
8    SN-042   sale             Warehouse A   NULL          ORD-2026-005
9    SN-050   sale             Warehouse A   NULL          ORD-2026-006
10   SN-060   sale             Warehouse A   NULL          ORD-2026-007
11   SN-070   sale             Warehouse A   NULL          ORD-2026-008
12   SN-071   sale             Warehouse A   NULL          ORD-2026-008
13   SN-080   sale             Warehouse A   NULL          ORD-2026-009
14   SN-081   sale             Warehouse A   NULL          ORD-2026-009
15   SN-090   sale             Warehouse A   NULL          ORD-2026-010
16   SN-100   sale             Warehouse A   NULL          ORD-2026-011
     ──
     ── Flow A: units arriving at shop for examination ──
17   SN-020   return_in        NULL          Warehouse A   CMP-2026-001    James sends unit in
18   SN-030   return_in        NULL          Warehouse A   CMP-2026-002    Karen sends unit in
19   SN-041   return_in        NULL          Warehouse A   CMP-2026-004    David sends unit in
20   SN-060   return_in        NULL          Warehouse A   CMP-2026-006    Emma sends unit in
21   SN-070   return_in        NULL          Warehouse A   CMP-2026-007    Robert sends unit in
     ──
     ── Flow B: replacement ships first (before old unit returns) ──
22   SN-043   replacement_out  Warehouse A   NULL          REP-2026-002    David — REP-002 immediate
23   SN-051   replacement_out  Warehouse A   NULL          REP-2026-003    Lisa  — REP-003 immediate
24   SN-072   replacement_out  Warehouse A   NULL          REP-2026-005    Robert — REP-005 immediate
25   SN-091   replacement_out  Warehouse A   NULL          REP-2026-007    Chris — REP-007 immediate
26   SN-101   replacement_out  Warehouse A   NULL          REP-2026-008    Jessica — REP-008 immediate
     ──
     ── Flow B: old units arrive back (days/weeks later) ──
27   SN-040   return_in        NULL          Warehouse A   CMP-2026-003    David SN-040 — 8 days later
28   SN-050   return_in        NULL          Warehouse A   CMP-2026-005    Lisa SN-050 — 9 days later
29   SN-072   return_in        NULL          Warehouse A   CMP-2026-008    Robert SN-072 — 10 days later
30   SN-100   return_in        NULL          Warehouse A   CMP-2026-010    Jessica SN-100 — 7 days later
          ← SN-090 NEVER ARRIVED — no movement record — still expected_return ⚠️
     ──
     ── Replacement units going out after Flow A examination confirms bad ──
31   SN-021   replacement_out  Warehouse A   NULL          REP-2026-001    James — REP-001 (bad confirmed)
32   SN-061   replacement_out  Warehouse A   NULL          REP-2026-004    Emma  — REP-004 (charged)
33   SN-073   replacement_out  Warehouse A   NULL          REP-2026-006    Robert — REP-006 chained (bad confirmed)
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

## Worst Case Chains

---

### ORD-008 — Robert Wilson (Chained Replacement, Both Flows)

```
ORD-2026-008  Robert Wilson  $450  stripe_terminal  Delivered
│  Widget Pro SN-070 $200 | Widget Basic SN-071 $150
│  Service $50 | Shipping $50
│  💰 Refund $90 (20% goodwill)  stripe  processed
│  SN-071 Widget Basic — no issues ✓
│
└── CMP-2026-007  "Unit completely dead, urgent"
     Flow A: Robert sends SN-070 in
     [shipment #21: return_in CMP-007]
     SN-070 arrives → examined → BAD → scrapped
     │
     └── REP-2026-005  Free  (SN-070 → SN-072)
          [shipment #24: replacement_out REP-005 same day]
          SN-072 ships to Robert immediately
          │
          SN-072 fails — Robert calls same day
          │
          └── CMP-2026-008  "Replacement unit also failed, urgent"
               Flow B: REP-006 created immediately
               [shipment #28: replacement_out REP-006]
               SN-073 ships → SN-072 expected back
               │
               [10 days later — SN-072 arrives]
               [shipment #29: return_in CMP-008]
               SN-072 examined → BAD → rebuild (damaged, repair team)
               💰 Refund $30 partial on REP-006  stripe
               │
               REP-2026-006  Free  Chained from REP-005  Delivered ✓

Final state:
  SN-070  scrapped         (confirmed bad)
  SN-071  sold             (with Robert, no issues)
  SN-072  damaged          (in rebuild at repair team)
  SN-073  sold             (with Robert — final unit ✓)

Financial:
  ORD-008  $450  paid  -$90 refunded   Net $360
  REP-005  free
  REP-006  free  -$30 refunded         Net -$30
  ───────────────────────────────────────────────
  Total refunded: -$120  |  Net collected: $330

Shipments: 5 legs
  FX-10008  ORD outbound  → Robert
  FX-20007  SN-070 inbound ← Robert (Flow A)
  FX-40005  SN-072 outbound → Robert (Flow B immediate)
  FX-20008  SN-072 inbound ← Robert (10 days later)
  FX-40006  SN-073 outbound → Robert (chained)
```

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

### ORD-011 — Jessica Lee (No Fault, Waived, Goodwill Free Replacement)

```
ORD-2026-011  Jessica Lee  $240  stripe_checkout  Delivered
  [stripe_checkout async: admin generates link → Jessica pays → webhook fires]
  │  Widget Pro SN-100 $200 | Service $20 | Shipping $20

Customer calls: "device not charging at all"
  CMP-2026-010 created: SN-100, Flow B
  Admin sends SN-101 immediately (trust customer)
  [shipment #26: SN-101 replacement_out same day]
  SN-100 expected_return

[7 days later — SN-100 arrives]
  [shipment #23: return_in CMP-010]
  Tech examines: "Charger cable fault — unit fully functional, no defect"
  CMP-2026-010: examination_result = no_fault_found

Admin decision: waive charge (goodwill), keep unit in stock, Jessica keeps SN-101
  CMP-2026-010: unit_outcome = back_to_stock, status = closed
  REP-2026-008: type stays free (charge waived)
  SN-100: in_stock  (back on shelf — good unit, can sell again)
  SN-101: sold  (with Jessica)
  No payment created for the replacement

Financial:
  ORD-011  $240  paid  Net $240
  REP-008  free (waived) — SN-100 recovered and back in stock
  Goodwill cost: one unit sent out = SN-101 cost price only
```

---

## All Edge Cases Covered

| Edge Case | Order |
|-----------|-------|
| Clean order, no issues | ORD-001 |
| Partial payment outstanding | ORD-002 |
| Flow A: bad → free replacement | ORD-003 |
| Flow A: no fault → unit returned, no charge | ORD-004, ORD-005 (SN-041) |
| Flow A: bad → charged replacement upfront | ORD-007 |
| Flow B: bad → scrapped, no charge | ORD-005 (SN-040) |
| Flow B: no fault → charged + kept in stock | ORD-006 |
| Flow B: no fault → waived + kept in stock (goodwill) | ORD-011 |
| Flow B: unit NEVER returned — overdue open case | ORD-010 |
| Chained replacement (rep of rep) | ORD-008 |
| Flow B rep also bad → rebuild | ORD-008 (SN-072) |
| Multi-line, different outcome per line | ORD-005, ORD-008 |
| Partial refund on original order | ORD-003, ORD-008 |
| Partial refund on replacement | ORD-007, ORD-008 |
| Full refund — order cancelled | ORD-009 |
| Cancelled order items returned | ORD-009 |
| Stripe card payment | ORD-001, ORD-006, ORD-009 |
| Stripe terminal payment | ORD-005, ORD-007, ORD-008 |
| Stripe checkout async (webhook) | ORD-011 |
| Cash payment + partial outstanding | ORD-002, ORD-003, ORD-004, ORD-010 |
| Multiple shipment legs per order | ORD-004, ORD-005, ORD-008 |
| Prepaid return label (label_cost > 0 inbound) | CMP-002, CMP-004, CMP-007, etc. |
| Customer's own label (label_cost = 0 inbound) | CMP-001, CMP-006 |

---

## Business Dashboard — May 2026

```
┌───────────────────────────────────────────────────────────┐
│ ORDERS                                                    │
│   Total Orders           11                               │
│   Delivered               9                               │
│   Cancelled               1   (ORD-009)                   │
│   Processing              1   (ORD-002 partial payment)   │
│                                                           │
│ COMPLAINTS & REPLACEMENTS                                 │
│   Total Complaints       10                               │
│   Closed                  9                               │
│   Open (overdue)          1   (CMP-009 SN-090 35d) ⚠️    │
│   Total Replacements      8                               │
│   Chained                 1   (REP-006 from REP-005)      │
│                                                           │
│ MONEY                                                     │
│   Orders Charged      $3,300.00                           │
│   Replacement Charged   $160.00  (REP-003 $80, REP-004 $80│
│   Total Charged       $3,460.00                           │
│   Partial Outstanding   $130.00  ⚠️ (ORD-002 balance)    │
│   Total Refunded        $478.00                           │
│   Net Collected       $2,852.00                           │
│                                                           │
│ SHIPPING                                                  │
│   Total Shipment Legs    32                               │
│   Outbound               20                               │
│   Inbound                12                               │
│   Label Cost Spent      $290.00  (approx)                 │
│   Shipping Charged      $290.00  (orders.shipping_amount) │
│   Pending Inbound         1   (CMP-009 SN-090 overdue)⚠️ │
│                                                           │
│ INVENTORY                                                 │
│   Units Sold             14                               │
│   In Stock (recovered)    4                               │
│   Scrapped                4                               │
│   In Rebuild              1   (SN-072)                    │
│   Overdue Return          1   (SN-090) ⚠️                │
└───────────────────────────────────────────────────────────┘
```
