# Example — Complaint, No-Fault → Unit Returned to Customer (extends ex-001)

> See [global.md](./global.md) — shared enums, column conventions, money + logging rules. Read first.

> _Example only — goal is to understand the data flow. IDs, numbers, timestamps illustrative._
> Extends **ex-001** (ORD-2026-0020, Marcus Webb, pickup cash, ECM-2024 `SN-201` line 48, completed 2026-05-26).

> ⚠ **Alternate branch** — ex-003 (fault → replacement chain) and ex-004 (no-fault → return-to-cx) both act on `SN-201` / line 48. Mutually exclusive timelines; `order_events` ids illustrative, don't co-occur on one real order.

**Scenario:** Marcus reports his **ECM `SN-201`** not working. Drops it at the counter (Day 0). Tech inspects → **no fault found**. Marcus accepts (does **not** want a charged replacement). **Same unit `SN-201` returned** to him next day. No replacement, no charge.

In-store, drop-and-collect. Contrast ex-003 R3 (no-fault but cx *insists* → charged replacement).

---

### Pipeline (no-fault → return branch)
```
unit reported faulty → cx DROPS at counter
  → file complaint (line=48 slot, serial=SN-201)
  → INTAKE VERIFY (serial match — anti-fraud)        ◄ Gate 1
  → SN-201: sold → under_examination   (cx leaves it)
  → tech inspect (checklist)                          ◄ Gate 2
  → NO fault → cx ACCEPTS → return SAME unit
  → cx collects: SN-201 → sold (back to same cx)
  → complaint closed: unit_outcome=returned_to_customer
  → NO replacement · NO payment · NO new serial
```
- Same `SN-201` round-trips: `sold → under_examination → sold`.
- This is the **default** no-fault path. If cx *insists* on a new unit → charged replacement (ex-003 R3) instead.

---

### Tables & enums (subset — full set in global.md; PHP enums)

| Table.column | Values used here |
|---|---|
| `complaints.status` | `open` · `in_progress` · `closed` |
| `complaints.examination_result` | `no_fault_found` |
| `complaints.unit_outcome` | `returned_to_customer` |
| `inventory_serials.status` | `sold` · `under_examination` |
| `inventory_movements.type` | `return_in` · `transfer` · `adjustment` |
| `order_events.event` | `complaint_opened` · `complaint_examined` · `complaint_closed` |

> No `replacements`, `payments`, or `shipments` rows — nothing replaced, no money, no carrier.

---

### Data Flow

```
[Day 0, 09:00 — SN-201 reported faulty, cx drops at counter]
        │
        ├──→ complaints INSERT (CMP-2026-0004: order=20, line=48, serial=SN-201, status=open, issue="not turning on")
        ├──→ INTAKE VERIFY: SN-201 matches ORD-2026-0020 line 48 → legit (Gate 1)
        ├──→ inventory_serials UPDATE (SN-201: sold → under_examination)
        ├──→ inventory_movements INSERT (SN-201 return_in, NULL → Receiving, CMP-2026-0004)
        └──→ order_events INSERT (complaint_opened {serial:SN-201})   [+ activity_log]
             (cx leaves the unit)

[Day 0, 14:00 — tech inspects]
        │
        ├──→ inventory_movements INSERT (SN-201 transfer, Receiving → Tech Area)
        ├──→ tech → NO fault, fully works (Gate 2)
        ├──→ complaints UPDATE (CMP-2026-0004: examination_result=no_fault_found, examined_by=3, examination_notes, status=in_progress)
        └──→ order_events INSERT (complaint_examined {result:no_fault_found})   [+ activity_log]

[Day +1, 10:00 — cx collects same unit]
        │
        ├──→ DECISION: cx accepts (no charged replacement) → return SAME unit
        ├──→ inventory_movements INSERT (SN-201 adjustment, Tech Area → NULL, CMP-2026-0004, back to cx)
        ├──→ inventory_serials UPDATE (SN-201: under_examination → sold, location=NULL, with Marcus)
        ├──→ complaints UPDATE (CMP-2026-0004: unit_outcome=returned_to_customer, closed_at, closed_by=1, status=closed)
        └──→ order_events INSERT (complaint_closed {outcome:returned_to_customer})   [+ activity_log]
             (no replacement · no payment · no shipment)
```

---

### Canonical Timeline (one source of truth)

| time        | complaint   | serial SN-201        | exam            | order_event       |
|-------------|-------------|----------------------|-----------------|-------------------|
| D0 09:00    | open        | under_examination    | —               | complaint_opened  |
| D0 14:00    | in_progress | under_examination    | no_fault_found  | complaint_examined |
| D+1 10:00   | closed      | sold (back to cx)    | —               | complaint_closed (returned_to_customer) |

> Same unit out and back — no new serial. `complaints.status` drives the `order_events` milestones (admin/user timeline).

---

### Schema + Data

**`complaints`**
```
id  number        order_id  order_line_id  serial   status  examination_result  unit_outcome          issue_description   unit_received_at     examined_by  examination_notes                       closed_at            closed_by  created_by  created_at
4   CMP-2026-0004  20     48    SN-201   closed  no_fault_found      returned_to_customer  ECM not turning on  2026-06-02 09:00:00  3            Fully functional — no defect found      2026-06-03 10:00:00  1          1           2026-06-02 09:00:00
```

**`order_events`** (order 20 — post-sale tail; ex-001 sale events 1–5 precede)
```
id  order_id  event              metadata                                          created_by  created_at
12  20        complaint_opened   {"complaint":"CMP-2026-0004","serial":"SN-201"}    1           2026-06-02 09:00:00
13  20        complaint_examined {"result":"no_fault_found"}                       3           2026-06-02 14:00:00
14  20        complaint_closed   {"outcome":"returned_to_customer"}                1           2026-06-03 10:00:00
```
> Admin + customer see the full story on the order timeline. `activity_log` (dev) records the same CRUD with `causer`.

**`inventory_serials`** (final — unchanged from before complaint)
```
serial  status  location  note
SN-201  sold    NULL      with Marcus Webb — no fault, returned (CMP-2026-0004)
```

**`inventory_movements`** (SN-201 round-trip)
```
id   serial  type        from          to            reference      notes
56   SN-201  sale        Warehouse A   NULL          ORD-2026-0020  picked up 2026-05-26 (ex-001)
75   SN-201  return_in   NULL          Receiving     CMP-2026-0004   dropped at counter for exam
76   SN-201  transfer    Receiving     Tech Area     CMP-2026-0004   inspect
77   SN-201  adjustment  Tech Area     NULL          CMP-2026-0004   no fault → returned to cx
```

**`replacements` / `payments` / `shipments`** — **none**. No replacement, no charge, no carrier.

---

### Financial Summary
```
order (ex-001):     $525.01  (unchanged)
complaint charge:   $0.00    (no fault, unit returned — no charge)
─────────────────────────────
net:                $525.01 ✓
```

---

### Invariants (guardrails)
- mandatory pipeline still runs: complaint → intake verify → inspect → decision (no skip)
- no_fault + cx accepts → **same unit returned** (`unit_outcome=returned_to_customer`); **no `replacements` row, no new serial**
- **no charge** (free return) → no `payments` row
- serial round-trip: `sold → under_examination → sold` (same unit, same cx)
- complaint milestones → **`order_events`** (admin/user timeline); `activity_log` = dev audit
- no_fault + cx **insists** on new unit → charged replacement instead (ex-003 R3) — different branch

---

### Key Design Notes
| Rule | Value |
|------|-------|
| Trigger | cx reports fault → drops unit at counter (in-store) |
| Pipeline | complaint → intake verify → tech inspect → decision (mandatory, no skip) |
| Outcome | `no_fault_found` + cx accepts → `unit_outcome=returned_to_customer` |
| Same unit | `SN-201` returns to cx — no new serial, no `replacements` row |
| Money | **$0** — no fault, no charge, no payment row |
| Serial path | `sold → under_examination → sold` (round-trip, same cx) |
| Events | `complaint_opened` → `complaint_examined` → `complaint_closed` on order timeline (`order_events`) |
| Logs | `order_events` (admin/user) + `activity_log` (dev) + `order_notes` (human) |
| Contrast | no_fault + cx insists → charged replacement (ex-003 R3); fault → free replacement |
| No carrier | in-store drop+collect → no `shipments` |
