> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## Example 1 — ORD-001 — Clean Stripe Card Order

**Scenario:** Sarah Johnson buys one item online. Pays full via Stripe card. Delivered. No issues.

---

### Data Flow

```
[Admin creates order]
        │
        ├──→ orders (customer_id=1, billing + shipping snapshot copied from customer_addresses, status=pending, payment_status=unpaid)
        ├──→ order_lines (1 line item)
        └──→ order_line_fees INSERT (Programming Fee $12 · Gas Tuning Fee $8)

[Customer pays via Stripe card — sync]
        │
        └──→ payments INSERT (status=paid, stripe_payment_intent_id, stripe_charge_id)
             orders.payment_status → paid
             orders.status → processing

[Admin ships]
        │
        └──→ shipments INSERT (direction=outbound)
             inventory_movements INSERT (sale)
             inventory_serials UPDATE (in_stock → sold)
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
id  name           email              phone         status
1   Sarah Johnson  sarah@example.com  555-100-0001  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email              phone         address_line1  address_line2  city    state  postal_code  country  is_default
1   1            Home   Sarah       Johnson    sarah@example.com  555-100-0001  123 Main St    NULL           Austin  TX     78701        US       true
```

**`orders`**
```
id  number        customer_id  source  status     payment_status  shipping  grand_total  created_by
1   ORD-2026-001  1            online  shipped    paid            20.00     240.00       1

-- billing snapshot (copied from customer_addresses at order creation)
billing_first_name  billing_last_name  billing_email      billing_phone  billing_address_line1  billing_address_line2  billing_city  billing_state  billing_postal_code  billing_country
Sarah               Johnson            sarah@example.com  555-100-0001   123 Main St            NULL                   Austin        TX             78701                US

-- shipping snapshot (online + delivery → same address as billing)
shipping_first_name  shipping_last_name  shipping_email     shipping_phone  shipping_address_line1  shipping_address_line2  shipping_city  shipping_state  shipping_postal_code  shipping_country
Sarah                Johnson             sarah@example.com  555-100-0001    123 Main St             NULL                    Austin         TX              78701                 US
```

One row in DB — split for readability. Address fields nullable — filled based on what customer provides.

**`order_lines`**
```
id  order  product_listing_id  sku     product_name  inventory_serial_id  unit_price  tax_amount  line_total
1   1      1                   PROD-A  Widget Pro    SN-001               200.00      0.00        200.00
```

**`order_line_fees`**
```
id  order_line_id  name              amount  tax_amount  fee_total  created_by  created_at
5   1              Programming Fee   12.00   0.00        12.00      1           2026-04-18 10:00:00
6   1              Gas Tuning Fee     8.00   0.00         8.00      1           2026-04-18 10:00:00
```

**Grand total**
```
lines $200 + fees $20 + shipping $20 + tax $0 = $240 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method       amount  status  stripe_payment_intent_id  stripe_charge_id
1   1         order         1           stripe_card  240.00  paid    pi_xxx                    ch_xxx
```

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking   label_cost  status     shipped_at            returned_at           delivered_at
1   order           1             outbound   FedEx    FX-10001   8.50        delivered  2026-04-20 09:00  NULL                  2026-04-22 14:00
```

**`inventory_serials`**
```
serial  status  location  note
SN-001  sold    NULL      with Sarah Johnson
```

**`inventory_movements`**
```
id  serial  type  from         to    reference     notes
1   SN-001  sale  Warehouse A  NULL  ORD-2026-001
```

---

### Financial Summary
```
charged:   $240.00
collected: $240.00
refunded:  $0.00
net:       $240.00 ✓
```

### Shipping Margin
```
revenue:  $20.00  (orders.shipping_amount)
cost:     $8.50   (shipments.label_cost)
margin:   +$11.50
```

---
