# Replacement Module — Schema

## Purpose

Tracks replacement units issued to resolve complaints. One replacement per complaint (unless chained). Supports free replacements (internal fault, warranty) and charged replacements (customer damage, no fault found). Chains via `parent_id` when a replacement unit itself fails.

---

## Tables Overview

| Table | Purpose |
|-------|---------|
| `replacements` | Replacement header — type, charge, status, chain link |
| `replacement_lines` | Which serial was swapped for which — old → new |

---

## Table: `replacements`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| number | string(20) | No | — | Unique — e.g. `REP-2026-0001`. Generated from `sequences` table |
| order_id | foreignId | No | — | FK → orders.id — always the originating order |
| parent_id | foreignId | Yes | null | FK → replacements.id — self-referential. NULL = first replacement in chain |
| complaint_id | foreignId | No | — | FK → complaints.id — the complaint that triggered this replacement |
| type | string(10) | No | — | `free` / `charged` |
| charge | decimal(10,2) unsigned | Yes | null | NULL if free. Set if charged — amount admin decides |
| pay_status | string(10) | Yes | null | NULL if free. `pending` / `paid` if charged |
| status | string(20) | No | `'pending'` | Cast to `ReplacementStatus` enum |
| shipped_at | timestamp | Yes | null | When replacement left warehouse |
| shipped_by | foreignId | Yes | null | FK → users.id — warehouse staff who shipped |
| delivered_at | timestamp | Yes | null | Admin records when customer receives unit |
| delivered_by | foreignId | Yes | null | FK → users.id — admin who confirmed delivery |
| created_by | foreignId | No | — | FK → users.id — admin/CSR who created replacement |
| created_at | timestamp | Yes | — | Auto |
| updated_at | timestamp | Yes | — | Auto |

### Indexes
- `number` — unique index
- `order_id` — foreign key index
- `complaint_id` — foreign key index
- `parent_id` — foreign key index (chain traversal)

---

## Table: `replacement_lines`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| replacement_id | foreignId | No | — | FK → replacements.id, cascade delete |
| order_line_id | foreignId | No | — | FK → order_lines.id — **always original order line**, never changes across chain |
| sku | string(100) | No | — | Snapshot of SKU |
| product_name | string(255) | No | — | Snapshot of product name |
| old_serial_id | foreignId | No | — | FK → inventory_serials.id — serial being replaced |
| new_serial_id | foreignId | No | — | FK → inventory_serials.id — replacement unit |
| created_at | timestamp | Yes | — | Auto |
| updated_at | timestamp | Yes | — | Auto |

### Indexes
- `replacement_id` — foreign key index
- `order_line_id` — foreign key index
- `old_serial_id` — foreign key index
- `new_serial_id` — foreign key index

---

## Status Enums

### `replacements.status`
| Value | Meaning |
|-------|---------|
| `pending` | Created, not yet shipped |
| `shipped` | In transit to customer (carrier) |
| `delivered` | Customer has the unit |

### `replacements.type`
| Value | When | Payment |
|-------|------|---------|
| `free` | `internal_issues` — warranty applies | No charge |
| `charged` | `damaged_by_customer` or `no_fault_found` | Admin charges customer |

### Charge rules
| `examination_result` | `type` | `charge` |
|---------------------|--------|---------|
| `internal_issues` (first occurrence) | `free` | NULL |
| `internal_issues` (second+ occurrence) | Business decision — could be `free` or refund | — |
| `damaged_by_customer` | `charged` | Full replacement price |
| `no_fault_found` | `charged` | Admin-set amount |

---

## Replacement chain (parent_id)

```
REP-001 (parent_id=NULL)   — first replacement
    ↓ replacement unit fails
REP-002 (parent_id=1)      — second replacement (replacement of replacement)
    ↓ admin decides refund instead of third replacement
```

Rules:
- `parent_id` NULL = first replacement in chain
- `parent_id = <id>` = links to previous replacement
- `order_line_id` stays same across entire chain — always original purchase line
- Each `replacement_lines.old_serial_id` = previous row's `new_serial_id`
- Chain depth unlimited — business policy stops it (e.g. Ex 10: admin chose refund at second fault)

### Chain query
```php
// Full replacement chain for an order line — join through replacement_lines for serial data
Replacement::where('order_id', $orderId)
    ->with(['lines.oldSerial', 'lines.newSerial', 'lines.orderLine'])
    ->orderBy('created_at')
    ->get();
```

```sql
-- Raw SQL equivalent
SELECT r.id, r.number, r.parent_id, rl.new_serial_id, r.status, r.created_at
FROM replacements r
JOIN replacement_lines rl ON rl.replacement_id = r.id
WHERE rl.order_line_id = :order_line_id
ORDER BY r.created_at ASC;
```

---

## In-person vs Carrier replacement

| | Carrier | In-person (counter handoff) |
|--|---------|----------------------------|
| Shipment row | Created (`replacement/id/outbound`) | No shipment row |
| New serial status at ship | `in_stock → assigned` (in transit) | `in_stock → sold` (immediately) |
| `replacement.status` after hand-off | `shipped` → later `delivered` | `delivered` (immediately) |
| `replacement_out` movement | `Warehouse A → NULL` | `Warehouse A → NULL` |

---

## Flow B: replacement ships before old unit arrives

When admin sends replacement immediately (Flow B):
1. `ReplacementService::create()` — INSERT replacements + replacement_lines, assign new serial
2. `ReplacementService::ship()` — shipment row + `replacement_out` movement + new serial → `assigned` + old serial → `expected_return` + **`complaints.status → in_progress`**
3. Old unit arrives later → `ComplaintService::receiveUnit()` (status stays `in_progress`, just sets `unit_received_at`)
4. Examination → `ComplaintService::examine()` + `ComplaintService::close()`

---

## Replacement Number Format

- Format: `REP-{YEAR}-{SEQUENCE}` e.g. `REP-2026-0001`
- Generated in `ReplacementService::generateNumber()`

---

## Migration Order

```
1. orders           (order_id FK)
2. complaints       (complaint_id FK)
3. order_lines      (order_line_id FK on replacement_lines)
4. inventory_serials (old_serial_id, new_serial_id FKs)
5. replacements     (self-referential parent_id — create table first, add FK after)
6. replacement_lines (depends on: replacements, order_lines, inventory_serials)
```

> `replacements.parent_id` is self-referential. In Laravel migration, create the column as nullable unsignedBigInteger, then add the FK constraint after the table exists.

---

## Relationships Summary

```
Order hasMany Replacements
Complaint hasMany Replacements
Replacement belongsTo Order
Replacement belongsTo Complaint
Replacement belongsTo Replacement (parent — nullable)
Replacement hasMany Replacements (children via parent_id)
Replacement hasMany ReplacementLines
Replacement hasMany Payments (polymorphic)
Replacement hasMany Shipments (polymorphic)
Replacement belongsTo User (created_by)
Replacement belongsTo User (shipped_by)
Replacement belongsTo User (delivered_by)

ReplacementLine belongsTo Replacement
ReplacementLine belongsTo OrderLine
ReplacementLine belongsTo InventorySerial (old_serial_id)
ReplacementLine belongsTo InventorySerial (new_serial_id)
```
