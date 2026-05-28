> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## Example 20 — ORD-020 — Walk-in Cash, Split Billing/Shipping, Expedited Carrier

**Scenario:** Rachel Park walks into the Houston shop. Buys one **Engine Control Module (ECM-2024)** with **Programming Fee** + **Gas Tuning Fee** (same per-line-fee pattern as ex-19). Pays $336.86 cash at counter. **Billing snapshot = Rachel's home address** (her own legal billing). **Shipping snapshot = Rachel's workshop in Dallas** — a *different* address. Pays **$50 extra for a FedEx Express label upgrade** so the programmed unit arrives next day. Staff performs programming + tuning over 55 minutes, then packs and ships via FedEx Express; admin records delivery the next afternoon.

**Key aspects of this scenario:**
- `source=walk_in` + `method=cash` + `status → shipped` (final) — cash counter sale that ships via carrier
- **Billing snapshot = CUSTOMER home address** — Rachel's own billing (not the shop, not NULL)
- **Shipping snapshot = DIFFERENT address** — Rachel's workshop in Dallas (split from billing)
- **Two `customer_addresses` rows** — one labelled `Home` (Houston), one labelled `Workshop` (Dallas). The label is a user-friendly name; *role* (billing vs shipping) is decided by which snapshot the address is copied into at order placement
- **Per-line fees with own tax** — Programming Fee ($40 + $3.30) and Gas Tuning Fee ($25 + $2.06) attached to the line
- **Expedited shipping upgrade** — `orders.shipping = 50.00` represents the FedEx Express label cost (counter-quoted upgrade)
- **AvaTax engaged on 3 lines using ship-to (Dallas) rate** — destination-based: Dallas combined rate (also 8.25%) drives unit + Programming + Gas Tuning tax
- **`shipped` is terminal for carrier orders** — `delivered_at` and `delivered_by` are recorded manually after carrier confirmation; **status does not advance to `complete`** (`complete` is pickup-only, see ex-19)

---

### Data Flow

```
[Rachel walks in — admin creates order at counter]
        │
        ├──→ customer_addresses INSERT × 2 (home billing in Houston, workshop shipping in Dallas)
        ├──→ orders INSERT (customer_id=20, source=walk_in, status=pending, payment_status=unpaid,
        │                   billing snapshot = Rachel's Houston home,
        │                   shipping snapshot = Rachel's Dallas workshop,
        │                   shipping=50.00 — FedEx Express upgrade quoted at counter)
        ├──→ order_lines INSERT (1 line: ECM-2024, SN-201)
        └──→ order_line_fees INSERT (Programming Fee $40 · Gas Tuning Fee $25)
              │
              └──→ AvaTax calculates tax for the unit ($200), Programming Fee ($40),
                   and Gas Tuning Fee ($25) — ship-to = Dallas workshop address.
                   Returns tax_amount per line.
              │
              └──→ order_events INSERT (order_placed, grand_total=$336.86)

[Rachel pays $336.86 cash at counter — 5 min later]
        │
        └──→ payments INSERT (cash, amount=336.86, status=paid, cash_received_at=10:05)
             orders.payment_status → paid
             orders.status → processing
             order_events INSERT (payment_received)

[Technician programs ECM + tunes fuel system — 55 min of work]
        │
        └──→ (no schema rows — work happens in physical world; fees pre-charged at order placement)

[Admin packs unit + buys FedEx Express label — 11:30]
        │
        └──→ shipments INSERT (shippable_type=order, shippable_id=20, direction=outbound,
                                carrier=FedEx, tracking=FX-EXP-2026-0020, label_cost=50.00,
                                customer_address_id=31 → Dallas workshop, status=in_transit)
             inventory_movements INSERT (sale, Warehouse A → NULL, ORD-2026-0020, 11:30)
             inventory_serials UPDATE (SN-201: in_stock → sold)
             orders.status → shipped
             orders.shipped_at = 2026-05-25 11:30, shipped_by = 1
             order_events INSERT (shipped, tracking=FX-EXP-2026-0020)

[FedEx Express delivers to Dallas workshop — next-day afternoon; admin records delivery]
        │
        └──→ shipments UPDATE (status=delivered, delivered_at=2026-05-26 14:00)
             orders.delivered_at = 2026-05-26 14:00, delivered_by = 1
             (orders.status stays shipped — no status change on delivery for carrier orders)
             order_events INSERT (delivered)
```

> `processing → shipped` are time-separated by the **work the per-line fees pay for** (programming + tuning). Payment recorded at 10:05; programmed + tuned unit shipped at 11:30. The express label fee ($50) is `orders.shipping`, **not** an `order_line_fees` row — it is a shipment-level cost, not per-line work.

---

### Schema + Data

**`customers`**
```
id   name         email               phone         status  tax_exempt
20   Rachel Park  rachel@example.com  555-200-0001  active  false
```

**`customer_addresses`**
```
id  customer_id  label     first_name  last_name  email               phone         address_line1        city     state  postal_code  country  is_default
30  20           Home      Rachel      Park       rachel@example.com  555-200-0001  812 Oak Ridge Ln     Houston  TX     77008        US       true
31  20           Workshop  Rachel      Park       rachel@example.com  555-200-0001  4421 Industrial Blvd Dallas   TX     75207        US       false
```

> Two rows — Rachel's home (default) and her workshop. Both belong to the same customer. The `label` is a user-friendly name (same convention as ex-14's `Home` / `Work`); the *role* — billing vs shipping — is decided at order creation by which snapshot the address is copied into. Here `Home` → billing snapshot, `Workshop` → shipping snapshot.

**`orders`**
```
id   number         customer_id  source   status   payment_status  shipping  grand_total  shipped_at           shipped_by  delivered_at         delivered_by  created_by
20   ORD-2026-0020  20           walk_in  shipped  paid            50.00     336.86       2026-05-25 11:30:00  1           2026-05-26 14:00:00  1             1

-- billing snapshot (CUSTOMER's Houston home — Rachel's own billing address)
billing_first_name   billing_last_name  billing_email        billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
Rachel               Park               rachel@example.com   555-200-0001   812 Oak Ridge Ln       Houston       TX             77008                US

-- shipping snapshot (Rachel's Dallas workshop — DIFFERENT from billing)
shipping_first_name  shipping_last_name  shipping_email       shipping_phone  shipping_address_line1   shipping_city  shipping_state  shipping_postal_code  shipping_country
Rachel               Park                rachel@example.com   555-200-0001    4421 Industrial Blvd     Dallas         TX              75207                 US
```

> **Split billing/shipping convention:** for in-store cash sales where the customer wants delivery to a different address, billing snapshot holds the **customer's own billing address** (here: Houston home) and shipping snapshot holds the **delivery address** (here: Dallas workshop). Both snapshots are filled — neither is NULL.
>
> `shipped_by = 1` (Admin John who dispatched the shipment). `delivered_by = 1` (same admin who **recorded** the delivery — carrier orders never auto-complete; the delivery timestamp + user are filled manually). Status stays `shipped` as terminal — there is no `complete` transition for carrier orders.

**`order_lines`**
```
id   order  product_listing_id  sku       product_name              inventory_serial_id  unit_price  tax_amount  line_total
48   20     14                  ECM-2024  Engine Control Module     SN-201               200.00      16.50       216.50
```

> **`line_total` = `unit_price` + `tax_amount`** = 200.00 + 16.50 = **216.50**

**`order_line_fees`**
```
id  order_line_id  name              amount  tax_amount  fee_total  created_by  created_at
3   48             Programming Fee   40.00   3.30        43.30      1           2026-05-25 10:00:00
4   48             Gas Tuning Fee    25.00   2.06        27.06      1           2026-05-25 10:00:00
```

> **`fee_total` = `amount` + `tax_amount`** (stored — same pattern as `order_lines.line_total`)

**Grand total math**
```
Line items:
  Widget ECM:        $200.00 unit + $16.50 tax = $216.50  ← line_total

Per-line fees:
  Programming Fee:   $ 40.00 + $ 3.30 tax = $ 43.30  ← fee_total
  Gas Tuning Fee:    $ 25.00 + $ 2.06 tax = $ 27.06  ← fee_total

Shipping:
  FedEx Express:     $ 50.00 (expedited label upgrade — orders.shipping)

──────────────────────────────────────────────────
Sum of line totals:                        $216.50
Sum of fee totals:                       + $ 70.36
Shipping (expedited):                    + $ 50.00
──────────────────────────────────────────────────
GRAND TOTAL:                               $336.86 ✓
```

**`payments`**
```
id   order_id  payable_type  payable_id  method  amount   status  cash_received_at      created_by
23   20        order         20          cash    336.86   paid    2026-05-25 10:05:00   1
```

One row. Full amount. Cash collected before technician started programming + tuning work.

**`shipments`**
```
id  shippable_type  shippable_id  direction  carrier  tracking          label_cost  status     shipped_at            returned_at  delivered_at          customer_address_id
12  order           20            outbound   FedEx    FX-EXP-2026-0020  50.00       delivered  2026-05-25 11:30:00   NULL         2026-05-26 14:00:00   31
```

> One outbound shipment, polymorphic FK = `order/20`. `label_cost = 50.00` matches `orders.shipping` — the customer paid the expedited upgrade cost in full. **Carrier service tier** (Express) lives in the `tracking` prefix (`FX-EXP-…`) and in pricing, **not** in a schema column. `customer_address_id = 31` points to Rachel's Dallas workshop row in `customer_addresses` — same address the order's shipping snapshot was copied from. `status` flips `in_transit → delivered` when FedEx confirms delivery.

**`inventory_serials`**
```
serial   status  location  note
SN-201   sold    NULL      shipped to Rachel Park's Dallas workshop — programmed + gas tuned, FedEx Express
```

**`inventory_movements`**
```
id   serial   type     from         to           reference        notes
54   SN-201   receive  NULL         Warehouse A  PO-2026-013      initial stock receipt
55   SN-201   sale     Warehouse A  NULL         ORD-2026-0020    dispatched via FedEx Express (11:30) — programmed + tuned
```

> Two movements for SN-201 — `receive` when PO stock arrived earlier, `sale` when staff dispatched via carrier at 11:30. **The 55-minute gap between payment (10:05) and dispatch (11:30) was used for ECM programming + gas tuning — the work the per-line fees cover.** Inventory movement fires at carrier handover (dispatch), not at delivery.

---

### Order Events

`order_events` rows (append-only):

```
id  order_id  event             metadata                                                                                                                  created_by  created_at
──  ────────  ────────────────  ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────  ──────────  ───────────────────
1   20        order_placed      {"sku":"ECM-2024","product_name":"Engine Control Module","grand_total":"336.86","shipping":"50.00"}                       1           2026-05-25 10:00:00
2   20        payment_received  {"method":"cash","amount":"336.86","shipping":"50.00"}                                                                    1           2026-05-25 10:05:00
3   20        shipped           {"carrier":"FedEx","tracking":"FX-EXP-2026-0020","label_cost":"50.00","ship_to_city":"Dallas"}                          1           2026-05-25 11:30:00
4   20        delivered         {"carrier":"FedEx","tracking":"FX-EXP-2026-0020","shipment_id":12,"address":"4421 Industrial Blvd, Dallas TX 75207"}    1           2026-05-26 14:00:00
```

> `created_by = 1` (Admin John) on every event — counter staff placed the order, took payment, dispatched the shipment, and **manually recorded the delivery** the next day. There is no automatic carrier-webhook event here; the admin enters `delivered_at` when FedEx confirms.

**Rendered timeline:**

```
● Order placed — Engine Control Module · ECM-2024 · $336.86
  ship to Dallas workshop · FedEx Express ($50)
  2026-05-25  10:00 AM  ·  by Admin John

● Payment received — $336.86 via Cash
  ECM $216.50 · programming fee $43.30 · gas tuning fee $27.06 · expedited shipping $50.00
  2026-05-25  10:05 AM  ·  by Admin John

● Shipped — FedEx Express · FX-EXP-2026-0020
  programmed + gas tuned, dispatched to Dallas workshop
  2026-05-25  11:30 AM  ·  by Admin John

● Delivered — FedEx Express
  recorded by Admin John from carrier confirmation
  2026-05-26   2:00 PM  ·  by Admin John
```

---

### Order Status Timeline

```
2026-05-25 10:00  order created          status=pending,    payment_status=unpaid
2026-05-25 10:05  cash payment           status=processing, payment_status=paid
2026-05-25 10:05–11:00  (technician programs ECM + tunes fuel system — fees pre-charged)
2026-05-25 11:30  FedEx Express dispatch status=shipped     (inventory_movement sale, shipments row)
2026-05-26 14:00  FedEx delivered        status=shipped     (delivered_at recorded; status unchanged)
```

> **85-minute gap** between payment and dispatch — 55 min of programming + tuning + ~30 min for pack and label. `shipped` fires when carrier takes the package and is the **terminal status** for carrier orders. The next-day delivery only records `delivered_at` / `delivered_by` on the order and updates the `shipments` row — no status change. Contrast with ex-19 (in-store pickup) where status advances to `complete` at counter handover.

---

### Inventory State Timeline (SN-201)

```
2026-05-21 09:00  receive  SN-201  status=in_stock, location=Warehouse A   (PO-2026-013)
2026-05-25 11:30  sale     SN-201  status=sold,     location=NULL          (ORD-2026-0020)
```

> SN-201 sat in `in_stock` at Warehouse A for 4 days. The `sale` row fires at 11:30 (dispatch time), NOT at 10:05 (payment time) and NOT at 14:00 next day (delivery time). Inventory state mirrors **physical handover to carrier**, not accounting or delivery state.

---

### Per-Line Fee Revenue (sample report)

```sql
SELECT name, SUM(amount) AS revenue, SUM(tax_amount) AS tax_collected
FROM order_line_fees
WHERE created_at::date = '2026-05-25';

name             revenue  tax_collected
Programming Fee  40.00    3.30
Gas Tuning Fee   25.00    2.06
```

```sql
-- Expedited shipping revenue (orders.shipping)
SELECT DATE(created_at) AS day, SUM(shipping) AS shipping_revenue
FROM orders
WHERE id = 20;

day         shipping_revenue
2026-05-25  50.00
```

---

### Key Design Notes

| Rule | Value |
|------|-------|
| Billing snapshot | **CUSTOMER's home address** (Houston) — Rachel's own billing, not the shop |
| Shipping snapshot | **DIFFERENT address** (Dallas workshop) — split from billing |
| `customer_addresses` rows | 2 — one labelled `billing`, one labelled `shipping` |
| `shipping` (cost) | 50.00 — FedEx Express label upgrade, quoted at counter |
| Shipments row | 1 outbound, `shippable_type=order`, `carrier=FedEx`, `tracking=FX-EXP-…` (Express implied), `label_cost=50.00`, `customer_address_id=31` |
| `shipped_at / shipped_by` | 2026-05-25 11:30 / Admin John (1) |
| `delivered_at / delivered_by` | 2026-05-26 14:00 / Admin John (1) — admin records delivery manually |
| Status final | **`shipped`** — terminal for carrier orders; no `complete` transition (see `schema-references.md` OrderStatus rules) |
| AvaTax ship-to | Dallas workshop address → destination-based combined rate |
| Per-line fees | **Programming Fee $40 + Gas Tuning Fee $25** — AvaTax fills `tax_amount`; `fee_total` stored |
| Shipping cost vs per-line fees | $50 expedited upgrade lives on `orders.shipping` (shipment-level), NOT in `order_line_fees` (per-line work) |
| Fee timing | Charged at order placement (10:00) — paid at 10:05 — work performed 10:05–11:00 |
| Status timing | `processing` at payment, `shipped` at dispatch (after work) — terminal. Delivery only sets `delivered_at`/`delivered_by`. |
| Grand total math | sum of line totals + sum of fee totals + shipping = grand_total |

---

### Conventions

1. **Split billing/shipping for walk-in carrier sales** — billing snapshot uses the **customer's own billing address**, shipping snapshot uses the **alternate delivery address**. Both snapshots filled; neither NULL.
2. **Two `customer_addresses` rows** are pre-saved (labelled `billing` and `shipping`) so the customer can reuse them on future orders; the order snapshot still copies their values frozen at order placement.
3. **Expedited shipping upgrade** is stored on `orders.shipping` (shipment-level cost) — **not** as an `order_line_fees` row. Per-line fees are for *per-line work* (programming, tuning, install); shipping label upgrades are *shipment-level* costs.
4. **AvaTax ship-to** uses the **shipping snapshot address** (Dallas), not billing — destination-based sales tax on every taxable line.
5. **Status timing for carrier walk-in sales:** `processing` set at payment time; `shipped` set at carrier dispatch (after any per-line work). **`shipped` is terminal** — delivery only records `delivered_at` and `delivered_by` on the order without changing `status`. (Contrast with ex-19 pickup, which advances `processing → complete` at counter handover.)
6. **`delivered_by` is the admin user** who manually recorded the delivery from the carrier's confirmation. It is never NULL for carrier orders — admin entry is required.
7. **Per-line fees** (`order_line_fees`) used for real per-line work: programming, gas tuning, diesel tuning, install, calibration. AvaTax populates `tax_amount` per fee; `fee_total` stored as `amount + tax_amount`.
8. **Grand total math** = sum of `order_lines.line_total` + sum of `order_line_fees.fee_total` + `orders.shipping` — every row carries its own all-in total.

---

### Financial Summary
```
charged:   $336.86   (1 payment row — full amount: unit + programming + tuning + tax + expedited shipping)
collected: $336.86
refunded:  $  0.00
net:       $336.86 ✓
```
