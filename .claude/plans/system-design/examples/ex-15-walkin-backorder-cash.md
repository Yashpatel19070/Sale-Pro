> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## Example 15 — ORD-017 — Walk-in Back Order (Prepaid Cash, Carrier)

**Scenario:** James Wilson walks in. Wants Widget Max (PROD-B) — out of stock. Admin creates back order. James pays full $245 cash upfront same visit. Stock arrives 5 days later via PO. Admin assigns serial SN-151 to the line. Order advances to processing. Admin ships via FedEx. Delivered.

---

### Data Flow

```
[Admin creates back order — James present at counter]
        │
        ├──→ orders INSERT (customer_id=15, source=walk_in, status=back_ordered, payment_status=unpaid,
        │                   billing=NULL — cash, shipping snapshot filled from address)
        ├──→ order_lines INSERT (inventory_serial_id=NULL, sku=PROD-B, unit_price=200.00)
        └──→ order_fees INSERT (Service Fee $20)

[James pays cash upfront — same visit]
        │
        └──→ payments INSERT (cash, amount=245.00, status=paid, cash_received_at=2026-05-14 11:00)
             orders.payment_status → paid
             (orders.status stays back_ordered — serial still NULL, stock not in)

[2026-05-19 — stock arrives via PO, new serial added to inventory]
        │
        └──→ inventory_serials INSERT (SN-151, in_stock, Warehouse A)
             inventory_movements INSERT (receive, NULL → Warehouse A, reference=PO-2026-010)

[Admin assigns SN-151 to back-ordered line]
        │
        └──→ order_lines.inventory_serial_id → SN-151
             payment_status=paid ✓ + all serials assigned ✓
             orders.status → processing

[Admin ships 2026-05-20]
        │
        └──→ shipments INSERT (order/17/outbound, FedEx FX-10017)
             inventory_movements INSERT (sale, Warehouse A → NULL, ORD-2026-017)
             inventory_serials UPDATE (SN-151: in_stock → sold)
             orders.status → shipped
             orders.shipped_at = 2026-05-20 09:00, shipped_by = 2

[Delivered 2026-05-22 — admin records manually]
        │
        └──→ orders.delivered_at = 2026-05-22 14:00, delivered_by = 1
             (orders.status stays shipped)
```

---

### Schema + Data

**`customers`**
```
id  name          email               phone         status
15  James Wilson  james@example.com   555-150-0001  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email              phone         address_line1  city    state  postal_code  country  is_default
16  15           Home   James       Wilson     james@example.com  555-150-0001  88 Oak Ave     Dallas  TX     75201        US       true
```

**`orders`**
```
id  number        customer_id  source   status   payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
17  ORD-2026-017  15           walk_in  shipped  paid            200.00    20.00  25.00     245.00       2026-05-20 09:00      2           2026-05-22 14:00      1

-- billing snapshot
NULL — cash payment, no billing snapshot required

-- shipping snapshot (filled at order creation)
shipping_first_name  shipping_last_name  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
James                Wilson              88 Oak Ave               Dallas         TX              75201                 US
```

One row in DB — split for readability.

**`order_lines`**
```
id  order  sku     product_name  serial   unit_price  tax_rate  tax_amount  line_total
44  17     PROD-B  Widget Max    SN-151   200.00      0.0000    0.00        200.00
```

> `serial` (inventory_serial_id) was NULL from 2026-05-14 (order creation) until 2026-05-19 (serial assigned after stock arrived). Shown here as final assigned state.

**`order_fees`**
```
id  order  name          amount
24  17     Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $25 + tax $0 = $245 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method  amount  status  cash_received_at       created_by
20  17        order         17          cash    245.00  paid    2026-05-14 11:00       1
```

> Payment collected at order creation — same counter visit. `orders.status` was already `back_ordered` at creation — payment updates `payment_status` only.

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking    label_cost  status     shipped_at            delivered_at          created_by
40  order           17            outbound   FedEx    FX-10017    9.00        delivered  2026-05-20 09:00      2026-05-22 14:00      2
```

**`inventory_serials`**
```
serial   status  location  note
SN-151   sold    NULL      with James Wilson — back order fulfilled 2026-05-20
```

**`inventory_movements`**
```
id  serial   type     from         to           reference      notes
49  SN-151   receive  NULL         Warehouse A  PO-2026-010    back order stock arrived
50  SN-151   sale     Warehouse A  NULL         ORD-2026-017
```

> Two movements for SN-151: `receive` when PO stock arrived (2026-05-19), `sale` when order shipped (2026-05-20). The receive movement references the PO, the sale references the order.

---

### Back Order State Timeline

```
2026-05-14  order created     status=back_ordered, payment_status=unpaid,  serial=NULL
2026-05-14  cash payment      status=back_ordered, payment_status=paid,    serial=NULL
2026-05-19  serial assigned   status=processing,   payment_status=paid,    serial=SN-151
2026-05-20  admin ships       status=shipped
2026-05-22  delivered         delivered_at set
```

---

### Financial Summary
```
charged:   $245.00
collected: $245.00  (cash — prepaid at order creation, 6 days before shipment)
refunded:  $0.00
net:       $245.00 ✓
```

### Shipping Margin
```
revenue:  $25.00  (orders.shipping)
cost:     $9.00   (shipments.label_cost)
margin:   +$16.00
```

---
