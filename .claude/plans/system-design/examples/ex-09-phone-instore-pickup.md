> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## Example 9 — ORD-011 — Phone Order, In-Store Pickup, In-Person Complaint

**Scenario:** Tom Wilson calls the store. Admin creates order on his behalf. Tom comes in next day, pays Stripe Terminal, picks up Widget Pro SN-100. Two days later returns — device not powering on. Hands unit at counter. Sam examines — internal fault. Free replacement SN-101 handed to Tom same day. No shipping at any point.

**What's new vs all previous examples:**
- `source=phone` — admin creates on behalf of customer who called
- `orders.status → complete` — in-store pickup, no carrier
- No shipments table — no labels, no carrier, customer walks in/out
- Complaint handled entirely at counter — unit handed over in person

---

### Data Flow

```
[Tom calls — admin creates order on his behalf]
        │
        ├──→ orders (customer_id=9, source=phone, billing NULL (terminal), shipping NULL (pickup), status=pending, payment_status=unpaid)
        ├──→ order_lines (1 line: SN-100)
        └──→ order_fees (service fee $20)

[Tom arrives next day — taps Stripe Terminal, picks up at counter]
        │
        └──→ payments INSERT #14 (stripe_terminal, status=paid)
             orders.payment_status → paid
             orders.status → processing
             inventory_movements INSERT #38 (sale, Warehouse A → NULL)
             inventory_serials UPDATE (in_stock → sold)
             orders.status → complete

[Tom returns 2 days later — device not powering on, hands unit at counter]
        │
        └──→ complaints INSERT CMP-2026-010 (order_line=16, serial=SN-100, status=open, created_by=4)
             inventory_movements INSERT #39 (return_in, NULL → Tech Area)
             inventory_serials UPDATE (sold → under_examination)
             complaints.unit_received_at = 2026-05-10 11:00
             complaints.status → in_progress

[Sam examines same day — internal fault confirmed]
        │
        └──→ complaints.examination_result → internal_issues
             complaints.examined_by = 3
             inventory_movements INSERT -- (adjustment, Tech Area → NULL)
             inventory_serials UPDATE SN-100 (under_examination → scrapped)

[Free replacement SN-101 handed to Tom at counter]
        │
        └──→ REP-2026-008 created (type=free, complaint_id=10)
             inventory_movements INSERT #40 (replacement_out, Warehouse A → NULL)
             inventory_serials UPDATE SN-101 (in_stock → sold)
             REP-2026-008.status → delivered
             complaints.unit_outcome → scrapped
             complaints.status → closed, closed_at = 2026-05-10 15:00, closed_by = 1
```

---

### Schema + Data

**`customers`**
```
id  name        email            phone         status
9   Tom Wilson  tom@example.com  555-100-0009  active
```

**`customer_addresses`**
```
-- no rows for Tom — phone order, in-store pickup, no address collected
```

**`orders`**
```
id  number        customer_id  source  status    payment_status  subtotal  fees   shipping  grand_total  shipped_at  shipped_by
11  ORD-2026-011  9            phone   complete  paid            200.00    20.00  0.00      220.00       NULL        NULL

-- billing snapshot (NULL — stripe_terminal, card-present)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (NULL — in-store pickup, no address needed)
shipping_first_name  shipping_last_name  shipping_email  shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
NULL                 NULL                NULL            NULL            NULL                    NULL           NULL            NULL                  NULL
```

`shipped_at=NULL`, `shipped_by=NULL` — in-store pickup, no carrier involved.

**`order_lines`**
```
id  order  sku     product_name  serial  unit_price  tax_rate  tax_amount  line_total
16  11     PROD-A  Widget Pro    SN-100  200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
11  11     Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $0 + tax $0 = $220 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method           amount  status  stripe_terminal_reader_id  stripe_payment_intent_id  stripe_charge_id
14  11        order         11          stripe_terminal  220.00  paid    tmr_xxx                    pi_xxx                    ch_xxx
```

**`complaints`**
```
id  number        order  line  serial  status  examination_result  unit_outcome  issue_description       unit_received_at     examined_by  examination_notes                     closed_at            closed_by  created_by  withdrawn_at          withdrawn_by
10  CMP-2026-010  11     16    SN-100  closed  internal_issues     scrapped      Device not powering on  2026-05-10 11:00     3            Internal component failure confirmed  2026-05-10 15:00     1          4  NULL                  NULL
```

**`replacements`**
```
id  number        order  parent  complaint  type  charge  pay_status  status
8   REP-2026-008  11     NULL    10         free  NULL    NULL        delivered
```

**`replacement_lines`**
```
id  rep  order_line  sku     product_name  old_serial  new_serial
8   8    16          PROD-A  Widget Pro    SN-100      SN-101
```

**`inventory_serials`**
```
serial  status    location  note
SN-100  scrapped  NULL      CMP-2026-010 — internal fault confirmed, scrapped
SN-101  sold      NULL      with Tom Wilson — REP-2026-008
```

**`inventory_movements`**
```
id   serial  type             from         to          reference      notes
38   SN-100  sale             Warehouse A  NULL        ORD-2026-011   picked up at counter
39   SN-100  return_in        NULL         Tech Area   CMP-2026-010   Tom hands unit at counter
--   SN-100  adjustment       Tech Area    NULL        CMP-2026-010   internal fault, scrapped
40   SN-101  replacement_out  Warehouse A  NULL        REP-2026-008   handed to Tom at counter
```

No shipments — in-store pickup, in-person return, in-store replacement. Zero carrier involvement.

---

### Financial Summary
```
charged:   $220.00
collected: $220.00
refunded:  $0.00
net:       $220.00 ✓
```

### Shipping Margin
```
revenue:  $0.00
cost:     $0.00  (no shipments)
margin:   $0.00
```

---
