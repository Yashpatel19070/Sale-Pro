# Example — Return + Refund (partial, in-store cash) — extends ex-001

> See [global.md](./global.md) — shared enums, column conventions, money + logging rules. Read first.

> _Example only — goal is to understand the data flow. IDs, numbers, timestamps illustrative._
> Extends **ex-001** (ORD-2026-0020, Marcus Webb, pickup cash, ECM-2024 `SN-201` line 48 + TCM-2024 `SN-301` line 49, completed 2026-05-26).

**Scenario:** Marcus changed his mind on the **TCM (`SN-301`)** — brings it back to the counter (Day +10). Unit is fine. Staff verify + inspect → accept return → `SN-301` back to stock, **cash refund $194.85** (TCM line total). He **keeps the ECM**. Partial return.

Not a complaint (no fault) — a **return for refund**.

---

### Refund rule (the key idea — same for every order shape)
**Refund = returned line's original `line_total` (unit + its tax). Nothing else.**

| order shape | cx paid for slot | refund on return |
|---|---|---|
| simple (this) | $194.85 | **$194.85** |
| chain, all free reps | $194.85 (reps $0) | **$194.85** |
| chain + charged rep | $194.85 + charged fee | **$194.85** (charged fee kept) |

- **Per-line fees** (Programming/Gas Tuning) = **service already done** → **non-refundable**.
- **Charged-replacement fees** = service/penalty already earned → **non-refundable**.
- So refund is **identical** whether simple, free-chain, or charged-chain — always the original product line total.
- **full** refund = all lines · **partial** = some lines (here: TCM only).
- Works on a replacement unit too: returning `SN-213` (ex-003) still refunds the **original** TCM/ECM line total, fees kept.

> **Manual amount + method:** the refund amount (`refunds.total_amount` / per-item `refund_lines.amount`) is **admin-entered**. Default = returned line's `line_total` (full); admin can enter a **partial** amount (e.g. damaged, restocking deduction). Method = **cash or card (Stripe refund)** — admin's choice / match original. So **partial two ways**: by **line** (some units) + by **amount** (< line_total).

---

### Pipeline (return → refund — no fault, condition check)
```
cx brings unit back → return request
  → VERIFY serial (SN-301 matches order 20, line 49 — anti-fraud)     ◄ Gate 1
  → INSPECT condition                                                  ◄ Gate 2
       good   → back_to_stock
       faulty → rebuild
  → APPROVE → goods in + refund (original line_total)
  → cash refund $194.85 · AvaTax adjust (−$14.85 unit tax)
  → return closed
```
- standalone return — `returns.complaint_id` = NULL (no fault, no complaint).
- refund **after** unit received + condition ok (gate).

---

### Tables & enums (subset — full set in global.md; PHP enums)

| Table.column | Values used here |
|---|---|
| `returns.status` | `requested` · `approved` · `received` · `closed` · `rejected` |
| `returns.reason` | `changed_mind` (· `not_needed` · `defective` · `other`) |
| `return_lines.condition` | `good` · `faulty` |
| `return_lines.restock` | `back_to_stock` · `rebuild` |
| `refunds.status` | `pending` · `refunded` |
| `refunds.reason` | `return` · `cancel` · `adjustment` · `other` |
| `payments.kind` | `payment` · `refund` |
| `payments.payable_type` | `order` · `replacement` · `refund` |
| `order_events.event` | `return_requested` · `refunded` · `return_closed` |
| `inventory_serials.status` | `sold` · `under_examination` · `in_stock` |
| `inventory_movements.type` | `return_in` · `transfer` · `adjustment` |

---

### Data Flow

```
[Day +10, 2026-06-05 — Marcus brings TCM back, wants refund]
        │
        ├──→ returns INSERT (RET-2026-0001: order_id=20, complaint_id=NULL, reason=changed_mind, status=requested)
        ├──→ return_lines INSERT (return_id=1, order_line_id=49, serial=SN-301, condition=?, restock=?)
        ├──→ VERIFY: SN-301 matches order 20, line 49 → legit (Gate 1)
        ├──→ inventory_serials UPDATE (SN-301: sold → under_examination)
        ├──→ inventory_movements INSERT (SN-301 return_in, NULL → Receiving, RET-2026-0001)
        └──→ order_events INSERT (return_requested {serial:SN-301})   [+ activity_log]

[Inspect condition — good]
        │
        ├──→ inventory_movements INSERT (SN-301 transfer, Receiving → Tech Area)
        ├──→ return_lines UPDATE (condition=good, restock=back_to_stock)   (Gate 2)
        └──→ returns UPDATE (status=approved → received)

[Refund + close]
        │
        ├──→ GATE: unit received + good → issue refund
        ├──→ refunds INSERT (REF-2026-0001: order_id=20, return_id=1, reason=return, total_amount=194.85, total_tax=14.85, method=cash, status=pending)
        ├──→ refund_lines INSERT (refund_id=1, order_line_id=49, amount=180.00, tax=14.85)
        ├──→ payments INSERT (kind=refund, payable_type=refund, payable_id=1, method=cash, amount=194.85, status=refunded, received_at, received_by=1)
        ├──→ AvaTax ADJUST original invoice (ORD-2026-0020) — reduce tax by TCM unit tax −$14.85
        ├──→ refunds UPDATE (REF-2026-0001: status=refunded)
        ├──→ inventory_serials UPDATE (SN-301: under_examination → in_stock, location=Warehouse A)
        ├──→ inventory_movements INSERT (SN-301 adjustment, Tech Area → Warehouse A, back_to_stock)
        ├──→ returns UPDATE (status=closed)
        └──→ order_events INSERT (refunded {amount:194.85} · return_closed)   [+ activity_log]
             (ECM SN-201 untouched · order stays · per-line fees NOT refunded)
```

---

### Schema + Data

**`returns`**
```
id  number        order_id  complaint_id  reason         status  created_by  created_at
1   RET-2026-0001  20       NULL          changed_mind   closed  1           2026-06-05 10:00:00
```
> `complaint_id=NULL` — return is standalone (no fault, no complaint). For a fault→refund case it would link a complaint.

**`return_lines`**
```
id  return_id  order_line_id  serial  condition  restock
1   1          49             SN-301  good       back_to_stock
```
> Targets the **current serial in the slot** (line 49). Works the same if the slot held a replacement unit.

**`refunds`** (money back — separate from goods; works for return-refund AND cancel-refund)
```
id  number        order_id  return_id  reason  total_amount  total_tax  method  status    created_by  created_at
1   REF-2026-0001  20       1          return  194.85        14.85      cash    refunded  1           2026-06-05 10:30:00
```
> `return_id` **nullable** — set here (refund from a return). For a **cancel-refund** (no goods back) it'd be NULL. `total_amount` = money back (unit+tax); `total_tax` = tax part (for AvaTax adjust).

**`refund_lines`** (money back per item)
```
id  refund_id  order_line_id  amount   tax
1   1          49             180.00   14.85
```
> Per-item refund $ + tax → per-product revenue/tax reversal for accounting. `total_amount` = Σ(amount + tax) = $194.85.

**`payments`** (recap order + the refund)
```
id  order_id  payable_type  payable_id  kind     method  amount   status     received_at           received_by  created_by
23  20        order         20          payment  cash    525.01   paid       2026-05-26 14:00:00   1            1
31  20        refund        1           refund   cash    194.85   refunded   2026-06-05 10:30:00   1            1
```
> Refund money row → `payable_type=refund`, `payable_id=1` (the `refunds` row). `kind=refund`. Amount = TCM `line_total` ($180 + $14.85 tax). Per-line fees ($43.30 Programming on TCM) **not** refunded — service done.

**`order_events`** (order 20 — post-sale tail)
```
id  order_id  event             metadata                              created_by  created_at
12  20        return_requested  {"return":"RET-2026-0001","serial":"SN-301"}  1     2026-06-05 10:00:00
13  20        refunded          {"amount":"194.85","method":"cash"}           1     2026-06-05 10:30:00
14  20        return_closed     {"restock":"back_to_stock"}                   1     2026-06-05 10:30:00
```

**`inventory_serials`** (TCM back in stock; ECM unchanged)
```
serial  status    location      note
SN-201  sold      NULL          with Marcus Webb — ECM kept
SN-301  in_stock  Warehouse A   RET-2026-0001 — returned good, back to stock
```

**`inventory_movements`** (SN-301 round-trip)
```
id   serial  type        from          to            reference      notes
57   SN-301  sale        Warehouse A   NULL          ORD-2026-0020  picked up 2026-05-26 (ex-001)
78   SN-301  return_in   NULL          Receiving     RET-2026-0001  brought back at counter
79   SN-301  transfer    Receiving     Tech Area     RET-2026-0001  condition check
80   SN-301  adjustment  Tech Area     Warehouse A   RET-2026-0001  good → back to stock
```

**No `shipments`** — in-store counter, no carrier.

---

### Financial Summary
```
original order:   $525.01
refund (TCM):    -$194.85   (TCM line_total; ECM + all fees kept)
─────────────────────────────
net collected:    $330.16  ✓   (ECM $216.50 + fees $113.66)
```
> Refund = unit + its tax only. TCM Programming Fee ($43.30) + ECM + ECM fees = **kept**.

---

### AvaTax
> Refund = a partial **adjust** of the original committed invoice (`ORD-2026-0020`). Reduce tax by the returned unit's tax (−$14.85). Per-line fee tax ($3.30) **not** adjusted — fee kept. (This is the `adjustTransaction` that was deferred — used on refund.)

---

### Invariants (guardrails)
- return runs the pipeline: request → verify serial → inspect condition → approve → refund (no skip)
- **refund = returned line's original `line_total`** (unit + its tax); per-line fees + charged-rep fees = **non-refundable** (service done)
- refund **identical** for simple / free-chain / charged-chain orders
- refund issued **only after** unit received + condition decided (gate)
- returned serial: good → `back_to_stock` · faulty → `rebuild`
- `returns.complaint_id` = NULL for standalone return; set if a complaint drove it
- partial = refund returned line(s); full = all lines
- refund money = `refunds` (header) + `refund_lines` (per-item $/tax, for accounting); cash/card movement = `payments` row `kind=refund`, `payable_type=refund`
- `refunds.return_id` nullable — set for return-refund, NULL for cancel-refund (no goods)
- targets **current serial in slot** — works on original or replacement unit
- in-store → no `shipments`

---

### Key Design Notes
| Rule | Value |
|------|-------|
| Entity (goods) | `returns` (header) + `return_lines` (per unit). Standalone (`complaint_id` nullable) |
| Entity (money) | `refunds` (header) + `refund_lines` (per item $/tax). `return_id` nullable (return-refund or cancel-refund) |
| Pipeline | request → verify → inspect condition → approve → refund. No skip |
| Refund amount | original `line_total` of returned line; fees (per-line + charged-rep) **non-refundable** |
| Same all shapes | simple / free-chain / charged-chain → refund = original line total |
| Refund money | `refunds`+`refund_lines` (per-item $/tax, acc); movement = `payments` `kind=refund`, `payable_type=refund`; cash/card |
| AvaTax | `adjustTransaction` on original invoice — refund the unit's tax only |
| Goods | returned serial → `back_to_stock` (good) / `rebuild` (faulty); counter, no shipment |
| Partial | per-line; ECM kept, only TCM refunded |
| Slot model | targets current serial in slot (original or replacement) |
| Events | `return_requested` → `refunded` → `return_closed` on order timeline |
| Logs | `order_events` (admin/user) + `activity_log` (dev) |
