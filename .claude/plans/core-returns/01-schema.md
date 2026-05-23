# Core Returns Module — Schema

## Purpose

Customers pay a refundable core deposit when buying a product with `has_core_charge=true`. When they return the old core unit, a technician inspects it. Accepted → deposit refunded + core moves to rebuild. Rejected → deposit forfeited. Customers have a 30-day reclaim window after acceptance; after that, a scheduled job moves the core to rebuild automatically.

Fraud check runs before intake: if the serial matches an existing product serial in our inventory, the return is blocked atomically.

---

## Tables Overview

| Table | Purpose |
|-------|---------|
| `order_core_charges` | One row per core-charge line — tracks deposit lifecycle |
| `core_returns` | Full lifecycle of a customer core return — intake to outcome |

---

## Modified Tables

| Table | Column added | Type | Default | Notes |
|-------|-------------|------|---------|-------|
| `products` | `has_core_charge` | boolean | false | Flags products that require a core deposit |
| `orders` | `core_charges` | decimal(12,2) | 0.00 | Sum of `order_core_charges.total` for this order |

> `orders.grand_total` formula: `subtotal + fees + core_charges + shipping`

---

## Enum Additions

### `SerialStatus` — `app/Enums/SerialStatus.php`

`inventory_serials.status` is a `string(50)` column — PHP enum is the guard. **No migration ALTER needed.**

| Case | Value | Meaning |
|------|-------|---------|
| `CoreReceived` | `core_received` | Unit received at dock or counter, awaiting tech inspection |
| `CoreAccepted` | `core_accepted` | Tech accepted — core in CORE-HOLD, within 30-day reclaim window |
| `CoreRejected` | `core_rejected` | Tech rejected — no refund, core awaiting disposal outcome |
| `CoreInRebuild` | `core_in_rebuild` | 30-day window expired — moved to rebuild bench |

### `MovementType` — `app/Enums/MovementType.php`

`inventory_movements.type` is a MySQL ENUM — **ALTER TABLE migration required.**

| Case | Value | Meaning |
|------|-------|---------|
| `CoreReceive` | `core_receive` | Customer core intake — `from_location_id = NULL` → `to_location_id = TECH-BENCH` |

---

## New Inventory Locations (seeder — not migration)

| id | name | code | is_active |
|----|------|------|-----------|
| 10 | Tech Bench | TECH-BENCH | true |
| 11 | Core Hold | CORE-HOLD | true |
| 12 | Rebuild Bench | REBUILD | true |
| 13 | Scrap Hold | SCRAP-HOLD | true |

> `core_receive` movement: `from_location_id = NULL` (not from our stock), `to_location_id = TECH-BENCH`.

---

## Table: `order_core_charges`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| order_id | foreignId | No | — | FK → orders.id |
| order_line_id | foreignId | No | — | FK → order_lines.id — one charge per line |
| description | string(255) | No | — | e.g. `"Core — Starter Motor"` |
| amount | decimal(12,2) unsigned | No | — | Base deposit amount before tax |
| tax_rate | decimal(8,4) unsigned | No | 0.0000 | Applied tax rate |
| tax_amount | decimal(12,2) unsigned | No | 0.00 | Computed: amount × tax_rate |
| total | decimal(12,2) unsigned | No | — | amount + tax_amount |
| status | string(20) | No | `'outstanding'` | `outstanding` / `refunded` / `forfeited` |
| created_at | timestamp | Yes | — | Auto |
| updated_at | timestamp | Yes | — | Auto |

### Indexes
- `order_id` — foreign key index
- `order_line_id` — foreign key index (also unique — one charge per line)
- `status` — index for filtering

### `order_core_charges.status`

| Value | Meaning | Terminal? |
|-------|---------|-----------|
| `outstanding` | Customer paid deposit — core not yet returned or accepted | no |
| `refunded` | Core accepted by tech — deposit refunded | yes |
| `forfeited` | Core rejected, fraud blocked, or 30-day expired without acceptance | yes |

---

## Table: `core_returns`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| number | string(25) | No | — | Unique — e.g. `CORE-RTN-2026-0001`. Generated from `sequences` table |
| order_core_charge_id | foreignId | No | — | FK → order_core_charges.id |
| return_method | string(10) | No | — | `counter` / `mail` |
| status | string(20) | No | `'pending'` | See status table below |
| received_at | timestamp | Yes | null | When core physically arrived |
| received_by | foreignId | Yes | null | FK → users.id — staff who received it |
| inspected_by | foreignId | Yes | null | FK → users.id — technician |
| inspected_at | timestamp | Yes | null | When tech completed inspection |
| expires_at | timestamp | Yes | null | `inspected_at + 30 days` — NULL until inspection done |
| inspection_result | string(10) | Yes | null | `accepted` / `rejected` / NULL |
| rejection_reason | text | Yes | null | Free text — NULL unless rejected |
| core_outcome | string(30) | Yes | null | See outcome table below |
| refund_payment_id | foreignId | Yes | null | FK → payments.id — NULL until refund fires on acceptance |
| notes | text | Yes | null | Free text |
| created_by | foreignId | No | — | FK → users.id — admin/CSR who created the return |
| created_at | timestamp | Yes | — | Auto |
| updated_at | timestamp | Yes | — | Auto |
| deleted_at | timestamp | Yes | null | Soft delete |

### Indexes
- `number` — unique index
- `order_core_charge_id` — foreign key index
- `status` — index for filtering
- `expires_at` — index for scheduled job (30-day expiry scan)

### `core_returns.status`

| Value | Meaning | Terminal? |
|-------|---------|-----------|
| `pending` | Return created — core not yet physically received | no |
| `received` | Core physically arrived — awaiting tech inspection | no |
| `accepted` | Tech accepted — core in CORE-HOLD, within 30-day reclaim window | no |
| `rejected` | Tech rejected core | no |
| `closed` | All done — outcome recorded, no further action | yes |

### `core_returns.inspection_result`

| Value | Meaning |
|-------|---------|
| `accepted` | Core is rebuildable |
| `rejected` | Core is not rebuildable |
| `NULL` | Not yet inspected |

### `core_returns.core_outcome`

| Value | Meaning |
|-------|---------|
| `rebuild` | Core moved to rebuild bench — scheduled job or admin |
| `back_to_stock` | Core in good condition — returned to warehouse as sellable unit |
| `returned_to_customer` | Core given or shipped back to customer (reclaim within 30 days) |
| `disposed` | Formal disposal — environmental waste or recycling |
| `scrapped` | Destroyed — sent to metal recycler |
| `NULL` | Not yet determined |

---

## Serial Tracking

Core units get their own `CORE-xxx` tracking serials in `inventory_serials`. These are distinct from product serials (`SN-xxx`).

| Serial pattern | Meaning |
|----------------|---------|
| `SN-xxx` | Product serial — sold unit, belongs to our inventory |
| `CORE-xxx` | Core tracking serial — customer's old part, intake label |

`inventory_movements.reference = core_returns.number` — this is the link. No FK column on `core_returns` pointing at movements.

---

## Fraud Check Rule

Runs before any `core_receive` movement is recorded:

1. Look up the physical unit's serial in `inventory_serials`.
2. **Serial found + pattern `SN-xxx` (product serial):** FRAUD — block. Create `core_returns` row with `status=closed`, `inspection_result=rejected`, `rejection_reason='fraud — serial matches our inventory'`. No `core_receive` movement. `order_core_charges.status → forfeited`.
3. **Serial found + pattern `CORE-xxx` (prior intake serial):** Check `core_returns` for same customer/order. If matched → ALLOWED. Assign a **new** `CORE-xxx` serial. Never reuse the prior serial.
4. **Not found in `inventory_serials`:** Proceed normally.

> Fraud block is atomic — `status=closed` on INSERT, never `rejected` transitioning to `closed`.

---

## Refund and Re-charge Rules

| Event | Action |
|-------|--------|
| Tech accepts core | `payments` INSERT: `payable_type=core_return`, `payable_id=core_return.id`, `amount=order_core_charge.total`, `status=refunded`. Auto — no CSR step. `order_core_charges.status → refunded`. |
| Customer reclaims accepted core within 30 days | `payments` INSERT: same `payable_type`/`payable_id`, positive `amount`, `status=paid`. Then move core out. `core_returns.status → closed`, `core_outcome=returned_to_customer`. |
| Tech rejects core | No payment. `order_core_charges.status → forfeited`. `core_returns.status → rejected` (awaits physical disposition). |
| 30-day window expires (scheduled job) | Core moves CORE-HOLD → REBUILD. `core_returns.core_outcome → rebuild`. `order_core_charges` stays `refunded` — refund already issued on acceptance. No new financial action. |

---

## Polymorphic Reuse

Core returns reuse existing tables — no new payment or shipment tables needed:

| Table | `payable_type` / `shippable_type` | `payable_id` / `shippable_id` | When |
|-------|----------------------------------|-------------------------------|------|
| `payments` | `core_return` | `core_returns.id` | Refund on acceptance; re-charge on reclaim |
| `shipments` | `core_return` | `core_returns.id` | Inbound mail return (customer ships core to us) |

---

## Business Rules Summary

1. Only one `order_core_charges` row per `order_line_id`.
2. `expires_at` is set immediately when tech inspection is recorded (`inspected_at + 30 days`). NULL until then. Scheduled job reads it to trigger rebuild — does not set it.
3. `orders.core_charges` column caches the sum; recompute on `order_core_charges` changes.
4. `core_receive` movement always: `from_location_id = NULL`, `to_location_id = TECH-BENCH`.
5. Fraud check is mandatory before any intake — no exceptions.
6. Refund auto-fires on acceptance — no separate CSR approval step.
7. Re-charge on reclaim uses same `payable_type/payable_id` as the refund row.
8. After 30-day expiry: financial state unchanged (`order_core_charges.status` stays `refunded`).
9. `core_returns.number` format: `CORE-RTN-YYYY-NNNN` — generated from `sequences` table.

---

## Sequences Table Entry

```
sequences:
prefix      year   last_number
CORE-RTN    2026   0
```

> Same `sequences` table used by `orders` (ORD-YYYY-NNNN) and other numbered entities.

---

## Migration Files Required

| Migration | Purpose |
|-----------|---------|
| `create_order_core_charges_table` | New table |
| `create_core_returns_table` | New table |
| `add_has_core_charge_to_products_table` | Add boolean column to products |
| `add_core_charges_to_orders_table` | Add decimal column to orders |
| `add_core_receive_to_inventory_movements_type_enum` | ALTER TABLE — add `core_receive` to MySQL ENUM |

> `SerialStatus` cases need no migration — `inventory_serials.status` is `string(50)`, PHP enum guards values.

---

## Examples

Full worked examples in `system-design/examples/`:

| File | Scenario |
|------|---------|
| [cr-01-counter-accept-rebuild.md](../system-design/examples/cr-01-counter-accept-rebuild.md) | Counter, accepted, 30-day expires → rebuild |
| [cr-02-counter-accept-reclaim.md](../system-design/examples/cr-02-counter-accept-reclaim.md) | Counter, accepted, customer reclaims within 30 days |
| [cr-03-counter-reject-takeback.md](../system-design/examples/cr-03-counter-reject-takeback.md) | Counter, rejected, customer takes core back |
| [cr-04-counter-reject-scrapped.md](../system-design/examples/cr-04-counter-reject-scrapped.md) | Counter, rejected, scrapped |
| [cr-05-counter-reject-disposed.md](../system-design/examples/cr-05-counter-reject-disposed.md) | Counter, rejected, disposed (hazmat) |
| [cr-06-mail-accept-rebuild.md](../system-design/examples/cr-06-mail-accept-rebuild.md) | Mail, accepted, 30-day expires → rebuild |
| [cr-07-mail-accept-reclaim.md](../system-design/examples/cr-07-mail-accept-reclaim.md) | Mail, accepted, customer reclaims → we ship back |
| [cr-08-mail-reject-shipback.md](../system-design/examples/cr-08-mail-reject-shipback.md) | Mail, rejected, we ship core back |
| [cr-09-mail-reject-disposed.md](../system-design/examples/cr-09-mail-reject-disposed.md) | Mail, rejected, disposed |
| [cr-10-fraud-blocked.md](../system-design/examples/cr-10-fraud-blocked.md) | Fraud blocked — serial matches our inventory |
| [cr-11-full-chain.md](../system-design/examples/cr-11-full-chain.md) | Full chain — fraud → mail → accept → reclaim → second return → rebuild |
