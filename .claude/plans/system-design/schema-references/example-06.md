## Example 6 — ORD-007 — Flow A: Damaged by Customer, Charged Replacement

**What's new vs all previous examples:**

| | Karen (Ex 3) | Lisa (Ex 5) | Emma (Ex 6) |
|--|--|--|--|
| Flow | A | B | A |
| Result | no_fault_found | no_fault_found | **damaged_by_customer** |
| Replacement | none | charged after exam | **charged after exam** |
| Refund | none | none | **none — customer's fault** |
| Old unit | returned_to_customer | back_to_stock | **scrapped** |
| Warranty | valid | valid | **voided** |

**Scenario:** Emma Davis walks in. Pays Stripe Terminal. Widget Pro SN-060 reported with grinding noise. Customer ships unit back (Flow A). Sam examines → physical damage found — unit dropped, screen cracked. Customer's fault — warranty voided, no free replacement. Admin offers replacement at full price ($80). Emma agrees, pays. SN-061 shipped. SN-060 scrapped.

---

### Data Flow

```
[Emma walks in — admin creates order at POS]
        │
        ├──→ orders (customer_id=6, billing NULL (terminal), shipping snapshot filled)
        ├──→ order_lines (1 line: SN-060)
        └──→ order_fees (service fee $20)

[Emma taps card on Stripe Terminal — instant]
        │
        └──→ payments INSERT #9 (status=paid, stripe_terminal_reader_id)
             orders.payment_status → paid
             orders.status → processing

[Ali ships]
        │
        └──→ shipments INSERT #7 (order/7/outbound, FX-10007)
             inventory_movements INSERT #10 (SN-060 sale)
             SN-060 → sold
             orders.status → shipped, shipped_at set, shipped_by = Ali (user_id=2)

[Delivered 2026-04-28 — carrier confirms, admin records manually]
        │
        └──→ orders.delivered_at set
             (orders.status stays shipped — no status change)

[Emma calls — SN-060 grinding noise]
        │
        └──→ CMP-2026-006 created (order_line=10, serial=SN-060)
             Admin: customer ships unit back first (Flow A)

[Emma ships SN-060 back — her own label]
        │
        └──→ shipments #15: inbound (complaint/6, UP-20006, label_cost=0)
             SN-060 → expected_return

[SN-060 arrives 2026-05-04]
        │
        ├──→ inventory_movements INSERT #20 (SN-060 return_in)
        │    SN-060 → under_examination
        │    CMP-2026-006.status → in_progress
        │
        └──→ Sam (Tech) examines → damaged_by_customer
             Physical damage — unit dropped, screen cracked
             Warranty voided — no free replacement
             inventory_movements -- (transfer → Tech Area)
             inventory_movements -- (adjustment → scrapped)
             SN-060 → scrapped

[Admin informs Emma — damage confirmed, offers replacement at $80]
        │
        └──→ Emma agrees → REP-2026-004 created (type=charged, $80)
             payments INSERT #10 (payable=replacement/4, stripe_terminal, $80, paid)
             REP-2026-004.pay_status → paid

[Replacement SN-061 shipped 2026-05-05]
        │
        ├──→ shipments INSERT #27 (replacement/4/outbound, FX-40004)
        └──→ inventory_movements INSERT #32 (SN-061 replacement_out)
             SN-061 → assigned (in transit)

[SN-061 delivered 2026-05-07]
        │
        └──→ REP-2026-004.status → delivered
             SN-061 → sold (with Emma)
             CMP-2026-006.status → closed
```

---

### Schema + Data

**`customers`**
```
id  name        email             phone         status
6   Emma Davis  emma@example.com  555-100-0006  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email             phone         address_line1  city         state  postal_code  country  is_default
6   6            Home   Emma        Davis      emma@example.com  555-100-0006  567 Pine Ave   San Antonio  TX     78201        US       true
```

**`orders`**
```
id  number        customer_id  source   status   payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
7   ORD-2026-007  6            walk_in  shipped  paid            200.00    20.00  20.00     240.00       2026-04-26 10:00      2           2026-04-28 11:00      1

-- billing snapshot (NULL — stripe_terminal, card-present, no manual billing entry)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (walk_in + delivery → staff enters home address at POS)
shipping_first_name  shipping_last_name  shipping_email    shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
Emma                 Davis               emma@example.com  555-100-0006    567 Pine Ave            San Antonio    TX              78201                 US
```

**`order_lines`**
```
id  order  sku     product_name  serial  unit_price  tax_rate  tax_amount  line_total
10  7      PROD-A  Widget Pro    SN-060  200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
7   7      Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $20 + tax $0 = $240 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method           amount  status  stripe_terminal_reader_id  stripe_payment_intent_id  stripe_charge_id
9   7         order         7           stripe_terminal  240.00  paid    tmr_xxx                    pi_ord_xxx                ch_ord_xxx
10  7         replacement   4           stripe_terminal   80.00  paid    tmr_xxx                    pi_rep_xxx                ch_rep_xxx
```

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking   label_cost  status     shipped_at            returned_at           delivered_at
7   order           7             outbound   FedEx    FX-10007    8.50       delivered  2026-04-26 10:00  NULL                  2026-04-28 11:00
15  complaint       6             inbound    UPS      UP-20006    0.00       delivered  2026-05-03 00:00  NULL                  2026-05-04 11:00
27  replacement     4             outbound   FedEx    FX-40004    8.50       delivered  2026-05-05 10:00  NULL                  2026-05-07 12:00
```

id=15 `label_cost=0` — Emma used her own return label.

**`complaints`**
```
id  number        order  line  serial  status  examination_result   unit_outcome  issue_description           unit_received_at     examined_by  examination_notes                              closed_at            closed_by  created_by  withdrawn_at          withdrawn_by
6   CMP-2026-006  7      10    SN-060  closed  damaged_by_customer  scrapped      Motor making grinding noise  2026-05-04 11:00    3            Physical damage — unit dropped, screen cracked  2026-05-07 15:00     1          4  NULL                  NULL
```

`damaged_by_customer` — warranty voided. No free replacement. Customer pays full price.

**`replacements`**
```
id  number         order  parent  complaint  type     charge  pay_status  status
4   REP-2026-004   7      NULL    6          charged  80.00   paid        delivered
```

Full charge — no discount, no refund. Customer's fault confirmed by examination.

**`replacement_lines`**
```
id  rep  order_line  sku     product_name  old_serial  new_serial
4   4    10          PROD-A  Widget Pro    SN-060      SN-061
```

**`inventory_serials`**
```
serial  status    location  note
SN-060  scrapped  NULL      CMP-2026-006 — damaged_by_customer, warranty voided, scrapped
SN-061  sold      NULL      with Emma Davis — REP-2026-004
```

**`inventory_movements`**
```
id   serial  type             from          to            reference      notes
10   SN-060  sale             Warehouse A   NULL          ORD-2026-007
20   SN-060  return_in        NULL            Receiving Area  CMP-2026-006   Emma ships back, her own label — logged at dock, unit_received_at set
--   SN-060  transfer         Receiving Area  Tech Area       CMP-2026-006   warehouse staff moves unit to technician
--   SN-060  adjustment       Tech Area     NULL          CMP-2026-006   damaged_by_customer, scrapped
32   SN-061  replacement_out  Warehouse A   NULL          REP-2026-004   replacement ships after damage confirmed
```

`--` between id=20 and id=32: intermediate IDs in global ledger.
`to=NULL` on SN-060 adjustment = scrapped permanently.

---

### Financial Summary
```
charged:   $320.00   (2 payment rows — payment #9 $240 order + payment #10 $80 replacement)
collected: $320.00
refunded:  $0.00     (no refund — customer's fault)
net:       $320.00 ✓
```

### Shipping Margin
```
revenue:  $20.00  (orders.shipping_amount)
cost:     $17.00  (id=7 $8.50 + id=15 $0 + id=27 $8.50)
margin:   +$3.00  (Emma used own label — saved $7 vs prepaid)
```

---
