> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## Example 12 — ORD-014 — Phone Order + Cheque

**Scenario:** Robert Kim calls in. Buys one Widget Basic ($150). Offers to pay by company cheque — gives cheque number and date by phone. CSR records order + cheque details (`status=pending`). Cheque arrives and clears next day. Admin marks payment received, ships via UPS. No issues.

> **Why this matters:** `source=phone` + `method=cheque`. Two-step payment: cheque recorded first (`pending`), order ships only after admin confirms cheque cleared (`paid`). Billing NULL. Shipping filled — phone order with home delivery.

---

### Data Flow

```
[Customer calls — admin creates order + records cheque]
        │
        ├──→ customer_addresses INSERT (Robert gives home address by phone)
        ├──→ orders (customer_id=12, billing NULL — cheque, shipping snapshot filled, status=pending, payment_status=unpaid)
        ├──→ order_lines (1 line item)
        ├──→ order_line_fees INSERT (Programming Fee $9 · Gas Tuning Fee $6)
        └──→ payments INSERT (status=pending, cheque_number, cheque_date)
             (order stays pending — not shipped until cheque clears)

[Cheque arrives + clears — admin confirms]
        │
        └──→ payments.status → paid
             orders.payment_status → paid
             orders.status → processing

[Admin ships]
        │
        └──→ shipments INSERT (direction=outbound)
             inventory_movements INSERT (sale)
             inventory_serials UPDATE (in_stock → sold)
             orders.status → shipped
             orders.shipped_at, shipped_by set

[Delivered — admin records manually]
        │
        └──→ orders.delivered_at, delivered_by set
             (orders.status stays shipped)
```

---

### Schema + Data

**`customers`**
```
id   name        email              phone         status
12   Robert Kim  robert@example.com  555-100-0012  active
```

**`customer_addresses`**
```
id   customer_id  label  first_name  last_name  email               phone         address_line1   address_line2  city       state  postal_code  country  is_default
12   12           Home   Robert      Kim        robert@example.com  555-100-0012  321 Elm Street  NULL           Phoenix    AZ     85001        US       true
```

**`orders`**
```
id  number        customer_id  source  status   payment_status  shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by  created_by
14  ORD-2026-014  12           phone   shipped  paid            15.00     180.00       2026-05-21 10:00      2           2026-05-23 15:00      1             1

-- billing snapshot (NULL — cheque payment, no card billing required)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (copied from customer_addresses — phone order, home delivery)
shipping_first_name  shipping_last_name  shipping_email      shipping_phone  shipping_address_line1  shipping_address_line2  shipping_city  shipping_state  shipping_postal_code  shipping_country
Robert               Kim                 robert@example.com  555-100-0012    321 Elm Street          NULL                    Phoenix        AZ              85001                 US
```

**`order_lines`**
```
id  order  product_listing_id  sku     product_name   inventory_serial_id  unit_price  tax_amount  line_total
41  14     2                   PROD-B  Widget Basic   SN-130               150.00      0.00        150.00
```

**`order_line_fees`**
```
id  order_line_id  name              amount  tax_amount  fee_total  created_by  created_at
25  41             Programming Fee    9.00   0.00         9.00      1           2026-05-20 10:00:00
26  41             Gas Tuning Fee     6.00   0.00         6.00      1           2026-05-20 10:00:00
```

**Grand total**
```
lines $150 + fees $15 + shipping $15 + tax $0 = $180 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method  amount  status  cheque_number  cheque_date
17  14        order         14          cheque  180.00  paid    CHQ-5500       2026-05-20
```

> `payments.status` transitions: `pending` (cheque recorded at call, not yet cleared) → `paid` (admin confirms cheque cleared, order advances to `processing`). Do NOT ship while `payments.status = pending` — cheque may bounce.

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking    label_cost  status     shipped_at            returned_at           delivered_at
35  order           14            outbound   UPS      UP-10014    9.00        delivered  2026-05-21 10:00  NULL                  2026-05-23 15:00
```

**`inventory_serials`**
```
serial   status  location  note
SN-130   sold    NULL      with Robert Kim
```

**`inventory_movements`**
```
id  serial   type  from         to    reference     notes
46  SN-130   sale  Warehouse A  NULL  ORD-2026-014
```

---

### Financial Summary
```
charged:   $180.00
collected: $180.00
refunded:  $0.00
net:       $180.00 ✓
```

### Shipping Margin
```
revenue:  $15.00  (orders.shipping_amount)
cost:     $9.00   (shipments.label_cost)
margin:   +$6.00
```

---
