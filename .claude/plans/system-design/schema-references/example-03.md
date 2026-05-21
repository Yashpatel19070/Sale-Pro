## Example 3 — ORD-004 — Flow A: No Fault, Unit Returned

**Scenario:** Karen White walks into the store. Pays cash at counter. Asks for delivery to her home — admin fills shipping address and ships. Delivered. Karen complains screen is flickering. Ships unit back. Tech examines — no defect found. Unit returned to Karen. No replacement, no refund.

> **Note — cash + delivery:** Karen is `source=walk_in` (paid cash at counter) but chose home delivery. Cash payment ≠ store pickup. These are independent decisions. Billing snapshot is NULL (cash — no card billing address needed). Shipping snapshot is filled (delivery to home requested). This pattern applies to any walk-in customer who wants items shipped.

---

### Data Flow

```
[Karen walks in — admin creates order]
        │
        ├──→ orders (customer_id=3, billing NULL—cash, shipping snapshot filled—delivery to home, status=pending, payment_status=unpaid)
        ├──→ order_lines (1 line item)
        └──→ order_fees (service fee)

[Karen pays cash at counter — admin records]
        │
        └──→ payments INSERT (status=paid, cash_received_at=2026-04-23 08:00)
             orders.payment_status → paid
             orders.status → processing

[Admin ships same day — Ali Hassan (Warehouse)]
        │
        └──→ shipments INSERT id=4 (direction=outbound, FedEx FX-10004)
             inventory_movements INSERT id=5 (sale, Warehouse A → NULL)
             inventory_serials UPDATE (in_stock → sold)
             orders.status → shipped
             orders.shipped_at = 2026-04-23 09:00, shipped_by = 2

[Delivered Apr 25 — carrier confirms, admin records manually]
        │
        └──→ orders.delivered_at = 2026-04-25 12:00, delivered_by = 1
             (orders.status stays shipped — no status change)

[Raj Patel (CS) logs complaint — screen flickering]
        │
        └──→ complaints INSERT (status=open, created_by=4)
             inventory_serials UPDATE (sold → expected_return)

[Karen ships unit back — Apr 30, prepaid label]
        │
        └──→ shipments INSERT id=13 (direction=inbound, FedEx FX-20002)
             inventory_movements INSERT id=18 (return_in, NULL → Receiving Area)
             inventory_serials UPDATE (expected_return → under_examination)
             complaints.status → in_progress

[Sam Chen (Tech) examines — no fault found]
        │
        └──→ inventory_movements INSERT (transfer, Receiving Area → Tech Area)
             inventory_movements INSERT (transfer, Tech Area → Shipping Area)
             complaints.examination_result → no_fault_found
             complaints.examined_by = 3, examination_notes set

[Admin closes complaint — unit returned to Karen, May 2]
        │
        └──→ shipments INSERT id=17 (direction=outbound, FedEx FX-30002)
             inventory_movements INSERT id=34 (adjustment, Shipping Area → NULL)
             inventory_serials UPDATE (under_examination → sold)
             complaints.status → closed
             complaints.unit_outcome → returned_to_customer
             complaints.closed_at = 2026-05-02, closed_by = 1
```

---

### Schema + Data

**`customers`**
```
id  name         email              phone         status
3   Karen White  karen@example.com  555-100-0003  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email              phone         address_line1   address_line2  city    state  postal_code  country  is_default
3   3            Home   Karen       White      karen@example.com  555-100-0003  456 Oak Avenue  NULL           Dallas  TX     75201        US       true
```

**`orders`**
```
id  number        customer_id  source   status   payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
4   ORD-2026-004  3            walk_in  shipped  paid            200.00    30.00  20.00     250.00       2026-04-23 09:00      2           2026-04-25 12:00      1

-- billing snapshot (NULL — cash payment, no card billing address)
billing_first_name  billing_last_name  billing_email  billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NULL                NULL               NULL           NULL           NULL                   NULL          NULL           NULL                 NULL

-- shipping snapshot (walk_in + delivery requested — Karen provided home address at counter)
shipping_first_name  shipping_last_name  shipping_email     shipping_phone  shipping_address_line1  shipping_address_line2  shipping_city  shipping_state  shipping_postal_code  shipping_country
Karen                White               karen@example.com  555-100-0003    456 Oak Avenue          NULL                    Dallas         TX              75201                 US
```

**`order_lines`**
```
id  order  sku     product_name  serial  unit_price  tax_rate  tax_amount  line_total
5   4      PROD-A  Widget Pro    SN-030  200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
4   4      Service Fee   30.00
```

**Grand total**
```
subtotal $200 + fees $30 + shipping $20 + tax $0 = $250 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method  amount  status  cash_received_at
5   4         order         4           cash    250.00  paid    2026-04-23 08:00
```

**`complaints`**
```
id  number        order  line  serial  status  examination_result  unit_outcome          issue_description            unit_received_at      examined_by  examination_notes                       closed_at   closed_by  created_by  withdrawn_at          withdrawn_by
2   CMP-2026-002  4      5     SN-030  closed  no_fault_found      returned_to_customer  Screen flickering, unusable  2026-05-01 14:00      3            Unit fully functional, no defect found  2026-05-02 15:00  1          4  NULL                  NULL
```

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking   label_cost  status     shipped_at            returned_at           delivered_at
4   order           4             outbound   FedEx    FX-10004   8.50        delivered  2026-04-23 09:00  NULL                  2026-04-25 12:00
13  complaint       2             inbound    FedEx    FX-20002   7.00        delivered  2026-04-30 00:00  NULL                  2026-05-01 14:00
17  complaint       2             outbound   FedEx    FX-30002   8.50        delivered  2026-05-02 14:00  NULL                  2026-05-04 12:00
```

**`inventory_serials`**
```
serial  status  location  note
SN-030  sold    NULL      with Karen White
```

**`inventory_movements`**
```
id   serial  type       from            to              reference      notes
5    SN-030  sale       Warehouse A     NULL            ORD-2026-004
18   SN-030  return_in  NULL            Receiving Area  CMP-2026-002   returned by customer
--   SN-030  transfer   Receiving Area  Tech Area       CMP-2026-002   moved for examination
--   SN-030  transfer   Tech Area       Shipping Area   CMP-2026-002   no fault, prepping return
34   SN-030  adjustment Shipping Area   NULL            CMP-2026-002   returned to customer, no fault
```

`--` = intermediate IDs sit between id=18 and id=34 in global ledger (other orders' movements in between).

---

### Financial Summary
```
charged:   $250.00
collected: $250.00
refunded:  $0.00
net:       $250.00 ✓
```

### Shipping Margin
```
revenue:  $20.00  (orders.shipping_amount — charged to Karen)
cost:     $24.00  (shipments: $8.50 outbound + $7.00 inbound complaint + $8.50 return)
margin:   -$4.00  (absorbed — no-fault complaint handling cost)
```

---
