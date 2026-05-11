# Purchase Order Module — Permissions Seeder

**File:** `database/seeders/PurchaseOrderPermissionSeeder.php`

---

## Permission Constants (add to `app/Enums/Permission.php`)

```php
// Purchase Orders
const PURCHASE_ORDERS_VIEW_ANY = 'purchase_orders.viewAny';
const PURCHASE_ORDERS_VIEW     = 'purchase_orders.view';
const PURCHASE_ORDERS_CREATE   = 'purchase_orders.create';
const PURCHASE_ORDERS_UPDATE   = 'purchase_orders.update';
const PURCHASE_ORDERS_DELETE   = 'purchase_orders.delete';
const PURCHASE_ORDERS_RESTORE  = 'purchase_orders.restore';
const PURCHASE_ORDERS_SUBMIT   = 'purchase_orders.submit';
const PURCHASE_ORDERS_APPROVE  = 'purchase_orders.approve';
const PURCHASE_ORDERS_REJECT   = 'purchase_orders.reject';
const PURCHASE_ORDERS_CANCEL         = 'purchase_orders.cancel';
const PURCHASE_ORDERS_QUALITY_CHECK  = 'purchase_orders.qualityCheck';

// Goods Receipts
const GOODS_RECEIPTS_VIEW_ANY = 'goods_receipts.viewAny';
const GOODS_RECEIPTS_VIEW     = 'goods_receipts.view';
const GOODS_RECEIPTS_CREATE   = 'goods_receipts.create';
const GOODS_RECEIPTS_UPDATE   = 'goods_receipts.update';
const GOODS_RECEIPTS_DELETE   = 'goods_receipts.delete';

// Invoices
const INVOICES_VIEW_ANY  = 'invoices.viewAny';
const INVOICES_VIEW      = 'invoices.view';
const INVOICES_CREATE    = 'invoices.create';
const INVOICES_APPROVE   = 'invoices.approve';
const INVOICES_MARK_PAID = 'invoices.markPaid';
const INVOICES_DELETE    = 'invoices.delete';
```

---

## Seeder Implementation

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PurchaseOrderPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $all = [
            Permission::PURCHASE_ORDERS_VIEW_ANY,
            Permission::PURCHASE_ORDERS_VIEW,
            Permission::PURCHASE_ORDERS_CREATE,
            Permission::PURCHASE_ORDERS_UPDATE,
            Permission::PURCHASE_ORDERS_DELETE,
            Permission::PURCHASE_ORDERS_RESTORE,
            Permission::PURCHASE_ORDERS_SUBMIT,
            Permission::PURCHASE_ORDERS_APPROVE,
            Permission::PURCHASE_ORDERS_REJECT,
            Permission::PURCHASE_ORDERS_CANCEL,
            Permission::PURCHASE_ORDERS_QUALITY_CHECK,
            Permission::GOODS_RECEIPTS_VIEW_ANY,
            Permission::GOODS_RECEIPTS_VIEW,
            Permission::GOODS_RECEIPTS_CREATE,
            Permission::GOODS_RECEIPTS_UPDATE,
            Permission::GOODS_RECEIPTS_DELETE,
            Permission::INVOICES_VIEW_ANY,
            Permission::INVOICES_VIEW,
            Permission::INVOICES_CREATE,
            Permission::INVOICES_APPROVE,
            Permission::INVOICES_MARK_PAID,
            Permission::INVOICES_DELETE,
        ];

        foreach ($all as $permission) {
            SpatiePermission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'web',
            ]);
        }

        $viewOnly = [
            Permission::PURCHASE_ORDERS_VIEW_ANY,
            Permission::PURCHASE_ORDERS_VIEW,
            Permission::GOODS_RECEIPTS_VIEW_ANY,
            Permission::GOODS_RECEIPTS_VIEW,
            Permission::INVOICES_VIEW_ANY,
            Permission::INVOICES_VIEW,
        ];

        $managerExclude = [
            Permission::PURCHASE_ORDERS_DELETE,
            Permission::PURCHASE_ORDERS_RESTORE,
            Permission::GOODS_RECEIPTS_DELETE,
            Permission::INVOICES_DELETE,
        ];

        Role::where('name', 'super-admin')->first()?->givePermissionTo($all);
        Role::where('name', 'admin')->first()?->givePermissionTo($all);
        Role::where('name', 'manager')->first()?->givePermissionTo(
            array_values(array_diff($all, $managerExclude))
        );
        Role::where('name', 'sales')->first()?->givePermissionTo($viewOnly);
    }
}
```

---

## Permission Matrix

### Purchase Orders
| Permission | Super Admin | Admin | Manager | Sales |
|------------|-------------|-------|---------|-------|
| `purchase_orders.viewAny` | ✅ | ✅ | ✅ | ✅ |
| `purchase_orders.view` | ✅ | ✅ | ✅ | ✅ |
| `purchase_orders.create` | ✅ | ✅ | ✅ | ❌ |
| `purchase_orders.update` | ✅ | ✅ | ✅ | ❌ |
| `purchase_orders.delete` | ✅ | ✅ | ❌ | ❌ |
| `purchase_orders.restore` | ✅ | ✅ | ❌ | ❌ |
| `purchase_orders.submit` | ✅ | ✅ | ✅ | ❌ |
| `purchase_orders.approve` | ✅ | ✅ | ✅ | ❌ |
| `purchase_orders.reject` | ✅ | ✅ | ✅ | ❌ |
| `purchase_orders.cancel` | ✅ | ✅ | ✅ | ❌ |
| `purchase_orders.qualityCheck` | ✅ | ✅ | ✅ | ❌ |

### Goods Receipts
| Permission | Super Admin | Admin | Manager | Sales |
|------------|-------------|-------|---------|-------|
| `goods_receipts.viewAny` | ✅ | ✅ | ✅ | ✅ |
| `goods_receipts.view` | ✅ | ✅ | ✅ | ✅ |
| `goods_receipts.create` | ✅ | ✅ | ✅ | ❌ |
| `goods_receipts.delete` | ✅ | ✅ | ❌ | ❌ |

### Invoices
| Permission | Super Admin | Admin | Manager | Sales |
|------------|-------------|-------|---------|-------|
| `invoices.viewAny` | ✅ | ✅ | ✅ | ✅ |
| `invoices.view` | ✅ | ✅ | ✅ | ✅ |
| `invoices.create` | ✅ | ✅ | ✅ | ❌ |
| `invoices.approve` | ✅ | ✅ | ✅ | ❌ |
| `invoices.markPaid` | ✅ | ✅ | ✅ | ❌ |
| `invoices.delete` | ✅ | ✅ | ❌ | ❌ |

---

## Routes (add to `routes/web.php`)

Add use imports:
```php
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\InvoiceController;
```

Add inside admin middleware group:

```php
Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
    Route::get('/',                                          [PurchaseOrderController::class, 'index'])->name('index');
    Route::get('/create',                                    [PurchaseOrderController::class, 'create'])->name('create');
    Route::post('/',                                         [PurchaseOrderController::class, 'store'])->name('store');
    Route::get('/{purchaseOrder}',                           [PurchaseOrderController::class, 'show'])->name('show');
    Route::get('/{purchaseOrder}/edit',                      [PurchaseOrderController::class, 'edit'])->name('edit');
    Route::put('/{purchaseOrder}',                           [PurchaseOrderController::class, 'update'])->name('update');
    Route::delete('/{purchaseOrder}',                        [PurchaseOrderController::class, 'destroy'])->name('destroy');
    Route::post('/{purchaseOrder}/restore',                  [PurchaseOrderController::class, 'restore'])->name('restore')->withTrashed();
    Route::post('/{purchaseOrder}/submit',                   [PurchaseOrderController::class, 'submit'])->name('submit');
    Route::post('/{purchaseOrder}/approve',                  [PurchaseOrderController::class, 'approve'])->name('approve');
    Route::post('/{purchaseOrder}/reject',                   [PurchaseOrderController::class, 'reject'])->name('reject');
    Route::post('/{purchaseOrder}/on-the-way',               [PurchaseOrderController::class, 'markOnTheWay'])->name('markOnTheWay');
    Route::post('/{purchaseOrder}/quality-check',            [PurchaseOrderController::class, 'qualityCheck'])->name('qualityCheck');
    Route::post('/{purchaseOrder}/cancel',                   [PurchaseOrderController::class, 'cancel'])->name('cancel');
    Route::get('/{purchaseOrder}/print',                     [PurchaseOrderController::class, 'print'])->name('print');

    // Nested GRN routes
    Route::prefix('/{purchaseOrder}/goods-receipts')->name('goods-receipts.')->group(function () {
        Route::get('/create',                                    [GoodsReceiptController::class, 'create'])->name('create');
        Route::post('/',                                         [GoodsReceiptController::class, 'store'])->name('store');
        Route::get('/{goodsReceipt}',                            [GoodsReceiptController::class, 'show'])->name('show');
        Route::get('/{goodsReceipt}/edit',                       [GoodsReceiptController::class, 'edit'])->name('edit');
        Route::put('/{goodsReceipt}',                            [GoodsReceiptController::class, 'update'])->name('update');
        Route::post('/{goodsReceipt}/complete',                  [GoodsReceiptController::class, 'complete'])->name('complete');
        Route::delete('/{goodsReceipt}',                         [GoodsReceiptController::class, 'destroy'])->name('destroy');
    });

    // Nested Invoice routes
    Route::prefix('/{purchaseOrder}/invoices')->name('invoices.')->group(function () {
        Route::get('/create',                      [InvoiceController::class, 'create'])->name('create');
        Route::post('/',                           [InvoiceController::class, 'store'])->name('store');
        Route::get('/{invoice}',                   [InvoiceController::class, 'show'])->name('show');
        Route::post('/{invoice}/approve',          [InvoiceController::class, 'approve'])->name('approve');
        Route::post('/{invoice}/mark-paid',        [InvoiceController::class, 'markPaid'])->name('markPaid');
        Route::delete('/{invoice}',                [InvoiceController::class, 'destroy'])->name('destroy');
    });
});
```

---

## Register in DatabaseSeeder

Add after `SupplierPermissionSeeder::class` and `SupplierSeeder::class`:

```php
// database/seeders/DatabaseSeeder.php
$this->call([
    // ...
    SupplierPermissionSeeder::class,
    SupplierSeeder::class,
    PurchaseOrderPermissionSeeder::class, // ← add here, after suppliers
]);
```

---

## Running the Seeder

```bash
php artisan db:seed --class=PurchaseOrderPermissionSeeder
```

---

## Notes
- 20 total permissions across 3 resource types
- Use `Permission::PURCHASE_ORDERS_*` constants — never raw strings
- `super-admin` bypasses all gates (already configured globally)
- Sales role = view-only on all 3 resources
- Manager role = full CRUD + workflow actions, no delete/restore
- Seeder safe to run multiple times via `firstOrCreate`

## Permission String Format Convention

New modules use `module_name.camelCaseAction` format:
- ✅ `purchase_orders.viewAny` — underscore separator + camelCase action
- ✅ `goods_receipts.create`
- ✅ `invoices.markPaid`

Do NOT copy the older `inventory-movements.view` pattern (hyphen separator). That predates this convention and will not be changed to avoid breaking existing seeded data, but all new modules must follow underscore + camelCase.

Rule: constant name = `SCREAMING_SNAKE_CASE`, string value = `snake_case_module.camelCaseAction`.
