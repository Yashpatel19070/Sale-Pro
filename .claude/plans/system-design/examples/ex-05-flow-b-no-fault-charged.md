> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## Example 5 — ORD-006 — Flow B: No Fault, Charged Replacement

**Scenario:** Lisa Chen orders online. Pays via Stripe card. Widget Pro SN-050 reported as not turning on. Admin sends replacement immediately (Flow B). SN-051 delivered to Lisa. SN-050 arrives 8 days later — examined, no fault found. Lisa charged $80 for the replacement. SN-050 goes back to warehouse stock.

Key distinction from Example 4 CMP-2026-003: both Flow B, but David's was `internal_issues` → free. Lisa's is `no_fault_found` → charged. Customer pays when the unit is fine.

---

### Data Flow

```
[Lisa orders online]
        │
        ├──→ orders (customer_id=5, billing + shipping snapshot filled)
        ├──→ order_lines (1 line: SN-050)
        └──→ order_fees (service fee $20)

[Lisa pays via Stripe card — sync]
        │
        └──→ payments INSERT #7 (status=paid, stripe_payment_intent_id, stripe_charge_id)
             orders.payment_status → paid
             orders.status → processing

[Ali (Warehouse) ships]
        │
        └──→ shipments INSERT #6 (order/6/outbound, FX-10006)
             inventory_movements INSERT #9 (SN-050 sale)
             SN-050 → sold
             orders.status → shipped, shipped_at set, shipped_by = Ali (user_id=2)

[Delivered 2026-04-27 — carrier confirms, admin records manually]
        │
        └──→ orders.delivered_at set
             (orders.status stays shipped — no status change)

[Lisa calls — SN-050 not turning on]
        │
        └──→ CMP-2026-005 created (order_line=9, serial=SN-050)
             Admin decides: send replacement immediately (Flow B)

[Admin sends replacement — same day]
        │
        ├──→ REP-2026-003 created (type=charged, complaint_id=5)
        ├──→ shipments INSERT #26 (replacement/3/outbound, FX-40003)
        └──→ inventory_movements INSERT #23 (SN-051 replacement_out)
             SN-051 → assigned (in transit to Lisa)
             SN-050 → expected_return (old unit, with Lisa)

[SN-051 delivered to Lisa 2026-05-03]
        │
        └──→ REP-2026-003.status → delivered
             SN-051 → sold (with Lisa)

[SN-050 arrives back 2026-05-09 — 8 days after replacement shipped]
        │
        ├──→ shipments #20: inbound (complaint/5, FX-20005, prepaid label)
        ├──→ inventory_movements INSERT #28 (SN-050 return_in)
        │    SN-050 → under_examination
        │    CMP-2026-005.status → in_progress
        │
        └──→ Sam (Tech) examines → no_fault_found
             inventory_movements -- (transfer → Tech Area)
             inventory_movements -- (adjustment → Warehouse A, back to stock)
             SN-050 → in_stock (Warehouse A)

[No fault confirmed — admin charges Lisa for replacement]
        │
        └──→ payments INSERT #8 (payable=replacement/3, stripe_card, $80, paid)
             REP-2026-003.pay_status → paid
             CMP-2026-005.status → closed
```

---

### Schema + Data

**`customers`**
```
id  name       email             phone         status
5   Lisa Chen  lisa@example.com  555-100-0005  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email             phone         address_line1  city     state  postal_code  country  is_default
5   5            Home   Lisa        Chen       lisa@example.com  555-100-0005  321 Elm St     Houston  TX     77001        US       true
```

**`orders`**
```
id  number        customer_id  source  status   payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
6   ORD-2026-006  5            online  shipped  paid            200.00    20.00  20.00     240.00       2026-04-25 09:00      2           2026-04-27 14:00      1

-- billing snapshot (stripe_card, card-not-present → billing filled)
billing_first_name  billing_last_name  billing_email     billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
Lisa                Chen               lisa@example.com  555-100-0005   321 Elm St             Houston       TX             77001                US

-- shipping snapshot (online + delivery → same address as billing)
shipping_first_name  shipping_last_name  shipping_email    shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
Lisa                 Chen                lisa@example.com  555-100-0005    321 Elm St              Houston        TX              77001                 US
```

**`order_lines`**
```
id  order  sku     product_name  serial  unit_price  tax_rate  tax_amount  line_total
9   6      PROD-A  Widget Pro    SN-050  200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
6   6      Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $20 + tax $0 = $240 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method       amount  status  stripe_payment_intent_id  stripe_charge_id
7   6         order         6           stripe_card  240.00  paid    pi_ord_xxx                ch_ord_xxx
8   6         replacement   3           stripe_card   80.00  paid    pi_rep_xxx                ch_rep_xxx
```

Two payments on same order. id=7 upfront for order. id=8 after-the-fact for replacement — triggered when examination confirms no fault.

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking   label_cost  status     shipped_at            returned_at           delivered_at
6   order           6             outbound   FedEx    FX-10006    8.50       delivered  2026-04-25 09:00  NULL                  2026-04-27 14:00
20  complaint       5             inbound    FedEx    FX-20005    7.00       delivered  2026-05-07 00:00  NULL                  2026-05-09 10:00
26  replacement     3             outbound   FedEx    FX-40003    8.50       delivered  2026-05-01 11:00  NULL                  2026-05-03 14:00
```

- id=6: original order delivery
- id=20: CMP-2026-005 — SN-050 arrives 8 days after replacement shipped. label_cost=$7 prepaid label we sent Lisa.
- id=26: REP-2026-003 — SN-051 shipped to Lisa immediately (Flow B, May 1)

**`complaints`**
```
id  number        order  line  serial  status  examination_result  unit_outcome   issue_description             unit_received_at     examined_by  examination_notes                          closed_at            closed_by  created_by  withdrawn_at          withdrawn_by
5   CMP-2026-005  6      9     SN-050  closed  no_fault_found      back_to_stock  Device not turning on at all  2026-05-09 10:00     3            Unit fully functional — no defect detected  2026-05-09 12:00     1          4  NULL                  NULL
```

Flow B — replacement shipped May 1, SN-050 arrives 8 days later May 9. No fault → charged.
`back_to_stock` outcome: SN-050 returned to Warehouse A inventory, not sent back to customer (Lisa already has SN-051).

**`replacements`**
```
id  number         order  parent  complaint  type     charge  pay_status  status
3   REP-2026-003   6      NULL    5          charged  80.00   paid        delivered
```

Charged — no fault confirmed = customer responsible. Payment collected after examination, not upfront.

**`replacement_lines`**
```
id  rep  order_line  sku     product_name  old_serial  new_serial
3   3    9           PROD-A  Widget Pro    SN-050      SN-051
```

**`inventory_serials`**
```
serial  status    location     note
SN-050  in_stock  Warehouse A  CMP-2026-005 — no fault, back to stock
SN-051  sold      NULL         with Lisa Chen — REP-2026-003
```

**`inventory_movements`**
```
id   serial  type             from          to            reference      notes
9    SN-050  sale             Warehouse A   NULL          ORD-2026-006
23   SN-051  replacement_out  Warehouse A   NULL          REP-2026-003   replacement shipped immediately (Flow B)
28   SN-050  return_in        NULL            Receiving Area  CMP-2026-005   old unit arrives 8 days later — logged at dock, unit_received_at set
--   SN-050  transfer         Receiving Area  Tech Area       CMP-2026-005   warehouse staff moves unit to technician
--   SN-050  adjustment       Tech Area       Warehouse A     CMP-2026-005   no fault, back to stock
```

`--` after id=28: intermediate IDs in global ledger.
`to=Warehouse A` on adjustment — back_to_stock. Compare: `to=NULL` means item left the system (returned to customer or scrapped).

---

### Financial Summary
```
charged:   $320.00   (2 payment rows — payment #7 $240 order + payment #8 $80 replacement)
collected: $320.00
refunded:  $0.00
net:       $320.00 ✓
```

### Shipping Margin
```
revenue:  $20.00  (orders.shipping_amount)
cost:     $24.00  (id=6 $8.50 + id=20 $7.00 + id=26 $8.50)
margin:   -$4.00  (absorbed — complaint handling overhead)
```

---
