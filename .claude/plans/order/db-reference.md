# Order System — Database Reference

Full schema, data examples, and worst-case scenarios.
Agreed in brainstorm session before any code was written.

---

## Tables Overview

| Table | Purpose |
|-------|---------|
| `orders` | Original customer sale |
| `order_lines` | Line items on the order (SKU, serial, price) |
| `order_fees` | Extra fees (service, handling) |
| `order_status_history` | Audit trail of every status change |
| `payments` | All payments — cash, cheque, stripe (polymorphic) |
| `replacements` | Replacement units — chained, free or charged |
| `replacement_lines` | Which serial went out, which serial expected back |
| `returns` | Auto-created per replacement line, tracks unit coming back |
| `refunds` | Full or partial refund against order or replacement |

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
│   -- Address snapshot at time of order
├── billing_name              varchar
├── billing_address           varchar
├── billing_city              varchar
├── billing_state             varchar
├── billing_zip               varchar
├── billing_country           varchar
│
├── shipping_name             varchar
├── shipping_address          varchar
├── shipping_city             varchar
├── shipping_state            varchar
├── shipping_zip              varchar
├── shipping_country          varchar
│
│   -- Shipping
├── shipping_method           varchar nullable        — "FedEx", "UPS"
├── shipping_tracking         varchar nullable
├── shipping_amount           decimal(10,2)           — $30.00
│
│   -- Financials
├── subtotal                  decimal(10,2)           — sum of line totals
├── discount_amount           decimal(10,2) default 0 — order-level discount
├── fees_total                decimal(10,2)           — sum of order_fees
├── tax_amount                decimal(10,2) default 0
├── grand_total               decimal(10,2)           — subtotal - discount + fees + tax + shipping
│
│   -- Payment (derived from payments table)
├── payment_status            enum                    — unpaid, partial, paid
│                                                       computed: SUM(payments.amount where paid)
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
├── product_id                FK → products nullable  — links to product catalog
├── sku                       varchar                 — denormalized (snapshot)
├── product_name              varchar                 — denormalized (snapshot)
├── serial_number_id          FK → inventory_serials nullable
├── quantity                  int default 1
├── unit_price                decimal(10,2)
├── discount_amount           decimal(10,2) default 0 — line-level discount
├── line_total                decimal(10,2)           — (qty × unit_price) - discount
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
├── model_type                varchar                 — 'order', 'replacement', 'return', 'refund'
├── model_id                  bigint                  — polymorphic
├── from_status               varchar nullable        — NULL on first entry
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
├── payable_type              varchar                 — 'order' or 'replacement'
├── payable_id                bigint                  — polymorphic
├── method                    enum                    — cash, cheque, stripe_checkout,
│                                                        stripe_card, stripe_terminal
├── amount                    decimal(10,2)
├── status                    enum                    — pending, paid, failed,
│                                                        bounced, refunded
│
│   -- Cash
├── cash_received_at          timestamp nullable
│
│   -- Cheque
├── cheque_number             varchar nullable
├── cheque_bank               varchar nullable
├── cheque_date               date nullable
├── cheque_cleared_at         timestamp nullable
│
│   -- Stripe (all three methods share these)
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

### `replacements`

```
replacements
├── id
├── replacement_number        varchar unique          — REP-2026-001
├── order_id                  FK → orders             — always the root order
├── parent_replacement_id     FK → replacements       — NULL = direct from order
│                             nullable                   set = replacement of replacement
├── reason                    text
├── type                      enum                    — free, charged
├── charge_amount             decimal(10,2) nullable  — only if type = charged
│
│   -- Shipping out
├── shipping_method           varchar nullable
├── shipping_tracking         varchar nullable
│
│   -- Payment (only if charged)
├── payment_status            enum nullable           — unpaid, paid
│
├── status                    enum                    — pending, processing,
│                                                        shipped, delivered
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
├── order_line_id             FK → order_lines        — which original line is being replaced
├── sku                       varchar                 — denormalized
├── product_name              varchar                 — denormalized
├── old_serial_number_id      FK → inventory_serials  — faulty unit going back
├── new_serial_number_id      FK → inventory_serials  — new unit going out
│                             nullable                   assigned when shipped
└── timestamps
```

---

### `returns`

```
returns
├── id
├── replacement_id            FK → replacements
├── replacement_line_id       FK → replacement_lines
├── serial_number_id          FK → inventory_serials  — unit expected back
│                                                        = old_serial on replacement_line
├── status                    enum                    — pending, received
├── received_at               timestamp nullable
├── condition_notes           text nullable           — "scrapped", "good condition"
└── timestamps
```

---

### `refunds`

```
refunds
├── id
├── refund_number             varchar unique          — REF-2026-001
├── order_id                  FK → orders             — always root (for grouping)
├── refundable_type           varchar                 — 'order' or 'replacement'
├── refundable_id             bigint                  — polymorphic
├── amount                    decimal(10,2)
├── reason                    text
├── refund_method             enum                    — cash, stripe, bank_transfer
├── stripe_refund_id          varchar nullable        — if refunded via Stripe
├── status                    enum                    — pending, processed
├── processed_at              timestamp nullable
├── created_by                FK → users
└── timestamps
```

---

## Relationship Map

```
products
    │
    └── order_lines (product_id, sku snapshot, product_name snapshot)

customers
    │
    └── orders (customer_id, address snapshot)
         │
         ├── order_lines ──────────────────→ inventory_serials
         ├── order_fees
         ├── order_status_history (polymorphic)
         │
         ├── payments (payable: order) ────→ Stripe / manual
         │
         ├── refunds (refundable: order)
         │
         └── replacements
              ├── parent_replacement_id ───→ replacements (self-referential chain)
              │
              ├── replacement_lines
              │    ├── old_serial ─────────→ inventory_serials
              │    ├── new_serial ─────────→ inventory_serials
              │    └── returns ────────────→ inventory_serials
              │
              ├── payments (payable: replacement)
              ├── order_status_history (polymorphic)
              └── refunds (refundable: replacement)
```

---

## Financial Logic

### Order payment_status (derived)

```
grand_total = subtotal - discount + fees_total + tax_amount + shipping_amount

paid_total  = SUM(payments.amount WHERE status = 'paid' AND payable_type = 'order')

payment_status:
  paid_total = 0              → unpaid
  paid_total < grand_total    → partial
  paid_total >= grand_total   → paid
```

### Replacement payment_status (derived)

```
paid_total = SUM(payments.amount WHERE status = 'paid' AND payable_type = 'replacement')

payment_status:
  paid_total >= charge_amount → paid
  paid_total = 0              → unpaid
```

### Financial summary for full order chain

```
net_order       = grand_total - SUM(refunds WHERE refundable_type = 'order')
net_replacement = charge_amount - SUM(refunds WHERE refundable_type = 'replacement')

chain_charged   = grand_total + SUM(replacements.charge_amount WHERE type = 'charged')
chain_refunded  = SUM(all refunds in chain)
chain_collected = SUM(all paid payments in chain)
chain_net       = chain_collected - chain_refunded
```

---

## Stripe Payment Flows

### Stripe Checkout (async)
```
1. Admin generates payment link
   → Stripe API: create Checkout Session
   → INSERT payments (status: pending, stripe_session_id stored)
   → Link copied to clipboard, sent to customer manually

2. Customer pays
   → Stripe fires webhook: checkout.session.completed
   → POST /webhooks/stripe
   → Verify Stripe signature
   → Find payment by stripe_session_id
   → UPDATE payments (status: paid, stripe_charge_id, stripe_receipt_url)
   → Recompute order/replacement payment_status
```

### Stripe Card (sync)
```
1. Admin opens card form
   → Stripe API: create Payment Intent
   → Admin enters card details (Stripe Elements)
   → Charged immediately

2. Success
   → INSERT payments (status: paid, stripe_payment_intent_id, stripe_charge_id)
   → Recompute payment_status

3. Failure
   → Show error, nothing inserted
```

### Stripe Terminal (sync)
```
1. Admin clicks "Charge Terminal"
   → Stripe Terminal API: create Payment Intent
   → Physical reader activates

2. Customer taps/swipes/inserts
   → Success: INSERT payments (status: paid, stripe_terminal_reader_id)
   → Failure: show error
```

### Stripe Refund
```
Refund via Stripe (when original payment was stripe_card or stripe_terminal):
   → Stripe API: create Refund against stripe_charge_id
   → INSERT refunds (refund_method: stripe, stripe_refund_id stored)

Refund for Stripe Checkout:
   → Same — refund against stripe_charge_id
```

---

## inventory_serials Status Flow

```
in_stock
  │
  ├── [sold on order]          → sold
  │
  └── [assigned to replacement] → assigned
       │
       ├── [return received, good] → in_stock  (back to stock)
       └── [return received, dmg]  → scrapped

sold / assigned
  └── [replacement created]    → expected_return
       │
       ├── [return received]   → in_stock / scrapped
       └── [never returned]    → expected_return  ⚠️ overdue
```

---

## 10-Order Data Example

### `orders`

```
id  order_number   customer          grand_total  payment_status  status      notes
────────────────────────────────────────────────────────────────────────────────────────────
1   ORD-2026-001   Sarah Johnson     $240.00      paid            delivered   clean
2   ORD-2026-002   Mike Torres       $380.00      paid            delivered   cheque bounced → cash
3   ORD-2026-003   Karen White       $250.00      paid            delivered   3 reps, 0 returned ⚠️
4   ORD-2026-004   David Park        $490.00      paid            delivered   3 items, 1 replaced, scrapped
5   ORD-2026-005   Lisa Chen         $380.00      partial         processing  Stripe link never paid ⚠️
6   ORD-2026-006   James Brown       $170.00      paid            delivered   simple rep, return received
7   ORD-2026-007   Emma Davis        $240.00      paid            delivered   charged rep + refund on rep
8   ORD-2026-008   Robert Wilson     $450.00      paid            delivered   2 charged reps, 1 unpaid ⚠️
9   ORD-2026-009   Amanda Taylor     $255.00      paid            cancelled   full refund, items returned
10  ORD-2026-010   Chris Martinez    $715.00      paid            delivered   absolute worst ⚠️⚠️⚠️
```

---

### `order_lines`

```
id  order_id  sku     product_name   serial           qty  unit_price  line_total
───────────────────────────────────────────────────────────────────────────────────
1   1         PROD-A  Widget Pro     SN-001234         1    200.00      200.00
2   2         PROD-A  Widget Pro     SN-002345         1    200.00      200.00
3   2         PROD-B  Widget Basic   SN-002346         1    150.00      150.00
4   3         PROD-A  Widget Pro     SN-003456         1    200.00      200.00
5   4         PROD-A  Widget Pro     SN-004567         1    200.00      200.00
6   4         PROD-B  Widget Basic   SN-004568         1    150.00      150.00
7   4         PROD-C  Widget Mini    SN-004569         1    80.00        80.00
8   5         PROD-A  Widget Pro     SN-005678         1    200.00      200.00
9   5         PROD-B  Widget Basic   SN-005679         1    150.00      150.00
10  6         PROD-B  Widget Basic   SN-006789         1    150.00      150.00
11  7         PROD-A  Widget Pro     SN-007890         1    200.00      200.00
12  8         PROD-A  Widget Pro     SN-008901         1    200.00      200.00
13  8         PROD-A  Widget Pro     SN-008902         1    200.00      200.00
14  9         PROD-B  Widget Basic   SN-009012         1    150.00      150.00
15  9         PROD-C  Widget Mini    SN-009013         1     80.00       80.00
16  10        PROD-A  Widget Pro     SN-010123         1    200.00      200.00
17  10        PROD-A  Widget Pro     SN-010124         1    200.00      200.00
18  10        PROD-B  Widget Basic   SN-010125         1    150.00      150.00
19  10        PROD-C  Widget Mini    SN-010126         1     80.00       80.00
```

---

### `payments`

```
id  order_id  payable_type  payable_id  method            amount   status    notes
───────────────────────────────────────────────────────────────────────────────────────────
1   1         order         1           stripe_card       240.00   paid
2   2         order         2           cheque            380.00   bounced   cheque #1234 ⚠️
3   2         order         2           cash              380.00   paid      re-paid after bounce
4   3         order         3           cash              250.00   paid
5   4         order         4           stripe_terminal   490.00   paid
6   5         order         5           cash              150.00   paid      partial only
7   5         order         5           stripe_checkout   230.00   pending   never paid ⚠️
8   6         order         6           cash              170.00   paid
9   7         order         7           stripe_card       240.00   paid
10  7         replacement   6           stripe_card       100.00   paid
11  8         order         8           cash              200.00   paid      partial
12  8         order         8           stripe_checkout   250.00   paid
13  8         replacement   7           cash               80.00   paid
14  8         replacement   8           stripe_checkout    80.00   unpaid    ⚠️ never paid
15  9         order         9           stripe_card       255.00   paid
16  10        order         10          cheque            715.00   bounced   cheque #9999 ⚠️
17  10        order         10          stripe_terminal   400.00   paid
18  10        order         10          cash              315.00   paid
19  10        replacement   10          stripe_checkout   100.00   paid
20  10        replacement   12          stripe_checkout    80.00   unpaid    ⚠️ never paid
```

---

### `replacements`

```
id  rep_number      order_id  parent_id  type     charge   pay_status  status     reason
────────────────────────────────────────────────────────────────────────────────────────────────
1   REP-2026-001    3         NULL       free     NULL     NULL        delivered  Dead on arrival
2   REP-2026-002    3         1          free     NULL     NULL        delivered  Still not working
3   REP-2026-003    3         2          charged  80.00    unpaid      shipped    3rd failure ⚠️
4   REP-2026-004    4         NULL       free     NULL     NULL        delivered  Widget Pro faulty
5   REP-2026-005    6         NULL       free     NULL     NULL        delivered  Basic unit failed
6   REP-2026-006    7         NULL       charged  100.00   paid        shipped    Motor failure
7   REP-2026-007    8         NULL       charged  80.00    paid        delivered  First unit failed
8   REP-2026-008    8         7          charged  80.00    unpaid      shipped    Rep also failed ⚠️
9   REP-2026-009    10        NULL       free     NULL     NULL        delivered  Widget Pro failed
10  REP-2026-010    10        9          charged  100.00   paid        shipped    SN-010127 failed
11  REP-2026-011    10        NULL       free     NULL     NULL        delivered  Widget Basic failed
12  REP-2026-012    10        11         charged  80.00    unpaid      processing SN-010129 failed ⚠️
```

---

### `replacement_lines`

```
id  rep_id  order_line_id  sku     old_serial    new_serial
────────────────────────────────────────────────────────────
1   1       4              PROD-A  SN-003456     SN-003457
2   2       4              PROD-A  SN-003457     SN-003458
3   3       4              PROD-A  SN-003458     SN-003459
4   4       5              PROD-A  SN-004567     SN-004570
5   5       10             PROD-B  SN-006789     SN-006790
6   6       11             PROD-A  SN-007890     SN-007891
7   7       12             PROD-A  SN-008901     SN-008903
8   8       12             PROD-A  SN-008903     SN-008904
9   9       16             PROD-A  SN-010123     SN-010127
10  10      16             PROD-A  SN-010127     SN-010128
11  11      18             PROD-B  SN-010125     SN-010129
12  12      18             PROD-B  SN-010129     SN-010130
```

---

### `returns`

```
id  rep_id  rep_line_id  serial       status    received_at           condition
────────────────────────────────────────────────────────────────────────────────────────────
1   1       1            SN-003456    pending   NULL                  ⚠️ 45 days overdue
2   2       2            SN-003457    pending   NULL                  ⚠️ 30 days overdue
3   3       3            SN-003458    pending   NULL                  ⚠️ 15 days overdue
4   4       4            SN-004567    received  2026-05-08 10:00:00   scrapped — damaged
5   5       5            SN-006789    received  2026-05-06 14:30:00   good condition
6   6       6            SN-007890    pending   NULL                  ⏳ 10 days
7   7       7            SN-008901    received  2026-05-04 09:00:00   good condition
8   8       8            SN-008903    pending   NULL                  ⏳ 5 days
9   9       9            SN-010123    received  2026-05-03 11:00:00   scrapped — burnt motor
10  10      10           SN-010127    pending   NULL                  ⚠️ 30 days overdue
11  11      11           SN-010125    received  2026-05-10 15:00:00   good condition
12  12      12           SN-010129    pending   NULL                  ⏳ 3 days
```

---

### `refunds`

```
id  refund_number  order_id  refundable_type  refundable_id  amount   method  reason                   status
────────────────────────────────────────────────────────────────────────────────────────────────────────────────
1   REF-2026-001   3         order            3               50.00   cash    Goodwill 3 failures       processed
2   REF-2026-002   6         order            6               34.00   cash    20% inconvenience         processed
3   REF-2026-003   7         replacement      6               30.00   stripe  Partial motor issue       processed
4   REF-2026-004   8         order            8               90.00   cash    20% goodwill              processed
5   REF-2026-005   8         replacement      7               24.00   cash    30% on REP-001            processed
6   REF-2026-006   9         order            9              255.00   stripe  Full refund - cancelled   processed
7   REF-2026-007   10        order            10             143.00   cash    20% all issues            processed
8   REF-2026-008   10        replacement      10              30.00   stripe  Partial REP-002           processed
```

---

### `inventory_serials` — Final Status

```
serial      sku     status            location
────────────────────────────────────────────────────────────────────
SN-001234   PROD-A  sold              with Sarah Johnson (ORD-001)
SN-002345   PROD-A  sold              with Mike Torres (ORD-002)
SN-002346   PROD-B  sold              with Mike Torres (ORD-002)
SN-003456   PROD-A  expected_return   Karen — REP-001 ⚠️ 45d overdue
SN-003457   PROD-A  expected_return   Karen — REP-002 ⚠️ 30d overdue
SN-003458   PROD-A  expected_return   Karen — REP-003 ⚠️ 15d overdue
SN-003459   PROD-A  assigned          Karen — REP-003 active
SN-004567   PROD-A  scrapped          returned David, damaged
SN-004568   PROD-B  sold              with David Park (ORD-004)
SN-004569   PROD-C  sold              with David Park (ORD-004)
SN-004570   PROD-A  assigned          David — REP-004 active
SN-005678   PROD-A  sold              with Lisa Chen (ORD-005, partial paid)
SN-005679   PROD-B  sold              with Lisa Chen (ORD-005, partial paid)
SN-006789   PROD-B  in_stock          returned James, good condition
SN-006790   PROD-B  assigned          James — REP-005 active
SN-007890   PROD-A  expected_return   Emma — REP-006 ⏳ 10 days
SN-007891   PROD-A  assigned          Emma — REP-006 active
SN-008901   PROD-A  in_stock          returned Robert, good condition
SN-008902   PROD-A  sold              with Robert Wilson (ORD-008)
SN-008903   PROD-A  expected_return   Robert — REP-008 ⏳ 5 days
SN-008904   PROD-A  assigned          Robert — REP-008 active
SN-009012   PROD-B  in_stock          returned Amanda, cancelled order
SN-009013   PROD-C  in_stock          returned Amanda, cancelled order
SN-010123   PROD-A  scrapped          returned Chris, burnt motor
SN-010124   PROD-A  sold              with Chris Martinez (ORD-010)
SN-010125   PROD-B  in_stock          returned Chris, good condition
SN-010126   PROD-C  sold              with Chris Martinez (ORD-010)
SN-010127   PROD-A  expected_return   Chris — REP-010 ⚠️ 30d overdue
SN-010128   PROD-A  assigned          Chris — REP-010 active
SN-010129   PROD-B  expected_return   Chris — REP-012 ⏳ 3 days
SN-010130   PROD-B  assigned          Chris — REP-012 active
```

---

## Worst-Case Activity Chains

---

### ORD-003 — Karen White (3 replacements, nothing returned)

```
ORD-2026-003  Karen White  $250  Paid (Cash) ✓  Delivered
│  Widget Pro  SN-003456  $200 | Service Fee $20 | Shipping $30
│  💰 Refund $50 goodwill  Processed (cash)
│
└── REP-001  Free  Delivered
     Out:  SN-003456 → SN-003457
     Back: SN-003456  ⚠️ NEVER RETURNED  45 days overdue
     │
     └── REP-002  Free  Delivered
          Out:  SN-003457 → SN-003458
          Back: SN-003457  ⚠️ NEVER RETURNED  30 days overdue
          │
          └── REP-003  Charged $80  Shipped
               Out:  SN-003458 → SN-003459
               Back: SN-003458  ⚠️ NEVER RETURNED  15 days overdue
               💳 Stripe Checkout $80  UNPAID ⚠️

Units sent: 4  |  Units back: 0  |  Money owed: $80
```

---

### ORD-008 — Robert Wilson (stacking refunds, unpaid replacement)

```
ORD-2026-008  Robert Wilson  $450  Paid ✓  Delivered
│  Widget Pro SN-008901 $200 | Widget Pro SN-008902 $200
│  Service Fee $20 | Shipping $30
│  💳 Cash $200 + Stripe Checkout $250  Paid ✓
│  💰 Refund $90 (20% goodwill)  Processed (cash)
│
└── REP-001  Charged $80  Delivered
     Out:  SN-008901 → SN-008903
     Back: SN-008901  ✓ Received  good condition
     💳 Cash $80  Paid ✓
     💰 Refund $24 (30%)  Processed (cash)
     │
     └── REP-002  Charged $80  Shipped
          Out:  SN-008903 → SN-008904
          Back: SN-008903  ⏳ Pending  5 days
          💳 Stripe Checkout $80  UNPAID ⚠️

Financial:
  ORD-008   $450 charged  $450 paid  -$90 refunded   Net $360
  REP-001    $80 charged   $80 paid  -$24 refunded   Net  $56
  REP-002    $80 charged    $0 paid    $0 refunded   Net -$80 OUTSTANDING ⚠️
  ─────────────────────────────────────────────────────────────
  Total charged   $610  |  Total refunded -$114
  Net collected   $530  |  Outstanding      $80
```

---

### ORD-010 — Chris Martinez (absolute worst)

```
ORD-2026-010  Chris Martinez  $715  Paid ✓  Delivered
│  Widget Pro SN-010123 $200 | Widget Pro SN-010124 $200
│  Widget Basic SN-010125 $150 | Widget Mini SN-010126 $80
│  Service Fee $30 | Handling $15 | Shipping $40
│
│  💳 Cheque #9999 $715  BOUNCED ⚠️
│  💳 Stripe Terminal $400  Paid ✓
│  💳 Cash $315  Paid ✓  (covered after bounce)
│  💰 Refund $143 (20% goodwill)  Processed (cash)
│
├── REP-001  Free  Delivered             [Widget Pro line]
│    Out:  SN-010123 → SN-010127
│    Back: SN-010123  ✓ Received  scrapped (burnt motor)
│    │
│    └── REP-002  Charged $100  Shipped
│         Out:  SN-010127 → SN-010128
│         Back: SN-010127  ⚠️ OVERDUE  30 days
│         💳 Stripe Checkout $100  Paid ✓
│         💰 Refund $30 partial  Processed (stripe)
│
└── REP-003  Free  Delivered             [Widget Basic line — different item]
     Out:  SN-010125 → SN-010129
     Back: SN-010125  ✓ Received  good condition
     │
     └── REP-004  Charged $80  Processing
          Out:  SN-010129 → SN-010130
          Back: SN-010129  ⏳ Pending  3 days
          💳 Stripe Checkout $80  UNPAID ⚠️

Financial:
  ORD-010  $715 charged  $715 paid  -$143 refunded  Net $572
  REP-001  $0 (free)
  REP-002  $100 charged  $100 paid  -$30 refunded   Net  $70
  REP-003  $0 (free)
  REP-004   $80 charged    $0 paid    $0 refunded   Net -$80 OUTSTANDING ⚠️
  ─────────────────────────────────────────────────────────────────────
  Total charged   $895  |  Total refunded -$173
  Net collected   $815  |  Outstanding      $80

Units sent: 6  |  Back: 3 (1 scrapped, 1 in stock, 1 pending 3d)
Overdue: 1 (SN-010127 — 30 days)  |  Chris has 4 active units
```

---

## Overall Business Dashboard Numbers

```
┌─────────────────────────────────────────────────────┐
│ BUSINESS SUMMARY — May 2026 (10 orders)             │
│                                                     │
│ Total Orders           10                           │
│ Total Replacements     12                           │
│ Total Returns          12  (7 received, 5 pending)  │
│ Total Refunds           8                           │
│                                                     │
│ MONEY                                               │
│ Total Charged      $4,534.00                        │
│ Total Collected    $4,074.00                        │
│ Total Refunded      -$626.00                        │
│ Outstanding          $240.00  ⚠️ (3 unpaid reps)   │
│ Net Collected      $3,448.00                        │
│                                                     │
│ UNITS                                               │
│ Units Sold             19                           │
│ Replacement Units Out  12                           │
│ Returns Received        7                           │
│ Returns Pending         5                           │
│ Overdue Returns         3  (Karen ×3, Chris ×1)    │
│ Scrapped                2  (SN-004567, SN-010123)  │
└─────────────────────────────────────────────────────┘
```

---

## Missing Features — To Brainstorm Next

These were identified as gaps during the brainstorm session.
Each needs a decision before schema is finalised:

1. **Address snapshot** — ✅ added to schema above
2. **Products table** — needs brainstorm (catalog vs free-text)
3. **Tax** — ✅ added to schema above
4. **Order-level discount** — ✅ added to schema above
5. **Refund method** — ✅ added to schema above
6. **Status history / audit trail** — ✅ added to schema above
7. **Order source** — ✅ added to schema above
8. **Invoice / receipt** — needs brainstorm
