# 14 — Events × Inventory Movements × Serial Transitions

> **Layer 1 — Foundation.** Depends on `01-enums.md` and `03-schema.md`.

## Scope

The single truth table for ex-19's lifecycle. For each of the 3 events fired by `OrderService`:

- **When** it fires (which service method)
- **What** `order_events.metadata` JSON shape it carries
- **What** `inventory_movements` row is created (if any)
- **What** transitions on `inventory_serials.status`
- **What** transitions on `orders.status` and `orders.payment_status`

Every other plan file references THIS file to stay consistent.

---

## Decisions LOCKED

| Decision | Rationale | ex-19 line |
|----------|-----------|-----------|
| Serial is **allocated** at `OrderService::recordCashPayment()` — `order_lines.inventory_serial_id` set when customer pays, NOT at `store()`. Pending orders never tie up inventory. | Pending orders shouldn't lock units another walk-in might need; allocation triggers once payment confirms the order is real | 90 |
| Serial **status** stays `in_stock` through pending AND processing — only flips to `sold` at handover | Inventory mirrors physical reality: unit is still in warehouse | 195-196 |
| **NO** `inventory_movement` row created at order creation OR payment — only at handover | Movement = physical event, not accounting state | 139-141 |
| `sale` `inventory_movement` fires once, at `OrderService::recordCashPayment()` (for cash walk-in, payment = sale event) | Customer is physically at the counter receiving the unit when they pay — payment and sale are the same moment | 141 |
| All side effects of each event fire in **one DB::transaction** | Atomic — either everything commits or rolls back | — |
| `order_events.metadata` JSON shape is fixed per event type | Future-proofs reporting + timeline rendering | 154-157 |
| `order_events.created_by` = user who triggered the action | Audit trail per transition | 155-157 |
| `inventory_movements.notes` = human-readable description (no formulaic template) | Lets staff add real context | 141 |

---

## The truth table (ex-19's 3 events)

| Event | Service method | order rows touched | order_events | payments | inventory_movements | inventory_serials | order status → | payment_status → |
|-------|---------------|--------------------|--------------|----------|---------------------|-------------------|---------------|------------------|
| **`order_placed`** | `OrderService::store()` | INSERT orders, INSERT order_lines (`inventory_serial_id` **NULL**), INSERT order_line_fees | INSERT (`order_placed`) | — | — | untouched | pending | unpaid |
| **`payment_received`** | `OrderService::recordCashPayment()` | **UPDATE order_lines.inventory_serial_id (serial allocated)**, UPDATE orders.payment_status, UPDATE orders.status | INSERT (`payment_received`) | INSERT (cash, paid) | **INSERT (`sale`, Warehouse → NULL, ref=order.number)** | `in_stock` → **`sold`** | pending → **processing** | unpaid → **paid** |
| **`completed`** | `OrderService::complete()` | UPDATE orders.status | INSERT (`completed`) | — | — | unchanged (already `sold`) | processing → **complete** | paid (unchanged) |

> **Read this table left-to-right.** Each column is what must happen in the same transaction. If any step fails, the whole event rolls back.

---

## Per-event detail

### Event 1 — `order_placed`

**Fires when:** `OrderService::store($data, $createdBy)` succeeds inside its `DB::transaction`.

**`order_events.metadata` shape (JSON):**
```json
{
  "sku": "ECM-2024",
  "product_name": "Engine Control Module",
  "grand_total": "286.86"
}
```

> For multi-line orders, `sku` = first line's SKU. Use `product_name` of first line. `grand_total` always reflects final order total.

**Side effects in the same transaction:**
- `orders` row INSERTed with `status=pending`, `payment_status=unpaid`
- `order_lines` rows INSERTed with `inventory_serial_id = NULL` (serial NOT allocated yet — happens at payment)
- `order_line_fees` rows INSERTed (Programming Fee, Gas Tuning Fee, etc.)
- `inventory_serials` table — completely untouched
- `inventory_movements` NOT touched
- `payments` NOT touched

**ex-19 ref:** line 155 — `{"sku":"ECM-2024","product_name":"Engine Control Module","grand_total":"286.86"}`

**Edge cases:**
- If `order_line_fees` insertion fails → rolls back the `orders` row too (atomic)
- Out-of-stock at create is no longer a failure — admin can fix stock before payment

---

### Event 2 — `payment_received`

**Fires when:** `OrderService::recordCashPayment($order, $data, $createdBy)` succeeds inside its `DB::transaction`.

**`order_events.metadata` shape (JSON):**
```json
{
  "method": "cash",
  "amount": "286.86",
  "shipping": "0.00"
}
```

> `method` always `"cash"` for ex-19 scope. `amount` matches `payments.amount`. `shipping` matches `orders.shipping`.

**Side effects in the same transaction:**
- `order_lines.inventory_serial_id` UPDATE for each line: NULL → allocated `inventory_serials.id` (locked via `lockForUpdate` from `in_stock` pool matching the product_listing)
- `inventory_movements` INSERT (one per line): `type=sale, from_location_id=serial's current location, to_location_id=NULL, reference=order.number, notes="cash sale at counter"`
- `inventory_serials.status` UPDATE: `in_stock → sold` (per line)
- `inventory_serials.inventory_location_id` UPDATE: → `NULL` (unit no longer at any warehouse location)
- `payments` row INSERTed with `method=cash, status=paid, cash_received_at=now()`
- `orders.payment_status` UPDATE: `unpaid → paid`
- `orders.status` UPDATE: `pending → processing`
- `order_line_fees` NOT touched

**ex-19 ref:** line 156 — `{"method":"cash","amount":"286.86","shipping":"0.00"}`

**Edge cases:**
- Order already `paid` → `DomainException` (no double-payment)
- Order `status != pending` → `DomainException` (can't pay a completed order)
- Payment amount must match `orders.grand_total` exactly (no partial payments in ex-19 scope) — `DomainException` otherwise
- No in-stock serial available at allocation time → `DomainException`, whole transaction (allocation + movement + payment + status) rolls back
- UNIQUE constraint conflict on `order_lines.inventory_serial_id` → `DomainException`, rolls back

---

### Event 3 — `completed`

**Fires when:** `OrderService::complete($order, $completedBy)` succeeds inside its `DB::transaction`.

**`order_events.metadata` shape (JSON):**
```json
{}
```

> Empty object. The act of completion needs no extra payload — the event row's `created_by` + `created_at` tell the full story.

**Side effects in the same transaction:**
- `orders.status` UPDATE: `processing → complete`
- `inventory_movements` NOT touched (already created at payment)
- `inventory_serials` NOT touched (already `sold` from payment)
- `payments` NOT touched
- `order_lines` / `order_line_fees` NOT touched

> `completed` is a pure status transition. All inventory work happened at `payment_received`. The event exists as a formal closeout signal — useful for reporting (e.g. "orders awaiting completion" filter) and future flows where payment and closeout are separated.

**ex-19 ref:** line 157 — `completed {}`. The `inventory_movements` sale row referenced in ex-19 line 141 is now created at the `payment_received` event, not here.

**Edge cases:**
- Order `status != processing` → `DomainException` (can't complete pending or already-complete order)

---

## Order status state machine (ex-19 scope only)

```
pending ──[payment_received]──> processing ──[completed]──> complete
   │
   └─[orders.delete]─> (row gone — hard delete)
```

| From | To | Trigger | Event fired |
|------|-----|---------|-------------|
| `pending` | `processing` | Cash payment recorded in full | `payment_received` |
| `processing` | `complete` | Unit handed to customer | `completed` |
| `pending` | (deleted) | Admin destroys mistake order | — (audit log only, no event) |

**No other transitions exist in ex-19 scope.** Cancellations, refunds, RTS, back orders, shipments are out of scope.

---

## Payment status state machine (ex-19 scope only)

```
unpaid ──[recordCashPayment full amount]──> paid
```

| From | To | Trigger |
|------|-----|---------|
| `unpaid` | `paid` | Full cash payment recorded |

**No `partial` state in ex-19 scope.** Partial payments are out of scope.

---

## Serial status state machine (ex-19 scope only)

```
in_stock ──[OrderService::complete()]──> sold
```

| From | To | Trigger | Movement created |
|------|-----|---------|------------------|
| `in_stock` | `sold` | Unit handed over (`completed` event) | `sale` movement |

**Allocation (`order_lines.inventory_serial_id` set) does NOT change the serial's status.** The UNIQUE constraint on `order_lines.inventory_serial_id` prevents double-allocation while still allowing the serial to remain `in_stock` until physically sold.

---

## Atomic transaction rule

Every service method that emits an event must execute these steps in **one `DB::transaction`**:

```php
DB::transaction(function () use (...) {
    // 1. mutate orders / order_lines / order_line_fees / payments / inventory_*
    // 2. INSERT the order_events row in the SAME transaction
    // 3. AuditLogService::log(...) (also in the same transaction)
});
```

**Invariant:** if the `order_events` row is in the DB, every side effect listed in the truth table above is also in the DB. If anything fails, ALL of it rolls back (including the event row).

---

## Dependencies

**Depends on:**
- `01-enums.md` — `OrderEvent`, `OrderStatus`, `PaymentStatus` cases
- `03-schema.md` — `order_events`, `inventory_movements`, `payments`, `inventory_serials` tables
- Existing: `InventoryMovementService::recordSale()` (extended if needed)

**Depended on by:**
- `07-service.md` — service methods implement exactly what this table says
- `11-controller.md` — controller actions delegate to service
- `12-views.md` — timeline rendering uses `metadata` shape
- `15-tests.md` — every test asserts this table's rows
- `16-audit-log.md` — audit log entries fire in same transactions

---

## Validation gates

- [ ] Every event has a defined `metadata` JSON shape
- [ ] Every event has a defined service method as its single trigger
- [ ] Every status transition is mapped to exactly one event
- [ ] No state change happens outside a `DB::transaction`
- [ ] Atomic invariant holds: `order_events` row implies all side effects are present
- [ ] Serial UNIQUE constraint enforced at DB level (per `03-schema.md`)
- [ ] No `inventory_movement` row created on `order_placed` or `payment_received`
- [ ] Exactly one `inventory_movement` (type=`sale`) created on `completed`
- [ ] Serial `status` flips ONLY on `completed`

---

## Cross-check vs ex-19

| ex-19 fact | This file says |
|------------|----------------|
| `order_events` has exactly 3 rows (line 155-157) | `order_placed`, `payment_received`, `completed` — one each, exactly |
| `payments` has exactly 1 row (line 123-124) | Created in `payment_received` event transaction |
| `inventory_movements` has 2 rows (line 139-141) — receive + sale | Only `sale` is fired by Orders module; `receive` came from PO module |
| `inventory_serials.SN-200.status = sold` at end (line 134) | Transition fires only on `completed` |
| `orders.status = complete` at end (line 73) | After `completed` event |
| `orders.payment_status = paid` (line 73) | After `payment_received` event |
| `inventory_serial_id = SN-200` on order_line at row creation (line 90) | Allocation at `order_placed` time |

All ex-19 facts mapped to events. No contradictions.
