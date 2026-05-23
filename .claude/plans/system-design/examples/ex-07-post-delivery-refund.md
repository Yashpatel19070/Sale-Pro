> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## Example 7 — ORD-009 — Post-Delivery Return, Full Refund

**Post-delivery return policy:**
```
Customer requests refund
→ Admin: "Send items back first"
→ Items arrive → inspection

Good condition → full refund processed
Damaged / signs of use → 20–30% restocking fee deducted, remainder refunded

Refund amount is 100% manual admin decision — no auto-calculation enforced.
Admin enters any amount based on reason, condition, and judgment.
```

**Scenario:** Amanda Taylor orders online. Pays Stripe card. Both items delivered Apr 30. Amanda contacts support — items not as described. Admin asks her to ship items back first. Both arrive May 4 — inspection confirms good condition. Full $330 refunded. Both serials back to stock. No complaints created — this is a return, not a defect report.

---

### Data Flow

```
[Amanda orders online]
        │
        ├──→ orders (customer_id=7, billing + shipping snapshot filled)
        ├──→ order_lines (2 lines: SN-080, SN-081)
        └──→ order_fees (service fee $30)

[Amanda pays via Stripe card — sync]
        │
        └──→ payments INSERT #12 (status=paid, stripe_payment_intent_id, stripe_charge_id)
             orders.payment_status → paid
             orders.status → processing

[Ali ships both items together]
        │
        └──→ shipments INSERT #9 (order/9/outbound, FX-10009)
             inventory_movements INSERT #13 (SN-080 sale)
             inventory_movements INSERT #14 (SN-081 sale)
             SN-080, SN-081 → sold
             orders.status → shipped, shipped_at set, shipped_by = Ali (user_id=2)

[Delivered 2026-04-30 — carrier confirms, admin records manually]
        │
        └──→ orders.delivered_at set
             (orders.status stays shipped — no status change)

[Amanda contacts support — items not as described]
        │
        └──→ Admin: "Please ship items back, we will inspect and process refund"
             No refund yet — items must arrive first

[Amanda ships both items back — her own label]
        │
        └──→ shipments INSERT #32 (order/9/inbound, UP-50009, label_cost=0)
             SN-080, SN-081 status → expected_return

[Both items arrive 2026-05-04 — inspection passes]
        │
        ├──→ inventory_movements INSERT #36 (SN-080 return_in)
        ├──→ inventory_movements INSERT #37 (SN-081 return_in)
        │    SN-080, SN-081 → in_stock (Warehouse A)
        │
        └──→ Inspection: good condition — admin enters full refund amount
             refunds INSERT REF-005 ($330, stripe, processed)
             orders.status → refunded, cancelled_at = 2026-05-04, cancelled_by = 1
```

---

### Schema + Data

**`customers`**
```
id  name           email               phone         status
7   Amanda Taylor  amanda@example.com  555-100-0007  active
```

**`customer_addresses`**
```
id  customer_id  label  first_name  last_name  email               phone         address_line1  city     state  postal_code  country  is_default
7   7            Home   Amanda      Taylor     amanda@example.com  555-100-0007  890 Maple Dr   Phoenix  AZ     85001        US       true
```

**`orders`**
```
id  number        customer_id  source  status     payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by  cancelled_at         cancelled_by
9   ORD-2026-009  7            online  refunded   paid            300.00    30.00  0.00      330.00       2026-04-28 10:00      2           2026-04-30 12:00      1             2026-05-04 12:00     1

-- billing snapshot (stripe_card, card-not-present → billing filled)
billing_first_name  billing_last_name  billing_email       billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
Amanda              Taylor             amanda@example.com  555-100-0007   890 Maple Dr           Phoenix       AZ             85001                US

-- shipping snapshot (online + delivery → same address as billing)
shipping_first_name  shipping_last_name  shipping_email      shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
Amanda               Taylor              amanda@example.com  555-100-0007    890 Maple Dr            Phoenix        AZ              85001                 US
```

`cancelled_at=2026-05-04` — reused as refund event timestamp. Set after inspection confirms good condition, not at request time.
`payment_status=paid` stays — refund tracked in `refunds` table separately.
`shipping=0.00` — free shipping, nothing to refund on shipping.

**`order_lines`**
```
id  order  sku     product_name  serial  unit_price  tax_rate  tax_amount  line_total
13  9      PROD-A  Widget Pro    SN-080  200.00      0.0000    0.00        200.00
14  9      PROD-B  Widget Basic  SN-081  100.00      0.0000    0.00        100.00
```

**`order_fees`**
```
id  order  name          amount
9   9      Service Fee   30.00
```

**Grand total**
```
subtotal $300 (200+100) + fees $30 + shipping $0 + tax $0 = $330 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method       amount  status  stripe_payment_intent_id  stripe_charge_id
12  9         order         9           stripe_card  330.00  paid    pi_xxx                    ch_xxx
```

**`refunds`**
```
id  number   order  type   payable  amount  ship_refund  method  reason                                                status
5   REF-005  9         order  9        330.00  0.00         stripe  Post-delivery return — inspection passed, full refund  processed
```

Refund issued after inspection, not at request time.
`amount=330.00` — admin entered full amount: good condition confirmed, no restocking fee.
`ship_refund=0.00` — shipping was $0, nothing to refund.

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking   label_cost  status     shipped_at            returned_at           delivered_at
9   order           9             outbound   FedEx    FX-10009   10.00       delivered  2026-04-28 10:00  NULL                  2026-04-30 12:00
32  order           9             inbound    UPS      UP-50009    0.00       delivered  2026-05-02 00:00  NULL                  2026-05-04 10:00
```

Both `shippable_type=order` — no complaint, no replacement involved.
id=32 `label_cost=0` — Amanda used her own return label.

**`inventory_serials`**
```
serial  status    location     note
SN-080  in_stock  Warehouse A  ORD-2026-009 — return inspected, good condition, back to stock
SN-081  in_stock  Warehouse A  ORD-2026-009 — return inspected, good condition, back to stock
```

**`inventory_movements`**
```
id   serial  type       from            to              reference      notes
13   SN-080  sale       Warehouse A     NULL            ORD-2026-009
14   SN-081  sale       Warehouse A     NULL            ORD-2026-009
36   SN-080  return_in  NULL            Receiving Area  ORD-2026-009   package arrives at dock — visual inspection at receiving
37   SN-081  return_in  NULL            Receiving Area  ORD-2026-009   package arrives at dock — visual inspection at receiving
--   SN-080  transfer   Receiving Area  Warehouse A     ORD-2026-009   good condition confirmed, back to stock
--   SN-081  transfer   Receiving Area  Warehouse A     ORD-2026-009   good condition confirmed, back to stock
```

Visual check happens at Receiving Area (not Tech Area) — no technician involved, warehouse staff inspect condition at dock.
Transfer → Warehouse A once condition confirmed. No Tech Area step for order-level returns.
Reference = `ORD-2026-009` (no complaint number) — return is against the order directly.

---

### Financial Summary
```
charged:   $330.00   (1 payment row)
collected: $330.00
refunded:  $330.00   (REF-005 — inspection passed, admin entered full amount)
net:       $0.00     (fully reversed)
```

### Shipping Margin
```
revenue:  $0.00   (shipping_amount=0 — free shipping)
cost:     $10.00  (id=9 outbound $10 + id=32 $0 Amanda's own label)
margin:   -$10.00 (absorbed)
```

---
