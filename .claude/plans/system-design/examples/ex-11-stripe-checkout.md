> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## Example 11 — ORD-013 — Walk-in + Stripe Checkout

**Scenario:** Diana Walsh walks into the store. Buys one Widget Pro. Doesn't have cash, doesn't have a physical card — CSR generates a Stripe checkout QR link. Diana scans it on her phone and pays online. Admin ships via FedEx to Diana's home address. No issues.

> **Why this matters:** `source=walk_in` + `method=stripe_checkout`. Billing snapshot is NULL (not card-not-present in our system — Stripe handles the billing on their hosted page). Shipping filled — delivery requested.

---

### Data Flow

```
[Customer walks in — admin creates order]
        │
        ├──→ customer_addresses INSERT (Diana provides home address)
        ├──→ orders (customer_id=11, billing NULL — checkout, shipping snapshot filled, status=pending, payment_status=unpaid)
        ├──→ order_lines (1 line item)
        └──→ order_fees (service fee)

[CSR generates Stripe checkout link — Diana scans QR on phone]
        │
        └──→ payments INSERT (status=pending, stripe_checkout_session_id=cs_xxx)
             (order stays pending until webhook fires)

[Stripe webhook fires — payment confirmed]
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
id   name         email               phone         status
11   Diana Walsh  diana@example.com   555-100-0011  active
```

**`customer_addresses`**
```
id   customer_id  label  first_name  last_name  email               phone         address_line1    address_line2  city     state  postal_code  country  is_default
11   11           Home   Diana       Walsh      diana@example.com   555-100-0011  789 Pine Street  NULL           Dallas   TX     75201        US       true
```

**`orders`**
```
id  number        customer_id  source   status   payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
13  ORD-2026-013  11           walk_in  shipped  paid            200.00    20.00  15.00     235.00       2026-05-19 11:00      2           2026-05-21 14:00      1

-- billing snapshot (NULL — stripe_checkout, Stripe handles billing on their hosted page)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (copied from customer_addresses — delivery requested)
shipping_first_name  shipping_last_name  shipping_email      shipping_phone  shipping_address_line1  shipping_address_line2  shipping_city  shipping_state  shipping_postal_code  shipping_country
Diana                Walsh               diana@example.com   555-100-0011    789 Pine Street         NULL                    Dallas         TX              75201                 US
```

**`order_lines`**
```
id  order  sku     product_name  serial   unit_price  tax_rate  tax_amount  line_total
40  13     PROD-A  Widget Pro    SN-120   200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
20  13     Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $15 + tax $0 = $235 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method           amount  status  stripe_checkout_session_id
16  13        order         13          stripe_checkout  235.00  paid    cs_xxx
```

> `payments.status` transitions: `pending` (session open, waiting for Diana to pay) → `paid` (Stripe webhook fires on successful payment). If Diana never pays and session expires: `expired` — order stays `pending`, CSR generates new link or takes alternate payment.

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking    label_cost  status     shipped_at            returned_at           delivered_at
34  order           13            outbound   FedEx    FX-10013    8.50        delivered  2026-05-19 11:00  NULL                  2026-05-21 14:00
```

**`inventory_serials`**
```
serial   status  location  note
SN-120   sold    NULL      with Diana Walsh
```

**`inventory_movements`**
```
id  serial   type  from         to    reference     notes
45  SN-120   sale  Warehouse A  NULL  ORD-2026-013
```

---

### Financial Summary
```
charged:   $235.00
collected: $235.00
refunded:  $0.00
net:       $235.00 ✓
```

### Shipping Margin
```
revenue:  $15.00  (orders.shipping_amount)
cost:     $8.50   (shipments.label_cost)
margin:   +$6.50
```

---
