> See [../global.md](../global.md) for agent rules, column conventions, and all status/enum references before reading this example.
> **Starting state = end state of [ex-19](./ex-19-walkin-cash-instore-pickup.md).** This example continues ORD-2026-0019 — do not re-create the order; it already exists, paid and complete, with SN-200 `sold`.

## Example 19a — CMP-2026-0019 / REP-2026-0019 — In-Person Complaint, Internal Fault, Free Warranty Replacement at Counter

**Scenario:** Three days after picking up her programmed + tuned ECM (ex-19), **Rachel Park** returns to the Houston shop. The **Engine Control Module (ECM-2024, SN-200)** won't boot. She brings the physical unit to the counter. This is an **in-person Flow A complaint** — the unit is handed over the moment the complaint is opened. Technician **Sam Chen** examines it and confirms an **internal manufacturing defect** (`internal_issues`) — warranty applies. Admin **John** issues a **free replacement ECM (SN-201)** across the counter. The faulty SN-200 is **scrapped**. **No charge** — first internal-fault occurrence is covered by warranty.

**Key aspects of this scenario:**
- **Chains off ex-19** — `complaints.order_line_id` points at the existing ECM line (order_line 47). No new order, no new order line.
- **Flow A (unit received first)** — `complaints.status: open → in_progress` the moment the `return_in` movement is recorded (counter handover), then `→ closed` after examination.
- **In-person counter handover** — no inbound carrier shipment. Old unit: `sold → under_examination` (skips `expected_return`, no transit). New unit: `in_stock → sold` directly (skips `assigned`, no transit).
- **`internal_issues` first occurrence → FREE replacement** — no `payments` row, no charge to Rachel (per global Payment rule per replacement).
- **Faulty unit scrapped** — `adjustment` movement to `NULL`, `unit_outcome = scrapped`.
- **Original order stays `complete`** — a replacement does not refund or reopen the order. Order status is untouched.

---

### Data Flow

```
[Rachel returns to counter with non-booting ECM — admin opens complaint]
        │
        ├──→ complaints INSERT (order_line_id=47, number=CMP-2026-0019, status=open, created_by=1)
        │
        └──→ [unit handed over at counter — same moment]
              │
              └──→ inventory_movements INSERT (return_in, NULL → Warehouse A, ref=CMP-2026-0019)
                   inventory_serials UPDATE (SN-200: sold → under_examination)
                   complaints UPDATE (status → in_progress)
                   order_events INSERT (complaint_opened)

[Technician Sam examines unit — confirms internal manufacturing defect]
        │
        └──→ complaints UPDATE (examination_result = internal_issues)
             (no automatic replacement — admin decides next, per global rule)

[Admin John reviews result → decides: free warranty replacement]
        │
        ├──→ replacements INSERT (complaint_id=<CMP id>, number=REP-2026-0019,
        │                         parent_id=NULL, status=delivered, created_by=1)
        ├──→ replacement_lines INSERT (replacement_id=<REP id>, order_line_id=47, new_serial_id=SN-201)
        │
        └──→ [new unit handed over at counter — no transit]
              │
              └──→ inventory_movements INSERT (replacement_out, Warehouse A → NULL, ref=REP-2026-0019)
                   inventory_serials UPDATE (SN-201: in_stock → sold)
                   order_events INSERT (replacement_issued)
              │
              └──→ (NO payments row — internal_issues first occurrence = free warranty)

[Admin closes complaint — faulty unit scrapped]
        │
        └──→ inventory_movements INSERT (adjustment, Warehouse A → NULL, ref=CMP-2026-0019)
             inventory_serials UPDATE (SN-200: under_examination → scrapped)
             complaints UPDATE (status → closed, unit_outcome = scrapped, closed_at set)
             order_events INSERT (complaint_closed)
```

> Original order ORD-2026-0019 status stays **`complete`** throughout. A warranty replacement neither refunds nor reopens the order.

---

### Schema + Data

**`complaints`**
```
id  number            line  status   examination_result  unit_outcome  closed_at             created_by
1   CMP-2026-0019     47    closed   internal_issues     scrapped      2026-05-28 14:30:00   1
```

> `line` = `order_line_id` → the existing ECM line from ex-19 (order_line 47). The line is **never** re-created for a replacement.

**`replacements`**
```
id  number          complaint  parent  status     shipped_at  shipped_by  delivered_at          delivered_by  created_by
1   REP-2026-0019   1          NULL    delivered  NULL        NULL        2026-05-28 14:15:00   1             1
```

> `parent = NULL` → first replacement in this complaint chain. `shipped_at = NULL` → counter handover, no carrier dispatch; `delivered_at` set at handover.

**`replacement_lines`**
```
id  rep  order_line  new_serial_id
1   1    47          SN-201
```

> `rep` = `replacement_id`. `order_line` = the original ECM line (47). `new_serial_id` = the fresh in-stock unit handed over.

**`order_lines`** (unchanged from ex-19 — shown for reference)
```
id   order  sku       product_name              inventory_serial_id  unit_price  tax_amount  line_total
47   19     ECM-2024  Engine Control Module     SN-200               200.00      16.50       216.50
```

> The order line still references **SN-200** (the originally sold serial). The replacement link to SN-201 lives in `replacement_lines`, not on the order line — the order line is an immutable record of what was originally sold.

**`payments`** — **no new row.** Internal-fault first replacement is free (warranty). Only the original ex-19 cash payment ($286.86) exists.

**`inventory_serials`**
```
serial   status     location     note
SN-200   scrapped   NULL         internal fault confirmed — CMP-2026-0019, scrapped 2026-05-28
SN-201   sold       NULL         warranty replacement for SN-200 — REP-2026-0019, with Rachel Park
```

**`inventory_movements`**
```
id   serial   type             from         to           reference        notes
53   SN-200   sale             Warehouse A  NULL         ORD-2026-0019    (from ex-19 — original sale)
54   SN-200   return_in        NULL         Warehouse A  CMP-2026-0019    customer returned non-booting ECM at counter
55   SN-201   replacement_out  Warehouse A  NULL         REP-2026-0019    warranty replacement handed to Rachel at counter
56   SN-200   adjustment       Warehouse A  NULL         CMP-2026-0019    scrapped — internal manufacturing defect confirmed
```

> `adjustment` row (56): `to_location_id IS NULL` + `reference = CMP-xxx` → join `complaints` to confirm `unit_outcome`. Here `unit_outcome = scrapped`. Never assume scrapped without the join (global agent rule).

---

### Order Events

`order_events` rows (append-only — continues ex-19's log, which ended at event id 3 `completed`):

```
id  order_id  event              metadata                                                                      created_by  created_at
4   19        complaint_opened   {"complaint":"CMP-2026-0019","serial":"SN-200","reason":"ECM not booting"}    1           2026-05-28 14:00:00
5   19        replacement_issued {"replacement":"REP-2026-0019","new_serial":"SN-201","charge":"0.00"}         1           2026-05-28 14:15:00
6   19        complaint_closed   {"complaint":"CMP-2026-0019","result":"internal_issues","outcome":"scrapped"} 1           2026-05-28 14:30:00
```

> See [order-events.md](./order-events.md) for the canonical event metadata shapes.

**Rendered timeline (continues ex-19):**

```
● Complaint opened — ECM not booting · SN-200 · CMP-2026-0019
  2026-05-28  2:00 PM  ·  by Admin John

● Replacement issued — SN-201 · free (warranty) · REP-2026-0019
  2026-05-28  2:15 PM  ·  by Admin John

● Complaint closed — internal fault confirmed · faulty unit scrapped
  2026-05-28  2:30 PM  ·  by Admin John
```

---

### Status Timelines

**Complaint (CMP-2026-0019)**
```
2026-05-28 14:00  complaint opened      status=open
2026-05-28 14:00  unit received (counter) status=in_progress   (return_in movement)
2026-05-28 14:30  examined + outcome    status=closed          (internal_issues, scrapped)
```

**Old serial (SN-200)**
```
2026-05-25 11:00  sale          status=sold              (ex-19 handover)
2026-05-28 14:00  return_in     status=under_examination (counter return — skips expected_return)
2026-05-28 14:30  adjustment    status=scrapped          (internal fault confirmed)
```

**New serial (SN-201)**
```
(pre)             in_stock      Warehouse A              (sitting in stock)
2026-05-28 14:15  replacement_out  status=sold           (counter handover — skips assigned)
```

**Order (ORD-2026-0019)** — unchanged: stays `complete`.

---

### Key Design Notes

| Rule | Value |
|------|-------|
| Chained to | ex-19 (ORD-2026-0019) — starting state, not re-created |
| Complaint flow | Flow A — unit received before examination (`in_progress` on `return_in`) |
| Handover type | In-person counter — no carrier shipment, no `shipments` row |
| Old serial path | `sold → under_examination → scrapped` (skips `expected_return`) |
| New serial path | `in_stock → sold` (skips `assigned` — counter handoff, no transit) |
| Examination result | `internal_issues` — manufacturing defect, warranty applies |
| Charge | **$0.00** — internal-fault first occurrence is free (no `payments` row) |
| Unit outcome | `scrapped` — `adjustment` to NULL, ref = CMP number |
| Replacement chain | `parent_id = NULL` — first (and only) replacement in this chain |
| Order status | Unchanged — stays `complete` (replacement ≠ refund) |
| Order line | Unchanged — still references SN-200; replacement link lives in `replacement_lines` |

---

### Conventions

1. **A replacement never creates a new order line.** `complaints.order_line_id` and `replacement_lines.order_line_id` both point at the original line. The order line is an immutable record of the original sale.
2. **Counter handover skips transit statuses** — old unit skips `expected_return`, new unit skips `assigned`.
3. **`internal_issues` first occurrence = free** — no `payments` row. (Charge only on `damaged_by_customer`, or business decision on repeat internal faults — see ex-10.)
4. **A warranty replacement does not touch order status** — the order stays `complete`. Only a refund moves an order to `refunded`.
5. **Scrapped-from-complaint** is an `adjustment` movement to NULL with `reference = CMP-xxx` — always join `complaints` to read `unit_outcome`.

---

### Financial Summary
```
charged:   $286.86   (original ex-19 cash payment — unchanged)
collected: $286.86
refunded:  $  0.00
replacement charge: $0.00   (internal fault — warranty)
net:       $286.86 ✓
```
