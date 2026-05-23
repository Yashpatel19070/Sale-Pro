> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## Example 16 — ORD-018 — Walk-in Back Order (Pay at Pickup, In-Store)

**Scenario:** Emma Clark walks in. Wants Widget Pro (PROD-A) — out of stock. Admin creates back order, no payment taken. Stock arrives 4 days later. Admin assigns serial SN-152, calls Emma. Emma comes in 2026-05-20, pays full $215 via Stripe Terminal at counter, takes unit home. No carrier involved.

---

### Data Flow

```
[Admin creates back order — Emma present at counter]
        │
        ├──→ orders INSERT (customer_id=16, source=walk_in, status=back_ordered, payment_status=unpaid,
        │                   billing=NULL, shipping=NULL — in-store pickup)
        ├──→ order_lines INSERT (inventory_serial_id=NULL, sku=PROD-A, unit_price=200.00)
        └──→ order_fees INSERT (Service Fee $15)

        (no payment taken — Emma will pay when she collects)

[2026-05-19 — stock arrives via PO]
        │
        └──→ inventory_serials INSERT (SN-152, in_stock, Warehouse A)
             inventory_movements INSERT (receive, NULL → Warehouse A, reference=PO-2026-011)

[Admin assigns SN-152 to back-ordered line, calls Emma]
        │
        └──→ order_lines.inventory_serial_id → SN-152
             payment_status=unpaid ✗ → orders.status stays back_ordered
             (serial assigned, waiting for Emma to come in and pay)

[2026-05-20 — Emma comes in, pays via Stripe Terminal]
        │
        └──→ payments INSERT (stripe_terminal, amount=215.00, status=paid)
             orders.payment_status → paid
             payment_status=paid ✓ + all serials assigned ✓
             orders.status → processing

[Emma takes unit at counter — in-store pickup]
        │
        └──→ inventory_movements INSERT (sale, Warehouse A → NULL, ORD-2026-018)
             inventory_serials UPDATE (SN-152: in_stock → sold)
             orders.status → complete
             (no shipment row — in-store pickup)
```

> `processing → complete` are two distinct counter actions: payment recorded first (processing), then unit physically handed to Emma (complete + inventory movement).

---

### Schema + Data

**`customers`**
```
id  name        email              phone         status
16  Emma Clark  emma@example.com   555-160-0001  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email              phone         address_line1  city    state  postal_code  country  is_default
17  16           Home   Emma        Clark      emma@example.com   555-160-0001  45 Pine St     Austin  TX     78702        US       true
```

**`orders`**
```
id  number        customer_id  source   status    payment_status  subtotal  fees   shipping  grand_total  created_by
18  ORD-2026-018  16           walk_in  complete  paid            200.00    15.00  0.00      215.00       1

-- billing + shipping snapshot: both NULL (Stripe Terminal card-present, in-store pickup)
```

**`order_lines`**
```
id  order  sku     product_name  serial   unit_price  tax_rate  tax_amount  line_total
45  18     PROD-A  Widget Pro    SN-152   200.00      0.0000    0.00        200.00
```

> `serial` (inventory_serial_id) was NULL from 2026-05-15 (order created) until 2026-05-19 (assigned after stock arrived). Shown as final state.

**`order_fees`**
```
id  order  name          amount
25  18     Service Fee   15.00
```

**Grand total**
```
subtotal $200 + fees $15 + shipping $0 + tax $0 = $215 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method           amount  status  stripe_terminal_reader_id  stripe_payment_intent_id  stripe_charge_id  created_by
21  18        order         18          stripe_terminal  215.00  paid    reader_01                  pi_xxx                    ch_xxx            1
```

> Payment at pickup — 5 days after order creation. Serial already assigned when Emma arrived — payment was the final condition to advance to `processing`.

**No shipment row** — in-store pickup, no carrier involved.

**`inventory_serials`**
```
serial   status  location  note
SN-152   sold    NULL      with Emma Clark — back order pickup 2026-05-20
```

**`inventory_movements`**
```
id  serial   type     from         to           reference      notes
51  SN-152   receive  NULL         Warehouse A  PO-2026-011    back order stock arrived
52  SN-152   sale     Warehouse A  NULL         ORD-2026-018
```

---

### Back Order State Timeline

```
2026-05-15  order created       status=back_ordered, payment_status=unpaid,  serial=NULL
2026-05-19  serial assigned     status=back_ordered, payment_status=unpaid,  serial=SN-152
2026-05-20  Emma pays terminal  status=processing,   payment_status=paid,    serial=SN-152
2026-05-20  Emma takes unit     status=complete
```

---

### Financial Summary
```
charged:   $215.00
collected: $215.00  (Stripe Terminal — paid at pickup, 5 days after order)
refunded:  $0.00
net:       $215.00 ✓
```

> No shipping margin — in-store pickup, no label generated.

---
