## Agent Rules — Read This First

### Column name convention
Example tables use **simplified display names** for readability. Actual DB column names differ. Always check the migration file for the real column name before writing any query or code.

| Example shows | Actual DB column | Table |
|---------------|-----------------|-------|
| `serial` | `serial_number` | `inventory_serials` |
| `location` | `inventory_location_id` (FK → `inventory_locations`) | `inventory_serials` |
| `note` | `notes` | `inventory_serials` |
| `serial` | `inventory_serial_id` (FK → `inventory_serials`) | `inventory_movements` |
| `from` | `from_location_id` (FK → `inventory_locations`) | `inventory_movements` |
| `to` | `to_location_id` (FK → `inventory_locations`) | `inventory_movements` |
| `order` | `order_id` (FK → `orders`) | `order_lines`, `order_fees`, `payments`, etc. |
| `parent` | `parent_id` (FK → `replacements`) | `replacements` |
| `line` | `order_line_id` (FK → `order_lines`) | `complaints` |
| `complaint` | `complaint_id` (FK → `complaints`) | `replacements` |
| `rep` | `replacement_id` (FK → `replacements`) | `replacement_lines` |
| `order_line` | `order_line_id` (FK → `order_lines`) | `replacement_lines` |

### FK value convention
FK columns show **referenced display values** in examples, not raw integer IDs.

| Example value | What DB actually stores |
|---------------|------------------------|
| `SN-001` in `inventory_serial_id` | integer FK e.g. `1` |
| `Warehouse A` in `from_location_id` | integer FK e.g. `2` |
| `ORD-2026-001` in `reference` | string — stored as-is ✓ |

### PHP enum files
| Status/type column | PHP enum | DB constraint |
|--------------------|----------|---------------|
| `inventory_serials.status` | `app/Enums/SerialStatus.php` | `string(50)` — PHP is the guard |
| `inventory_movements.type` | `app/Enums/MovementType.php` | MySQL ENUM — DB enforces values |

### Migration files
| Table | Migration |
|-------|-----------|
| `inventory_locations` | `2026_04_14_180000_create_inventory_locations_table.php` |
| `inventory_serials` | `2026_04_14_181000_create_inventory_serials_table.php` |
| `inventory_movements` | `2026_04_14_182000_create_inventory_movements_table.php` |

### Shipment delivery address vs order snapshot
- `orders.shipping_snapshot` — JSON captured at order creation; **immutable after placement**. Reflects customer's intended delivery address when they placed the order.
- `shipments.customer_address_id` — FK → `customer_addresses`; the actual address used **for that shipment attempt**. For re-ship after RTS, the new shipment row references a different `customer_address_id` than the original — do NOT read the order snapshot for the re-ship address.

> Query pattern: `JOIN customer_addresses ON shipments.customer_address_id = customer_addresses.id`

> **Shipment examples (Ex 1–13):** `customer_address_id` is omitted from shipment tables for brevity. In real data, all outbound carrier shipments require `customer_address_id` FK → `customer_addresses`. Inbound returns and in-store pickup shipments use NULL. See Example 14 for the full pattern including RTS re-ship with two different address IDs.

### payments.order_id — always populated
`payments.order_id` (FK → `orders`) is set on every payment row, regardless of `payable_type`:
- `payable_type = order` → `order_id = payable_id` (same value, order paid directly)
- `payable_type = replacement` → `order_id = parent order` (replacement charged back to original order)

> Use `WHERE payments.order_id = ?` to fetch **all** payments for an order (direct + replacement charges) without JOINing through `replacements`.

### order_lines — one serial per line
`order_lines` has no `quantity` column. One line = one physical unit = one serial number. Multiple units on a single order = multiple `order_lines` rows. This is by design — each serial has its own complaint, replacement, and examination lifecycle. Grouping units into one line with qty > 1 would break complaint tracking.

### inventory_movements — example IDs are illustrative
IDs in examples are for reference within each order/complaint chain. Within a single chain, IDs are chronological. Cross-example IDs do not reflect exact global insertion order — concurrent orders interleave in real insertion sequence. Never hardcode example IDs in queries or seeds.

### Example data is illustrative only
All IDs, timestamps, amounts, serial numbers, and sequence numbers in these examples are dummy values chosen for readability. They do not reflect real insertion order, global auto-increment sequences, or production data. Never infer "there must be N prior records" from an ID value — IDs are illustrative, not authoritative.

---

## Payment Rule (Global)

```
No payment  → order NOT placed
Full payment received → order placed → processing → ships
```

`orders.payment_status` = `unpaid | paid` only. No partial.

---

## Global Reference — Staff Users

Used across all examples. Role assigned via Spatie `model_has_roles` pivot — not a column on `users`.

**`departments`**
```
id  name              code   is_active
1   Administration    ADMIN  true
2   Warehouse         WH     true
3   Technical Repair  TECH   true
4   Customer Service  CS     true
```

**`users`**
```
id  name        email               department_id  job_title          status
1   Admin User  admin@salepro.com   1              Administrator      active
2   Ali Hassan  ali@salepro.com     2              Warehouse Staff    active
3   Sam Chen    sam@salepro.com     3              Technician         active
4   Raj Patel   raj@salepro.com     4              CS Representative  active
```

Roles (via pivot):
- User 1 → `admin`
- Users 2, 3, 4 → `sales`

## Global Reference — Status Enums

### `orders.status`
| Value | Meaning | Terminal? |
|-------|---------|-----------|
| `pending` | Order created, payment not yet received | no |
| `processing` | Payment received, not yet shipped/picked up | no |
| `shipped` | Carrier has the package (UPS/FedEx) — no delivery confirmation in system | no |
| `complete` | In-store pickup — customer collected at counter | no |
| `cancelled` | Cancelled before shipment/pickup | yes |
| `refunded` | Full refund issued — post-delivery return or post-pickup complaint escalation | yes |
| `rts` | Return-to-sender — carrier returning package to warehouse | no |

> `shipped` stays as final status for carrier orders — admin records `delivered_at` manually, no status change on delivery.
> `complete` stays as final status for pickup orders — transitions to `refunded` only if admin issues a refund after complaint escalation. Orders have no `closed` status — `closed` belongs to `complaints` only.
> `cancelled_at` column reused as terminal timestamp for both `cancelled` and `refunded` states.

### `complaints.status`
| Value | Meaning | Terminal? |
|-------|---------|-----------|
| `open` | Complaint created, unit not yet received | no |
| `in_progress` | Unit received (Flow A) or replacement shipped (Flow B) | no |
| `closed` | Resolved — examination complete, outcome recorded | yes |
| `withdrawn` | Customer withdrew complaint before examination | yes |

> Flow A: `in_progress` set when unit physically arrives (`return_in` movement recorded).
> Flow B: `in_progress` set when replacement ships — old unit not yet received.

### `complaints.examination_result`
| Value | Meaning |
|-------|---------|
| `internal_issues` | Internal component failure or manufacturing defect — warranty applies |
| `damaged_by_customer` | Physical damage caused by customer — warranty voided |
| `no_fault_found` | Unit fully functional — no defect detected |
| `NULL` | Not yet examined, or complaint withdrawn before examination |

### `complaints.unit_outcome`
| Value | Meaning |
|-------|---------|
| `scrapped` | Unit destroyed — confirmed fault or unrecoverable damage |
| `returned_to_customer` | Unit handed back to customer — no fault found |
| `back_to_stock` | Unit returned to warehouse inventory — no fault, unit reusable |
| `NULL` | Not yet determined, or complaint withdrawn before examination |

### `inventory_serials.status`
| Value | Meaning |
|-------|---------|
| `in_stock` | Available in warehouse, assignable to orders |
| `sold` | With customer |
| `assigned` | In transit as replacement — shipped, not yet with customer |
| `expected_return` | Return requested — awaiting inbound shipment from customer |
| `under_examination` | Unit received, being examined by technician |
| `scrapped` | Written off — internal fault, customer damage, or warehouse write-off |
| `missing` | Expected return never arrived — written off after admin decision |

---

## Global Reference — Inventory Movements

> **Code references:**
> - Movement types: `app/Enums/MovementType.php` — PHP enum backing `inventory_movements.type` MySQL ENUM
> - Serial statuses: `app/Enums/SerialStatus.php` — PHP enum backing `inventory_serials.status` string column
> - Locations: `inventory_locations` DB table — `from_location_id` / `to_location_id` are FK integers pointing to this table. Location names shown in examples are display values (`inventory_locations.name`), not raw column values.

> **ID ordering note:** movement IDs in examples are illustrative. Within a single order/complaint chain they are chronological. Cross-example IDs may not reflect exact global insertion order — 14 concurrent orders interleave in real sequence. `--` rows represent intermediate IDs belonging to other orders' movements in the global ledger.

### `inventory_movements.type`

| Type | PHP case | Meaning |
|------|----------|---------|
| `receive` | `MovementType::Receive` | PO receiving — new stock arriving from supplier |
| `sale` | `MovementType::Sale` | Unit sold — leaves warehouse to customer |
| `return_in` | `MovementType::ReturnIn` | Customer return inbound — first movement when package arrives at dock |
| `transfer` | `MovementType::Transfer` | Internal location move — no ownership change |
| `replacement_out` | `MovementType::ReplacementOut` | Replacement unit shipped to customer — separate from `sale` for reporting |
| `adjustment` | `MovementType::Adjustment` | Write-off, back to stock, or scrap — see sub-cases below |

---

### `adjustment` sub-cases

`adjustment` is reused for 4 distinct operations. Never query `type = 'adjustment'` alone — always filter further using `to_location_id` and `reference`:

| `to_location_id` | `reference` | Operation | How to identify |
|------------------|-------------|-----------|-----------------|
| set | CMP-xxx | **Back to stock** — no fault found, unit returned to sellable inventory | `to_location_id IS NOT NULL` |
| `NULL` | CMP-xxx | **Scrapped** — unit destroyed, internal fault or customer damage confirmed | join `complaints.unit_outcome = 'scrapped'` |
| `NULL` | CMP-xxx | **Returned to customer** — unit handed back after no fault found | join `complaints.unit_outcome = 'returned_to_customer'` |
| `NULL` | `NULL` | **Warehouse write-off** — pre-sale damage, no complaint, no order | `reference IS NULL` |

> **Agent rule:** for `type = 'adjustment' AND reference IS NOT NULL AND to_location_id IS NULL` — always join `complaints` on `complaints.number = reference` to determine outcome. Never assume scrapped without confirming `unit_outcome`.

---

### Reporting queries

Use these patterns for accurate inventory reports. Do not use `type = 'adjustment'` alone.

```sql
-- Scrapped units from complaints (internal fault or customer damage confirmed by tech)
SELECT im.inventory_serial_id, im.reference, c.examination_result, c.closed_at
FROM inventory_movements im
JOIN complaints c ON c.number = im.reference
WHERE im.type = 'adjustment'
  AND im.to_location_id IS NULL
  AND c.unit_outcome = 'scrapped';

-- Units returned to customer after examination (no fault found, unit handed back)
SELECT im.inventory_serial_id, im.reference, c.closed_at
FROM inventory_movements im
JOIN complaints c ON c.number = im.reference
WHERE im.type = 'adjustment'
  AND im.to_location_id IS NULL
  AND c.unit_outcome = 'returned_to_customer';

-- Units returned to stock after examination (no fault found, unit reusable)
SELECT im.inventory_serial_id, im.reference, c.closed_at
FROM inventory_movements im
JOIN complaints c ON c.number = im.reference
WHERE im.type = 'adjustment'
  AND im.to_location_id IS NOT NULL
  AND c.unit_outcome = 'back_to_stock';

-- Warehouse write-offs (no complaint, no order — pre-sale damage)
SELECT im.inventory_serial_id, im.notes, im.created_at
FROM inventory_movements im
WHERE im.type = 'adjustment'
  AND im.reference IS NULL;
```

---

## Serial Assignment Rule — Oversell Prevention

### Schema constraint (required)
Unique constraint on `order_lines.serial_number_id`.
DB rejects duplicate assignment at write time — silent data corruption impossible.

### Business rule
Only `in_stock` serials can be assigned to an order line.

| Serial status      | Assignable | Reason |
|--------------------|-----------|--------|
| in_stock           | ✓ yes     | available |
| sold               | ✗ no      | belongs to another order |
| assigned           | ✗ no      | in transit for replacement |
| expected_return    | ✗ no      | awaiting customer return |
| under_examination  | ✗ no      | being examined by tech |
| scrapped           | ✗ no      | written off |
| missing            | ✗ no      | unaccounted |

### Implementation notes (for agent)
- Wrap serial assignment + status update in a single DB transaction
- Use `lockForUpdate()` on the serial row before checking status — prevents
  race condition where two concurrent requests grab the same serial
- Throw exception if status ≠ in_stock before assigning
- Unique constraint is the DB-level safety net; app-level lock is the
  race-condition guard — both are required, neither alone is sufficient

---

## Warehouse Damage Write-off Rule

Unit damaged in warehouse before sale. No customer, no complaint, no order involved.

### Data pattern
```
inventory_movements:
id   serial  type        from         to    reference  notes
--   SN-XXX  adjustment  Warehouse A  NULL  NULL       warehouse damage write-off — [reason]

inventory_serials:
serial  status    location  note
SN-XXX  scrapped  NULL      warehouse damage — written off [date]
```

### Rules
- `type=adjustment` — same movement type used for all inventory removals
- `to=NULL` — unit permanently removed from system
- `reference=NULL` — no order, complaint, or replacement linked
- `inventory_serials.status → scrapped` — same as complaint-scrapped units

### Reporting write-offs
```sql
-- All unlinked adjustments = warehouse write-offs (no complaint, no order)
SELECT * FROM inventory_movements
WHERE type = 'adjustment'
AND reference IS NULL;
```

Financial impact: unit cost absorbed as a loss at write-off date.

---

## Global Reference — Payments

> All payment data lives in the `payments` table (polymorphic — covers orders and replacements).
> All monetary amounts are **USD**. Schema includes `currency char(3) DEFAULT 'USD'` on `orders`, `payments`, and `refunds`.

### Source → method rule

| `orders.source` | Allowed `payments.method` values |
|-----------------|----------------------------------|
| `online` | `stripe_card` only |
| `walk_in` | `stripe_terminal`, `cash`, `stripe_checkout`, `cheque` |
| `phone` | `stripe_terminal`, `cash`, `stripe_checkout`, `cheque` |

`stripe_checkout` = CSR generates a Stripe-hosted checkout link / QR code. Customer pays on their own device. Used in-house (walk_in / phone) — not the same as online card.

### Billing snapshot rule

| `payments.method` | Billing snapshot | Reason |
|-------------------|-----------------|--------|
| `stripe_card` | **Required** — copied from `customer_addresses` | Stripe requires billing address for card-not-present |
| `stripe_terminal` | NULL | Card-present — chip/tap, no manual billing entry |
| `cash` | NULL | No card, no billing required |
| `stripe_checkout` | NULL | Customer pays on Stripe-hosted page, no billing snapshot stored in our DB |
| `cheque` | NULL | No card, no billing required |

### Shipping snapshot rule

| Order type | Shipping snapshot |
|------------|-----------------|
| Carrier delivery (FedEx, UPS, etc.) | **Required** — both NULL not allowed if a shipment row exists |
| In-store pickup | NULL allowed — no shipment row, `orders.status = complete` |

### Method → columns filled

| `payments.method` | Stripe-specific columns | Other columns | `payments.status` flow |
|-------------------|------------------------|---------------|----------------------|
| `stripe_card` | `stripe_payment_intent_id`, `stripe_charge_id` | — | `paid` (sync at checkout) |
| `stripe_terminal` | `stripe_terminal_reader_id`, `stripe_payment_intent_id`, `stripe_charge_id` | — | `paid` (sync at tap/chip) |
| `cash` | — | `cash_received_at` | `paid` (admin marks on receipt) |
| `stripe_checkout` | `stripe_checkout_session_id` | — | `pending` → `paid` (webhook on payment), `expired` if session times out |
| `cheque` | — | `cheque_number`, `cheque_date` | `pending` → `paid` (admin marks when cheque clears) |

> `stripe_terminal` stores all three Stripe columns: `stripe_terminal_reader_id` (which device processed it), `stripe_payment_intent_id` + `stripe_charge_id` (Stripe Dashboard lookup + refund API).

> **Agent rule:** never read `orders.payment_status = paid` without also checking `payments.status`. For stripe_checkout and cheque, `payments.status = pending` while awaiting payment/clearance. The order won't advance to `processing` until `payments.status = paid`.

---

## Global Reference — Shipments

### `shipments.status`

| Value | Meaning | Timestamp set |
|-------|---------|---------------|
| `pending` | Label generated, not yet in carrier network | — |
| `in_transit` | Carrier scanned, package in network | `shipped_at` |
| `delivered` | Delivered — admin records manually for carrier orders | `delivered_at` |
| `returned` | Carrier returned package to sender after failed delivery | `returned_at` |
| `voided` | Label cancelled before use — label cost not recoverable | — |

### Timestamp rules

| Column | When set |
|--------|---------|
| `shipped_at` | Carrier activates label (first scan) |
| `returned_at` | Package physically arrives back at warehouse |
| `delivered_at` | Admin records on delivery confirmation |

> All three default NULL. `in_transit` → sets `shipped_at`. `returned` → sets `returned_at`. `delivered` → sets `delivered_at`. `voided` and `pending` → all three remain NULL.

### Direction values

| Value | Meaning |
|-------|---------|
| `outbound` | Warehouse → customer (orders, replacements) |
| `inbound` | Customer → warehouse (complaint returns, order returns) |

> In-store pickup orders have no shipment row — `orders.status = complete` is the delivery signal.

---

## Global Reference — Refunds

> Refunds are created manually by admin after inspection or decision. Never auto-created by the system.

### Column aliases
| Example shows | Actual DB column | Notes |
|---------------|-----------------|-------|
| `order` | `order_id` (FK → `orders`) | Always populated — the originating order |
| `payable` | `payable_id` | Paired with `type` (polymorphic) |

### `refunds.type` + `refunds.payable` (polymorphic)
| `type` | `payable` points to | When used |
|--------|--------------------|-----------| 
| `order` | `orders.id` | Direct return — no complaint, customer returns order |
| `complaint` | `complaints.id` | Complaint escalated to refund after examination |

### `refunds.status`
| Value | Meaning |
|-------|---------|
| `pending` | Refund initiated, not yet processed (Stripe async) |
| `processed` | Refund complete — money returned to customer |

### `refunds.method`
Simplified refund channel — maps from original `payments.method`. All Stripe variants (card, terminal, checkout) refund via Stripe API and are stored as `stripe`.

| Original `payments.method` | `refunds.method` |
|---------------------------|-----------------|
| `stripe_card` | `stripe` |
| `stripe_terminal` | `stripe` |
| `stripe_checkout` | `stripe` |
| `cash` | `cash` |
| `cheque` | `cheque` |

### `refunds.amount` vs `refunds.ship_refund`
- `amount` — product refund (may be less than grand_total if restocking fee applied)
- `ship_refund` — shipping cost refunded separately (`0.00` if free shipping or shipping not refunded)
- Total refund = `amount + ship_refund`

---

## Global Reference — Notes

All free-text notes across the entire order lifecycle live in one table.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `order_id` | FK → `orders` | Always set — every note ties back to originating order |
| `body` | text | Free-text content |
| `created_by` | FK → `users` | Staff member who wrote the note |
| `created_at` | timestamp | When written |

> Query pattern: `WHERE order_id = ?` returns all notes for an order and everything under it (complaints, replacements, refunds, shipments). One query = full order history.

---

## Global Reference — Replacements

> Replacement data lives in the `replacements` table. Each replacement links to the complaint that triggered it.

### Replacement creation is always manual

Tech sets `examination_result` only — no automatic replacement is triggered. Admin reviews the result and decides: replace, refund, or return unit. Only then creates the replacement row manually.

| Role | Action |
|------|--------|
| Technician | Sets `examination_result` (internal_issues / damaged_by_customer / no_fault_found) |
| Admin/CSR | Reviews result → decides outcome → manually creates replacement row or processes refund |

> **Agent rule:** never auto-create a replacement on examination result. Always wait for explicit admin action. The `replacements.created_by` column will always be an admin/CSR user_id — never a system process.

---

### `replacements.parent_id`

| Value | Meaning |
|-------|---------|
| `NULL` | First replacement for this complaint chain |
| `<replacements.id>` | Second or later replacement — points to previous replacement in the chain |

Set when admin issues a replacement for a unit that **was itself a replacement**. Forms a linked list: REP-001 → REP-002 (`parent_id=1`) → REP-003 (`parent_id=2`).

---

### Chain rules

- `complaints.order_line_id` stays the **same** across all complaints in a chain — replacement units never create a new order line
- Each replacement = a new serial (`in_stock` unit assigned). Old serial goes through the full complaint flow: `return_in → examine → outcome`
- No hard DB limit on chain depth — business policy decides when to stop. Ex 10 shows admin choosing refund at second fault rather than issuing a second replacement
- `parent_id` is set on the `replacements` row only — not on the complaint row. Trace the chain via `replacements` joins

### Chain query
```sql
-- Full replacement chain for an order line
SELECT r.id, r.number, r.parent_id, rl.new_serial_id, r.status, r.created_at
FROM replacements r
JOIN replacement_lines rl ON rl.replacement_id = r.id
WHERE rl.order_line_id = :order_line_id
ORDER BY r.created_at ASC;
```

---

### Payment rule per replacement

| Fault type | Charge? |
|-----------|---------|
| `internal_issues` (first occurrence) | Free — warranty |
| `internal_issues` (second occurrence) | Business decision — Ex 10: refund chosen over second replacement |
| `damaged_by_customer` | Charged — customer responsible |

---

### Serial lifecycle per chain link

```
New serial (in_stock)
    → replacement_out movement (Warehouse A → NULL)
    → serial status: in_stock → assigned → sold (if shipped to customer)
      Counter handoff (in-person): no transit — assigned skipped → in_stock → sold directly
    │
    ├── If fails again:
    │       complaint opened on replacement serial
    │       serial: sold → expected_return → under_examination
    │       admin decides: new replacement (parent_id set) OR refund
    │
    └── If no further issue:
            serial stays sold — chain ends
```

> See Ex 10 (ORD-012) for a worked chain ending in refund. `replacements.parent_id` is NULL in Ex 10 — second replacement never issued, admin chose refund instead.

---
