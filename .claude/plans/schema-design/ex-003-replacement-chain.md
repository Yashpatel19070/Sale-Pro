# Example — Replacement Chain (extends ex-001) — In-Store, free → free → charged (fraud)

> See [global.md](./global.md) — shared enums, column conventions, money + logging rules. Read first.

> _Example only — goal is to understand the data flow. IDs, numbers, timestamps illustrative._
> Extends **ex-001** (ORD-2026-0020, Marcus Webb, pickup cash, ECM-2024 `SN-201` line 48 + TCM-2024 `SN-301` line 49, completed 2026-05-26).

> ⚠ **Alternate branch** — ex-003 (fault → replacement chain) and ex-004 (no-fault → return-to-cx) both act on `SN-201` / line 48. Mutually exclusive timelines; `order_events` ids illustrative, don't co-occur on one real order.

**Scenario:** After pickup, Marcus's **ECM** fails three times.
1. Day +7: `SN-201` dead → complaint → tech: **internal fault** → **free** replacement `SN-211`.
2. Day +14: `SN-211` dead → complaint → tech: **internal fault** → **free** replacement `SN-212`.
3. Day +21: Marcus claims `SN-212` dead → complaint → tech: **NO fault (cx lying)** → Marcus still wants a new unit → **charged** replacement `SN-213` ($216.50 cash). Old `SN-212` good → back to stock.

TCM (`SN-301`) untouched. Pure in-store, no carrier.

---

### Pipeline (mandatory every round — no skip, no goodwill)
```
unit fails → cx to counter
  → file complaint (line = slot order_line, serial = current unit)
  → INTAKE VERIFY (serial match + params — anti-fraud)        ◄ Gate 1
  → tech inspect (checklist)                                   ◄ Gate 2
  → decision:
       fault      → FREE replacement   · old unit → rebuild
       no_fault   → CHARGED replacement · old unit → back_to_stock · cx pays (AvaTax)
  → new unit reserve→sold, handed at counter (charged → pay FIRST)
  → complaint closed
```
- `complaint.order_line_id` = the **slot** (original `order_line` 48); `serial` = whichever unit fills it now (`SN-201`→`211`→`212`→`213`).
- Replacement requires a complaint (`complaint` FK NOT NULL). No standalone swap.

---

### Tables & enums (allowed values — PHP enums, not MySQL ENUM)

| Table.column | Allowed values |
|---|---|
| `complaints.status` | `open` · `in_progress` · `closed` |
| `complaints.examination_result` | `fault_found` · `internal_issues` · `no_fault_found` |
| `complaints.unit_outcome` | `rebuild` · `back_to_stock` · `returned_to_customer` |
| `replacements.type` | `free` · `charged` |
| `replacements.pay_status` | `none` · `unpaid` · `paid` |
| `replacements.status` | `requested` · `approved` · `completed` · `rejected` |
| `inventory_serials.status` | `in_stock` · `reserved` · `sold` · `under_examination` · `to_rebuild` |
| `inventory_movements.type` | `receive` · `sale` · `return_in` · `transfer` · `adjustment` · `replacement_out` |
| `payments.payable_type` | `order` · `replacement` |
| `shipments.shippable_type` | `order` · `complaint` · `replacement` (none used here — in-store) |

> Enums in PHP (backed enum + cast + validation). New value = PHP change, no migration.

---

### Data Flow

```
[Round 1 — SN-201 fails, day +7 (2026-06-02)]
        │
        ├──→ complaints INSERT (CMP-2026-0001: order_id=20, order_line_id=48, serial=SN-201, status=open, issue="dead")
        ├──→ INTAKE VERIFY: SN-201 matches ORD-2026-0020 line 48 → legit (Gate 1)
        ├──→ inventory_serials UPDATE (SN-201: sold → under_examination)
        ├──→ tech inspect → internal fault (Gate 2)
        │    complaints UPDATE (CMP-2026-0001: examination_result=internal_issues, examined_by, status=in_progress)
        ├──→ DECISION: fault → FREE
        │
        ├──→ replacements INSERT (REP-2026-0001: order_id=20, parent_id=NULL, complaint_id=1, type=free, charge=0, pay_status=none, status=approved)
        ├──→ replacement_lines INSERT (order_line_id=48, old=SN-201, new=SN-211)
        ├──→ inventory_serials UPDATE (SN-201: under_examination → to_rebuild, location=Rebuild Area)
        ├──→ inventory_serials UPDATE (SN-211: in_stock → reserved → sold)
        ├──→ inventory_movements INSERT (SN-201 transfer → Rebuild Area · SN-211 replacement_out → cx counter)
        ├──→ order_events INSERT (complaint_opened · complaint_examined · replacement_issued · complaint_closed)   [+ activity_log]
        └──→ complaints UPDATE (CMP-2026-0001: unit_outcome=rebuild, status=closed)
             replacements UPDATE (REP-2026-0001: status=completed)
             (no payment — free · no shipment — counter)

[Round 2 — SN-211 fails, day +14 (2026-06-09)]   — same as round 1, chained
        │
        ├──→ CMP-2026-0002 (order_line_id=48, serial=SN-211) → internal fault → FREE
        ├──→ REP-2026-0002 (parent_id=1, complaint_id=2, type=free)   ◄ CHAIN
        ├──→ replacement_lines (order_line_id=48, old=SN-211, new=SN-212)
        ├──→ SN-211 → to_rebuild (Rebuild Area) · SN-212 → reserved → sold (counter)
        ├──→ order_events INSERT (complaint_opened · complaint_examined · replacement_issued · complaint_closed)
        └──→ CMP-2026-0002 unit_outcome=rebuild, closed · REP-2026-0002 completed

[Round 3 — SN-212 claimed dead, day +21 (2026-06-16) — cx LYING]
        │
        ├──→ complaints INSERT (CMP-2026-0003: order_id=20, order_line_id=48, serial=SN-212, status=open, issue="dead")
        ├──→ INTAKE VERIFY: SN-212 matches slot → legit device (Gate 1)
        ├──→ inventory_serials UPDATE (SN-212: sold → under_examination)
        ├──→ tech inspect → NO fault, unit fully works (Gate 2)
        │    complaints UPDATE (CMP-2026-0003: examination_result=no_fault_found, examined_by, status=in_progress)
        ├──→ cx STILL wants new unit → DECISION: no_fault → CHARGED
        │
        ├──→ replacements INSERT (REP-2026-0003: order_id=20, parent_id=2, complaint_id=3, type=charged,
        │                         charge=216.50, pay_status=unpaid, status=approved)   ◄ CHAIN + CHARGED
        ├──→ AvaTax QUOTE on charged unit ($200 + $16.50 tax = $216.50)
        ├──→ GATE: cx pays FIRST → payments INSERT (payable_type=replacement, payable_id=3, cash, 216.50, paid, received_at, received_by)
        │         AvaTax COMMIT (SalesInvoice, code = REP-2026-0003)
        │         replacements UPDATE (REP-2026-0003: pay_status=paid)
        ├──→ replacement_lines INSERT (order_line_id=48, old=SN-212, new=SN-213)
        ├──→ inventory_serials UPDATE (SN-212: under_examination → in_stock, location=Warehouse A)   ◄ GOOD → back_to_stock
        ├──→ inventory_serials UPDATE (SN-213: in_stock → reserved → sold)
        ├──→ inventory_movements INSERT (SN-212 adjustment → Warehouse A · SN-213 replacement_out → cx counter)
        ├──→ order_events INSERT (complaint_opened · complaint_examined · replacement_issued · complaint_closed)   [+ activity_log]
        └──→ complaints UPDATE (CMP-2026-0003: unit_outcome=back_to_stock, status=closed)
             replacements UPDATE (REP-2026-0003: status=completed)
```

---

### Chain view
```
SN-201 → SN-211 → SN-212 → SN-213            (ECM slot, order_line_id 48)
            REP-2026-0001  REP-2026-0002  REP-2026-0003
parent_id:  NULL           1              2
type:    free       free      charged
exam:    fault      fault     no_fault (cx lying)
old→:    rebuild    rebuild   back_to_stock (good)
```
Marcus now holds **SN-213** (ECM) + **SN-301** (TCM, untouched).

---

### Canonical Timeline (one source of truth)

| round | date       | complaint    | serial in slot (48) | exam            | rep         | type    | old → fate     | new → cx |
|-------|------------|--------------|---------------------|-----------------|-------------|---------|----------------|----------|
| R1    | 2026-06-02 | CMP-2026-0001 | SN-201              | internal_issues | REP-2026-0001 | free    | rebuild        | SN-211   |
| R2    | 2026-06-09 | CMP-2026-0002 | SN-211              | internal_issues | REP-2026-0002 | free    | rebuild        | SN-212   |
| R3    | 2026-06-16 | CMP-2026-0003 | SN-212              | no_fault_found  | REP-2026-0003 | charged | back_to_stock  | SN-213   |

> Each round = full pipeline (complaint → verify → inspect → decision). `rep.parent` chains R2→R1, R3→R2. Slot = line 48; serial = current unit.

---

### Schema + Data

**`complaints`**
```
id  number        order_id  order_line_id  serial   status  examination_result  unit_outcome    issue_description  unit_received_at     examined_by  examination_notes            closed_at            closed_by  created_by  created_at
1   CMP-2026-0001  20     48    SN-201   closed  internal_issues     rebuild         ECM dead           2026-06-02 10:00:00  3            Internal fault confirmed     2026-06-02 12:00:00  1          1           2026-06-02 10:00:00
2   CMP-2026-0002  20     48    SN-211   closed  internal_issues     rebuild         ECM dead           2026-06-09 10:00:00  3            Internal fault confirmed     2026-06-09 12:00:00  1          1           2026-06-09 10:00:00
3   CMP-2026-0003  20     48    SN-212   closed  no_fault_found      back_to_stock   ECM dead (claim)   2026-06-16 10:00:00  3            Fully functional, no defect  2026-06-16 12:00:00  1          1           2026-06-16 10:00:00
```
> `order_line_id=48` (the ECM slot) every round; `serial` = unit in the slot at claim time.

**`replacements`**
```
id  number        order_id  parent_id  complaint_id  type     charge   pay_status  status     created_by  created_at
1   REP-2026-0001  20     NULL    1          free     0.00     none        completed  1           2026-06-02 11:00:00
2   REP-2026-0002  20     1       2          free     0.00     none        completed  1           2026-06-09 11:00:00
3   REP-2026-0003  20     2       3          charged  216.50   paid        completed  1           2026-06-16 11:30:00
```
> `parent_id` chains: REP-2026-0002→REP-2026-0001, REP-2026-0003→REP-2026-0002. Count chain = reps where `order_id=20` → 3.

**`replacement_lines`**
```
id  replacement_id  order_line_id  sku       product_name           old_serial  new_serial
1   1    48          ECM-2024  Engine Control Module  SN-201      SN-211
2   2    48          ECM-2024  Engine Control Module  SN-211      SN-212
3   3    48          ECM-2024  Engine Control Module  SN-212      SN-213
```

**`order_events`** (order 20 — post-sale tail; ex-001 sale events 1–5 precede. This is where replacement shows on the admin/user timeline.)
```
id  order_id  event              metadata                                                          created_by  created_at
12  20        complaint_opened   {"complaint":"CMP-2026-0001","serial":"SN-201"}                   1           2026-06-02 10:00:00
13  20        complaint_examined {"result":"internal_issues"}                                      3           2026-06-02 11:00:00
14  20        replacement_issued {"rep":"REP-2026-0001","old":"SN-201","new":"SN-211","free":true} 1           2026-06-02 11:30:00
15  20        complaint_closed   {"outcome":"rebuild"}                                             1           2026-06-02 12:00:00
16  20        complaint_opened   {"complaint":"CMP-2026-0002","serial":"SN-211"}                   1           2026-06-09 10:00:00
17  20        complaint_examined {"result":"internal_issues"}                                      3           2026-06-09 11:00:00
18  20        replacement_issued {"rep":"REP-2026-0002","old":"SN-211","new":"SN-212","free":true} 1           2026-06-09 11:30:00
19  20        complaint_closed   {"outcome":"rebuild"}                                             1           2026-06-09 12:00:00
20  20        complaint_opened   {"complaint":"CMP-2026-0003","serial":"SN-212"}                   1           2026-06-16 10:00:00
21  20        complaint_examined {"result":"no_fault_found"}                                       3           2026-06-16 11:00:00
22  20        replacement_issued {"rep":"REP-2026-0003","old":"SN-212","new":"SN-213","charged":true} 1        2026-06-16 11:30:00
23  20        complaint_closed   {"outcome":"back_to_stock"}                                       1           2026-06-16 12:00:00
```
> Replacement's order_events = on the **order** (`order_id=20`). Per round: `complaint_opened` → `complaint_examined` → `replacement_issued` → `complaint_closed`. Admin + cx see the full chain here. `complaints`/`replacements` status drive these; `activity_log` (dev) mirrors the CRUD.

**`payments`** (recap order + the one charged replacement)
```
id  order_id  payable_type  payable_id  kind     method  amount   status  received_at           received_by  created_by
23  20        order         20          payment  cash    525.01   paid    2026-05-26 14:00:00   1            1
30  20        replacement   3           payment  cash    216.50   paid    2026-06-16 11:30:00   1            1
```
> REP-2026-0001/0002 free → no payment. REP-2026-0003 charged → payment `payable_type=replacement`, paid **before** handover. AvaTax committed against REP number.

**`inventory_serials`** (final)
```
serial  status    location      note
SN-201  to_rebuild Rebuild Area  CMP-2026-0001 — internal fault
SN-211  to_rebuild Rebuild Area  CMP-2026-0002 — internal fault
SN-212  in_stock   Warehouse A   CMP-2026-0003 — no fault, back to stock (cx lied)
SN-213  sold       NULL          with Marcus Webb — REP-2026-0003 (charged)
SN-301  sold       NULL          with Marcus Webb — original TCM, untouched
```

**`inventory_movements`** (ECM chain — representative; intermediate ids omitted)
```
id   serial  type             from            to            reference      notes
54   SN-201  receive          NULL            Warehouse A   PO-2026-012    initial stock
56   SN-201  sale             Warehouse A     NULL          ORD-2026-0020  picked up 2026-05-26
70   SN-201  return_in        NULL            Receiving     CMP-2026-0001   returned at counter
71   SN-201  transfer         Receiving       Rebuild Area  CMP-2026-0001   internal fault → rebuild
72   SN-211  replacement_out  Warehouse A     NULL          REP-2026-0001   free replacement, counter
80   SN-211  return_in        NULL            Receiving     CMP-2026-0002   returned at counter
81   SN-211  transfer         Receiving       Rebuild Area  CMP-2026-0002   internal fault → rebuild
82   SN-212  replacement_out  Warehouse A     NULL          REP-2026-0002   free replacement, counter
90   SN-212  return_in        NULL            Receiving     CMP-2026-0003   returned at counter
91   SN-212  transfer         Receiving       Tech Area     CMP-2026-0003   inspect
92   SN-212  adjustment       Tech Area       Warehouse A   CMP-2026-0003   no fault → back to stock
93   SN-213  replacement_out  Warehouse A     NULL          REP-2026-0003   charged replacement, counter
```
> `to=NULL` = left building (to customer). `to=Warehouse A` = back to stock. In-store → no `shipments` rows.

---

### Financial Summary
```
order:               $525.01  (payment 23, cash)
charged replacement: $216.50  (payment 30, cash — REP-2026-0003, no_fault)
free replacements:   $0.00    (REP-2026-0001, REP-2026-0002)
─────────────────────────────
collected:           $741.51
refunded:            $0.00
net:                 $741.51 ✓
```

---

### AvaTax

> **Free reps = no AvaTax** (no sale, no money). **Charged rep = a sale** → `calculateTax()` quote at decision → `commitInvoice()` on payment, **doc code = REP number** (`REP-2026-0003`), tax rounded 2 dp per line. Here $200 + $16.50 = $216.50.

---

### Events & logs

> Same 3 logs as ex-001 — **no overlap**:
> - **`order_events`** = admin/user-facing timeline. Carries the order lifecycle **+ each round's complaint/replacement milestones** on order 20: per round `complaint_opened` → `complaint_examined` (`internal_issues` R1/R2, `no_fault_found` R3) → `replacement_issued` → `complaint_closed`.
> - **`complaints.status` / `replacements.status`** = each entity's **own** lifecycle (drives the events above).
> - **`activity_log`** (spatie) = **developer** audit of all CRUD, `causer` = user. Not customer-facing.
> - **`order_notes`** = human free-text (e.g. private "cx lied — charged on R3").

---

### Invariants (guardrails)
- replacement requires a **complaint** (`replacements.complaint` NOT NULL) — pipeline only, no skip
- pipeline order fixed: complaint → intake verify → inspect → decision → replacement
- `type` set by exam: `fault`/`internal_issues` → `free` · `no_fault_found` → `charged`
- charged replacement: cx **pays before handover** (`pay_status=paid` before `replacement_out`)
- old unit fate by exam: faulty → `to_rebuild` (Rebuild Area) · good → `in_stock` (back_to_stock)
- new unit: `in_stock → reserved → sold`
- chain: `replacements.parent_id` self-ref; every rep carries root `order_id` → flat "how many reps" query, no recursion
- `complaint.order_line_id` = slot (original order_line); `serial` = current unit in slot

---

### Key Design Notes
| Rule | Value |
|------|-------|
| Pipeline | complaint → intake verify (anti-fraud) → tech inspect → decision. Mandatory, no skip, no goodwill |
| Replacement entity | `replacements` (header) + `replacement_lines` (old→new per unit); `complaint` required |
| Chain | `parent_id` self-ref; root `order_id` on each → count = reps where `order_id=N` |
| Free vs charged | exam fault → `free` · no_fault (cx lying) → `charged` (cx pays, AvaTax) |
| Old unit fate | faulty → `to_rebuild` · good → `back_to_stock` |
| Charged gate | pay before handover; payment `payable_type=replacement`; AvaTax doc code = REP number |
| In-store | no `shipments` (no carrier); handover = `replacement_out` movement; return = `return_in` |
| Money | free reps = $0; charged rep = unit + tax; refund path separate scenario |
| Slot model | `complaint.order_line_id` = order_line slot; `serial` = current unit (SN-201→211→212→213) |
| Logging | `order_events`/`order_notes`/`activity_log` apply same as ex-001/002 |
| Add-later | `complaint_lines` (multi-unit claim) · `inspections` (multi-round exam) — bolt-on, no redesign |
