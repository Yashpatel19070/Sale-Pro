## Example 2 — ORD-002 — Clean Cash Order

**Scenario:** Mike Torres walks in. Buys two items. Pays full cash at counter. Wants home delivery — provides address at counter. Admin records address, payment, ships via FedEx. No issues.

---

### Data Flow

```
[Customer walks in — admin creates order]
        │
        ├──→ customer_addresses INSERT (Mike gives home address for delivery)
        ├──→ orders (customer_id=2, billing snapshot NULL — cash, shipping snapshot copied from address, status=pending, payment_status=unpaid)
        ├──→ order_lines (2 line items)
        └──→ order_fees (service fee)

[Customer pays cash in full at counter — admin records]
        │
        └──→ payments INSERT (status=paid, cash_received_at=now)
             orders.payment_status → paid
             orders.status → processing

[Admin ships]
        │
        └──→ shipments INSERT (direction=outbound)
             inventory_movements INSERT (sale × 2)
             inventory_serials UPDATE × 2 (in_stock → sold)
             orders.status → shipped
             orders.shipped_at, shipped_by set

[Delivered — carrier confirms, admin records manually]
        │
        └──→ orders.delivered_at, delivered_by set
             (orders.status stays shipped — no status change)
```

---

### Schema + Data

**`customers`**
```
id  name        email             phone         status
2   Mike Torres mike@example.com  555-100-0002  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email             phone         address_line1   address_line2  city     state  postal_code  country  is_default
2   2            Home   Mike        Torres     mike@example.com  555-100-0002  456 Oak Avenue  NULL           Houston  TX     77001        US       true
```

**`orders`**
```
id  number        customer_id  source   status     payment_status  subtotal  fees   shipping  grand_total
2   ORD-2026-002  2            walk_in  shipped    paid            350.00    30.00  15.00     395.00

-- billing snapshot (NULL — cash payment, no billing address required)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (copied from customer_addresses at order creation — delivery required)
shipping_first_name  shipping_last_name  shipping_email    shipping_phone  shipping_address_line1  shipping_address_line2  shipping_city  shipping_state  shipping_postal_code  shipping_country
Mike                 Torres              mike@example.com  555-100-0002    456 Oak Avenue          NULL                    Houston        TX              77001                 US
```

Billing NULL — cash payment, no billing address required. Shipping filled — walk-in customer provided home address for FedEx delivery. Both NULL not allowed when a carrier shipment exists.

**`order_lines`**
```
id  order  sku     product_name  serial  unit_price  tax_rate  tax_amount  line_total
2   2      PROD-A  Widget Pro    SN-010  200.00      0.0000    0.00        200.00
3   2      PROD-B  Widget Basic  SN-011  150.00      0.0000    0.00        150.00
```

**`order_fees`**
```
id  order  name          amount
2   2      Service Fee   30.00
```

**Grand total**
```
subtotal $350 (200+150) + fees $30 + shipping $15 + tax $0 = $395 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method  amount  status  cash_received_at
2   2         order         2           cash    395.00  paid    2026-04-21 10:30
```

One row. Full amount. Cash in hand before order processes.

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking   label_cost  status     shipped_at            returned_at           delivered_at
2   order           2             outbound   FedEx    FX-10002   12.00       delivered  2026-04-21 11:00  NULL                  2026-04-23 11:00
```

**`inventory_serials`**
```
serial  status  location  note
SN-010  sold    NULL      with Mike Torres
SN-011  sold    NULL      with Mike Torres
```

**`inventory_movements`**
```
id  serial  type  from         to    reference     notes
2   SN-010  sale  Warehouse A  NULL  ORD-2026-002
3   SN-011  sale  Warehouse A  NULL  ORD-2026-002
```

---

### Financial Summary
```
charged:   $395.00
collected: $395.00
refunded:  $0.00
net:       $395.00 ✓
```

### Shipping Margin
```
revenue:  $15.00  (orders.shipping_amount)
cost:     $12.00  (shipments.label_cost)
margin:   +$3.00
```

---
