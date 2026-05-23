> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## Example 17 — ORD-019 — Phone Back Order (Prepaid Stripe Checkout, Carrier)

**Scenario:** CSR takes a phone order for an item out of stock. Customer prepays via Stripe Checkout link. Stock arrives 3 days later — serial assigned — order advances to processing — ships — delivered.

**Customer 17: Ryan Foster**
- `customers.id = 17`, `customer_addresses.id = 18` (home address — 321 Birch Ave, Tucson AZ 85701)
- Source: `phone`
- Payment method: `stripe_checkout` (async — admin sends link, customer pays on Stripe hosted page)

---

### Data: `orders` row
```
id:                   19
number:               ORD-2026-0019
customer_id:          17
source:               phone
status:               back_ordered          ← set at creation (serial=NULL)
payment_status:       unpaid                ← set at creation
created_by:           3                     ← CSR who took the call
subtotal:             220.00                ← unit_price + tax_amount
fees:                 0.00
shipping:             20.00
grand_total:          240.00
currency:             USD
billing_*:            NULL                  ← stripe_checkout — Stripe hosted page handles billing
shipping_first_name:  Ryan
shipping_last_name:   Foster
shipping_email:       ryan.foster@email.com
shipping_phone:       520-555-0192
shipping_address_line1: 321 Birch Ave
shipping_city:        Tucson
shipping_state:       AZ
shipping_postal_code: 85701
shipping_country:     US
shipped_at:           NULL → 2026-05-20
shipped_by:           NULL → 5
delivered_at:         NULL → 2026-05-22
delivered_by:         NULL → 3
cancelled_at:         NULL
cancelled_by:         NULL
created_at:           2026-05-16 10:00:00
```

> Billing snapshot NULL — `stripe_checkout` Stripe hosted page captures billing. Phone source requires shipping snapshot (CSR collects address over the phone).

---

### Data: `order_lines` row
```
id:                   46
order_id:             19
sku:                  HDMI-4K-PRO
product_name:         4K HDMI Cable Pro 6ft
inventory_serial_id:  NULL              ← back-ordered — no serial yet
unit_price:           200.00
tax_rate:             0.0825
tax_amount:           16.50
line_total:           216.50
```

> `inventory_serial_id` NULL at creation = back-ordered line. Set to SN-153 when stock arrives from PO-2026-012.

---

### Data: `order_fees` — none
```
(no rows — order has no service fee)
```

---

### Data: `payments` row
```
id:                       22
order_id:                 19
payable_type:             order
payable_id:               19
method:                   stripe_checkout
amount:                   240.00
status:                   pending           ← created pending; webhook sets → paid
created_by:               3
currency:                 USD
stripe_checkout_session_id: cs_live_abc789xyz
stripe_payment_intent_id: NULL              ← stripe_checkout: no intent/charge IDs at creation
stripe_charge_id:         NULL
cash_received_at:         NULL
cheque_number:            NULL
paid_at:                  NULL → 2026-05-16 11:30:00   ← set by webhook on checkout.session.completed
paid_by:                  NULL              ← webhook-confirmed, no admin action
created_at:               2026-05-16 10:05:00
```

> `stripe_checkout` is async: payment row created with `status=pending` immediately after order. Webhook fires `checkout.session.completed` → `payments.status → paid` → `orders.payment_status → paid`. Order stays `back_ordered` (serial still NULL).

---

### Data: `inventory_movements` rows (2 total)
```
-- Movement 1: serial assigned from arriving PO stock
id:           53
serial_id:    153  (SN-153)
type:         back_order_fill      ← serial assigned to fulfill back order
order_line_id: 46
from_location: PO-2026-012         ← arriving purchase order
to_location:   ORDER-019           ← assigned to order
created_by:   3
created_at:   2026-05-19 09:15:00

-- Movement 2: sale movement when order ships
id:           54
serial_id:    153
type:         sale
order_line_id: 46
from_location: Warehouse A
to_location:   NULL                ← leaves inventory
created_by:   5                    ← warehouse staff who shipped
created_at:   2026-05-20 08:30:00
```

---

### Data: `shipments` row
```
id:                   41
shippable_type:       order
shippable_id:         19
customer_address_id:  18            ← Ryan's home address
direction:            outbound
carrier:              FedEx
tracking:             7489234721034
label_cost:           9.00
status:               pending → in_transit → delivered
created_by:           5
shipped_at:           2026-05-20 08:30:00
returned_at:          NULL
delivered_at:         2026-05-22 14:00:00
delivered_by:         3
created_at:           2026-05-20 08:00:00
```

---

### Back Order State Timeline
```
2026-05-16 10:00  order created         status=back_ordered, payment_status=unpaid,  serial=NULL
2026-05-16 10:05  checkout link sent    status=back_ordered, payment_status=unpaid,  serial=NULL
2026-05-16 11:30  webhook — Ryan paid   status=back_ordered, payment_status=paid,    serial=NULL
2026-05-19 09:15  serial assigned       status=processing,   payment_status=paid,    serial=SN-153
2026-05-20 08:30  admin ships           status=shipped
2026-05-22 14:00  delivered             delivered_at set
```

> `back_ordered` trigger: serial=NULL at creation (not payment). Payment advances `payment_status` independently. `processing` only when BOTH `payment_status=paid` AND all serials set.

---

### Financial Summary
```
charged:   $240.00
collected: $240.00  (Stripe Checkout — prepaid 90 min after order)
refunded:  $0.00
net:       $240.00 ✓
```

### Shipping Margin
```
revenue:   $20.00   (orders.shipping)
cost:      -$9.00   (shipments.label_cost)
margin:    +$11.00
```

---
