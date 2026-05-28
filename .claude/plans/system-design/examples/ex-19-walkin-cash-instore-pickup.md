> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.

## Example 19 — ORD-019 — Walk-in Cash, In-Store Pickup, ECM with Programming + Gas Tuning

**Scenario:** Rachel Park walks into the Houston shop. Buys one **Engine Control Module (ECM-2024)** with **Programming Fee** + **Gas Tuning Fee** (typical for ECM sales — unit must be programmed for vehicle VIN and tuned for fuel system). Pays $286.86 cash at counter. Billing recorded as the **shop's address** (sale happened in person at NPC Sales Pro LLC). No shipping — in-store pickup. Staff performs programming + tuning over 55 minutes; unit handed to Rachel **1 hour after payment**.

**Key aspects of this scenario:**
- `source=walk_in` + `method=cash` + `status → complete` — cash counter sale
- **Billing snapshot = SHOP address** — records WHERE the sale happened (legal + audit clarity for cash transactions)
- **Shipping snapshot = NULL** — in-store pickup, no carrier, no destination
- **Per-line fees with own tax** — Programming Fee ($40 + $3.30 tax = $43.30) and Gas Tuning Fee ($25 + $2.06 tax = $27.06) attached to the line
- **AvaTax engaged on 3 separate lines** — unit + Programming + Gas Tuning all at store-local Houston rate
- **55-min payment-to-handover delay** — represents real technician work (programming + tuning) the per-line fees pay for

---

### Data Flow

```
[Rachel walks in — admin creates order at counter]
        │
        ├──→ orders INSERT (customer_id=19, source=walk_in, status=pending, payment_status=unpaid,
        │                   billing snapshot = NPC Sales Pro LLC shop address,
        │                   shipping snapshot = NULL — in-store pickup)
        ├──→ order_lines INSERT (1 line: ECM-2024, SN-200)
        └──→ order_line_fees INSERT (Programming Fee $40 · Gas Tuning Fee $25)
              │
              └──→ AvaTax calculates tax for the unit ($200), Programming Fee ($40),
                   and Gas Tuning Fee ($25) — shipping to shop address.
                   Returns tax_amount per line.
              │
              └──→ order_events INSERT (order_placed, grand_total=$286.86)

[Rachel pays $286.86 cash at counter — 5 min later]
        │
        └──→ payments INSERT (cash, amount=286.86, status=paid, cash_received_at=10:05)
             orders.payment_status → paid
             orders.status → processing
             order_events INSERT (payment_received)

[Technician programs ECM + tunes fuel system — 55 min of work]
        │
        └──→ (no schema rows — work happens in physical world; fees pre-charged at order placement)

[Staff hands programmed + tuned unit to Rachel — 1 hr after payment]
        │
        └──→ inventory_movements INSERT (sale, Warehouse A → NULL, ORD-2026-0019, 11:00)
             inventory_serials UPDATE (SN-200: in_stock → sold)
             orders.status → complete
             order_events INSERT (completed)
             (no shipment row — no carrier)
```

> `processing → complete` are time-separated by the **work the per-line fees pay for** (programming + tuning). Payment recorded at 10:05; programmed + tuned unit handed over at 11:00.

---

### Schema + Data

**`customers`**
```
id   name         email               phone         status  tax_exempt
19   Rachel Park  rachel@example.com  555-190-0001  active  false
```

**`customer_addresses`**
```
-- no rows for Rachel — no address collected at counter (walk-in cash, in-store pickup)
```

**`orders`**
```
id   number         customer_id  source   status    payment_status  shipping  grand_total  shipped_at  shipped_by  delivered_at  delivered_by  created_by
19   ORD-2026-0019  19           walk_in  complete  paid            0.00      286.86       NULL        NULL        NULL          NULL          1

-- billing snapshot (SHOP address — cash sale happened at the shop, recorded for audit)
billing_first_name   billing_last_name  billing_email           billing_phone  billing_address_line1  billing_city  billing_state  billing_postal_code  billing_country
NPC Sales Pro LLC    NULL               sales@npcsalespro.com   713-555-0100   5426 N Shepherd Dr     Houston       TX             77091                US

-- shipping snapshot (NULL — in-store pickup, no carrier, no destination)
shipping_first_name  shipping_last_name  shipping_email  shipping_phone  shipping_address_line1  shipping_city  shipping_state  shipping_postal_code  shipping_country
NULL                 NULL                NULL            NULL            NULL                    NULL           NULL            NULL                  NULL
```

> **Billing convention:** for in-store cash sales without a customer address, billing snapshot uses the **shop's own address**. Records WHERE the transaction physically occurred. The `billing_first_name` field holds the business legal name; remaining personal fields are NULL.

**`order_lines`**
```
id   order  product_listing_id  sku       product_name              inventory_serial_id  unit_price  tax_amount  line_total
47   19     14                  ECM-2024  Engine Control Module     SN-200               200.00      16.50       216.50
```

> **`line_total` = `unit_price` + `tax_amount`** = 200.00 + 16.50 = **216.50**

**`order_line_fees`**
```
id  order_line_id  name              amount  tax_amount  fee_total  created_by  created_at
1   47             Programming Fee   40.00   3.30        43.30      1           2026-05-25 10:00:00
2   47             Gas Tuning Fee    25.00   2.06        27.06      1           2026-05-25 10:00:00
```

> **`fee_total` = `amount` + `tax_amount`** (stored — same pattern as `order_lines.line_total`)

**Grand total math**
```
Line items:
  Widget ECM:        $200.00 unit + $16.50 tax = $216.50  ← line_total

Per-line fees:
  Programming Fee:   $ 40.00 + $ 3.30 tax = $ 43.30  ← fee_total
  Gas Tuning Fee:    $ 25.00 + $ 2.06 tax = $ 27.06  ← fee_total

──────────────────────────────────────────────────
Sum of line totals:                        $216.50
Sum of fee totals:                       + $ 70.36
Shipping:                                + $  0.00
──────────────────────────────────────────────────
GRAND TOTAL:                               $286.86 ✓
```

**`payments`**
```
id   order_id  payable_type  payable_id  method  amount   status  cash_received_at      created_by
22   19        order         19          cash    286.86   paid    2026-05-25 10:05:00   1
```

One row. Full amount. Cash collected before technician started programming + tuning work.

**No shipment row** — in-store pickup, no carrier involved.

**`inventory_serials`**
```
serial   status  location  note
SN-200   sold    NULL      with Rachel Park — programmed + gas tuned, picked up at counter
```

**`inventory_movements`**
```
id   serial   type     from         to           reference        notes
52   SN-200   receive  NULL         Warehouse A  PO-2026-012      initial stock receipt
53   SN-200   sale     Warehouse A  NULL         ORD-2026-0019    handed to Rachel at counter (11:00) — programmed + tuned
```

> Two movements for SN-200 — `receive` when PO stock arrived earlier, `sale` when staff handed over at 11:00. **The 55-minute gap between payment (10:05) and handover (11:00) was used for ECM programming + gas tuning — the work the per-line fees cover.** No inventory movement during the work itself; movement only fires at physical handover.

---

### Order Events

`order_events` rows (append-only):

```
id  order_id  event             metadata                                                                                                  created_by  created_at
──  ────────  ────────────────  ────────────────────────────────────────────────────────────────────────────────────────────────────────  ──────────  ───────────────────
1   19        order_placed      {"sku":"ECM-2024","product_name":"Engine Control Module","grand_total":"286.86"}                          1           2026-05-25 10:00:00
2   19        payment_received  {"method":"cash","amount":"286.86","shipping":"0.00"}                                                     1           2026-05-25 10:05:00
3   19        completed         {}                                                                                                         1           2026-05-25 11:00:00
```

> `created_by = 1` → Admin John (counter staff). Every transition is admin-driven.

**Rendered timeline:**

```
● Order placed — Engine Control Module · ECM-2024 · $286.86
  2026-05-25  10:00 AM  ·  by Admin John

● Payment received — $286.86 via Cash
  ECM $216.50 · programming fee $43.30 · gas tuning fee $27.06 · no shipping
  2026-05-25  10:05 AM  ·  by Admin John

● Order completed — in-store pickup
  unit programmed + gas tuned, handed to customer
  2026-05-25  11:00 AM  ·  by Admin John
```

---

### Order Status Timeline

```
2026-05-25 10:00  order created          status=pending,    payment_status=unpaid
2026-05-25 10:05  cash payment           status=processing, payment_status=paid
2026-05-25 10:05–11:00  (technician programs ECM + tunes fuel system — fees pre-charged)
2026-05-25 11:00  unit handed over       status=complete    (inventory_movement sale)
```

> **55-minute gap** between payment and handover. Order stays in `processing` while technician completes the work the **per-line fees** pay for (programming + tuning). `complete` fires only when the programmed + tuned unit physically leaves the counter.

---

### Inventory State Timeline (SN-200)

```
2026-05-20 09:00  receive  SN-200  status=in_stock, location=Warehouse A   (PO-2026-012)
2026-05-25 11:00  sale     SN-200  status=sold,     location=NULL          (ORD-2026-0019)
```

> SN-200 sat in `in_stock` at Warehouse A for 5 days before Rachel's purchase. The `sale` row fires at 11:00 (handover time), NOT at 10:05 (payment time). Inventory state mirrors physical reality, not accounting state.

---

### Per-Line Fee Revenue (sample report)

```sql
SELECT name, SUM(amount) AS revenue, SUM(tax_amount) AS tax_collected
FROM order_line_fees
WHERE created_at::date = '2026-05-25'
GROUP BY name;

name             revenue  tax_collected
Programming Fee  40.00    3.30
Gas Tuning Fee   25.00    2.06
```

---

### Key Design Notes

| Rule | Value |
|------|-------|
| Billing snapshot | **SHOP address** (NPC Sales Pro LLC, Houston) — convention for in-store cash sales |
| Shipping snapshot | NULL — in-store pickup, no carrier |
| `shipping` (cost) | 0.00 — no carrier fee |
| `customer_addresses` | No rows — no address collected at counter |
| Shipments row | None — no carrier involvement |
| `shipped_at / shipped_by` | NULL — no carrier dispatch |
| Status final | `complete` — unit in customer's hands |
| AvaTax ship-to for pickup | Same as ship-from (shop address) → store-local rate applied |
| Per-line fees | **Programming Fee $40 + Gas Tuning Fee $25** — AvaTax fills `tax_amount`; `fee_total` stored |
| Fee timing | Charged at order placement (10:00) — paid at 10:05 — work performed 10:05–11:00 |
| Payment-vs-handover timing | `processing` at payment (10:05), `complete` at handover (11:00) — gap = work time |
| Grand total math | sum of line totals + sum of fee totals + shipping = grand_total |

---

### Conventions

1. **Billing snapshot for in-store cash sales** uses the **SHOP's address** (recorded in `billing_first_name` as legal entity name + address fields)
2. **Shipping snapshot for pickup** is always **NULL** (no carrier, no destination)
3. **AvaTax ship-to for pickup** equals ship-from (store address) → store-local tax rate applied
4. **Status timing**: `processing` set at payment time; `complete` only when unit physically leaves the counter — even if hours apart (often used to perform paid work in between)
5. **Per-line fees** (`order_line_fees`) used for real per-line work: programming, gas tuning, diesel tuning, install, calibration. AvaTax populates `tax_amount` per fee; `fee_total` stored as `amount + tax_amount`
6. **Grand total math** = sum of `order_lines.line_total` + sum of `order_line_fees.fee_total` + `orders.shipping` — every row carries its own all-in total

---

### Financial Summary
```
charged:   $286.86   (1 payment row — full amount: unit + programming + tuning + tax)
collected: $286.86
refunded:  $  0.00
net:       $286.86 ✓
```
