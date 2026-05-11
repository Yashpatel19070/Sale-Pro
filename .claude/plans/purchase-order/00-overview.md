# Purchase Order Module — Overview

## Prerequisites

| Module / Dependency | Requirement |
|--------------------|-------------|
| `Supplier` model | Must exist at `app/Models/Supplier.php` with `id`, `name`, `code`, `contact_email` |
| `Product` model | Must exist at `app/Models/Product.php` with `id`, `sku`, `name`, `cost_price` |
| `User` model | Core auth — already exists |
| Role seeder | `admin`, `manager`, `sales` roles must be in DB (`php artisan db:seed --class=RoleSeeder`) |
| Migrations | `create_suppliers_table` and `create_products_table` must be run **before** PO migrations |

---

## Purpose
Manage the full procurement lifecycle: create POs against suppliers, get manager approval, track shipments, receive goods (GRN), and record invoices. Soft delete only throughout. Activity logging on all models.

## Modules in Scope

| Module | Description |
|--------|-------------|
| Purchase Orders | Core PO with lines, approval workflow, status lifecycle |
| Goods Receipt (GRN) | Receive full or partial goods against approved PO |
| Invoices | Record supplier invoices against PO, approve, mark paid |
| Audit Trail | Auto-logged via Spatie LogsActivity on all models |

## Features

### Purchase Orders
| # | Feature |
|---|---------|
| 1 | List POs — paginated table, filter by status/supplier/date |
| 2 | View PO — full detail, lines, GRNs, invoices |
| 3 | Create PO — select supplier, add lines (product, qty, unit cost, tax) |
| 4 | Edit PO — only in `draft` or `rejected` state |
| 5 | Submit PO — changes status to `pending_approval` |
| 6 | Approve PO — manager approves, records approver + timestamp |
| 7 | Reject PO — manager rejects with reason |
| 8 | Resubmit PO — after rejection, user edits and resubmits |
| 9 | Cancel PO — any status, soft record |
| 10 | Soft delete / restore |
| 11 | Print view — browser print / save as PDF (HTML + print CSS, no package) |
| 12 | Pass Quality Check — physical inspection step after full receipt; optional notes; sets PO to `received` |

### Goods Receipt (GRN)
| # | Feature |
|---|---------|
| 1 | Create GRN — against approved/on_the_way/partially_received PO, saved as draft |
| 2 | Edit GRN — draft only, fix qty mistakes freely |
| 3 | Complete GRN — locks record, commits qty_received to PO lines, recalculates PO status |
| 4 | Auto-update PO status → `partially_received` or `quality_check` on complete |
| 5 | View / list GRNs per PO |
| 6 | Soft delete GRN — draft only |

### Invoices
| # | Feature |
|---|---------|
| 1 | Create invoice against PO |
| 2 | Approve invoice |
| 3 | Mark invoice as paid |
| 4 | View / list invoices per PO |
| 5 | Soft delete invoice |

## File Map

### Enums
| File | Path |
|------|------|
| PurchaseOrderStatus | `app/Enums/PurchaseOrderStatus.php` |
| GoodsReceiptStatus  | `app/Enums/GoodsReceiptStatus.php`  |
| InvoiceStatus       | `app/Enums/InvoiceStatus.php`       |

### Models
| File | Path |
|------|------|
| PurchaseOrder | `app/Models/PurchaseOrder.php` |
| PurchaseOrderLine | `app/Models/PurchaseOrderLine.php` |
| GoodsReceipt | `app/Models/GoodsReceipt.php` |
| GoodsReceiptLine | `app/Models/GoodsReceiptLine.php` |
| Invoice | `app/Models/Invoice.php` |

### Services
| File | Path |
|------|------|
| PurchaseOrderService | `app/Services/PurchaseOrderService.php` |
| GoodsReceiptService | `app/Services/GoodsReceiptService.php` |
| InvoiceService | `app/Services/InvoiceService.php` |

### Controllers
| File | Path |
|------|------|
| PurchaseOrderController | `app/Http/Controllers/PurchaseOrderController.php` |
| GoodsReceiptController | `app/Http/Controllers/GoodsReceiptController.php` |
| InvoiceController | `app/Http/Controllers/InvoiceController.php` |

### Form Requests — Purchase Orders
| File | Path |
|------|------|
| StorePurchaseOrderRequest | `app/Http/Requests/PurchaseOrder/StorePurchaseOrderRequest.php` |
| UpdatePurchaseOrderRequest | `app/Http/Requests/PurchaseOrder/UpdatePurchaseOrderRequest.php` |
| RejectPurchaseOrderRequest | `app/Http/Requests/PurchaseOrder/RejectPurchaseOrderRequest.php` |

### Form Requests — GRN
| File | Path |
|------|------|
| StoreGoodsReceiptRequest | `app/Http/Requests/GoodsReceipt/StoreGoodsReceiptRequest.php` |

### Form Requests — Invoice
| File | Path |
|------|------|
| StoreInvoiceRequest | `app/Http/Requests/Invoice/StoreInvoiceRequest.php` |

### Policies
| File | Path |
|------|------|
| PurchaseOrderPolicy | `app/Policies/PurchaseOrderPolicy.php` |
| GoodsReceiptPolicy | `app/Policies/GoodsReceiptPolicy.php` |
| InvoicePolicy | `app/Policies/InvoicePolicy.php` |

### Views — Purchase Orders
| File | Path |
|------|------|
| index | `resources/views/purchase-orders/index.blade.php` |
| show | `resources/views/purchase-orders/show.blade.php` |
| create | `resources/views/purchase-orders/create.blade.php` |
| edit | `resources/views/purchase-orders/edit.blade.php` |
| print | `resources/views/purchase-orders/print.blade.php` |

### Views — GRN
| File | Path |
|------|------|
| create | `resources/views/goods-receipts/create.blade.php` |
| edit | `resources/views/goods-receipts/edit.blade.php` |
| show | `resources/views/goods-receipts/show.blade.php` |

### Views — Invoice
| File | Path |
|------|------|
| create | `resources/views/invoices/create.blade.php` |
| show | `resources/views/invoices/show.blade.php` |

### Seeders & Tests
| File | Path |
|------|------|
| PurchaseOrderPermissionSeeder | `database/seeders/PurchaseOrderPermissionSeeder.php` |
| PurchaseOrderControllerTest | `tests/Feature/PurchaseOrderControllerTest.php` |
| GoodsReceiptControllerTest | `tests/Feature/GoodsReceiptControllerTest.php` |
| InvoiceControllerTest | `tests/Feature/InvoiceControllerTest.php` |
| PurchaseOrderServiceTest | `tests/Unit/PurchaseOrderServiceTest.php` |
| GoodsReceiptServiceTest | `tests/Unit/GoodsReceiptServiceTest.php` |
| InvoiceServiceTest | `tests/Unit/InvoiceServiceTest.php` |

## Implementation Order

1. Migrations (all 5 tables) → run migrate
2. Enum (PurchaseOrderStatus)
3. Models + Factories (all 5)
4. Services (PurchaseOrderService → GoodsReceiptService → InvoiceService)
5. FormRequests (all 5)
6. Policies (all 3)
7. Controllers (PurchaseOrderController → GoodsReceiptController → InvoiceController)
8. Routes (add to web.php)
9. Views (PO: index → show → create → edit | GRN: create → edit → show | Invoice: create → show)
10. Permission Seeder → run seeder
11. Tests

## PO Status Lifecycle

```
draft → pending_approval → approved → on_the_way
  → partially_received  (more GRNs still allowed)
  → quality_check       (all qty received, pending physical inspection)
  → received            (QC passed — serials can now be assigned via bulk receive)
  → invoiced
  → closed

any stage → cancelled
received/partially_received → returning → returned  (future)
```

| Status | Meaning |
|--------|---------|
| `draft` | Created, not submitted |
| `pending_approval` | Submitted, awaiting manager |
| `rejected` | Manager rejected, user must fix + resubmit |
| `approved` | Approved, ready to ship |
| `on_the_way` | Supplier shipped, not yet arrived |
| `partially_received` | Some qty received via GRN — more GRNs allowed |
| `quality_check` | All qty received; awaiting physical inspection before stock update |
| `received` | QC passed — invoice can be created; serials assignable via Inventory → Bulk Receive |
| `invoiced` | Invoice matched, awaiting payment |
| `returning` | Goods being returned to supplier — future |
| `returned` | Return complete — future |
| `closed` | Paid and complete |
| `cancelled` | Terminated at any stage |

## Role Access Matrix

### Purchase Orders
| Permission | Super Admin | Admin | Manager | Sales |
|------------|-------------|-------|---------|-------|
| viewAny | ✅ | ✅ | ✅ | ✅ |
| view | ✅ | ✅ | ✅ | ✅ |
| create | ✅ | ✅ | ✅ | ❌ |
| update | ✅ | ✅ | ✅ | ❌ |
| delete | ✅ | ✅ | ❌ | ❌ |
| restore | ✅ | ✅ | ❌ | ❌ |
| submit | ✅ | ✅ | ✅ | ❌ |
| approve | ✅ | ✅ | ✅ | ❌ |
| reject | ✅ | ✅ | ✅ | ❌ |
| cancel | ✅ | ✅ | ✅ | ❌ |
| qualityCheck | ✅ | ✅ | ✅ | ❌ |

### Goods Receipts
| Permission | Super Admin | Admin | Manager | Sales |
|------------|-------------|-------|---------|-------|
| viewAny | ✅ | ✅ | ✅ | ✅ |
| view | ✅ | ✅ | ✅ | ✅ |
| create | ✅ | ✅ | ✅ | ❌ |
| delete | ✅ | ✅ | ❌ | ❌ |

### Invoices
| Permission | Super Admin | Admin | Manager | Sales |
|------------|-------------|-------|---------|-------|
| viewAny | ✅ | ✅ | ✅ | ✅ |
| view | ✅ | ✅ | ✅ | ✅ |
| create | ✅ | ✅ | ✅ | ❌ |
| approve | ✅ | ✅ | ✅ | ❌ |
| markPaid | ✅ | ✅ | ✅ | ❌ |
| delete | ✅ | ✅ | ❌ | ❌ |

## Key Rules (NEVER break these)
- `strict_types=1` on every PHP file
- Always use `$request->validated()` — never `$request->all()`
- Always eager load with `with()` — never lazy load
- `DB::transaction()` required for all multi-table writes (PO + lines, GRN + lines + PO qty update)
- Soft delete only — never hard delete
- PO editable only in `draft` or `rejected` state — service must guard this
- GRN only creatable against `approved` or `partially_received` PO — service must guard this
- Policy gates on every controller action via `$this->authorize()`
- Every controller action must have a Pest feature test
- Every service method must have a Pest unit test
- All 3 models use `LogsActivity` trait

## Implementation Checklist

### Migrations
- [ ] `create_purchase_orders_table` migration created
- [ ] `create_purchase_order_lines_table` migration created
- [ ] `create_goods_receipts_table` migration created
- [ ] `create_goods_receipt_lines_table` migration created
- [ ] `create_invoices_table` migration created
- [ ] `php artisan migrate` runs without error

### Enum
- [ ] `PurchaseOrderStatus` enum at `app/Enums/PurchaseOrderStatus.php`
- [ ] 13 cases present (see status list above)
- [ ] `label()` method present
- [ ] `color()` method present
- [ ] `GoodsReceiptStatus` enum at `app/Enums/GoodsReceiptStatus.php` — 2 cases: `draft` / `complete`
- [ ] `InvoiceStatus` enum at `app/Enums/InvoiceStatus.php` — 3 cases: `pending` / `approved` / `paid`

### Models
- [ ] `PurchaseOrder` model — HasFactory, SoftDeletes, LogsActivity
- [ ] `PurchaseOrderLine` model — HasFactory, includes `qty_on_hand_snapshot`
- [ ] `GoodsReceipt` model — HasFactory, SoftDeletes, LogsActivity
- [ ] `GoodsReceiptLine` model — HasFactory
- [ ] `Invoice` model — HasFactory, SoftDeletes, LogsActivity
- [ ] All models registered in `AuditLogService::SUBJECT_TYPES`

### Services
- [ ] `PurchaseOrderService` — all methods implemented
- [ ] `GoodsReceiptService` — all methods implemented
- [ ] `InvoiceService` — all methods implemented

### Requests
- [ ] `StorePurchaseOrderRequest` with nested lines validation
- [ ] `UpdatePurchaseOrderRequest` with nested lines validation
- [ ] `RejectPurchaseOrderRequest` — rejection_reason required
- [ ] `StoreGoodsReceiptRequest` with nested lines validation
- [ ] `StoreInvoiceRequest`

### Policies
- [ ] `PurchaseOrderPolicy` — 10 methods
- [ ] `GoodsReceiptPolicy` — 5 methods (viewAny, view, create, update, delete)
- [ ] `InvoicePolicy` — 6 methods

### Controllers
- [ ] `PurchaseOrderController` — all actions
- [ ] `GoodsReceiptController` — all actions
- [ ] `InvoiceController` — all actions

### Routes
- [ ] All PO routes added to `web.php`
- [ ] All GRN routes added (nested under PO)
- [ ] All Invoice routes added (nested under PO)
- [ ] `php artisan route:list | grep purchase-orders` shows all routes

### Views
- [ ] PO index, show, create, edit, print
- [ ] GRN create, show
- [ ] Invoice create, show

### Permissions Seeder
- [ ] `PurchaseOrderPermissionSeeder` creates all permissions
- [ ] All 4 roles get correct permissions
- [ ] Seeder registered in `DatabaseSeeder`
- [ ] `php artisan db:seed --class=PurchaseOrderPermissionSeeder` runs without error

### Tests
- [ ] All factories created
- [ ] Feature tests for all 3 controllers
- [ ] Unit tests for all 3 services
- [ ] All tests pass
