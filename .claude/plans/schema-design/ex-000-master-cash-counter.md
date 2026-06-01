# ex-000 — MASTER — Full Cash / Counter Order Lifecycle (the spine)

> See [global.md](./global.md) — shared enums, column conventions, money + logging rules. **Read first.**

> _Spine, not a single literal order. It chains every **compatible** post-sale event on one order, then forks the **mutually-exclusive** terminals. Every other `ex-*` file is one **path** through this spine; this doc is the integrating map._

**What this covers:** the complete **cash / counter** (walk-in or phone, `origin=admin` force-process) order lifecycle — sale, cancel, cancel-refund, free replacement, replacement chain, charged replacement, no-fault return-to-customer, return + refund, and return reject. Card/Stripe + shipped flow = [ex-002](./ex-002-sale-shipped-card.md) (out of scope here).

**Anchor order:** ORD-2026-0020 · Marcus Webb · ECM-2024 `SN-201` (line 48) + TCM-2024 `SN-301` (line 49). Same anchor as ex-001/003/004/005. Fork A money-twin standalone = [ex-006](./ex-006-cancel-refund-cash.md) (Dana, ORD-2026-0022).

---

## How to read this

- **Spine phases (0→4)** = blocks that **chain** on one order — they can all happen in sequence, no contradiction.
- **Forks (A/B)** = **mutually-exclusive** terminals — they *replace* a spine outcome, can't co-exist with it.
- A **real order** = a path: pick a subset of phases in order. See [Coverage](#coverage--every-cash-walk-in-path).
- Full row-level data per block lives in the referenced `ex-*` file. This doc shows the **flow + the decision logic**, not every column (no duplication).

---

## The spine (one continuous timeline)

```
PHASE 0 — SALE  (cash, pay-at-pickup)                                   [ex-001]
─────────────────────────────────────────────────────────────────────────────
  place      pending · unpaid · AvaTax QUOTE · 2 lines + 3 fees, tax per line
  process    assign SN-201 + SN-301 → RESERVED (force, origin=admin, still unpaid)
  ready      ready_for_pickup  (event only — status stays processing)
  pay+pickup cash = grand_total EXACT → AvaTax COMMIT → serials SOLD → COMPLETED
                  ⑂ FORK A (pre-pickup only) = cancel / cancel-refund  ──┐
                                                                          │
PHASE 1 — COMPLAINT R1   (+7)   internal fault → FREE                   [ex-003 R1]
─────────────────────────────────────────────────────────────────────────────
  ECM SN-201 dead → complaint → intake verify (Gate 1, serial match)
  → SN-201 sold→under_examination → tech inspect (Gate 2): internal fault
  → FREE replacement SN-211 · old SN-201 → TO_REBUILD
  → SN-211 reserved→sold, handed at counter      (no payment · no AvaTax)

PHASE 2 — COMPLAINT R2   (+14)  internal fault → FREE   [chains R1]     [ex-003 R2]
─────────────────────────────────────────────────────────────────────────────
  SN-211 dead → same pipeline → FREE replacement SN-212
  → SN-211 → TO_REBUILD · SN-212 sold            (rep.parent → R1)

PHASE 3 — COMPLAINT R3   (+21)  tech inspect → 3-way decision    [ex-003 R3 / ex-004]
─────────────────────────────────────────────────────────────────────────────
  SN-212 claimed dead → intake verify → under_examination → tech inspect →
     ├─ internal fault           → FREE replacement · old → TO_REBUILD     (=Phase 1)
     ├─ no fault + cx ACCEPTS     → SAME unit back · $0 · no replacement    [ex-004]
     │      SN-212 under_examination → SOLD (back to cx) · unit_outcome=returned_to_customer
     └─ no fault / cx-DAMAGED + cx INSISTS → CHARGED replacement            [ex-003 R3]
            cx PAYS FIRST → AvaTax COMMIT (doc=REP) → SN-213 reserved→sold
            old SN-212:  damaged → TO_REBUILD  ·  good → BACK_TO_STOCK

PHASE 4 — RETURN + REFUND   (changed mind, no fault)                   [ex-005]
─────────────────────────────────────────────────────────────────────────────
  cx returns a line (e.g. TCM SN-301) → return request (complaint_id=NULL)
  → verify (Gate 1) → SN-301 sold→under_examination → inspect condition (Gate 2) →
     ├─ good            → BACK_TO_STOCK + REFUND
     ├─ faulty (our def) → REBUILD       + REFUND
     └─ not our device / cx-damaged → ⑂ FORK B (reject)  ──┐
  REFUND = original line_total (unit + tax) · per-line fees + charged-rep fee KEPT
  → AvaTax ADJUST (−unit tax) · payments kind=refund cash · return closed
```

### Forks — mutually exclusive, cannot sit on the spine

```
FORK A — CANCEL (pre-pickup) — replaces Phase-0 pickup. Goods NEVER left.
─────────────────────────────────────────────────────────────────────────────
  serials still RESERVED, order not picked up. cx: cancel + money back.
     ├─ UNPAID  → no money. release serials reserved→in_stock. status=cancelled   [ex-001 fork]
     └─ PAID    → FULL refund grand_total (units + tax + ALL fees — nothing rendered)
                  AvaTax full reversal · release serials (NO movement row)
                  refunds.reason=cancel · return_id=NULL · status=cancelled         [ex-006]
  ✗ Excludes the rest of the spine — if cancelled pre-pickup, no sale completed →
    no Phases 1–4 (nothing to complain about / return).

FORK B — RETURN REJECTED — replaces Phase-4 refund outcome.
─────────────────────────────────────────────────────────────────────────────
  inspect: not our device (serial mismatch = FRAUD) OR cx-caused damage.
  → hand unit back (stays SOLD to cx) · NO refund · NO replacement
  → returns.status=rejected · $0 moves
```

---

## Decision trees (the branching logic)

### Post-sale: what does the customer want?
```
completed order, cx returns →
  ├─ "it's broken / not working"            → COMPLAINT pipeline (Phase 1–3)
  ├─ "changed my mind, want money back"     → RETURN + REFUND (Phase 4)  · goods came back, fees KEPT
  └─ (pre-pickup) "cancel, money back"      → CANCEL (Fork A)            · goods never left, fees REFUNDED
```

### Complaint → tech inspect → decision (Phase 1–3, per [global.md](./global.md#complaints--replacements-post-sale))
```
tech inspect (Gate 2) →
  fault_found / internal_issues          → FREE replacement   · old unit → rebuild
  no_fault_found + cx ACCEPTS            → NO replacement, $0 · same unit → returned_to_customer   [ex-004]
  no_fault_found + cx INSISTS (or cx-damaged) → CHARGED replacement (pay first) · old unit:
                                               damaged → rebuild · good → back_to_stock            [ex-003 R3]
```

### Old-unit fate (independent of who pays) — drive by **physical condition**
```
old unit physically broken  → to_rebuild   (Rebuild Area)
old unit physically good     → back_to_stock (Warehouse) — or returned_to_customer if cx accepts it back
```

### Refund amount (per [global.md money rule](./global.md#money))
```
RETURN (goods came back, unit used/programmed) → refund = original line_total (unit + tax) · fees KEPT
CANCEL (goods never left, nothing rendered)    → refund = FULL grand_total (units + tax + ALL fees)
```
> Returning a **replacement** unit (e.g. SN-213) still refunds the **original** product line_total — the charged-rep fee is a service/penalty already earned → **kept**.

---

## State machines

### `orders.status`
```
pending ──process──▶ processing ──pay+handover──▶ completed   (terminal)
   │                     │
   └─────────────────────┴──cancel (pre-handover)──▶ cancelled (terminal)
```
> Two terminals, pick one: `completed` (spine) or `cancelled` (Fork A). Replacements/returns hang off a `completed` order — they don't change its terminal state.

### `inventory_serials.status` (order-flow subset)
```
in_stock ──assign──▶ reserved ──pay+handover──▶ sold
   ▲                    │                          │
   │                    └──cancel (release)────────┘  (back to in_stock, NO movement row)
   │                                               │
   │                                  complaint/return intake
   │                                               ▼
   │                                      under_examination
   │                         ┌──────────────┬──────────────┐
   └──good: back_to_stock────┘    faulty/cx-damage:    cx accepts (no-fault):
                                   to_rebuild           sold (back to same cx)
```

### `replacements` (per round) & `complaints`
```
complaint:   open → in_progress → closed
replacement: requested → approved → completed     (rejected = decision declined)
pay_status:  none (free)  ·  unpaid → paid (charged, before handover)
```

### `returns` & `refunds`
```
returns:  requested → approved → received → closed   (rejected = Fork B)
refunds:  pending → refunded
```

---

## Money gates (when does cash move?)

| event | gate (must hold first) |
|---|---|
| sale completion | `payment_status = paid` (cash collected at pickup) |
| charged replacement handover | rep `pay_status = paid` **before** `replacement_out` |
| return refund | unit **received + condition decided** (good/faulty), not rejected |
| cancel refund | order `paid` **+** serials still `reserved` (goods never left) |

> Nothing leaves the building unpaid; no money returns before goods are in hand (return) or confirmed never-left (cancel). Cash = exact full, USD, no partial ([global.md money](./global.md#money)).

---

## AvaTax across the spine

| moment | call | doc code |
|---|---|---|
| order placed | `calculateTax()` — QUOTE (uncommitted) | ORD number |
| payment received | `commitInvoice()` — COMMIT | ORD number |
| charged replacement paid | quote → `commitInvoice()` | **REP** number |
| free replacement | **none** — no sale, no money | — |
| return refund | `adjustTransaction()` — reduce by returned **unit** tax (fee tax kept) | ORD number |
| cancel refund (paid) | `adjustTransaction()` — **full** reversal (all lines + fees) | ORD number |
| cancel (unpaid) | nothing committed → **nothing to void** | — |

---

## Coverage — every cash walk-in path

| path | phases | terminal | notes |
|---|---|---|---|
| plain sale | 0 | completed | ex-001 |
| cancel, unpaid (no-show) | 0 → Fork A (unpaid) | cancelled | no money |
| cancel + refund (paid, pre-pickup) | 0 → Fork A (paid) | cancelled | full refund incl fees · ex-006 |
| free replacement | 0 → 1 | completed | ex-003 R1 |
| replacement chain | 0 → 1 → 2 → … | completed | rep.parent chain |
| no-fault, same unit back | 0 → 3 (accept) | completed | ex-004 |
| charged replacement | 0 → 3 (insist/damaged) | completed | ex-003 R3, pay-first |
| return + refund (partial) | 0 → 4 | completed | some lines · ex-005 |
| return + refund (full) | 0 → 4 (all lines) | completed | all lines, fees still kept |
| return rejected | 0 → 4 → Fork B | completed | not our device / cx-fault |
| combined | 0 → 1 → 2 → 3 → 4 | completed | the full spine |

> Every cash/counter order is one row above. Build the schema + services once from the spine; each path is a sequence of the same blocks.

---

## Invariants (must hold for every path)

- `grand_total = Σ line_totals + Σ fee_totals + shipping`; cash payment = `grand_total` **exact** (no partial).
- serial: `reserved` by **at most one** order at a time (locked while staged).
- force-process (`origin ∈ admin/web_admin`): serials may be `reserved` **before** payment; `online` reserves after paid.
- `status=completed` requires `payment_status=paid`; nothing leaves the building unpaid.
- **replacement requires a complaint** (`replacements.complaint_id` NOT NULL) — no standalone swap.
- decision → money + unit fate: internal fault → free·rebuild · no-fault+accept → free·returned_to_customer · no-fault+insist/damaged → charged·(rebuild|back_to_stock).
- charged replacement: cx **pays before handover** (`pay_status=paid` precedes `replacement_out`).
- **return** = goods back (`returns`+`return_lines`); refund = original line_total, **fees kept**. **Cancel** = goods never left (no `returns`); refund = full incl fees.
- refund issued **only after** goods received + condition decided (return) or paid+not-left confirmed (cancel).
- `reserved → in_stock` on cancel = **status only, NO `inventory_movements` row** (unit never physically moved).
- fully-refunded order keeps `payment_status=paid`; **net money = Σ payments `kind=payment` − Σ `kind=refund`** (don't read `paid` as "still held").
- complaint/return targets the **slot** (`order_line_id`); `serial` = current unit in the slot (SN-201→211→212→213).
- chain: `replacements.parent_id` self-ref; root `order_id` on each → flat count, no recursion.
- every transition → one `order_events` row (timeline) + `activity_log` (audit) + optional `order_notes` (human).

---

## TDD matrix (one verifiable case per block)

| # | block | given | when | then |
|---|---|---|---|---|
| 0.1 | place | cart 2 lines + 3 fees | place order | `pending`/`unpaid`, AvaTax quote stored per line/fee, `grand_total` = Σ |
| 0.2 | force-process | placed, unpaid | admin process | serials `reserved` (locked), `status=processing`, still `unpaid` |
| 0.3 | ready | processing | mark ready | `ready_for_pickup` **event**, status unchanged |
| 0.4 | pay+pickup | reserved, unpaid | collect exact cash | COMMIT, serials `sold`, `completed`, `payment_status=paid` |
| 0.5 | pay guard | reserved | pay ≠ grand_total | rejected (exact-full only) |
| A.1 | cancel unpaid | processing, unpaid | cancel | serials → `in_stock` (no movement), `cancelled`, no payment/refund |
| A.2 | cancel paid | paid, reserved, not picked up | cancel-refund | full refund incl fees, AvaTax full reversal, `cancelled`, net cash 0 |
| A.3 | cancel guard | already `sold` (picked up) | cancel | rejected → must be a **return** instead |
| 1.1 | free replacement | complaint, internal fault | issue free rep | new serial `sold`, old → `to_rebuild`, no payment, no AvaTax |
| 1.2 | rep needs complaint | no complaint | issue replacement | rejected (`complaint_id` required) |
| 2.1 | chain | rep R1 exists | issue R2 | `parent_id=R1`, flat count = 2 for order |
| 3.1 | no-fault accept | complaint, `no_fault_found`, cx accepts | close | same serial `sold` back to cx, `returned_to_customer`, no rep, $0 |
| 3.2 | charged insist | `no_fault_found`/cx-damaged, cx insists | issue charged rep | `pay_status=unpaid` → pay → `paid` → handover; old: damaged→rebuild / good→stock |
| 3.3 | charged gate | charged rep `unpaid` | hand over before pay | rejected (pay-first) |
| 4.1 | return good | completed line, changed mind | return + inspect good | serial `back_to_stock`, refund = line_total (unit+tax), fees kept, AvaTax adjust |
| 4.2 | return partial-amount | returned line | admin enters amount < line_total | refund = entered amount (damage/restock deduction) |
| 4.3 | refund gate | return requested, unit not received | issue refund | rejected (goods-in-hand first) |
| B.1 | reject mismatch | returned serial ≠ order line | inspect | `returns.rejected`, hand back, no refund/replacement, fraud logged |
| B.2 | reject cx-damage | cx-caused damage | inspect | `returns.rejected`, hand back, no refund |

> Each row = one Pest feature/unit test when the module is built. RED → GREEN → refactor.

---

## Open spec notes (carry into build)

1. **`return_rejected` event** — `order_events.event` ([global.md](./global.md)) has no reject value; Fork B currently maps to `return_closed {status:rejected}`. Decide: add `return_rejected` or keep metadata flag. (Surfaced from ex-005 review.)
2. **Reject reason split** — "not our device" (serial mismatch = fraud, hard-log) vs "cx-damaged" (legit unit, ineligible). Same `rejected` status, different audit weight / note.
3. **`fault_found` vs `internal_issues`** — both map to FREE; examples only use `internal_issues`. Confirm canonical fault value or document both→free.
4. **`refund_fee_lines`** — fee-level refund itemization not stored (`refund_lines` is per `order_line`); bolt-on later if fee-level reporting needed ([global.md](./global.md#money)).

> Spec only — **no code yet**. Build schema + services from this spine; each `ex-*` is a worked path through it.
