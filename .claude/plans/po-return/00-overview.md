# PO Return Module — Overview

## Purpose
When a unit fails at any pipeline stage, the system automatically creates a Return PO.
The Return PO records the intent to send the failed unit back to the supplier for
refund or replacement. It is a PO of `type = return` linked to the original PO.

## Key Rules

| Rule | Detail |
|------|--------|
| Auto-created | System creates it, no user action required. Triggered by `PipelineService::fail()`. |
| Type | `type = return` on `purchase_orders` table |
| Linked | `parent_po_id` FK → original purchase PO |
| Supplier | Same supplier as the original PO |
| Product | The failed unit's product (from the PO line) |
| Qty | Always 1 (one failed unit per return PO) |
| Unit price | Copied from the original PO line's `unit_price` |
| Status | Created as `open` (no draft phase for returns) |
| Failed unit | The `PoUnitJob` row is marked `status = failed`. The return PO references it via notes or a reference field. |
| No pipeline | Return POs do NOT go through the pipeline. They are records only. |
| No lines | Return POs have one pre-populated line (the failed product). No editing. |

## Return PO Lifecycle

```
[Unit fails in pipeline]
         ↓
[System creates Return PO: type=return, status=open]
         ↓
[Procurement ships unit back to supplier]
         ↓
[Manager manually marks Return PO as closed]
         ↓
[Supplier sends refund/replacement → new regular PO if needed]
```

## Features

| # | Feature |
|---|---------|
| 1 | List Return POs — filtered view of POs with type=return |
| 2 | View Return PO — detail with failed unit info and original PO link |
| 3 | Close Return PO — manager marks it resolved after supplier confirms receipt |

## Role Access Matrix

| Permission | super-admin | admin | manager | procurement | Others |
|------------|:-----------:|:-----:|:-------:|:-----------:|:------:|
| List | ✅ | ✅ | ✅ | ✅ | ❌ |
| View | ✅ | ✅ | ✅ | ✅ | ❌ |
| Close | ✅ | ✅ | ✅ | ❌ | ❌ |

## File Map

| File | Path |
|------|------|
| Service | `app/Services/PoReturnService.php` |
| Controller | `app/Http/Controllers/PoReturnController.php` |
| Feature Test | `tests/Feature/PoReturnControllerTest.php` |
| Unit Test | `tests/Unit/Services/PoReturnServiceTest.php` |

No separate permission seeder — return POs reuse `PURCHASE_ORDERS_*` permissions.

**No separate migration** — Return POs use the `purchase_orders` and `po_lines` tables with
`type = return` and `parent_po_id` set. These columns are defined in the PO module schema.

## Dependencies
- `PurchaseOrder` model (with `type` and `parent_po_id` columns from PO module)
- `PoLine` model
- `PoUnitJob` model (to get the failed unit's product and PO line)
- `PurchaseOrderPermissionSeeder` must run first (returns share the same PO permissions)
