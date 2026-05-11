# Purchase Order Module — Policies

---

## Policy: `PurchaseOrderPolicy`

**File:** `app/Policies/PurchaseOrderPolicy.php`

All methods check `$user->can('purchase_orders.{action}')` via Spatie permissions.

| Method | Permission Checked |
|--------|--------------------|
| `viewAny(User $user)` | `purchase_orders.viewAny` |
| `view(User $user, PurchaseOrder $po)` | `purchase_orders.view` |
| `create(User $user)` | `purchase_orders.create` |
| `update(User $user, PurchaseOrder $po)` | `purchase_orders.update` |
| `delete(User $user, PurchaseOrder $po)` | `purchase_orders.delete` |
| `restore(User $user, PurchaseOrder $po)` | `purchase_orders.restore` |
| `submit(User $user, PurchaseOrder $po)` | `purchase_orders.submit` |
| `approve(User $user, PurchaseOrder $po)` | `purchase_orders.approve` |
| `reject(User $user, PurchaseOrder $po)` | `purchase_orders.reject` |
| `cancel(User $user, PurchaseOrder $po)` | `purchase_orders.cancel` |
| `qualityCheck(User $user, PurchaseOrder $po)` | `purchase_orders.qualityCheck` |

---

## Policy: `GoodsReceiptPolicy`

**File:** `app/Policies/GoodsReceiptPolicy.php`

| Method | Permission Checked |
|--------|--------------------|
| `viewAny(User $user)` | `goods_receipts.viewAny` |
| `view(User $user, GoodsReceipt $grn)` | `goods_receipts.view` |
| `create(User $user)` | `goods_receipts.create` |
| `update(User $user, GoodsReceipt $grn)` | `goods_receipts.update` |
| `delete(User $user, GoodsReceipt $grn)` | `goods_receipts.delete` |

> `update` checks `goods_receipts.update` — dedicated permission, same role matrix as `create` (admin + manager). Seeded alongside create in `PurchaseOrderPermissionSeeder`.

> `delete` checks `goods_receipts.delete` — only admin/super-admin (not manager, not sales).

```php
<?php
// app/Policies/GoodsReceiptPolicy.php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\GoodsReceipt;
use App\Models\User;

class GoodsReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('goods_receipts.viewAny');
    }

    public function view(User $user, GoodsReceipt $grn): bool
    {
        return $user->can('goods_receipts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('goods_receipts.create');
    }

    public function update(User $user, GoodsReceipt $grn): bool
    {
        return $user->can(Permission::GOODS_RECEIPTS_UPDATE);
    }

    public function delete(User $user, GoodsReceipt $grn): bool
    {
        return $user->can('goods_receipts.delete');
    }
}
```

---

## Policy: `InvoicePolicy`

**File:** `app/Policies/InvoicePolicy.php`

| Method | Permission Checked |
|--------|--------------------|
| `viewAny(User $user)` | `invoices.viewAny` |
| `view(User $user, Invoice $invoice)` | `invoices.view` |
| `create(User $user)` | `invoices.create` |
| `approve(User $user, Invoice $invoice)` | `invoices.approve` |
| `markPaid(User $user, Invoice $invoice)` | `invoices.markPaid` |
| `delete(User $user, Invoice $invoice)` | `invoices.delete` |

---

## Policy Registration

Register all 3 in `app/Providers/AppServiceProvider.php` inside the `boot()` method, using `Gate::policy()` — same pattern as existing policies:

```php
Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
Gate::policy(GoodsReceipt::class,  GoodsReceiptPolicy::class);
Gate::policy(Invoice::class,       InvoicePolicy::class);
```

---

## Notes
- All policy methods return `bool` — just `return $user->can('...')`
- No complex logic in policies — all state guards live in service layer
- `super-admin` role bypasses all policy checks via Spatie's `Gate::before()` (already configured globally)
