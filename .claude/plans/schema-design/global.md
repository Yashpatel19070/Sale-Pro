# global.md — shared rules for schema-design examples

Single source of truth for the `ex-*` examples. Read this first. Examples = data flow; this = the rules.

> _Examples are illustrative — IDs/numbers/timestamps not real._

---

## Examples
| File | Scenario |
|------|----------|
| **ex-000** | **MASTER — full cash/counter lifecycle** (spine + cancel/cancel-refund/free+charged replacement/chain/return-to-cx/return+refund). Decision tree + state machine + TDD matrix. |
| ex-001 | Pickup, cash. Admin force-process. Completed at counter handover. |
| ex-002 | Shipped, card (Stripe). Admin force-process. Completed on ship. |
| ex-003 | Replacement chain (extends ex-001). complaints + replacements, free + charged. |
| ex-004 | Complaint, no-fault → unit returned to customer (extends ex-001). No replacement, no charge. |
| ex-005 | Return + refund (partial, in-store cash, extends ex-001). Unit back to stock, fees kept. |
| ex-006 | Cancel + refund (full, cash, standalone — ORD-2026-0022). Paid order, cx cancels before pickup. Goods never left → refund all (fees too), `return_id` NULL. |

---

## Enums (all in PHP — backed enum + cast + FormRequest validation; DB column = plain `string`, never MySQL `ENUM`. New value = PHP change, no migration.)

| Table.column | Values |
|---|---|
| `orders.status` | `pending` · `processing` · `completed` · `cancelled` |
| `orders.payment_status` | `unpaid` · `paid` |
| `orders.source` | `walk_in` · `phone` · `web` · … |
| `orders.origin` | `online` · `admin` · `web_admin` |
| `inventory_serials.status` | `in_stock` · `reserved` · `sold` · `under_examination` · `to_rebuild` |
| `inventory_movements.type` | `receive` · `sale` · `return_in` · `transfer` · `adjustment` · `replacement_out` |
| `order_events.event` | `order_placed` · `processing` · `ready_for_pickup` · `payment_received` · `shipped` · `completed` · `order_cancelled` · `complaint_opened` · `complaint_examined` · `complaint_closed` · `replacement_issued` · `return_requested` · `refunded` · `return_closed` |
| `order_notes.type` | `private` · `customer` |
| `payments.method` | `cash` · `card` · … |
| `payments.status` | `paid` · `refunded` |
| `payments.payable_type` | `order` · `replacement` · `refund` |
| `payments.kind` | `payment` · `refund` |
| `shipments.status` | `pending` · `shipped` · `in_transit` · `delivered` |
| `shipments.direction` | `outbound` · `inbound` |
| `shipments.shippable_type` | `order` · `complaint` · `replacement` |
| `complaints.status` | `open` · `in_progress` · `closed` |
| `complaints.examination_result` | `fault_found` · `internal_issues` · `no_fault_found` |
| `complaints.unit_outcome` | `rebuild` · `back_to_stock` · `returned_to_customer` |
| `replacements.type` | `free` · `charged` |
| `replacements.pay_status` | `none` · `unpaid` · `paid` |
| `replacements.status` | `requested` · `approved` · `completed` · `rejected` |
| `returns.status` | `requested` · `approved` · `received` · `closed` · `rejected` |
| `returns.reason` | `changed_mind` · `not_needed` · `defective` · `other` |
| `return_lines.condition` | `good` · `faulty` |
| `return_lines.restock` | `back_to_stock` · `rebuild` |
| `refunds.status` | `pending` · `refunded` |
| `refunds.reason` | `return` · `cancel` · `adjustment` · `other` |

---

## Column conventions
- **payments**: `received_at` + `received_by` (all methods — not `cash_received_at`). Stripe ids deferred.
- **audit cols**: `created_by`, `created_at` on every table. `deleted_at` nullable = soft delete (e.g. `order_notes`).
- **polymorphic**: `payments.payable` (`order`/`replacement`) · `shipments.shippable` (`order`/`complaint`/`replacement`).
- **numbers**: pattern **`<PREFIX>-<YYYY>-<NNNN>`** — per-module **continuous** sequence (never resets; year = label, swaps Jan 1 but count keeps going). Zero-pad **min 4**; grows past 9999 → 5+ digits, **no cap** (counter = BIGINT). Prefixes: `ORD` order · `CMP` complaint · `REP` replacement · `RET` return · `REF` refund. DB sequence (never `SELECT max()+1` — deadlock/hot-row).
- **FK naming**: foreign keys = `<parent>_id` (`order_id`, `order_line_id`, `complaint_id`, `replacement_id`, `parent_id`). Exception: `serial` = natural key (the `SN-xxx` value), not a surrogate id.
- **order ref**: internal FK = order **id** · human/external ref = order **number**.

---

## Money
- **USD only**, **exact full** payment (no partial). One settling payment per payable.
- **tax = AvaTax API**, per line/fee, rounded 2 dp. Never hand-entered.
- AvaTax: quote at placement → **commit on payment**; doc code = order (or REP) number. Void = future.
- **shipping**: `orders.shipping` = charged to customer (revenue, on receipt). `shipments.label_cost` = our carrier cost (expense, admin-only, off receipt). Margin = charge − Σ label_cost. Free shipping → charge 0, label_cost still recorded.
- **receipt rule**: show **`orders.shipping` only** (paid = amount · free = $0/"Free"). **Never show `label_cost`** — internal. Same rule both cases; only the value differs.
- charged thing ships/hands-over **only after `paid`**.
- **refund**: own entity — **`refunds`** (header) + **`refund_lines`** (per-item `amount`+`tax`, for accounting/tax reversal). `refunds.return_id` **nullable** → set for return-refund, NULL for cancel-refund (no goods). Money movement = `payments` `kind=refund`, `payable_type=refund`. Method = **cash or card (Stripe refund)**.
  - default amount = returned line's `line_total` (unit + tax); per-line fees + charged-rep fees = **service rendered → non-refundable**. Same default whether simple / free-chain / charged-chain.
  - **admin-entered** — full (default) or **partial**. **Partial two ways**: by **line** (some items) + by **amount** (< line_total).
  - AvaTax `adjustTransaction` on original invoice for the refunded tax.
  - **`refund_lines.amount`** = pre-tax $ refunded for that `order_line` (unit — **plus its fees when the whole line/order is cancelled**); `tax` = matching tax. ex-005 (return, fees kept) → `amount` = unit only · ex-006 (cancel, fees refunded) → `amount` = unit + fees. Per-**fee** itemization not stored (`refund_lines` is per `order_line`) → add `refund_fee_lines` later if fee-level reporting needed (bolt-on).
  - **Reporting:** a fully-refunded order keeps `orders.payment_status = paid` — **net money = Σ `payments` `kind=payment` − Σ `kind=refund`**. Don't read `payment_status=paid` as "money still held"; join `refunds`/`payments`.

---

## Order lifecycle
- **Force-process** (`origin ∈ admin/web_admin`): serials `reserved` at process decision, **before payment**. `online` = reserve after paid.
- **Ship/complete gate**: nothing ships until `payment_status = paid` (all origins).
- **Completion**: pickup → `completed_at`/`completed_by` at handover. Shipped → completed on ship.
- **`delivered_at`/`delivered_by` = deferred** (future delivery-confirmation feature; not in schema now).
- **Serial slot**: `order_line` = slot; `inventory_serials` = which unit fills it.

---

## Cancel vs return vs refund
- **Cancel** = order cancelled **before goods leave** (serials `reserved`) → release → `in_stock`, `status=cancelled`. Two cases by payment:
  - **unpaid** → no money (ex-001).
  - **paid** → **full refund** (units + tax + **all fees** — nothing rendered), `refunds.reason=cancel`, `return_id=NULL` (ex-006).
- **Return** = **goods** come back → `returns` + `return_lines` (condition, restock: `back_to_stock`/`rebuild`). Standalone (`complaint_id` nullable). ex-005.
- **Refund** = **money** back → `refunds` + `refund_lines` (per-item $/tax). `return_id` nullable: set for return-refund (ex-005), NULL for cancel-refund (paid order cancelled, no goods). Movement = `payments` `kind=refund`.
- Clean split: **goods = returns · money = refunds** — linked when both happen.

---

## Complaints + replacements (post-sale)
- **Mandatory pipeline, no skip, no goodwill**: file complaint → intake verify (serial match, anti-fraud) → tech inspect → decision.
- **Replacement requires a complaint** (`replacements.complaint` NOT NULL). No standalone swap.
- **Decision drives money + unit fate:**
  - `fault_found` / `internal_issues` → **free** replacement · old unit → `rebuild`
  - `no_fault_found` + cx **accepts** → **no replacement, no charge** · same unit → `returned_to_customer` (ex-004)
  - `no_fault_found` + cx **insists** on new unit → **charged** replacement (cx pays, AvaTax) · old unit → `back_to_stock` (ex-003 R3)
- **Chain**: `replacements.parent` self-ref. Root `order` on every rep → count = reps where `order=N` (flat, no recursion).
- **Add-later (bolt-on, no redesign)**: `complaint_lines` (multi-unit claim) · `inspections` (multi-round exam).

---

## Three logs (no overlap)
- **`order_events`** = **admin/user-facing timeline** — order lifecycle **+ complaint/replacement milestones** (`complaint_opened`/`complaint_examined`/`complaint_closed`/`replacement_issued`). The full picture admin + customer see.
- **`activity_log`** (spatie/laravel-activitylog) = **developer** audit — who-did-what CRUD, `causer` = user. Not customer-facing.
- **`order_notes`** = human free-text (`private`/`customer`).
