> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## Example 4 — ORD-005 — Multi-Line, Concurrent Complaints

**Scenario:** David Park walks in. Pays with Stripe Terminal (card-present). Buys 3 items. Asks for home delivery. Two items fail shortly after — both complaints run in parallel:
- **CMP-003** (SN-040, Widget Pro) — device dead. Admin sends replacement immediately (Flow B). Old unit arrives 7 days later, examined, internal fault, scrapped. Free replacement kept.
- **CMP-004** (SN-041, Widget Basic) — overheating. Customer ships unit back first (Flow A). Examined, no fault found, returned to David. No charge.
- **SN-042** (Widget Mini) — no issues throughout.

---

### Data Flow

```
[David walks in — admin creates order at POS]
        │
        ├──→ orders (customer_id=4, billing NULL (terminal, card-present), shipping snapshot filled)
        ├──→ order_lines (3 line items: SN-040, SN-041, SN-042)
        └──→ order_line_fees (service fee $50 on line 6)

[David taps card on Stripe Terminal — instant]
        │
        └──→ payments INSERT (status=paid, stripe_terminal_reader_id)
             orders.payment_status → paid
             orders.status → processing

[Ali (Warehouse) packs and ships all 3 items together]
        │
        └──→ shipments INSERT #5 (order/5/outbound, FX-10005)
             inventory_movements INSERT #6 (SN-040 sale)
             inventory_movements INSERT #7 (SN-041 sale)
             inventory_movements INSERT #8 (SN-042 sale)
             inventory_serials: SN-040, SN-041, SN-042 → sold
             orders.status → shipped, shipped_at set, shipped_by = Ali (user_id=2)

[Delivered 2026-04-26 — carrier confirms, admin records manually]
        │
        └──→ orders.delivered_at set
             (orders.status stays shipped — no status change)

[David calls — SN-040 dead]
        │
        └──→ CMP-2026-003 created (order_line=6, serial=SN-040)
             Admin decides: send replacement immediately (Flow B)

[David calls — SN-041 overheating]
        │
        └──→ CMP-2026-004 created (order_line=7, serial=SN-041)
             Admin decides: customer ships unit back first (Flow A)

── PARALLEL ─────────────────────────────────────────────────────────────

CMP-2026-003 Flow B (replacement first):     CMP-2026-004 Flow A (examine first):
  REP-002 created (free, SN-043)               Raj sends David prepaid return label
  shipments #25: SN-043 → David               David ships SN-041 (May 1)
  inventory_movements #22: SN-043             shipments #14: inbound (FX-20004)
    replacement_out                            SN-041 status → expected_return
  SN-043 status → assigned (in transit)
  SN-040 status → expected_return             [SN-041 arrives May 3]
                                               inventory_movements #19: SN-041 return_in
  [SN-043 delivered May 3]                    SN-041 → under_examination
                                               CMP-2026-004.status → in_progress
  REP-002 status → delivered                  Sam (Tech) examines → no_fault_found
  SN-043 → sold (with David)                 inventory_movements --: transfer to Shipping Area
                                              inventory_movements #35: adjustment (handed back)
  [SN-040 arrives May 8]                     SN-041 → sold (returned to David)
  shipments #19: inbound (UP-20003)          shipments #18: outbound return (FX-30004)
  inventory_movements #27: SN-040 return_in  CMP-2026-004 closed: no_fault_found,
  SN-040 → under_examination                              returned_to_customer
  CMP-2026-003.status → in_progress
  Sam (Tech) examines → internal_issues
  inventory_movements --: transfer to Tech Area
  inventory_movements --: adjustment (scrapped)
  SN-040 → scrapped
  CMP-2026-003 closed: internal_issues, scrapped, free

─────────────────────────────────────────────────────────────────────────

[All complaints resolved — 2026-05-08]
```

---

### Schema + Data

**`customers`**
```
id  name        email              phone         status
4   David Park  david@example.com  555-100-0004  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email              phone         address_line1  city    state  postal_code  country  is_default
4   4            Home   David       Park       david@example.com  555-100-0004  789 Oak Ave    Dallas  TX     75201        US       true
```

**`orders`**
```
id  number        customer_id  source   status   payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
5   ORD-2026-005  4            walk_in  shipped  paid            430.00    50.00  30.00     510.00       2026-04-24 11:00      2           2026-04-26 15:00      1

-- billing snapshot (NULL — stripe_terminal, card-present, no manual billing entry at POS)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (walk_in + delivery → staff enters home address at POS)
shipping_first_name  shipping_last_name  shipping_email     shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
David                Park               david@example.com  555-100-0004    789 Oak Ave             Dallas         TX              75201                 US
```

stripe_terminal = card-present. Terminal reads the chip/tap — no manual billing address entry. Billing snapshot NULL.

**`order_lines`**
```
id  order  sku     product_name   serial  unit_price  tax_rate  tax_amount  line_total
6   5      PROD-A  Widget Pro     SN-040  200.00      0.0000    0.00        200.00
7   5      PROD-B  Widget Basic   SN-041  150.00      0.0000    0.00        150.00
8   5      PROD-C  Widget Mini    SN-042   80.00      0.0000    0.00         80.00
```

AvaTax calculates `tax_rate` per line (product tax code + shipping destination). `tax_amount` = unit_price × tax_rate. Zero in fixture data — AvaTax not engaged.

**`order_line_fees`**
```
id  order_line_id  name         amount  tax_amount  fee_total  created_by  created_at
3   6              Service Fee  50.00   0.00        50.00      1           2026-04-24 11:00:00
```

**Grand total**
```
subtotal $430 (200+150+80) + fees $50 + shipping $30 + tax $0 = $510 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method           amount  status  stripe_terminal_reader_id  stripe_payment_intent_id  stripe_charge_id
6   5         order         5           stripe_terminal  510.00  paid    tmr_xxx                    pi_xxx                    ch_xxx
```

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking   label_cost  status     shipped_at            returned_at           delivered_at
5   order           5             outbound   FedEx    FX-10005   14.00       delivered  2026-04-24 11:00  NULL                  2026-04-26 15:00
14  complaint       4             inbound    FedEx    FX-20004    7.00       delivered  2026-05-01 00:00  NULL                  2026-05-03 09:00
18  complaint       4             outbound   FedEx    FX-30004    8.50       delivered  2026-05-03 14:00  NULL                  2026-05-06 11:00
19  complaint       3             inbound    UPS      UP-20003    0.00       delivered  2026-05-06 00:00  NULL                  2026-05-08 11:00
25  replacement     2             outbound   FedEx    FX-40002    8.50       delivered  2026-05-01 14:00  NULL                  2026-05-03 12:00
```

- id=5: original order delivery (3 items together, $14 label)
- id=14, 18: CMP-2026-004 (SN-041, Flow A) — David ships to us, we ship back. id=14 label_cost=$7 prepaid label we sent David.
- id=19: CMP-2026-003 (SN-040, Flow B) — old unit arrives 7 days after replacement shipped. label_cost=$0 David used own label.
- id=25: REP-002 — SN-043 replacement shipped to David same day CMP-2026-003 reported.

**`complaints`**
```
id  number        order  line  serial  status  examination_result  unit_outcome          issue_description               unit_received_at     examined_by  examination_notes                            closed_at            closed_by  created_by  withdrawn_at          withdrawn_by
3   CMP-2026-003  5      6     SN-040  closed  internal_issues     scrapped              Device dead, needs unit now     2026-05-08 11:00     3            Confirmed dead — internal component failure  2026-05-08 12:00     1          4  NULL                  NULL
4   CMP-2026-004  5      7     SN-041  closed  no_fault_found      returned_to_customer  Widget Basic overheating badly  2026-05-03 09:00     3            No defect found, unit fully functional       2026-05-03 15:00     1          4  NULL                  NULL
```

Both created ~2026-04-29 (shortly after delivery). Run in parallel with different protocols.
CMP-2026-003 unit_received_at = May 8 — old unit arrives well after replacement already delivered (Flow B).
CMP-2026-004 unit_received_at = May 3 — David ships promptly for examination (Flow A).

**`replacements`**
```
id  number         order  parent  complaint  type  charge  pay_status  status
2   REP-2026-002   5      NULL    3          free  NULL    NULL        delivered
```

Free — internal fault confirmed. Company absorbs cost. No charge to David.

**`replacement_lines`**
```
id  rep  order_line  sku     product_name  old_serial  new_serial
2   2    6           PROD-A  Widget Pro    SN-040      SN-043
```

**`notes`**
```
id  order_id  body                                              created_by  created_at
1   5         Customer walked in — 3 items, terminal payment    4           2026-04-24 11:00
2   5         CMP-003: SN-040 dead on arrival, Flow B started   4           2026-04-29 09:00
3   5         CMP-004: SN-041 overheating badly, Flow A started 4           2026-04-29 09:30
4   5         REP-002: SN-043 replacement shipped to David      1           2026-05-01 14:00
5   5         CMP-004 closed — no fault, SN-041 returned        1           2026-05-03 15:00
6   5         CMP-003 closed — internal fault confirmed, SN-040 scrapped    1           2026-05-08 12:00
```

All 6 notes share `order_id=5`. Single `WHERE order_id = 5` returns full chain history.

**`inventory_serials`**
```
serial  status    location  note
SN-040  scrapped  NULL      CMP-2026-003 — internal fault confirmed, written off
SN-041  sold      NULL      with David Park — no fault (Flow A), returned to customer
SN-042  sold      NULL      with David Park — no issues ✓
SN-043  sold      NULL      with David Park — REP-002 free replacement
```

**`inventory_movements`**

CMP-003 (SN-040, Flow B — replacement first, old unit examined 7 days later):
```
id   serial  type             from          to            reference      notes
6    SN-040  sale             Warehouse A   NULL          ORD-2026-005
22   SN-043  replacement_out  Warehouse A   NULL          REP-2026-002   replacement shipped immediately
27   SN-040  return_in        NULL            Receiving Area  CMP-2026-003   old unit arrives 7 days later — logged at dock, unit_received_at set
--   SN-040  transfer         Receiving Area  Tech Area       CMP-2026-003   warehouse staff moves unit to technician
--   SN-040  adjustment       Tech Area     NULL          CMP-2026-003   internal fault confirmed, scrapped
```

`--` after id=27: intermediate IDs sit between id=27 and next global entries (other orders between them).

CMP-004 (SN-041, Flow A — examine first, no fault, returned):
```
id   serial  type       from          to             reference      notes
7    SN-041  sale       Warehouse A   NULL           ORD-2026-005
19   SN-041  return_in  NULL            Receiving Area  CMP-2026-004   David ships unit in — logged at dock, unit_received_at set
--   SN-041  transfer   Receiving Area  Tech Area       CMP-2026-004   warehouse staff moves unit to technician
--   SN-041  transfer   Tech Area       Shipping Area   CMP-2026-004   no fault, prepping return to customer
35   SN-041  adjustment Shipping Area NULL           CMP-2026-004   no fault, handed back to David
```

`--` between id=19 and id=35: other orders' movements sit in between in global ledger.

SN-042 (no complaint):
```
id  serial  type  from         to    reference
8   SN-042  sale  Warehouse A  NULL  ORD-2026-005
```

---

### Financial Summary
```
charged:   $510.00   (1 payment row — REP-002 is free, no separate charge)
collected: $510.00
refunded:  $0.00
net:       $510.00 ✓
```

### Shipping Margin
```
revenue:  $30.00  (orders.shipping_amount — charged to David)
cost:     $38.00  (id=5 $14 + id=14 $7 + id=18 $8.50 + id=19 $0 + id=25 $8.50)
margin:   -$8.00  (absorbed — two concurrent complaints, 5 shipment legs)
```

---
