# Shipment Module — Schema

## Purpose

Tracks all physical package movements: outbound (order delivery, replacement delivery), inbound (customer returns, order returns). Polymorphic — one table covers order shipments, complaint return shipments, and replacement shipments.

---

## Tables Overview

| Table | Purpose |
|-------|---------|
| `shipments` | All shipment records — outbound and inbound, carrier and in-store |

---

## Table: `shipments`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| shippable_type | string(30) | No | — | `order` / `complaint` / `replacement` |
| shippable_id | unsignedBigInteger | No | — | FK to orders / complaints / replacements |
| customer_address_id | foreignId | Yes | null | FK → customer_addresses.id — actual delivery address for outbound carrier shipments |
| direction | string(10) | No | — | `outbound` / `inbound` |
| carrier | string(50) | Yes | null | `FedEx`, `UPS`, etc. |
| tracking | string(100) | Yes | null | Carrier tracking number |
| label_cost | decimal(8,2) unsigned | No | 0.00 | Cost paid for label. 0.00 = customer-paid label |
| status | string(20) | No | `'pending'` | Cast to `ShipmentStatus` enum |
| created_by | foreignId | No | — | FK → users.id — warehouse staff who created the shipment |
| shipped_at | timestamp | Yes | null | Carrier activates label (first scan) |
| returned_at | timestamp | Yes | null | Package back at warehouse (status=returned) |
| delivered_at | timestamp | Yes | null | Admin records on delivery confirmation |
| delivered_by | foreignId | Yes | null | FK → users.id — admin who recorded delivery. NULL for MVP (carrier confirms via tracking); future: integrate carrier webhook or manual admin marking |
| created_at | timestamp | Yes | — | Auto |
| updated_at | timestamp | Yes | — | Auto |

### Indexes
- `(shippable_type, shippable_id)` — polymorphic lookup index
- `customer_address_id` — foreign key index
- `tracking` — lookup by tracking number

---

## Status Enums

### `shipments.status`
| Value | Meaning | Timestamps set |
|-------|---------|---------------|
| `pending` | Label generated, not yet in carrier network | none |
| `in_transit` | Carrier scanned, package in network | `shipped_at` |
| `delivered` | Delivered — admin records manually | `delivered_at` |
| `returned` | Carrier returned package to sender | `returned_at` |
| `voided` | Label cancelled before use — cost already absorbed | none |

> `voided` labels: cost already paid to carrier — not recoverable. Set when complaint is withdrawn before customer drops off package.

### `shipments.direction`
| Value | Who ships | Shippable types |
|-------|-----------|----------------|
| `outbound` | Warehouse → customer | `order`, `replacement` |
| `inbound` | Customer → warehouse | `complaint`, `order` (direct return) |

---

## customer_address_id rules

| Scenario | `customer_address_id` |
|----------|----------------------|
| Outbound carrier (order delivery) | Required — FK to delivery address |
| Outbound carrier (replacement) | Required — FK to delivery address |
| Inbound (complaint return) | NULL — customer ships to warehouse |
| Inbound (order return) | NULL — customer ships to warehouse |
| In-store pickup | No shipment row created at all |

> **RTS re-ship:** new shipment row with new `customer_address_id` (work address). Original shipment row has `status=returned` with original address id. Order snapshot unchanged — two different address ids for two shipment attempts = full audit trail.

---

## label_cost rules

| Scenario | label_cost |
|----------|-----------|
| Prepaid return label (we generate for customer) | Cost paid upfront — set at label creation |
| Customer uses own label | 0.00 — we track nothing |
| Outbound order/replacement label | Actual label cost |
| Voided prepaid label | Cost retained — still shows original cost |

---

## In-store pickup / in-person complaint

**No shipment row** created for:
- In-store pickup orders (`orders.status → complete` is the delivery signal)
- In-person complaint handoff (customer hands unit at counter)
- In-person replacement handoff (replacement given at counter)

Inventory movements still record the `sale` / `return_in` / `replacement_out` — the shipments table is skipped entirely.

---

## Migration Order

```
1. customer_addresses   (customer_address_id FK)
2. orders               (shippable_id FK for order shipments)
3. complaints           (shippable_id FK for complaint shipments)
4. replacements         (shippable_id FK for replacement shipments)
5. shipments            (depends on all of the above)
```

> `shippable_id` is polymorphic — no real DB FK. Only `customer_address_id` has a real FK constraint.

---

## Relationships Summary

```
Order morphMany Shipments (as shippable)
Complaint morphMany Shipments (as shippable)
Replacement morphMany Shipments (as shippable)
Shipment morphsTo Shippable (order / complaint / replacement)
Shipment belongsTo CustomerAddress (nullable)
Shipment belongsTo User (created_by)
Shipment belongsTo User (delivered_by — nullable)
```
