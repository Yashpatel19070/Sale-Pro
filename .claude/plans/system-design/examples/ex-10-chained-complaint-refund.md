> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## Example 10 — ORD-012 — Phone Order, In-Store Pickup, Chained Complaint, Full Refund

**Scenario:** Jane Kim calls the store. Admin creates order. Jane comes in next day, pays cash, picks up Widget Pro SN-110. Three days later SN-110 fails — Jane returns to counter. Internal fault confirmed. Free replacement SN-111 handed at counter (REP-009). Three days later SN-111 also fails. Jane returns again. Internal fault again. Admin decides: two consecutive faults = refund, no second replacement. Full $220 cash refund.

**What's new vs Example 9:**
- Two complaints, same order — second complaint serial is replacement unit (SN-111), not original (SN-110)
- `complaints.order_line=17` stays same across both complaints — replacement serial never creates new order line
- `replacements.parent` shown — NULL on REP-009 (first). If second replacement issued, it would set `parent=9`
- `orders.status → refunded` — admin policy: two faults = refund over second replacement
- Cash payment → cash refund

---

### Data Flow

```
[Jane calls — admin creates order]
        │
        ├──→ orders (customer_id=10, source=phone, billing NULL (cash), shipping NULL (pickup), status=pending, payment_status=unpaid)
        ├──→ order_lines (1 line: SN-110)
        └──→ order_fees (service fee $20)

[Jane arrives next day — pays cash at counter, picks up SN-110]
        │
        └──→ payments INSERT #15 (cash, status=paid, cash_received_at=2026-05-11 10:00)
             orders.payment_status → paid
             orders.status → processing
             inventory_movements INSERT #41 (sale, Warehouse A → NULL)
             inventory_serials UPDATE SN-110 (in_stock → sold)
             orders.status → complete

[Jane returns 3 days later — SN-110 not working, hands unit at counter]
        │
        └──→ complaints INSERT CMP-2026-011 (order_line=17, serial=SN-110, status=open, created_by=4)
             inventory_movements INSERT #42 (return_in, NULL → Tech Area)
             inventory_serials UPDATE SN-110 (sold → under_examination)
             complaints.unit_received_at = 2026-05-13 10:00
             complaints.status → in_progress

[Sam examines — internal fault confirmed]
        │
        └──→ complaints.examination_result → internal_issues
             complaints.examined_by = 3
             inventory_movements INSERT -- (adjustment, Tech Area → NULL)
             inventory_serials UPDATE SN-110 (under_examination → scrapped)

[Free replacement SN-111 handed to Jane at counter]
        │
        └──→ REP-2026-009 created (type=free, complaint_id=11)
             inventory_movements INSERT #43 (replacement_out, Warehouse A → NULL)
             inventory_serials UPDATE SN-111 (in_stock → sold)
             REP-2026-009.status → delivered
             CMP-2026-011.unit_outcome → scrapped
             CMP-2026-011.status → closed, closed_at = 2026-05-13 14:00, closed_by = 1

[Jane returns 3 days later — SN-111 also not working, hands unit at counter]
        │
        └──→ complaints INSERT CMP-2026-012 (order_line=17, serial=SN-111, status=open, created_by=4)
             ⚠️ serial=SN-111 (replacement unit) — same order_line=17 (original line, no new line created)
             inventory_movements INSERT #44 (return_in, NULL → Tech Area)
             inventory_serials UPDATE SN-111 (sold → under_examination)
             complaints.unit_received_at = 2026-05-16 11:00
             complaints.status → in_progress

[Sam examines — internal fault confirmed again]
        │
        └──→ complaints.examination_result → internal_issues
             complaints.examined_by = 3
             inventory_movements INSERT -- (adjustment, Tech Area → NULL)
             inventory_serials UPDATE SN-111 (under_examination → scrapped)

[Admin decides: two faults = refund, no second replacement]
        │
        └──→ refunds INSERT REF-006 ($220, cash, processed)
             CMP-2026-012.unit_outcome → scrapped
             CMP-2026-012.status → closed, closed_at = 2026-05-16 15:00, closed_by = 1
             orders.status → refunded
```

---

### Schema + Data

**`customers`**
```
id  name      email             phone         status
10  Jane Kim  jane@example.com  555-100-0010  active
```

**`customer_addresses`**
```
-- no rows for Jane — phone order, in-store pickup, no address collected
```

**`orders`**
```
id  number        customer_id  source  status    payment_status  subtotal  fees   shipping  grand_total  shipped_at  shipped_by  cancelled_at         cancelled_by
12  ORD-2026-012  10           phone   refunded  paid            200.00    20.00  0.00      220.00       NULL        NULL        2026-05-16 15:00     1

-- billing snapshot (NULL — cash payment)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (NULL — in-store pickup)
shipping_first_name  shipping_last_name  shipping_email  shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
NULL                 NULL                NULL            NULL            NULL                    NULL           NULL            NULL                  NULL
```

`shipped_at=NULL`, `shipped_by=NULL` — pickup, no carrier.
`cancelled_at` reused as terminal timestamp for refund event — same column serves both `cancelled` and `refunded` final states.

**`order_lines`**
```
id  order  sku     product_name  serial  unit_price  tax_rate  tax_amount  line_total
17  12     PROD-A  Widget Pro    SN-110  200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
12  12     Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $0 + tax $0 = $220 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method  amount  status  cash_received_at
15  12        order         12          cash    220.00  paid    2026-05-11 10:00
```

**`refunds`**
```
id  number   order  type   payable  amount  ship_refund  method  reason                                                         status
6   REF-006  12        complaint  12       220.00  0.00         cash    Two consecutive internal faults — refund over second replacement  processed
```

**`complaints`**
```
id  number        order  line  serial  status  examination_result  unit_outcome  issue_description             unit_received_at     examined_by  examination_notes                      closed_at            closed_by  created_by  withdrawn_at          withdrawn_by
11  CMP-2026-011  12     17    SN-110  closed  internal_issues     scrapped      Widget Pro not working        2026-05-13 10:00     3            Internal component failure confirmed   2026-05-13 14:00     1          4  NULL                  NULL
12  CMP-2026-012  12     17    SN-111  closed  internal_issues     scrapped      Replacement also not working  2026-05-16 11:00     3            Internal fault — second occurrence     2026-05-16 15:00     1          4  NULL                  NULL
```

CMP-2026-012 `serial=SN-111` — replacement unit, not original. `order_line=17` same across both complaints — no new line created for replacement units.

**`replacements`**
```
id  number        order  parent  complaint  type  charge  pay_status  status
9   REP-2026-009  12     NULL    11         free  NULL    NULL        delivered
```

`parent=NULL` — first replacement in chain.

**Sub-case: Chained replacement (replacement unit itself fails, second replacement issued)**

If admin had issued a second replacement instead of refund, the `replacements` table would show:

```
id  number        order  parent  complaint  type  charge  pay_status  status
9   REP-2026-009  12     NULL    11         free  NULL    NULL        delivered
10  REP-2026-010  12     9       12         free  NULL    NULL        delivered
```

And `replacement_lines`:
```
id  rep  order_line  sku     product_name  old_serial  new_serial
9   9    17          PROD-A  Widget Pro    SN-110      SN-111
10  10   17          PROD-A  Widget Pro    SN-111      SN-112
```

Rules:
- `parent=9` on REP-010 — links to the replacement it supersedes
- `order_line=17` stays same across entire chain — always the original purchase line
- Each replacement's `old_serial` = previous replacement's `new_serial`
- Chain depth unlimited — follow `parent` FK to trace full history
- SN-112 would be `sold` (with customer). SN-111 `scrapped` (second fault confirmed).

**`replacement_lines`**
```
id  rep  order_line  sku     product_name  old_serial  new_serial
9   9    17          PROD-A  Widget Pro    SN-110      SN-111
```

**`inventory_serials`**
```
serial  status    location  note
SN-110  scrapped  NULL      CMP-2026-011 — internal fault, scrapped
SN-111  scrapped  NULL      CMP-2026-012 — internal fault again, scrapped — refund issued
```

**`inventory_movements`**
```
id   serial  type             from         to          reference      notes
41   SN-110  sale             Warehouse A  NULL        ORD-2026-012   picked up at counter
42   SN-110  return_in        NULL         Tech Area   CMP-2026-011   Jane hands SN-110 at counter
--   SN-110  adjustment       Tech Area    NULL        CMP-2026-011   internal fault, scrapped
43   SN-111  replacement_out  Warehouse A  NULL        REP-2026-009   handed to Jane at counter
44   SN-111  return_in        NULL         Tech Area   CMP-2026-012   Jane returns SN-111 — second failure
--   SN-111  adjustment       Tech Area    NULL        CMP-2026-012   internal fault again, scrapped
```

No shipments — all in-store.

---

### Financial Summary
```
charged:   $220.00   (1 payment row)
collected: $220.00
refunded:  $220.00   (REF-006 — two faults, full cash refund)
net:       $0.00     (fully reversed)
```

### Shipping Margin
```
revenue:  $0.00
cost:     $0.00  (no shipments)
margin:   $0.00
```
