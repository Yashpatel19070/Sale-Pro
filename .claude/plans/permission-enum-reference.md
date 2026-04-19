# Permission Enum — Complete Reference

All constants that must exist in `app/Enums/Permission.php`.
Each module seeder calls `Permission::CONSTANT` — all must be defined before any seeder runs.

## Complete Enum

```php
<?php
// app/Enums/Permission.php

declare(strict_types=1);

namespace App\Enums;

class Permission
{
    // ── Suppliers ─────────────────────────────────────────────────────────────────
    const SUPPLIERS_VIEW_ANY = 'suppliers.viewAny';
    const SUPPLIERS_VIEW     = 'suppliers.view';
    const SUPPLIERS_CREATE   = 'suppliers.create';
    const SUPPLIERS_UPDATE   = 'suppliers.update';
    const SUPPLIERS_DELETE   = 'suppliers.delete';
    const SUPPLIERS_RESTORE  = 'suppliers.restore';

    // ── Purchase Orders ───────────────────────────────────────────────────────────
    const PURCHASE_ORDERS_VIEW_ANY = 'purchase-orders.viewAny';
    const PURCHASE_ORDERS_VIEW     = 'purchase-orders.view';
    const PURCHASE_ORDERS_CREATE   = 'purchase-orders.create';
    const PURCHASE_ORDERS_UPDATE   = 'purchase-orders.update';
    const PURCHASE_ORDERS_CONFIRM  = 'purchase-orders.confirm';
    const PURCHASE_ORDERS_CANCEL   = 'purchase-orders.cancel';
    const PURCHASE_ORDERS_REOPEN   = 'purchase-orders.reopen';

    // ── Pipeline ──────────────────────────────────────────────────────────────────
    const PIPELINE_VIEW_ANY      = 'pipeline.viewAny';
    const PIPELINE_RECEIVE       = 'pipeline.receive';
    const PIPELINE_VISUAL        = 'pipeline.visual';
    const PIPELINE_SERIAL_ASSIGN = 'pipeline.serial_assign';
    const PIPELINE_TECH          = 'pipeline.tech';
    const PIPELINE_QA            = 'pipeline.qa';
    const PIPELINE_SHELF         = 'pipeline.shelf';
}
```

---

## Who Gets What

| Permission | super-admin | admin | manager | procurement | warehouse | tech | qa | sales |
|------------|:-----------:|:-----:|:-------:|:-----------:|:---------:|:----:|:--:|:-----:|
| SUPPLIERS_VIEW_ANY | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| SUPPLIERS_VIEW | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| SUPPLIERS_CREATE | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| SUPPLIERS_UPDATE | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| SUPPLIERS_DELETE | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| SUPPLIERS_RESTORE | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| PURCHASE_ORDERS_VIEW_ANY | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| PURCHASE_ORDERS_VIEW | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| PURCHASE_ORDERS_CREATE | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| PURCHASE_ORDERS_UPDATE | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| PURCHASE_ORDERS_CONFIRM | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| PURCHASE_ORDERS_CANCEL | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| PURCHASE_ORDERS_REOPEN | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| PIPELINE_VIEW_ANY | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| PIPELINE_RECEIVE | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| PIPELINE_VISUAL | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| PIPELINE_SERIAL_ASSIGN | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| PIPELINE_TECH | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| PIPELINE_QA | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| PIPELINE_SHELF | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |

---

## Notes

- super-admin bypasses all policy checks via `Gate::before()` — permissions still seeded for consistency.
- Return PO close reuses `PURCHASE_ORDERS_CANCEL` — no separate return permission constant needed.
- Add new module constants here first before adding to individual module policy files.
- Seeder call order: `RoleSeeder` → `SupplierPermissionSeeder` → `PurchaseOrderPermissionSeeder` → `PipelinePermissionSeeder`.
