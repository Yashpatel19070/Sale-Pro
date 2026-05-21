## Example 8 — ORD-010 — Flow B: Unit Never Returned, Open Case

**Scenario:** Chris Martinez walks in. Pays cash. Admin ships SN-090 to his home via FedEx. Item delivered May 1 — Chris calls same day, reports device malfunctioning after delivery. Admin sends replacement (SN-091) — Flow B, trust customer. Chris ships SN-090 back May 5 (his own label) but package never arrived — tracking shows in transit, delivered_at NULL. 35 days later: open case, no examination, no closure.

**What's new vs all previous examples:**
- Cash + walk_in + delivery (billing NULL, shipping filled)
- Complaint filed after delivery — complaint still open, status=`in_progress`, no `closed_at`
- Shipment inbound has `delivered_at=NULL` ⚠️ — package never arrived after 35 days
- No `examination_result`, no `unit_outcome` — nothing examined, unit never received
- Order status stays `shipped` — no `closed_at` on order (complaint unresolved)
- Admin has 3 options still pending at day 35

---

### Data Flow

```
[Chris walks in — buys at counter]
        │
        ├──→ orders (customer_id=8, billing NULL (cash), shipping snapshot filled)
        ├──→ order_lines (1 line: SN-090)
        └──→ order_fees (service fee $20)

[Chris pays cash at counter]
        │
        └──→ payments INSERT #13 (cash, status=paid, cash_received_at=2026-04-29 09:00)
             orders.payment_status → paid
             orders.status → processing

[Ali ships SN-090 via FedEx]
        │
        └──→ shipments INSERT #10 (order/10/outbound, FX-10010)
             inventory_movements INSERT #15 (SN-090 sale, Warehouse A → NULL)
             SN-090 → sold
             orders.status → shipped, shipped_at=2026-04-29 09:00, shipped_by=Ali (user_id=2)

[Delivered 2026-05-01 — carrier confirms, admin records manually]
        │
        └──→ orders.delivered_at=2026-05-01 14:00, delivered_by=1
             (orders.status stays shipped — no status change)

[Chris calls May 1 — reports device malfunctioning after delivery]
        │
        └──→ Admin decides: send replacement immediately (Flow B — trust customer)
             CMP-2026-009 created (order_line=15, serial=SN-090, status=open, created_by=4)
             REP-2026-007 created (free, complaint_id=9)
             shipments INSERT #30 (replacement/7/outbound, FX-40007)
             inventory_movements INSERT #25 (SN-091 replacement_out, Warehouse A → NULL)
             SN-091 → assigned (in transit to Chris)
             SN-090 → expected_return
             CMP-2026-009.status → in_progress

[SN-091 delivered to Chris 2026-05-03]
        │
        └──→ REP-2026-007.status → delivered
             SN-091 → sold (with Chris)

[Chris ships SN-090 back 2026-05-05 — his own label]
        │
        └──→ shipments #22: inbound (complaint/9, UP-20009, label_cost=0)
             tracking created — package dropped off by Chris

[35 days pass — SN-090 never arrives]
        │
        └──→ shipments #22: delivered_at = NULL ⚠️
             SN-090: status=expected_return, location=NULL — 35 days with no update ⚠️
             CMP-2026-009: still in_progress — no examination possible, no closure

[Admin options at day 35 — unresolved]
        ├── Option A: Write off → SN-090.status = missing, complaint closed (absorb loss)
        ├── Option B: Charge Chris → REP-007 type=charged, INSERT payment ($200 unit cost)
        └── Option C: Escalate → contact Chris, 7-day final notice before charging
```

---

### Schema + Data

**`customers`**
```
id  name            email              phone         status
8   Chris Martinez  chris@example.com  555-100-0008  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email              phone         address_line1   city       state  postal_code  country  is_default
8   8            Home   Chris       Martinez   chris@example.com  555-100-0008  123 Cedar Blvd  Las Vegas  NV     89101        US       true
```

**`orders`**
```
id  number        customer_id  source   status     payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
10  ORD-2026-010  8            walk_in  shipped    paid            200.00    20.00  20.00     240.00       2026-04-29 09:00      2           2026-05-01 14:00      1

-- billing snapshot (NULL — cash payment, no billing address)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (walk_in + delivery → staff enters home address at counter)
shipping_first_name  shipping_last_name  shipping_email     shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
Chris                Martinez            chris@example.com  555-100-0008    123 Cedar Blvd          Las Vegas      NV              89101                 US
```

Complaint still open — order stays `shipped`. Orders have no `closed` status.

**`order_lines`**
```
id  order  sku     product_name  serial  unit_price  tax_rate  tax_amount  line_total
15  10     PROD-A  Widget Pro    SN-090  200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
10  10     Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $20 + tax $0 = $240 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method  amount  status  cash_received_at
13  10        order         10          cash    240.00  paid    2026-04-29 09:00
```

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking   label_cost  status     shipped_at            returned_at           delivered_at
10  order           10            outbound   FedEx    FX-10010    8.50       delivered   2026-04-29 09:00  NULL                  2026-05-01 14:00
22  complaint       9             inbound    UPS      UP-20009    0.00       in_transit  2026-05-05 00:00  NULL                  NULL            ⚠️
30  replacement     7             outbound   FedEx    FX-40007    8.50       delivered   2026-05-01 16:00  NULL                  2026-05-03 12:00
```

- id=10: original order delivery
- id=22: SN-090 return — `delivered_at=NULL` — package never arrived after 35 days ⚠️. label_cost=0 — Chris used his own label.
- id=30: REP-2026-007 — SN-091 shipped to Chris immediately (Flow B)

**`complaints`**
```
id  number        order  line  serial  status           examination_result  unit_outcome  issue_description              unit_received_at  examined_by  examination_notes  closed_at  closed_by  created_by  withdrawn_at          withdrawn_by
9   CMP-2026-009  10     15    SN-090  in_progress      NULL                NULL          Device malfunctioning, urgent  NULL              NULL         NULL               NULL       NULL       4  NULL                  NULL
```

All NULL after `status` — unit never arrived, no examination possible. Case still open.
`in_progress` = replacement shipped, waiting for old unit — stuck here 35 days.

**`replacements`**
```
id  number         order  parent  complaint  type  charge  pay_status  status
7   REP-2026-007   10     NULL    9          free  NULL    NULL        delivered
```

Started free (Flow B trust). May change to `charged` if admin picks Option B at day 35.

**`replacement_lines`**
```
id  rep  order_line  sku     product_name  old_serial  new_serial
7   7    15          PROD-A  Widget Pro    SN-090      SN-091
```

**`inventory_serials`**
```
serial  status           location  note
SN-090  expected_return  NULL      CMP-2026-009 — 35 days overdue, never arrived ⚠️⚠️
SN-091  sold             NULL      with Chris Martinez — REP-2026-007
```

`expected_return` — status set when REP-007 shipped. Never updated because unit never arrived.

**`inventory_movements`**
```
id   serial  type             from          to    reference      notes
15   SN-090  sale             Warehouse A   NULL  ORD-2026-010
25   SN-091  replacement_out  Warehouse A   NULL  REP-2026-007   Flow B — immediate replacement
```

No `return_in` for SN-090 — package never arrived. Ledger stops here for this chain.

---

### Financial Summary
```
charged:   $240.00   (1 payment row — REP-007 free, no separate charge)
collected: $240.00
refunded:  $0.00
net:       $240.00

⚠️ Unresolved: SN-090 ($200 unit) unaccounted — financial risk pending admin decision
```

### Shipping Margin
```
revenue:  $20.00  (orders.shipping_amount)
cost:     $17.00  (id=10 $8.50 + id=22 $0 Chris's label + id=30 $8.50)
margin:   +$3.00
```

---
