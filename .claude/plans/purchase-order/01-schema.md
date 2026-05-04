# Purchase Order Module — Schema

## Tables Overview

| Table | Purpose |
|-------|---------|
| `purchase_orders` | PO header — supplier, status, totals, approval |
| `purchase_order_lines` | PO line items — product, qty, price |
| `goods_receipts` | GRN header — received date, receiver |
| `goods_receipt_lines` | GRN line items — qty received per PO line |
| `invoices` | Supplier invoice linked to PO |

> **Migration rule:** All `decimal` columns for money or quantity use `->unsigned()`. Negative values are a data error — enforce at DB level.

---

## Table: `purchase_orders`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| po_number | string(20) | No | — | Unique, auto-generated e.g. `PO-2026-0001` |
| supplier_id | foreignId | No | — | FK → suppliers.id |
| status | string | No | `'draft'` | Cast to `PurchaseOrderStatus` enum |
| expected_delivery_date | date | Yes | null | |
| notes | text | Yes | null | Internal notes |
| subtotal | decimal(12,2) unsigned | No | 0.00 | Sum of line totals before tax |
| tax_total | decimal(12,2) unsigned | No | 0.00 | Sum of all line taxes |
| grand_total | decimal(12,2) unsigned | No | 0.00 | subtotal + tax_total |
| approved_by | foreignId | Yes | null | FK → users.id |
| approved_at | timestamp | Yes | null | When approved |
| rejection_reason | text | Yes | null | Set on reject action |
| created_by | foreignId | No | — | FK → users.id |
| deleted_at | timestamp | Yes | null | Soft delete |
| created_at | timestamp | Yes | — | Auto |
| updated_at | timestamp | Yes | — | Auto |

### Indexes
- `po_number` — unique index
- `supplier_id` — foreign key index
- `status` — index for filtering

---

## Table: `purchase_order_lines`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| purchase_order_id | foreignId | No | — | FK → purchase_orders.id, cascade delete |
| product_id | foreignId | No | — | FK → products.id |
| description | string(500) | No | — | Snapshot of product name at PO time |
| qty_ordered | decimal(12,2) unsigned | No | — | Quantity ordered |
| qty_received | decimal(12,2) unsigned | No | 0.00 | Filled in as GRNs created |
| qty_on_hand_snapshot | decimal(12,2) unsigned | Yes | null | Stock on hand at time PO line was created |
| unit_cost | decimal(12,2) unsigned | No | — | Price snapshot at PO time |
| tax_rate | decimal(5,2) unsigned | No | 0.00 | Tax % e.g. 10.00 for 10% |
| line_total | decimal(12,2) unsigned | No | — | (qty_ordered × unit_cost) + tax |
| created_at | timestamp | Yes | — | Auto |
| updated_at | timestamp | Yes | — | Auto |

### Notes
- No soft delete on lines — cascade delete when PO deleted
- `description`, `unit_cost`, and `qty_on_hand_snapshot` are snapshots — not linked live to product data
- `qty_received` updated incrementally as GRN lines are created
- `qty_on_hand_snapshot` captured from `Product::qty_on_hand` at time of PO line creation

---

## Table: `goods_receipts`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| purchase_order_id | foreignId | No | — | FK → purchase_orders.id |
| grn_number | string(20) | No | — | Unique, auto-generated e.g. `GRN-2026-0001` |
| received_by | foreignId | No | — | FK → users.id |
| received_date | date | No | — | Date goods physically arrived |
| notes | text | Yes | null | Optional notes on delivery |
| status | string | No | `'draft'` | Enum: draft / complete |
| deleted_at | timestamp | Yes | null | Soft delete |
| created_at | timestamp | Yes | — | Auto |
| updated_at | timestamp | Yes | — | Auto |

### Indexes
- `grn_number` — unique index
- `purchase_order_id` — foreign key index

---

## Table: `goods_receipt_lines`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| goods_receipt_id | foreignId | No | — | FK → goods_receipts.id, cascade delete |
| purchase_order_line_id | foreignId | No | — | FK → purchase_order_lines.id |
| qty_received | decimal(12,2) unsigned | No | — | Qty received in this GRN for this line |
| notes | text | Yes | null | Notes per line (damage, shortage) |
| created_at | timestamp | Yes | — | Auto |
| updated_at | timestamp | Yes | — | Auto |

### Notes
- No soft delete — cascade delete when GRN deleted
- One PO line can appear in multiple GRNs (partial deliveries)
- Sum of all GRN lines for a PO line = total `qty_received` on that PO line

---

## Table: `invoices`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| purchase_order_id | foreignId | No | — | FK → purchase_orders.id |
| invoice_number | string(100) | No | — | Supplier's invoice number |
| invoice_date | date | No | — | Date on supplier invoice |
| due_date | date | Yes | null | Payment due date |
| amount | decimal(12,2) unsigned | No | — | Total invoice amount |
| status | string | No | `'pending'` | Enum: pending / approved / paid |
| notes | text | Yes | null | |
| approved_by | foreignId | Yes | null | FK → users.id |
| approved_at | timestamp | Yes | null | |
| paid_at | timestamp | Yes | null | When marked paid |
| deleted_at | timestamp | Yes | null | Soft delete |
| created_at | timestamp | Yes | — | Auto |
| updated_at | timestamp | Yes | — | Auto |

### Indexes
- `purchase_order_id` — foreign key index
- `status` — index for filtering

> **Note:** `invoice_number` is intentionally **not unique** — different suppliers may use the same invoice numbering scheme (e.g. both use "INV-001"). Uniqueness is enforced at the PO scope only if business rules require it.

---

## Migration Order (IMPORTANT)

Run in this exact order — FKs depend on prior tables existing:

1. `create_purchase_orders_table`
2. `create_purchase_order_lines_table`
3. `create_goods_receipts_table`
4. `create_goods_receipt_lines_table`
5. `create_invoices_table`

---

## Auto-Generated Number Format

### PO Number
- Format: `PO-{YEAR}-{SEQUENCE}` e.g. `PO-2026-0001`
- Sequence: 4-digit zero-padded, resets each year
- Generated in `PurchaseOrderService::generatePoNumber()`

### GRN Number
- Format: `GRN-{YEAR}-{SEQUENCE}` e.g. `GRN-2026-0001`
- Same logic in `GoodsReceiptService::generateGrnNumber()`

---

## Relationships Summary

```
Supplier hasMany PurchaseOrders
PurchaseOrder belongsTo Supplier
PurchaseOrder hasMany PurchaseOrderLines
PurchaseOrder hasMany GoodsReceipts
PurchaseOrder hasMany Invoices
PurchaseOrder belongsTo User (created_by)
PurchaseOrder belongsTo User (approved_by)

PurchaseOrderLine belongsTo PurchaseOrder
PurchaseOrderLine belongsTo Product
PurchaseOrderLine hasMany GoodsReceiptLines

GoodsReceipt belongsTo PurchaseOrder
GoodsReceipt belongsTo User (received_by)
GoodsReceipt hasMany GoodsReceiptLines

GoodsReceiptLine belongsTo GoodsReceipt
GoodsReceiptLine belongsTo PurchaseOrderLine

Invoice belongsTo PurchaseOrder
Invoice belongsTo User (approved_by)
```
