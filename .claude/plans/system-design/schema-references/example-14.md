## Example 14 — ORD-016 — Return to Sender, Re-shipped

**Scenario:** Marcus Rivera orders one item online, Stripe card, home delivery. Admin ships via FedEx (FX-10016). FedEx attempts delivery twice — apartment complex, no building access, no safe drop. Package returned to warehouse. `orders.status → rts`. Admin contacts Marcus — Marcus gives work address. Admin adds work address to `customer_addresses`, updates shipping snapshot, re-ships via FedEx (FX-10017). Delivered successfully. Re-ship label cost absorbed internally.

> **Serial stays `sold` throughout.** RTS is a logistics event — ownership did not change. No `return_in` movement, no serial status change.

> **`rts` is not terminal.** Order re-enters `shipped` after re-ship. Status flow: `processing → shipped → rts → shipped`.

> **Shipping snapshot vs billing snapshot:**
> - Billing snapshot — tied to the Stripe charge. Immutable. Never changes regardless of RTS or re-ship.
> - Shipping snapshot — operational ("where does this order ship to"). Updated by admin after RTS before re-ship. If left as old address, system pre-fills wrong address on new label — error-prone. Admin explicitly corrects it so shipment #39 label is accurate.
> - Audit trail preserved via shipment #38 (`status=returned`) + activity log. The RTS event is not lost.

---

### Data Flow

```
[Admin creates order — Marcus pays online]
        │
        ├──→ orders (customer_id=14, billing + shipping filled from home address, status=pending)
        ├──→ order_lines (1 line item, SN-150)
        ├──→ order_fees (service fee)
        └──→ payments INSERT #19 (stripe_card, status=paid, stripe_payment_intent_id, stripe_charge_id)
             orders.payment_status → paid, orders.status → processing

[Admin ships — 2026-05-20]
        │
        └──→ shipments INSERT #38 (outbound, FedEx FX-10016, to home address)
             inventory_movements INSERT #48 (sale, SN-150 Warehouse A → NULL)
             inventory_serials UPDATE: in_stock → sold
             orders.status → shipped, shipped_at=2026-05-20 10:00, shipped_by=2

[FedEx attempts delivery twice — failed — 2026-05-22, 2026-05-23]
        │
        └──→ (carrier events — external, no DB changes)

[Package arrives back at warehouse — 2026-05-26]
        │
        └──→ shipments UPDATE #38: status → returned
             orders.status → rts
             (serial stays sold — RTS is not a return, no inventory movement)

[Admin contacts Marcus — Marcus gives work address — 2026-05-26]
        │
        └──→ customer_addresses INSERT id=15 (work address)
             (orders snapshot stays as home — original intent preserved, never changed)

[Admin re-ships — 2026-05-27]
        │
        └──→ shipments INSERT #39 (outbound, FedEx FX-10017, customer_address_id=15 — work)
             orders.status → shipped
             (no new inventory movement — serial already recorded as sold)

[Delivered — admin records — 2026-05-29]
        │
        └──→ orders.delivered_at=2026-05-29 14:00, delivered_by=1
             (orders.status stays shipped)
```

---

### Schema + Data

**`customers`**
```
id   name           email                phone         status
14   Marcus Rivera  marcus@example.com   555-100-0014  active
```

**`customer_addresses`**
```
id   customer_id  label  first_name  last_name  email                phone         address_line1      city    state  postal_code  country  is_default
14   14           Home   Marcus      Rivera     marcus@example.com   555-100-0014  200 Maple Ave #4B  Austin  TX     78704        US       true
15   14           Work   Marcus      Rivera     marcus@example.com   555-100-0014  900 Commerce St    Austin  TX     78701        US       false
```

> `id=14` (Home) — existed at order creation, used for billing + shipping snapshot (both immutable). `id=15` (Work) — added after RTS. Referenced via `shipments.customer_address_id=15` on re-ship — order snapshot not changed.

**`orders`**
```
id  number        customer_id  source  status   payment_status  subtotal  fees   shipping  grand_total  shipped_at            shipped_by  delivered_at          delivered_by
16  ORD-2026-016  14           online  shipped  paid            200.00    20.00  20.00     240.00       2026-05-20 10:00      2           2026-05-29 14:00      1

-- billing snapshot (home address — tied to Stripe charge, never changes)
billing_first_name  billing_last_name  billing_email        billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
Marcus              Rivera             marcus@example.com   555-100-0014   200 Maple Ave #4B      Austin        TX             78704                US

-- shipping snapshot (home address — original intent at order creation, never changes)
shipping_first_name  shipping_last_name  shipping_email       shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
Marcus               Rivera              marcus@example.com   555-100-0014    200 Maple Ave #4B       Austin         TX              78704                 US
```

> Billing and shipping snapshots both immutable — frozen at order creation. Actual delivery address per shipment attempt tracked via `shipments.customer_address_id` FK. `shipped_at` stays 2026-05-20 — records when admin first shipped. Re-ship date tracked via shipment #39's own `shipped_at`.

**`order_lines`**
```
id  order  sku     product_name  serial   unit_price  tax_rate  tax_amount  line_total
43  16     PROD-A  Widget Pro    SN-150   200.00      0.0000    0.00        200.00
```

**`order_fees`**
```
id  order  name          amount
23  16     Service Fee   20.00
```

**Grand total**
```
subtotal $200 + fees $20 + shipping $20 + tax $0 = $240 ✓
```

**`payments`**
```
id  order_id  payable_type  payable_id  method       amount  status  stripe_payment_intent_id  stripe_charge_id
19  16        order         16          stripe_card  240.00  paid    pi_xxx                    ch_xxx
```

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking    label_cost  status     shipped_at            returned_at           delivered_at      customer_address_id
38  order           16            outbound   FedEx    FX-10016    8.50        returned   2026-05-20 10:00      2026-05-24 14:00  NULL              14   ← home (failed attempt)
39  order           16            outbound   FedEx    FX-10017    8.50        delivered  2026-05-27 09:00  NULL                  2026-05-29 14:00  15   ← work (delivered)
```

> `customer_address_id` FK → `customer_addresses` — records exact address used for each shipment attempt. Shipment #38 → home (id=14, failed). Shipment #39 → work (id=15, delivered). Full audit trail without mutating `orders.shipping_snapshot`.
> Both shipments are `shippable_type=order, shippable_id=16` — same order, two attempts.

> **Shipment status `returned`:** package physically back at sender's warehouse after carrier failed all delivery attempts. Distinct from `voided` (label never used) and `in_transit` (in carrier network).

**`inventory_serials`**
```
serial   status  location  note
SN-150   sold    NULL      with Marcus Rivera
```

> Status never changes after sale. RTS does not affect ownership or serial status.

**`inventory_movements`**
```
id  serial   type  from         to    reference      notes
48  SN-150   sale  Warehouse A  NULL  ORD-2026-016
```

> One movement only. No second movement for re-ship — unit was already recorded as sold when first shipped. Physical transit back and forth is a carrier event, not an inventory event.

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
revenue:            $20.00   (orders.shipping_amount)
first label cost:   -$8.50   (shipments #38 — returned, cost not recoverable)
re-ship label cost: -$8.50   (shipments #39 — absorbed, customer not recharged)
margin:             +$3.00
```
