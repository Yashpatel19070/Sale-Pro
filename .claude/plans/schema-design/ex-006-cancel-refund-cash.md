# ex-006 — Cancel + Refund (cash, full) — standalone

> See [global.md](./global.md) — shared enums, column conventions, money + logging rules. Read first.

> _Example only — goal is to understand the data flow. IDs, numbers, timestamps illustrative._
> **Standalone** — own order (ORD-2026-0022, Dana Cruz). Does **not** extend ex-001; nothing reused, no clash with other order-20 examples.

**Scenario:** Dana Cruz phones the Houston shop. Admin force-processes (`origin=admin`) — two serials `SN-220` (ECM-2024) + `SN-320` (TCM-2024) **reserved**, staged `ready_for_pickup`. Dana **prepays $525.01 cash** to hold the units. **Day +3 she calls: "cancel my order, refund my money — reason: changed my mind."** She **never picked up** — goods **never left the building**. Full cash refund, units released back to stock.

This is the **money twin of an unpaid cancel** (ex-001 cancel fork = *unpaid*, no money). Here the order was **paid**, so the goods-never-left cancel also **moves money back**.

---

### Cancel vs return (the key idea)
| | goods | fees | refund |
|---|---|---|---|
| **Cancel** (this, ex-006) | **never left** → no `returns` row | **refunded** (service NOT rendered) | **full** `grand_total` |
| **Return** (ex-005) | came back → `returns` + `return_lines` | **kept** (units used/programmed) | product line only |

- **Cancel ≠ return.** Nothing handed over, nothing programmed → **everything refunded, including the 3 fees**.
- No goods coming back → **`refunds.return_id = NULL`**, **no `returns`/`return_lines`** rows.

---

### Pipeline (cancel paid order → refund)
```
cx calls: cancel + refund (reason xyz)
  → GATE: order paid + goods NOT left (serials still `reserved`)        ◄ Gate
       (if goods already left → it's a RETURN, not a cancel — ex-005)
  → refund FULL grand_total (units + tax + all fees)
  → AvaTax adjustTransaction — full reversal of committed invoice
  → release serials: reserved → in_stock (no movement row — never moved)
  → orders.status → cancelled
  → order closed
```

---

### Tables & enums (subset — full set in global.md; PHP enums)

| Table.column | Values used here |
|---|---|
| `orders.status` | `cancelled` |
| `orders.payment_status` | `paid` (money WAS paid; cancel carried by `status`) |
| `orders.source` | `phone` |
| `orders.origin` | `admin` |
| `refunds.status` | `pending` · `refunded` |
| `refunds.reason` | `cancel` (· `return` · `adjustment` · `other`) |
| `payments.kind` | `payment` · `refund` |
| `payments.status` | `paid` · `refunded` |
| `payments.payable_type` | `order` · `refund` |
| `order_events.event` | `order_placed` · `processing` · `payment_received` · `ready_for_pickup` · `refunded` · `order_cancelled` |
| `inventory_serials.status` | `in_stock` · `reserved` |
| `inventory_movements.type` | `receive` |

> No `returns` / `return_lines` (goods never came back) · no `shipments` (in-store) · no `replacements` (no fault).

---

### Data Flow

```
[2026-05-26 — phone order, force-processed, PREPAID cash to hold units]
        │
        ├──→ orders INSERT (customer_id=21, source=phone, origin=admin, status=pending, payment_status=unpaid,
        │                   billing snapshot = Dana Cruz's address, shipping snapshot = NULL — in-store pickup)
        ├──→ order_lines INSERT (2 lines: ECM-2024 + TCM-2024)
        ├──→ order_line_fees INSERT (Programming $40 + Gas Tuning $25 on ECM · Programming $40 on TCM)
        ├──→ order_events INSERT (order_placed)
        │     └──→ AvaTax QUOTE (uncommitted) on 5 taxable lines (2 units + 3 fees)
        ├──→ order_lines UPDATE (line 56 ← SN-220, line 57 ← SN-320)
        ├──→ inventory_serials UPDATE (SN-220, SN-320: in_stock → reserved, locked to ORD-2026-0022)
        ├──→ orders.status → processing
        ├──→ order_events INSERT (processing, {"forced":true,"origin":"admin"})
        ├──→ payments INSERT (kind=payment, payable_type=order, cash, 525.01, paid, received_at=10:00)   ◄ PREPAY (upfront, not at pickup)
        ├──→ orders.payment_status → paid
        ├──→ AvaTax COMMIT — SalesInvoice, code = ORD-2026-0022 (paid → committed)
        └──→ order_events INSERT (payment_received · ready_for_pickup)
             (units staged, waiting for Dana — NOT picked up, NOT sold, NOT completed)

[Day +3, 2026-05-29 11:00 — cx calls: cancel + refund, reason=changed mind]
        │
        ├──→ GATE: order paid + serials still `reserved` (goods never left) → cancel-refund allowed
        ├──→ refunds INSERT (REF-2026-0002: order_id=22, return_id=NULL, reason=cancel,
        │                    total_amount=525.01, total_tax=40.01, method=cash, status=pending)
        ├──→ refund_lines INSERT (full — every order_line, unit + its fees)
        ├──→ payments INSERT (kind=refund, payable_type=refund, payable_id=2, cash, 525.01, status=refunded, received_at=11:00)
        ├──→ AvaTax ADJUST — full reversal of committed invoice ORD-2026-0022 (−$40.01 all tax)
        ├──→ refunds UPDATE (status=refunded)
        ├──→ inventory_serials UPDATE (SN-220, SN-320: reserved → in_stock, location stays Warehouse A)   ◄ release, NO movement row
        ├──→ orders.status → cancelled, closed_at=11:00   (payment_status stays paid)
        └──→ order_events INSERT (refunded · order_cancelled {reason:"changed_mind"})   [+ activity_log]
             (NO returns/return_lines — goods never left · NO shipments · NO sale movement)
```

---

### Schema + Data

**`customers`**
```
id   name        email             phone         status  tax_exempt
21   Dana Cruz   dana@example.com  555-210-0021  active  false
```

**`customer_addresses`**
```
id  customer_id  type     line1              city     state  postal  is_default
32  21           billing  77 Bissonnet St    Houston  TX     77005   true
```
> In-store pickup → no shipping address row. Houston → 8.25% rate.

**`orders`** (key fields — cancelled, but was paid)
```
id  number         customer_id  source  origin  status     payment_status  shipping  grand_total  created_by
22  ORD-2026-0022  21           phone   admin   cancelled  paid            0.00      525.01       1

-- timestamps (never completed — no pickup)
created_at           completed_at  completed_by  closed_at
2026-05-26 09:00:00  NULL          NULL          2026-05-29 11:00:00

-- billing snapshot (customer's billing address)
billing_first_name  billing_last_name  billing_email     billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
Dana                Cruz               dana@example.com  555-210-0021   77 Bissonnet St        Houston       TX             77005                US

-- shipping snapshot (NULL — in-store pickup)
shipping_first_name … all NULL
```
> Never `completed` (cx never picked up). `payment_status=paid` — money WAS paid; `status=cancelled` + refund row carry the reversal. `closed_at` = cancel time.

**`order_lines`**
```
id  order_id  product_listing_id  sku       product_name                 inventory_serial_id  unit_price  tax_amount  line_total
56  22        14                  ECM-2024  Engine Control Module        SN-220               200.00      16.50       216.50
57  22        15                  TCM-2024  Transmission Control Module  SN-320               180.00      14.85       194.85
```
> `line_total = unit_price + tax_amount`. Each line allocated one serial (then released on cancel).

**`order_line_fees`**
```
id  order_line_id  name             amount  tax_amount  fee_total  created_by  created_at
12  56             Programming Fee  40.00   3.30        43.30      1           2026-05-26 09:00:00
13  56             Gas Tuning Fee   25.00   2.06        27.06      1           2026-05-26 09:00:00
14  57             Programming Fee  40.00   3.30        43.30      1           2026-05-26 09:00:00
```
> `fee_total = amount + tax_amount`. ECM (line 56) = 2 fees · TCM (line 57) = 1 fee.

**Grand total math**
```
Line items:  ECM 216.50 + TCM 194.85                  = $411.35
Per-line fees: 43.30 + 27.06 + 43.30                  = $113.66
Shipping:                                             +   $0.00
──────────────────────────────────────────────────────────────
grand_total:                                            $525.01
```

**`payments`** (prepay + the refund)
```
id  order_id  payable_type  payable_id  kind     method  amount   status     received_at           received_by  created_by
40  22        order         22          payment  cash    525.01   paid       2026-05-26 10:00:00   1            1
41  22        refund        2           refund   cash    525.01   refunded   2026-05-29 11:00:00   1            1
```
> Original prepay (id 40) → refund row (id 41) `payable_type=refund`, `payable_id=2` (the `refunds` row), `kind=refund`. Net cash = $0.

**`refunds`** (money back — full; no goods, so standalone)
```
id  number         order_id  return_id  reason  total_amount  total_tax  method  status    created_by  created_at
2   REF-2026-0002  22        NULL       cancel  525.01        40.01      cash    refunded  1           2026-05-29 11:00:00
```
> `return_id=NULL` — **cancel-refund**, no goods came back (units never left). `reason=cancel`. Full `grand_total` reversed. `total_tax` = tax part (for AvaTax adjust).

**`refund_lines`** (full — per order_line)
```
id  refund_id  order_line_id  amount   tax
1   2          56             265.00   21.86
2   2          57             220.00   18.15
```
> `amount` per line = **unit + its fees** (whole order cancelled, nothing rendered → fees refunded): line 56 = ECM 200 + Programming 40 + Gas Tuning 25; line 57 = TCM 180 + Programming 40. `tax` = matching tax. Per the global `refund_lines.amount` rule. Σ(amount+tax) = 286.86 + 238.15 = **$525.01** = full. (Contrast ex-005 return: `amount` = unit only, fees kept.)

**`order_events`** (order 22 — prepay lifecycle then cancel-refund tail)
```
id  order_id  event             metadata                                                       created_by  created_at
1   22        order_placed      {"lines":2,"grand_total":"525.01"}                             1           2026-05-26 09:00:00
2   22        processing        {"forced":true,"origin":"admin","serials":["SN-220","SN-320"]} 1           2026-05-26 09:05:00
3   22        payment_received  {"method":"cash","amount":"525.01","prepay":true}              1           2026-05-26 10:00:00
4   22        ready_for_pickup  {"units":2}                                                    1           2026-05-26 12:00:00
5   22        refunded          {"refund":"REF-2026-0002","amount":"525.01","method":"cash"}   1           2026-05-29 11:00:00
6   22        order_cancelled   {"reason":"changed_mind","serials_released":["SN-220","SN-320"]} 1         2026-05-29 11:00:00
```
> No `completed` event — units never handed over. `refunded` then `order_cancelled` close the order. `activity_log` (dev) mirrors the CRUD.

**`inventory_serials`** (released — back to stock, never sold)
```
serial  status    location      note
SN-220  in_stock  Warehouse A   ORD-2026-0022 cancelled — released, never picked up
SN-320  in_stock  Warehouse A   ORD-2026-0022 cancelled — released, never picked up
```

**`inventory_movements`** (only the original receives — nothing else fired)
```
id  serial   type     from   to           reference     notes
60  SN-220   receive  NULL   Warehouse A  PO-2026-020   initial stock receipt
61  SN-320   receive  NULL   Warehouse A  PO-2026-021   initial stock receipt
```
> `reserved → in_stock` on cancel = **status only, NO movement row** (units never physically left Warehouse A). No `sale`, no `return_in`.

**No `returns` / `return_lines`** — goods never left, nothing to bring back.
**No `shipments`** — in-store. **No `replacements`** — no fault.

---

### Financial Summary
```
prepaid (cash):     $525.01   (payment 40)
refund (cash):     -$525.01   (payment 41 — full: units + tax + all 3 fees)
─────────────────────────────
net collected:      $0.00  ✓   (full cancel — nothing rendered, nothing kept)
```
> Full reversal. Fees refunded too (unlike ex-005 return) — units never handed over, no programming/service done.

---

### AvaTax
> Invoice was **committed at prepay** (paid 2026-05-26). Cancel → `adjustTransaction` **full reversal** of `ORD-2026-0022` — all 5 tax lines reversed (−$40.01: 2 units + 3 fees). (Contrast an *unpaid* cancel = quote never committed → nothing to void. Here paid → committed → must adjust.)

---

### Invariants (guardrails)
- cancel-refund allowed only when **paid + goods NOT left** (serials still `reserved`); if goods left → it's a **return** (ex-005), not a cancel
- **full refund** = `grand_total` (units + tax + **all fees**) — nothing rendered, so nothing kept
- `refunds.return_id = NULL` — cancel-refund has **no goods back**, **no `returns`/`return_lines`** rows
- `refunds.reason = cancel`; free-text "why" → `order_notes` (private)
- serials `reserved → in_stock` = **status only, no `inventory_movements` row** (never physically moved)
- `orders.payment_status` stays **`paid`**; `status=cancelled` + `payments` refund row carry the reversal (net money = Σ `kind=payment` − Σ `kind=refund`)
- order never `completed` (no handover) — terminal state = `cancelled`
- net cash = $0 (prepay − full refund)

---

### Key Design Notes
| Rule | Value |
|------|-------|
| Trigger | paid cash order, cx cancels **before pickup** (goods never left) → full refund |
| Cancel vs return | **cancel = goods never left** (no `returns`, full refund incl. fees) · **return = goods came back** (ex-005, fees kept) |
| Entity (money) | `refunds` (header, `reason=cancel`, `return_id=NULL`) + `refund_lines` (full) |
| Refund amount | **full `grand_total`** — units + tax + all fees (nothing rendered) |
| `refund_lines.amount` | unit **+ its fees** here (whole line cancelled); per global rule (ex-005 = unit only) |
| Goods | serials `reserved → in_stock`, **no movement row** (never moved); no `returns` |
| Payment | refund `payments` row `kind=refund`, `payable_type=refund`, cash; original stays `paid` |
| AvaTax | invoice committed at prepay → `adjustTransaction` **full reversal** |
| Status | `orders.status=cancelled`, `payment_status=paid`, never `completed` |
| Events | `refunded` → `order_cancelled` on order timeline (`order_events`) |
| Logs | `order_events` (admin/user) + `activity_log` (dev) + `order_notes` (free-text reason) |
| Contrast | unpaid cancel = no money · ex-005 = return+refund (goods back, fees kept) · ex-006 = paid cancel (no goods, full refund) |
