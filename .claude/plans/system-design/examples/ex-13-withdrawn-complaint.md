> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## Example 13 — ORD-015 — Withdrawn Complaint

**Scenario:** Linda Green buys one item online. Pays via Stripe card. Delivered. Same day calls in — device won't turn on. CSR opens complaint CMP-2026-013, serial → `expected_return`. CSR generates prepaid UPS return label ($7.00 paid upfront). Next morning Linda calls back — device started working after full charge overnight, wants to withdraw. CSR records withdrawal. Complaint closes as `withdrawn`. Label voided — cost already paid, no refund from carrier. Serial reverts `expected_return → sold`.

> **Key rule:** `withdrawn` only valid while complaint is `open` — no `return_in` movement yet. Once carrier scans the package (`status → in_progress`), withdrawal is impossible.

> **Prepaid label cost rule:** label generated = cost paid to carrier immediately. Voiding does not recover the cost. Always absorbed as a loss regardless of withdrawal. Distinguish from customer-paid labels (`label_cost=0.00`) which are only tracked once the carrier scans the package.

---

### Data Flow

```
[Admin creates order — Linda pays online]
        │
        ├──→ orders (customer_id=13, billing + shipping filled, status=pending)
        ├──→ order_lines (1 line item, SN-140)
        ├──→ order_fees (service fee)
        └──→ payments INSERT #18 (stripe_card, status=paid)
             orders.payment_status → paid, orders.status → processing

[Admin ships]
        │
        └──→ shipments INSERT #36 (outbound, FedEx FX-10015)
             inventory_movements INSERT #47 (sale, SN-140 Warehouse A → NULL)
             inventory_serials UPDATE: in_stock → sold
             orders.status → shipped, shipped_at, shipped_by set

[Delivered — admin records — 2026-05-28 09:00]
        │
        └──→ orders.delivered_at, delivered_by set

[Linda calls — device won't turn on — 2026-05-28 10:00]
        │
        └──→ complaints INSERT CMP-2026-013 (status=open, created_by=4)
             inventory_serials UPDATE: sold → expected_return
             shipments INSERT #37 (inbound, status=pending, label_cost=7.00)
             ← label generated + paid upfront at this point

[Linda calls next morning — device works, wants to withdraw — 2026-05-29 09:00]
        │
        └──→ complaints UPDATE: status → withdrawn
                                withdrawn_at = 2026-05-29 09:00
                                withdrawn_by = 4
             inventory_serials UPDATE: expected_return → sold   ← revert
             shipments UPDATE #37: status → voided
             ← label voided, $7.00 already paid — no refund from carrier
```

---

### Schema + Data

**`customers`**
```
id   name         email               phone         status
13   Linda Green  linda@example.com   555-100-0013  active
```

**`customer_addresses`**
```
id   customer_id  label  first_name  last_name  email               phone         address_line1   city         state  postal_code  country  is_default
13   13           Home   Linda       Green      linda@example.com   555-100-0013  654 Cedar Lane  San Antonio  TX     78201        US       true
```

**`orders`**
```
id  number        customer_id  source  status   payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
15  ORD-2026-015  13           online  shipped  paid            200.00    20.00  20.00     240.00       2026-05-26 10:00      2           2026-05-28 09:00      1

-- billing snapshot (filled — online stripe_card)
billing_first_name  billing_last_name  billing_email       billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
Linda               Green              linda@example.com   555-100-0013   654 Cedar Lane         San Antonio   TX             78201                US

-- shipping snapshot (same address — delivery)
shipping_first_name  shipping_last_name  shipping_email      shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
Linda                Green               linda@example.com   555-100-0013    654 Cedar Lane          San Antonio    TX              78201                 US
```

**`order_lines`**
```
id  order  sku     product_name  serial   unit_price  tax_rate  tax_amount  line_total
42  15     PROD-A  Widget Pro    SN-140   200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
22  15     Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $20 + tax $0 = $240 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method       amount  status  stripe_payment_intent_id  stripe_charge_id
18  15        order         15          stripe_card  240.00  paid    pi_xxx                    ch_xxx
```

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking    label_cost  status     shipped_at            returned_at           delivered_at
36  order           15            outbound   FedEx    FX-10015    8.50        delivered  2026-05-26 10:00  NULL                  2026-05-28 09:00
37  complaint       13            inbound    UPS      UP-20013    7.00        voided     NULL              NULL              NULL
```

> Shipment #37: `status=pending` when label generated (cost paid). `status → voided` on withdrawal. `shipped_at=NULL` — Linda never dropped off the package. `label_cost=7.00` — paid to UPS, not recoverable.

> **Shipment status values for prepaid labels:**
> - `pending` — label generated and paid, waiting for customer to drop off
> - `voided` — label cancelled, cost already absorbed
> - `in_transit` — carrier scanned the package (label activated)
> - `delivered` — package arrived at warehouse

**`complaints`**
```
id   number         order  line  serial   status     examination_result  unit_outcome  issue_description       unit_received_at  examined_by  examination_notes  closed_at  closed_by  created_by  withdrawn_at          withdrawn_by
13   CMP-2026-013   15     42    SN-140   withdrawn  NULL                NULL          Device won't turn on    NULL              NULL         NULL               NULL       NULL       4           2026-05-29 09:00      4
```

> `examination_result=NULL, unit_outcome=NULL` — withdrawn before any examination. `closed_at=NULL` — `withdrawn` is its own terminal state, separate from `closed`. `unit_received_at=NULL` — unit never arrived.

**`inventory_serials`**
```
serial   status  location  note
SN-140   sold    NULL      with Linda Green
```

> Status timeline: `in_stock → sold` (sale) → `expected_return` (complaint opened) → `sold` (withdrawn — unit stays with Linda).

**`inventory_movements`**
```
id  serial   type  from         to    reference      notes
47  SN-140   sale  Warehouse A  NULL  ORD-2026-015
```

> One movement only. No `return_in`, no `adjustment` — unit never left Linda.

---

### Financial Summary
```
charged:   $240.00
collected: $240.00
refunded:  $0.00
net:       $240.00 ✓

label cost (voided prepaid):  -$7.00  (shipments.label_cost, status=voided — not recoverable)
net after label loss:         $233.00
```

### Shipping Margin
```
revenue:  $20.00  (orders.shipping_amount)
cost:     $8.50   (shipments #36 — outbound FX-10015, delivered)
margin:   +$11.50

voided return label:  -$7.00  (shipments #37 — prepaid label, never used, not recoverable)
net after label loss: +$4.50
```

---
