# PO Return Module — Model

## No New Model

Return POs are `PurchaseOrder` records with `type = PoType::Return`.
No separate Eloquent model. Use `PurchaseOrder` model directly and filter by `type`.

## Scopes on PurchaseOrder to Use

```php
// These scopes already exist on the PurchaseOrder model:

$po->scopeOfType($query, PoType::Return)  // filter to return type
$po->scopeOfStatus($query, PoStatus::Open) // filter by status
```

Usage in query:
```php
PurchaseOrder::ofType(PoType::Return)->with(['supplier', 'lines.product', 'parentPo'])->paginate(25);
```

## Accessors Available (from PurchaseOrder model)

- `$returnPo->parentPo` — the original purchase PO (via `parent_po_id` FK)
- `$returnPo->supplier` — same supplier as original PO
- `$returnPo->lines` — one line (the failed product)
- `$returnPo->createdBy` — the user who triggered the failure

## PoReturnService — Standalone Service (not separate model)

The `PoReturnService` wraps PurchaseOrder + PoLine creation for return-specific logic.
It is the single place that knows how to create a return PO from a failed unit job.

See `03-service.md` for the full service implementation.
