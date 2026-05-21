# Complaint Module — Schema

## Purpose

Tracks post-sale product issues. One complaint per order line (per serial). Covers two flows:
- **Flow A** — customer ships unit back first, examined, outcome decided
- **Flow B** — admin sends replacement immediately, old unit examined later

---

## Tables Overview

| Table | Purpose |
|-------|---------|
| `complaints` | Complaint lifecycle — creation through examination to resolution |

---

## Table: `complaints`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| number | string(20) | No | — | Unique — e.g. `CMP-2026-0001`. Generated from `sequences` table |
| order_id | foreignId | No | — | FK → orders.id |
| order_line_id | foreignId | No | — | FK → order_lines.id — **stays same across full replacement chain** |
| inventory_serial_id | foreignId | No | — | FK → inventory_serials.id — the specific unit complained about |
| status | string(20) | No | — | Cast to `ComplaintStatus` enum |
| examination_result | string(30) | Yes | null | Cast to `ExaminationResult` enum — NULL until examined |
| unit_outcome | string(30) | Yes | null | Cast to `UnitOutcome` enum — NULL until closed |
| issue_description | text | No | — | Customer-reported issue |
| unit_received_at | timestamp | Yes | null | When unit physically arrived at warehouse / handed at counter |
| examined_by | foreignId | Yes | null | FK → users.id — technician who examined |
| examination_notes | text | Yes | null | Technician's examination findings |
| closed_at | timestamp | Yes | null | When complaint was resolved (closed or withdrawn) |
| closed_by | foreignId | Yes | null | FK → users.id |
| created_by | foreignId | No | — | FK → users.id — CS rep who opened complaint |
| withdrawn_at | timestamp | Yes | null | When complaint was withdrawn |
| withdrawn_by | foreignId | Yes | null | FK → users.id |
| created_at | timestamp | Yes | — | Auto |
| updated_at | timestamp | Yes | — | Auto |

### Indexes
- `number` — unique index
- `order_id` — foreign key index
- `order_line_id` — foreign key index
- `inventory_serial_id` — foreign key index
- `status` — index for filtering

---

## Status Enums

### `complaints.status`
| Value | Meaning | Terminal? | Set when |
|-------|---------|-----------|----------|
| `open` | Complaint created, unit not yet received | no | `ComplaintService::open()` |
| `in_progress` | Unit received (Flow A) OR replacement shipped (Flow B) | no | `ComplaintService::receiveUnit()` (Flow A) / `ReplacementService::ship()` (Flow B) |
| `closed` | Resolved — examination complete, outcome recorded | yes | `ComplaintService::close()` |
| `withdrawn` | Customer withdrew before examination | yes | `ComplaintService::withdraw()` |

> Flow B: `in_progress` set by `ReplacementService::ship()` — not by ComplaintService.
> `withdrawn` only valid while `status = open` — no `return_in` movement yet.

### `complaints.examination_result`
| Value | Meaning | Warranty? |
|-------|---------|-----------|
| `internal_issues` | Internal component failure / manufacturing defect | applies — free replacement |
| `damaged_by_customer` | Physical damage caused by customer | voided — charged replacement |
| `no_fault_found` | Unit fully functional | N/A — charged replacement |
| NULL | Not yet examined, or withdrawn before examination | — |

### `complaints.unit_outcome`
| Value | Meaning | Serial final status | Adjustment movement `to` |
|-------|---------|--------------------|--------------------------| 
| `scrapped` | Unit destroyed | `scrapped` | NULL |
| `returned_to_customer` | Unit handed back — no fault | `sold` | NULL |
| `back_to_stock` | Unit returned to warehouse — no fault | `in_stock` | `inventory_location_id` (Warehouse A) |
| NULL | Not yet determined, or withdrawn | — | — |

---

## Flow A vs Flow B

| | Flow A | Flow B |
|--|--------|--------|
| First action | Customer ships unit in | Admin sends replacement immediately |
| `status=open` → `in_progress` | When `return_in` movement recorded | When replacement ships (`ReplacementService::ship()`) |
| Old serial status at complaint open | `sold` → `expected_return` | `sold` → `expected_return` |
| Old serial when unit arrives | `expected_return` → `under_examination` | `expected_return` → `under_examination` |
| `unit_received_at` | Set when unit arrives | Set when old unit arrives (after replacement already delivered) |

---

## In-person vs Carrier complaint

| | Carrier | In-person (counter handoff) |
|--|---------|----------------------------|
| `open()` serial status | `sold` → `expected_return` | `sold` → `under_examination` (immediate) |
| `open()` complaint status | `open` | `in_progress` (immediate — no waiting) |
| `open()` return_in movement | None yet | `NULL → Tech Area` (immediately) |
| `unit_received_at` | Set later by `receiveUnit()` | Set at `open()` time |
| Withdrawal | Possible while `open` | Not applicable — already `in_progress` |

---

## Complaint Number Format

- Format: `CMP-{YEAR}-{SEQUENCE}` e.g. `CMP-2026-0001`
- Generated in `ComplaintService::generateNumber()`

---

## Migration Order

```
1. orders         (order_id FK)
2. order_lines    (order_line_id FK)
3. inventory_serials (inventory_serial_id FK)
4. users          (examined_by, closed_by, created_by, withdrawn_by FKs — already exists)
5. complaints     (depends on all of the above)
```

---

## Relationships Summary

```
Order hasMany Complaints
OrderLine hasMany Complaints
InventorySerial hasMany Complaints
Complaint belongsTo Order
Complaint belongsTo OrderLine
Complaint belongsTo InventorySerial
Complaint belongsTo User (examined_by)
Complaint belongsTo User (closed_by)
Complaint belongsTo User (created_by)
Complaint belongsTo User (withdrawn_by)
Complaint hasMany Replacements
Complaint hasMany Shipments (polymorphic — inbound return label, outbound return to customer)
Complaint hasOne Refund (polymorphic — if escalated to refund)
```
