# Purchase Order Module — Schema

## Table: `purchase_orders`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| po_number | string(20) | No | — | Auto-generated `PO-YYYY-XXXX`. Unique. |
| type | enum(purchase,return) | No | purchase | `return` = auto-created return PO |
| parent_po_id | unsignedBigInteger | Yes | null | FK → purchase_orders.id. Null for purchase type. |
| supplier_id | unsignedBigInteger | No | — | FK → suppliers.id |
| status | enum(draft,open,partial,closed,cancelled) | No | draft | Lifecycle state |
| skip_tech | boolean | No | false | Units skip tech inspection stage |
| skip_qa | boolean | No | false | Units skip QA stage |
| reopen_count | unsignedTinyInteger | No | 0 | Number of times PO has been reopened |
| reopened_at | timestamp | Yes | null | When PO was most recently reopened (overwritten on each reopen) |
| notes | text | Yes | null | Internal notes |
| created_by_user_id | unsignedBigInteger | No | — | FK → users.id |
| confirmed_at | timestamp | Yes | null | When PO moved to open |
| closed_at | timestamp | Yes | null | When PO auto-closed |
| cancelled_at | timestamp | Yes | null | When PO was cancelled |
| cancel_notes | text | Yes | null | Required reason when cancelling. Null on non-cancelled POs. |
| created_at | timestamp | Yes | — | Auto by Laravel |
| updated_at | timestamp | Yes | — | Auto by Laravel |

**No `deleted_at`** — POs are immutable records. Cancel via `status = cancelled`.

---

## Table: `po_lines`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| purchase_order_id | unsignedBigInteger | No | — | FK → purchase_orders.id |
| product_id | unsignedBigInteger | No | — | FK → products.id |
| qty_ordered | unsignedInteger | No | — | Must be ≥ 1 |
| qty_received | unsignedInteger | No | 0 | Incremented as units pass `receive` stage |
| unit_price | decimal(10,2) | No | — | Price per unit. Locked when PO moves to open. |
| snapshot_stock | unsignedInteger | No | 0 | Units of this SKU physically in warehouse at line creation time |
| snapshot_inbound | unsignedInteger | No | 0 | Units of this SKU already on order (other open POs) at line creation time |
| created_at | timestamp | Yes | — | Auto by Laravel |
| updated_at | timestamp | Yes | — | Auto by Laravel |

---

## Migrations — Exact Code

### purchase_orders migration

```php
<?php
// database/migrations/xxxx_create_purchase_orders_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 20)->unique();
            $table->enum('type', ['purchase', 'return'])->default('purchase');
            $table->foreignId('parent_po_id')
                  ->nullable()
                  ->constrained('purchase_orders')
                  ->nullOnDelete();
            $table->foreignId('supplier_id')
                  ->constrained('suppliers')
                  ->restrictOnDelete(); // block supplier delete if POs exist
            $table->enum('status', ['draft', 'open', 'partial', 'closed', 'cancelled'])
                  ->default('draft');
            $table->boolean('skip_tech')->default(false);
            $table->boolean('skip_qa')->default(false);
            $table->unsignedTinyInteger('reopen_count')->default(0);
            $table->timestamp('reopened_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')
                  ->constrained('users')
                  ->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_notes')->nullable(); // required at cancel time, null otherwise
            $table->timestamps();

            $table->index('status');
            $table->index('supplier_id');
            $table->index('created_by_user_id');
            $table->index('created_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
```

### po_lines migration

```php
<?php
// database/migrations/xxxx_create_po_lines_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')
                  ->constrained('purchase_orders')
                  ->cascadeOnDelete();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->restrictOnDelete();
            $table->unsignedInteger('qty_ordered');
            $table->unsignedInteger('qty_received')->default(0);
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('snapshot_stock')->default(0);   // in-stock at line creation
            $table->unsignedInteger('snapshot_inbound')->default(0); // on-order at line creation (other open POs)
            $table->timestamps();

            $table->index('purchase_order_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_lines');
    }
};
```

---

## After Creating Migrations

```bash
php artisan migrate
```

---

## PO Number Auto-Generation

- Format: `PO-YYYY-XXXX`
- YYYY = 4-digit year of PO creation (e.g. `2026`)
- XXXX = sequential counter within that year, zero-padded to 4 digits
- Counter resets each new year (unlike supplier codes which never reset)
- Generated by `PurchaseOrderService::generatePoNumber()`
- Logic: `COUNT(*) WHERE YEAR(created_at) = current year` + 1, formatted with `sprintf`

---

## Permission Enum Constants to Add

```php
// app/Enums/Permission.php — add these constants

const PURCHASE_ORDERS_VIEW_ANY = 'purchase-orders.viewAny';
const PURCHASE_ORDERS_VIEW     = 'purchase-orders.view';
const PURCHASE_ORDERS_CREATE   = 'purchase-orders.create';
const PURCHASE_ORDERS_UPDATE   = 'purchase-orders.update';
const PURCHASE_ORDERS_CONFIRM  = 'purchase-orders.confirm';
const PURCHASE_ORDERS_CANCEL   = 'purchase-orders.cancel';
const PURCHASE_ORDERS_REOPEN   = 'purchase-orders.reopen';
```

---

## Notes

- `supplier_id` uses `restrictOnDelete()` — cannot delete a supplier if they have POs.
- `parent_po_id` self-referential FK with `nullOnDelete()` — if parent PO is ever deleted (edge case), child return PO loses its parent link but still exists.
- `reopen_count` is a `tinyInteger` unsigned — max value 255. More than 3 reopens triggers super-admin check.
- `confirmed_at`, `cancelled_at` are audit timestamps — set once, never updated.
- `cancel_notes` set at cancellation time alongside `cancelled_at`. Required in application layer via `CancelPurchaseOrderRequest` — nullable at DB level for pre-existing rows.
- `closed_at` is set on first close. Never nulled on reopen — `reopened_at` captures the reopen timestamp instead.
- `reopened_at` is overwritten on each reopen — `reopen_count` tracks the total, `reopened_at` tracks the most recent.
- No soft delete on `purchase_orders` — cancelled POs stay visible for audit history.
- `snapshot_stock` and `snapshot_inbound` are written once at line creation, never updated. They answer "why did we order this?" when reviewing old POs. `snapshot_inbound` counts units on order from OTHER open POs only — not this PO's own lines.
