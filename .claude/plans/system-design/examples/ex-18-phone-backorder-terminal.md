> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## Example 18 — ORD-020 — Phone Back Order (Pay When Stock Arrives, Stripe Terminal)

**Scenario:** CSR takes a phone order for an out-of-stock item. No payment at creation. Stock arrives 4 days later — admin calls customer, collects payment via Stripe Terminal — serial assigned — both conditions met — ships — delivered.

**Customer 18: Marcus Webb**
- `customers.id = 18`, `customer_addresses.id = 19` (home — 88 Maple St, Denver CO 80201)
- Source: `phone`
- Payment method: `stripe_terminal` (admin reads card over terminal after stock arrives)

---

### Data: `orders` row
```
id:                   20
number:               ORD-2026-0020
customer_id:          18
source:               phone
status:               back_ordered          ← set at creation (serial=NULL)
payment_status:       unpaid                ← no payment at creation
created_by:           3
subtotal:             162.38                ← sum of order_lines.line_total
fees:                 10.00
shipping:             15.00
grand_total:          187.38
currency:             USD
billing_*:            NULL                  ← stripe_terminal — card-present, no billing entry
shipping_first_name:  Marcus
shipping_last_name:   Webb
shipping_email:       marcus.webb@email.com
shipping_phone:       720-555-0147
shipping_address_line1: 88 Maple St
shipping_city:        Denver
shipping_state:       CO
shipping_postal_code: 80201
shipping_country:     US
shipped_at:           NULL → 2026-05-21
shipped_by:           NULL → 5
delivered_at:         NULL → 2026-05-23
delivered_by:         NULL → 3
cancelled_at:         NULL
cancelled_by:         NULL
created_at:           2026-05-17 14:00:00
```

> Billing snapshot NULL — `stripe_terminal` card-present, no manual billing entry. Shipping snapshot filled — CSR collects address over phone at order creation.

---

### Data: `order_lines` row
```
id:                   47
order_id:             20
sku:                  USB-C-HUB-7
product_name:         7-Port USB-C Hub
inventory_serial_id:  NULL              ← back-ordered — no serial yet
unit_price:           150.00
tax_rate:             0.0825
tax_amount:           12.38             ← 150.00 × 0.0825 = 12.375 → rounded
line_total:           162.38
```

> `inventory_serial_id` NULL at creation. Set to SN-154 when stock arrives from PO-2026-013.

---

### Data: `order_fees` row
```
id:        26
order_id:  20
name:      Service Fee
amount:    10.00
```

---

### Data: `payments` row — inserted when stock arrives and admin calls customer
```
id:                         23
order_id:                   20
payable_type:               order
payable_id:                 20
method:                     stripe_terminal
amount:                     187.38
status:                     paid              ← instant-paid (card-present)
created_by:                 3                 ← CSR who called Marcus back
currency:                   USD
stripe_terminal_reader_id:  tmr_abc123
stripe_payment_intent_id:   pi_xyz789
stripe_charge_id:           ch_xyz789
stripe_checkout_session_id: NULL
cash_received_at:           NULL
cheque_number:              NULL
paid_at:                    NULL              ← instant-paid: use created_at
paid_by:                    NULL              ← instant-paid: no separate confirmation step
created_at:                 2026-05-21 09:00:00
```

> No payment row at order creation — `payment_status=unpaid` with no payments record until stock arrives. `stripe_terminal` = instant paid; `paid_at` / `paid_by` stay NULL — `created_at` is the payment timestamp.

---

### Data: `inventory_movements` rows (2 total)
```
-- Movement 1: serial assigned from arriving PO stock
id:            55
serial_id:     154  (SN-154)
type:          back_order_fill
order_line_id: 47
from_location: PO-2026-013
to_location:   ORDER-020
created_by:    3
created_at:    2026-05-21 08:45:00

-- Movement 2: sale movement when order ships
id:            56
serial_id:     154
type:          sale
order_line_id: 47
from_location: Warehouse A
to_location:   NULL
created_by:    5
created_at:    2026-05-21 11:00:00
```

---

### Data: `shipments` row
```
id:                   42
shippable_type:       order
shippable_id:         20
customer_address_id:  19
direction:            outbound
carrier:              UPS
tracking:             1Z999AA10123456784
label_cost:           8.50
status:               pending → in_transit → delivered
created_by:           5
shipped_at:           2026-05-21 11:00:00
returned_at:          NULL
delivered_at:         2026-05-23 15:30:00
delivered_by:         3
created_at:           2026-05-21 10:30:00
```

---

### Back Order State Timeline
```
2026-05-17 14:00  order created       status=back_ordered, payment_status=unpaid,  serial=NULL
2026-05-21 08:45  serial assigned     status=back_ordered, payment_status=unpaid,  serial=SN-154
                                      ← serial set but unpaid — stays back_ordered
2026-05-21 09:00  admin calls Marcus  payment inserted → payment_status=paid
                  stripe_terminal     status=processing    ← both conditions met
2026-05-21 11:00  admin ships         status=shipped
2026-05-23 15:30  delivered           delivered_at set
```

> Key distinction from Ex 17: serial assigned BEFORE payment (stock arrives, then admin calls customer). `status` stays `back_ordered` after serial assignment because `payment_status=unpaid`. Advances to `processing` only after payment collected — both conditions required simultaneously.

---

### Financial Summary
```
charged:   $187.38
collected: $187.38  (Stripe Terminal — paid when stock arrived, 4 days after order)
refunded:  $0.00
net:       $187.38 ✓
```

### Shipping Margin
```
revenue:   $15.00
cost:      -$8.50
margin:    +$6.50
```
