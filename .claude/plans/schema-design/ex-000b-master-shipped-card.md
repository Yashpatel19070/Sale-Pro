# ex-000b — MASTER — Full Card / Shipped Order Lifecycle (second spine)

> See [global.md](./global.md) — shared enums, column conventions, money + logging rules. **Read first.**
> Twin of [ex-000](./ex-000-master-cash-counter.md) (cash/counter). Same skeleton, **different fulfillment + payment**: card (Stripe) paid before ship, goods leave by **carrier**, order completes **on ship**.

> _Spine, not a literal order. The **decision logic** (complaint→inspect→decision, refund amount, cancel-vs-return) is **identical to [ex-000](./ex-000-master-cash-counter.md)** — read that for the trees. This doc shows only what **shipped/card changes**: payment timing, carrier legs, shipping money._

**What this covers:** card / shipped (`origin=admin` force-process, `source=phone/web`, Stripe link) — sale, cancel, cancel-refund, replacement, return + refund — all with **carrier movement** instead of counter handover.

**Anchor order:** ORD-2026-0021 · Marcus Webb · ECM-2024 `SN-203` (line 52) + TCM-2024 `SN-303` (line 53) · shipping address 9001 Westheimer Rd · `orders.shipping=20.00`.

---

## ⚠ Provenance (what's example-backed vs derived)

| spine part | source | status |
|---|---|---|
| **Phase 0 sale (card, ship, complete-on-ship)** | [ex-002](./ex-002-sale-shipped-card.md) | ✅ example-backed |
| **Fork A — cancel unpaid, pre-ship** | [ex-002](./ex-002-sale-shipped-card.md) | ✅ example-backed |
| shipping charge vs `label_cost`, margin, receipt rule | [ex-002](./ex-002-sale-shipped-card.md) + [global.md](./global.md#money) | ✅ example-backed |
| **decision logic** (fault→free, no-fault→accept/insist, refund amount, cancel-vs-return) | [ex-000](./ex-000-master-cash-counter.md) / ex-003/004/005/006 | ✅ (counter examples) |
| **post-sale CARRIER legs** (cx ships unit back = inbound · we ship replacement = outbound) | [global.md](./global.md) enums only (`direction=inbound`, `shippable_type=complaint/replacement`) | ⚠ **DERIVED — no worked shipped post-sale example exists** |
| who pays return shipping, RMA/inbound-label issuance | — | ❓ **UNSPECIFIED — needs a decision (see open notes)** |

> Bottom line: the **shipped sale** is solid (ex-002). The **shipped complaint/return** is logical extrapolation — every counter "handover at counter" becomes an **outbound shipment**, every "drops at counter" becomes an **inbound shipment**. Confirm before building.

---

## Deltas vs the cash spine (the only real differences)

| dimension | cash/counter ([ex-000](./ex-000-master-cash-counter.md)) | card/shipped (this) |
|---|---|---|
| payment method | cash, exact full | card via Stripe link, exact full |
| payment timing | **at pickup** (end) | **after process, before ship** (middle) |
| fulfillment | counter handover | **carrier** (UPS/etc.) |
| completion trigger | handover at counter | **on ship** (`shipped` → `completed`) |
| sale movement fires | at pickup | **at ship time** |
| extra table | — | **`shipments`** (carrier, tracking, `label_cost`) |
| extra money | — | `orders.shipping` (revenue) **+** `shipments.label_cost` (expense, admin-only) |
| goods come back (post-sale) | cx walks in | **inbound shipment** (`direction=inbound`) |
| goods go out (replacement) | counter | **outbound shipment** |
| AvaTax ship-to | billing addr | **shipping addr** |

> Everything else — enums, force-process reserve-before-pay, pay-before-goods-leave gate, refund amounts, slot model, 3 logs, AvaTax quote→commit→adjust — is **identical** to the cash spine.

---

## The spine (one continuous timeline)

```
PHASE 0 — SALE  (card, shipped)                                        [ex-002]
─────────────────────────────────────────────────────────────────────────────
  place      pending · unpaid · AvaTax QUOTE (ship-to = shipping addr) · orders.shipping=20.00
  process    assign SN-203 + SN-303 → RESERVED (force, origin=admin, still unpaid)
  pay (card) Stripe link paid → AvaTax COMMIT → payment_status=paid   ◄ pay BEFORE ship
  ship       buy label (shipments: outbound, carrier, tracking, label_cost=12.40)
             → sale movement (Warehouse→NULL) → serials SOLD → shipped event
             → COMPLETED on ship (completed_at = shipped_at)
                  ⑂ FORK A (pre-ship only) = cancel / cancel-refund  ──┐
                                                                        │
POST-SALE (after delivered) — SAME decision logic as ex-000, + carrier legs   ⚠ DERIVED
─────────────────────────────────────────────────────────────────────────────
PHASE 1/2 — COMPLAINT → FREE replacement (chain)
  cx ships faulty unit back  → INBOUND shipment (direction=inbound, shippable=complaint)
  → intake verify → under_examination → tech: internal fault
  → FREE replacement → we ship new unit → OUTBOUND shipment (shippable=replacement)
  → old unit → to_rebuild · new serial reserved→sold (on outbound ship)
  (free → no charge for the unit; return-shipping policy = OPEN, see notes)

PHASE 3 — COMPLAINT → 3-way decision (same as ex-000)
  ├─ internal fault            → FREE replacement (ship out)
  ├─ no fault + cx ACCEPTS     → ship SAME unit back to cx · $0 · no replacement
  └─ no fault / cx-damaged + INSISTS → CHARGED replacement
        cx PAYS FIRST (card) → COMMIT (doc=REP) → ship new unit out
        old: damaged → to_rebuild · good → back_to_stock

PHASE 4 — RETURN + REFUND  (changed mind)
  cx ships unit back → INBOUND shipment → verify → under_examination → inspect →
     ├─ good            → back_to_stock + REFUND
     ├─ faulty (our def) → rebuild       + REFUND
     └─ not our device / cx-damaged → ⑂ FORK B (reject) ──┐
  REFUND = original line_total (unit + tax) · fees + charged-rep fee KEPT
  → AvaTax ADJUST · refund to CARD (Stripe refund) or cash · return closed
```

### Forks (mutually exclusive)

```
FORK A — CANCEL (pre-ship) — replaces Phase-0 ship. Goods NEVER shipped.
─────────────────────────────────────────────────────────────────────────────
  serials still RESERVED, label not bought, nothing shipped. cx: cancel.
     ├─ UNPAID → no money · release serials reserved→in_stock · cancelled         [ex-002]
     └─ PAID   → FULL refund grand_total incl shipping charge + all fees
                 (nothing shipped, nothing rendered) · Stripe refund · AvaTax full reversal
                 ⚠ DERIVED — ex-006 is the cash twin; card-refund-pre-ship not separately worked
  ✗ Excludes Phases 1–4 (nothing to complain about / return).
  ⚠ Once shipped → goods left → it's a RETURN (Phase 4), not a cancel.

FORK B — RETURN REJECTED — replaces Phase-4 refund outcome.
─────────────────────────────────────────────────────────────────────────────
  inspect: not our device (serial mismatch = FRAUD) OR cx-caused damage.
  → unit stays SOLD to cx · ship it back out (OUTBOUND) · NO refund · NO replacement
  → returns.status=rejected · who-pays-return-ship = OPEN
```

---

## Shipping money (the shipped-only model) — from [ex-002](./ex-002-sale-shipped-card.md) + [global.md](./global.md#money)

```
orders.shipping     = $20.00   what CUSTOMER pays (revenue) — ON receipt, part of grand_total
shipments.label_cost= $12.40   what WE pay carrier (expense) — ADMIN-ONLY, NOT on receipt, NOT in grand_total
margin              = 20.00 − 12.40 = $7.60
```
- **Receipt rule:** show `orders.shipping` only (paid → `$20.00` · free → `$0.00`/"Free"). **Never** `label_cost`.
- **Free shipping:** `orders.shipping=0` but `label_cost` **still recorded** — we still pay the carrier; spend tracked.
- Every label = an **outbound** shipment row → total shipping spend **per order / per year**.
- `orders.shipping` is **not taxed** in the example (flat charge).

---

## Reused from cash spine (do not re-derive)

These are **identical** — see [ex-000](./ex-000-master-cash-counter.md):
- **Decision tree** — complaint → inspect → (fault→free · no-fault+accept→same-unit · no-fault/damaged+insist→charged).
- **Old-unit fate** — broken→rebuild · good→back_to_stock.
- **Refund amount** — return → original line_total (fees kept) · cancel → full incl fees.
- **Cancel vs return** — goods left? → return · never left? → cancel.
- **Slot model**, **chain (parent_id)**, **3 logs**, **money exact-full**.

Only the **physical movement** changes: counter handover ↔ shipment leg.

---

## State machines (shipped deltas)

### `orders.status`
```
pending ─process─▶ processing ─pay(card)─▶ processing ─ship─▶ completed   (terminal)
   │                   │
   └───────────────────┴── cancel (pre-ship) ─▶ cancelled (terminal)
```
> Completion = **on ship** (not on delivery — `delivered_at`/`delivered_by` deferred, future feature). Pay is a mid-step, not the terminal trigger.

### `inventory_serials.status`
```
in_stock ─assign─▶ reserved ─SHIP─▶ sold      (sale movement at ship, not at pay)
   ▲                  │
   └─ cancel pre-ship ┘  (release, no movement row)
   post-sale: sold ─inbound ship in─▶ under_examination ─▶ {to_rebuild | back_to_stock | sold-back(outbound)}
```

### `shipments` (the new table)
```
direction: outbound (to cx: sale, replacement, reject-return-out) · inbound (from cx: complaint/return goods in)
status:    pending → shipped   (in_transit / delivered = future)
shippable: order | complaint | replacement
label_cost recorded on EVERY outbound (even free shipping)
```

---

## Money gates (shipped)

| event | gate |
|---|---|
| **ship** (sale) | `payment_status = paid` — **nothing ships unpaid**, all origins |
| charged replacement ship-out | rep `pay_status = paid` (card) before outbound |
| return refund | inbound unit **received + condition decided**, not rejected |
| cancel refund | order `paid` **+** **not yet shipped** (serials `reserved`) |

> Reserve can precede pay (force-process, `origin=admin`). **Ship can never precede pay.** Same as cash, but the gated action is "ship" not "hand over".

---

## AvaTax (shipped specifics)

| moment | call | note |
|---|---|---|
| place | `calculateTax()` QUOTE | **ship-to = shipping address** (rate from shipping addr) |
| force-process | — | does **not** touch AvaTax |
| pay (card) | `commitInvoice()` | doc = ORD number |
| charged replacement | quote → `commitInvoice()` | doc = **REP** number |
| free replacement | none | no sale |
| return refund | `adjustTransaction()` | reduce by returned unit tax |
| cancel refund (paid, pre-ship) | `adjustTransaction()` full reversal | ⚠ derived (card) |
| cancel (unpaid) | none | quote never committed |

---

## Coverage — card/shipped paths

| path | phases | terminal | source |
|---|---|---|---|
| plain shipped sale | 0 | completed | ✅ ex-002 |
| cancel, unpaid pre-ship | 0 → Fork A (unpaid) | cancelled | ✅ ex-002 |
| cancel + refund (paid, pre-ship) | 0 → Fork A (paid) | cancelled | ⚠ derived |
| free replacement (ship legs) | 0 → 1 | completed | ⚠ derived |
| replacement chain | 0 → 1 → 2 → … | completed | ⚠ derived |
| no-fault, same unit shipped back | 0 → 3 (accept) | completed | ⚠ derived |
| charged replacement (ship out) | 0 → 3 (insist) | completed | ⚠ derived |
| return + refund (inbound) | 0 → 4 | completed | ⚠ derived |
| return rejected (ship back) | 0 → 4 → Fork B | completed | ⚠ derived |

---

## Invariants (shipped — deltas only; rest inherit from [ex-000](./ex-000-master-cash-counter.md))

- **nothing ships until `payment_status = paid`** (all origins).
- serial `reserved → sold` at **ship time** (not at pay, not at pickup).
- completion = **paid + shipped** → `completed_at`/`completed_by` = ship time. `delivered_at`/`delivered_by` deferred.
- `grand_total = Σ line_totals + Σ fee_totals + orders.shipping`; card payment = `grand_total` exact.
- `shipments.label_cost` is **never** on the receipt, **never** in `grand_total`; recorded even on free shipping.
- every goods movement = a `shipments` row: out (sale/replacement/reject-back) = `outbound` · in (complaint/return) = `inbound`.
- cancel = **before ship** (serials `reserved`); once shipped → return path only.

---

## TDD matrix (shipped-specific; counter cases inherit from [ex-000](./ex-000-master-cash-counter.md))

| # | block | given | when | then |
|---|---|---|---|---|
| 0.1 | place shipped | cart + shipping addr | place | quote ship-to = shipping addr, `orders.shipping` set, in `grand_total` |
| 0.2 | pay-before-ship gate | processing, **unpaid** | attempt ship | rejected (must be paid) |
| 0.3 | ship | paid, reserved | buy label + ship | `shipments` outbound row, `label_cost` recorded, serials `sold`, `completed` on ship |
| 0.4 | receipt | shipped order | render receipt | shows `orders.shipping` only, **never** `label_cost` |
| 0.5 | free shipping | `orders.shipping=0` | ship | receipt "Free", `label_cost` still recorded |
| 0.6 | margin | shipped | report | margin = shipping − Σ label_cost |
| A.1 | cancel unpaid pre-ship | processing, unpaid, no label | cancel | serials → in_stock, cancelled, no shipment |
| A.2 | cancel paid pre-ship | paid, reserved, not shipped | cancel-refund | full refund incl shipping + fees, Stripe refund, AvaTax reversal |
| A.3 | cancel guard | already shipped | cancel | rejected → must be a return |
| 4.1 | return inbound | shipped order, changed mind | cx ships back, inspect good | inbound shipment, back_to_stock, refund line_total, Stripe/cash refund |
| 4.2 | refund gate | return requested, unit not received | issue refund | rejected (inbound goods-in-hand first) |
| 1.1 | replacement ship | complaint, internal fault | issue free rep | inbound (old) + outbound (new) shipments, old→rebuild, new→sold on ship |
| 3.2 | charged ship gate | charged rep unpaid | ship new unit | rejected (pay-first) |
| B.1 | reject + ship back | inbound serial mismatch | inspect | rejected, outbound ship-back, no refund, fraud logged |

---

## Open spec notes (shipped — decide before building)

1. **Return shipping cost — who pays?** Defective (our fault) vs changed-mind vs reject — return-shipping policy unspecified. Likely: our fault → we pay inbound label · changed-mind → cx pays · reject → cx pays ship-back. **Needs decision.**
2. **RMA / inbound label** — do we issue an inbound shipment + label for the customer, or do they self-ship? `shipments.direction=inbound` exists; the **issuance flow** isn't worked anywhere.
3. **Refund method on card orders** — Stripe refund (to original card) vs cash. global.md allows both; default should likely = **match original method** (card→Stripe). Confirm.
4. **`shipped` vs `delivered` completion** — currently completes **on ship**; delivery confirmation deferred. If returns/replacements need "delivered" state, the deferred `delivered_at` + `delivered` event come back into scope.
5. **All cash-spine open notes still apply** — `return_rejected` event, reject-reason split, `fault_found` vs `internal_issues`, `refund_fee_lines` (see [ex-000](./ex-000-master-cash-counter.md#open-spec-notes-carry-into-build)).

> **Honesty marker:** Phase 0 + Fork-A-unpaid = ex-002-backed. **All post-sale carrier blocks = derived** from global.md enums + cash-spine logic — no worked shipped complaint/return example exists. Validate the carrier legs (esp. inbound-label + return-shipping cost) before coding. **No code yet** — spec only.
