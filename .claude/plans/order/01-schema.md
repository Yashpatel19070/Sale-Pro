# Order Module — Schema (Source of Truth)

> **READ [`00-rules.md`](00-rules.md) FIRST.** This file is the **source of truth for all column names** in the order module.
> Any code, view, or test that references a column not in this file is wrong. STOP and ask before adding to schema.

---

## Purpose

Core order lifecycle: creation, line items, fees. Status moves from `pending` → `processing` → `shipped`/`complete` → `refunded`/`cancelled`.
Payment, shipments, complaints, replacements, refunds each live in their own modules — this module owns the order header and lines only.

---

## ASK Triggers (specific to schema)

| # | Trigger | Question |
|---|---------|----------|
| 1 | Adding a column not listed below | "Add column `X` to orders/order_lines/order_fees? Type and nullability?" |
| 2 | Renaming a column | "Rename `X` → `Y`? This will break view/service/tests referencing `X`." |
| 3 | Plan disagrees with existing migration | "Migration `2026_05_23_*_create_orders_table.php` has `X`, schema says `Y` — which wins?" |
| 4 | Computing a total in code that is also stored | "Field `X` is stored AND computable — single source of truth is the stored column. Confirm." |

---

## Tables Overview

| Table | Purpose |
|-------|---------|
| `orders` | Order header — customer, status, totals, snapshots, audit |
| `order_lines` | One row per unit — one serial per line, no quantity column |
| `order_fees` | Additional fees (service fee, etc.) attached to the order |

---

## Table: `orders`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| number | string(20) | No | — | Unique — e.g. `ORD-2026-0001`. Generated from `sequences` table |
| customer_id | foreignId | No | — | FK → customers.id |
| source | string(20) | No | — | `online` / `walk_in` / `phone` |
| status | string(30) | No | `'pending'` | Cast to `OrderStatus` enum |
| payment_status | string(10) | No | `'unpaid'` | `unpaid` / `paid` only — no partial |
| created_by | foreignId | No | — | FK → users.id — admin/CSR who created the order |
| subtotal | decimal(12,2) unsigned | No | 0.00 | Sum of `order_lines.line_total` — **tax already included** (see Tax Rule below) |
| fees | decimal(12,2) unsigned | No | 0.00 | Sum of `order_fees.amount` |
| shipping | decimal(12,2) unsigned | No | 0.00 | Shipping charge (0.00 = free) |
| grand_total | decimal(12,2) unsigned | No | 0.00 | `subtotal + fees + shipping` — **do NOT add tax again** |
| currency | char(3) | No | `'USD'` | ISO 4217 |
| billing_first_name | string(100) | Yes | null | Billing snapshot — copied at order creation |
| billing_last_name | string(100) | Yes | null | |
| billing_email | string(255) | Yes | null | |
| billing_phone | string(30) | Yes | null | |
| billing_address_line1 | string(255) | Yes | null | |
| billing_address_line2 | string(255) | Yes | null | |
| billing_city | string(100) | Yes | null | |
| billing_state | string(10) | Yes | null | |
| billing_postal_code | string(20) | Yes | null | |
| billing_country | char(2) | Yes | null | |
| shipping_first_name | string(100) | Yes | null | Shipping snapshot — copied at order creation |
| shipping_last_name | string(100) | Yes | null | |
| shipping_email | string(255) | Yes | null | |
| shipping_phone | string(30) | Yes | null | |
| shipping_address_line1 | string(255) | Yes | null | |
| shipping_address_line2 | string(255) | Yes | null | |
| shipping_city | string(100) | Yes | null | |
| shipping_state | string(10) | Yes | null | |
| shipping_postal_code | string(20) | Yes | null | |
| shipping_country | char(2) | Yes | null | |
| shipped_at | timestamp | Yes | null | When admin shipped (carrier orders) |
| shipped_by | foreignId | Yes | null | FK → users.id |
| delivered_at | timestamp | Yes | null | Admin records manually on delivery confirmation |
| delivered_by | foreignId | Yes | null | FK → users.id |
| cancelled_at | timestamp | Yes | null | Reused as terminal timestamp for both `cancelled` and `refunded` states |
| cancelled_by | foreignId | Yes | null | FK → users.id |
| created_at | timestamp | Yes | — | Auto |
| updated_at | timestamp | Yes | — | Auto |

### Indexes
- `number` — unique index
- `customer_id` — foreign key index
- `status` — index for filtering
- `payment_status` — index for filtering

### Columns explicitly NOT in this table
> If a view, model, service, or test references one of these, **it is a bug**.
- `tax` / `tax_amount` / `tax_total` on `orders` — tax lives on `order_lines` only; orders totals are tax-inclusive via `subtotal`
- `core_charges` — not a real column. Remove from `Order::$fillable` if present.
- `fees_total` — column is named `fees`. Not `fees_total`.
- `shipping_amount` — column is named `shipping`. Form input is `shipping_amount`; DB column is `shipping`. Map at write time.
- `deleted_at` — orders are not soft-deletable. Delete is a hard delete, gated by status=cancelled.
- `notes` — order notes live in the `notes` polymorphic table.

---

## Snapshot Rules (per payment method × delivery method)

> Snapshots are **immutable** after order creation, except by `OrderService::update()` on pending orders only.
> For re-ship after RTS, use `shipments.customer_address_id` FK — never mutate the snapshot.

| Payment method | Delivery | Billing snapshot | Shipping snapshot |
|----------------|----------|-----------------|-------------------|
| `cash` | walk-in pickup | NULL | NULL |
| `cash` | carrier delivery | NULL | Required |
| `stripe_terminal` | walk-in pickup | NULL | NULL |
| `stripe_terminal` | carrier delivery | NULL | Required |
| `cheque` | walk-in pickup | NULL | NULL |
| `cheque` | carrier delivery | NULL | Required |
| `stripe_card` (online CNP) | any | Required | Required if carrier delivery |
| `stripe_checkout` (hosted) | any | NULL — Stripe hosted page collects billing | Required if carrier delivery |

**Default for cash orders in `create.blade.php`:** `billingType = 'none'`, billing snapshot stays NULL. (Known bug — see [`00-rules.md`](00-rules.md) §7.3.)

---

## Tax Rule (CRITICAL)

> Tax is **per-line**, computed by AvaTax server-side, stored on `order_lines.tax_amount`.
> `order_lines.line_total` = `unit_price + tax_amount`.
> `orders.subtotal` = sum of `order_lines.line_total` — **tax is already inside subtotal**.
> `orders.grand_total` = `subtotal + fees + shipping`. **DO NOT add tax separately.**

| Place | What to display | What to compute |
|-------|----------------|------------------|
| Order create form | Read-only tax preview per line (AJAX from AvaTax) | Server side via `OrderService::taxPreview()` |
| Order show page totals | `Subtotal (incl. tax)`, `Fees`, `Shipping`, `Grand Total` | Read directly from `orders.*` columns |
| Order show page lines table | `Unit Price`, `Tax Rate`, `Tax`, `Line Total` | Read directly from `order_lines.*` |

---

## Table: `order_lines`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| order_id | foreignId | No | — | FK → orders.id, cascade delete |
| sku | string(100) | No | — | Snapshot of product SKU at order time |
| product_name | string(255) | No | — | Snapshot of product name at order time |
| inventory_serial_id | foreignId | Yes | null | FK → inventory_serials.id — NULL when back-ordered. **Unique** when set — no duplicate serials across lines |
| unit_price | decimal(10,2) unsigned | No | — | Price at order time |
| tax_rate | decimal(6,4) unsigned | No | 0.0000 | e.g. `0.0825` = 8.25% |
| tax_amount | decimal(10,2) unsigned | No | 0.00 | `round(unit_price × tax_rate, 2)` |
| line_total | decimal(10,2) unsigned | No | — | `round(unit_price + tax_amount, 2)` |
| created_at | timestamp | Yes | — | Auto |
| updated_at | timestamp | Yes | — | Auto |

### Indexes
- `order_id` — foreign key index
- `inventory_serial_id` — unique index (oversell prevention at DB level)

### Rules
- **No quantity column.** One line = one physical unit = one serial. Multiple units = multiple rows.
- `inventory_serial_id` NULL = back-ordered line — stock not yet available. Set when serial assigned from arriving PO stock.
- `inventory_serial_id` unique constraint (when set) is the DB-level oversell guard.
- Service layer uses `lockForUpdate()` on serial row before assigning — prevents race condition.
- `sku` and `product_name` are snapshots copied at order creation — used to match correct serial when fulfilling back orders.
- `complaints.order_line_id` always references this table. Replacement serials never create new `order_lines` rows.
- Back-order advance rule: `orders.status → processing` requires `payment_status = paid` AND all lines have `inventory_serial_id` set.

---

## Table: `order_fees`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| order_id | foreignId | No | — | FK → orders.id, cascade delete |
| name | string(100) | No | — | e.g. `Service Fee` |
| amount | decimal(10,2) unsigned | No | — | Fee amount |
| created_at | timestamp | Yes | — | Auto |
| updated_at | timestamp | Yes | — | Auto |

### Rules
- No soft delete — cascade delete when order deleted.
- Multiple fee rows allowed per order.
- `orders.fees` column = sum of all fee rows — kept in sync by `OrderService`.

---

## Migration Order

1. `orders` — depends on: `customers`, `users`
2. `order_lines` — depends on: `orders`, `inventory_serials`
3. `order_fees` — depends on: `orders`
4. Sequences seed: insertOrIgnore into `sequences` with `name='orders', value=0`. The `sequences` table already exists.

> `customer_addresses` has **no FK** from `orders` — snapshots are plain string columns copied at order creation, no DB constraint. `customer_addresses` dependency belongs to the shipment module only.
> `products` does **not** appear in `order_lines` — reach product via `inventory_serials.product_id`.

---

## Status Enums

### `orders.status`

| Value | Meaning | Terminal? |
|-------|---------|-----------|
| `pending` | Created — transient initial state only | no |
| `back_ordered` | Set at creation when no serial available. Payment tracked separately via `payment_status` — may be paid or unpaid | no |
| `processing` | Payment received + serial assigned on all lines — ready to ship/pickup | no |
| `shipped` | Carrier has package | no |
| `complete` | In-store pickup — customer collected at counter | no |
| `cancelled` | Cancelled before shipment | yes |
| `refunded` | Full refund issued | yes |
| `rts` | Return-to-sender — carrier returning package | no |

> `cancelled_at` reused as terminal timestamp for both `cancelled` and `refunded`.
> `orders` has no `closed` status — `closed` belongs to `complaints` only.

### `orders.payment_status`

| Value | Meaning |
|-------|---------|
| `unpaid` | No payment received |
| `paid` | Full payment received |

> No partial payment state. Full payment required before order advances to `processing`.

### `orders.source`

| Value | Meaning |
|-------|---------|
| `online` | Customer ordered via website |
| `walk_in` | Customer present at counter |
| `phone` | Admin created order on behalf of customer who called |

---

## Order Number Format

- Format: `ORD-{YEAR}-{SEQUENCE}` — e.g. `ORD-2026-0001`
- Sequence: 4-digit zero-padded, from `sequences` table (already exists)
- Generated in `OrderService::nextOrderNumber()` — see [`02-services.md`](02-services.md)
- Uses `lockForUpdate()` on the sequences row to prevent collisions under concurrent creation

---

## Relationships Summary

| From | Relationship | To | Reason |
|------|--------------|-----|--------|
| Customer | hasMany | Order | One customer can place many orders |
| Order | belongsTo | Customer | FK `customer_id` |
| Order | hasMany | OrderLine | One order → many lines |
| Order | hasMany | OrderFee | One order → many fees |
| Order | hasMany | Payment | One order → many payments (cash/cheque/stripe) |
| Order | morphMany | Shipment | Polymorphic on `shippable_*` — replacements also ship |
| Order | hasMany | Complaint | Complaints attach to orders |
| Order | hasMany | Replacement | Replacement shipments |
| Order | hasMany | Refund | Refund records |
| Order | hasMany | Note | Polymorphic notes |
| Order | belongsTo | User | `created_by` |
| Order | belongsTo | User | `shipped_by` |
| Order | belongsTo | User | `delivered_by` |
| Order | belongsTo | User | `cancelled_by` |
| OrderLine | belongsTo | Order | FK `order_id` |
| OrderLine | belongsTo | InventorySerial | FK `inventory_serial_id` |
| OrderLine | hasMany | Complaint | One line can have many complaints |
| OrderFee | belongsTo | Order | FK `order_id` |

> Polymorphic morph map: `'order' => App\Models\Order::class` — registered in `AppServiceProvider::boot()`. **Required** for `Order::shipments()` to resolve when `Shipment::shippable_type = 'order'`. Missing morph map causes `ModelNotFoundException` in `markDelivered()`.

---

**Reference:** [`skills/references/database.md`](../../skills/references/database.md) · [`skills/references/model.md`](../../skills/references/model.md#relations--always-typed) · [`00-rules.md`](00-rules.md)
