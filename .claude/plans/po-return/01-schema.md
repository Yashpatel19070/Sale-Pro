# PO Return Module — Schema

## No New Tables

Return POs use the existing `purchase_orders` and `po_lines` tables from the PO module.
The `type` and `parent_po_id` columns were defined in the PO module migration.

## Relevant Columns (from `purchase_orders`)

| Column | Return PO Value |
|--------|----------------|
| `type` | `return` |
| `parent_po_id` | ID of the original purchase PO |
| `supplier_id` | Copied from the original PO |
| `status` | `open` on creation. Transitions to `closed` manually. |
| `skip_tech` | `false` — returns do not go through the pipeline |
| `skip_qa` | `false` — returns do not go through the pipeline |
| `notes` | Auto-populated: `"Return for failed unit in job #{job_id} at stage {stage}"` |
| `created_by_user_id` | The user who triggered the failure (the one who called fail()) |

## Return PO Line (in `po_lines`)

One line is created per return PO:

| Column | Value |
|--------|-------|
| `purchase_order_id` | ID of the return PO |
| `product_id` | Copied from `PoUnitJob → PoLine → product_id` |
| `qty_ordered` | Always `1` |
| `qty_received` | `0` — returns don't receive, they send out |
| `unit_price` | Copied from the original `PoLine.unit_price` |

## Permissions

No new permission constants. Return POs reuse existing PO permissions:
- `viewAny` / `view` → `PURCHASE_ORDERS_VIEW_ANY` / `PURCHASE_ORDERS_VIEW`
- `close` → `PURCHASE_ORDERS_CANCEL` (via `PurchaseOrderPolicy::close()`)

No new seeder required.

## Notes

- No new migration needed for this module.
- `qty_received` on the return PO line stays `0` — the line is a record of what is being returned,
  not what is received.
- The return PO does NOT go through `po_unit_jobs` — it is a paper record only.
- `status` progresses only: `open` → `closed`. No draft, no partial, no cancel on returns.
