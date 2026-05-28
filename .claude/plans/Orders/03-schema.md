# 03 — Schema (Migrations)

> **Layer 1 — Foundation.** Depends on `01-enums.md`.

## Scope

Migrations for 5 tables:

- `orders` — order header + billing/shipping snapshots + totals
- `order_lines` — items on the order
- `order_line_fees` — per-line fees (Programming, Gas Tuning, etc.)
- `order_events` — append-only audit trail
- `payments` — polymorphic payments table (cash for ex-19)

Plus `AppServiceProvider` morph map for polymorphic `payable_type`.

---

## Decisions LOCKED

| Decision | Rationale | ex-19 line |
|----------|-----------|-----------|
| `orders` has only `shipping` + `grand_total` totals — no `subtotal`/`fees`/`tax` columns | Receipt-style math: every row carries its all-in total | 73 |
| `orders.shipped_at`/`shipped_by`/`delivered_at`/`delivered_by` columns exist but NULL for ex-19 | Schema supports future shipping flows without restructure | 73 |
| **No `deleted_at` column on any table** | Hard delete only — `AuditLogService` captures deletion event | — |
| **No `cancelled_at`/`cancelled_by` columns** | Cancellation flow out of scope | — |
| `order_lines.tax_amount` only (no `tax_rate`) | AvaTax is source of truth — only store the dollar amount | 89 |
| `order_lines.line_total` = `unit_price + tax_amount` (stored) | Each row carries its own total | 90 |
| `order_line_fees.fee_total` = `amount + tax_amount` (stored) | Parallel to `line_total` | 97-99 |
| `order_line_fees.created_by` (FK → users) | Audit trail — which staff entered the fee | 97-99 |
| `order_events` is append-only — `created_at` only, no `updated_at` | Immutable audit log | 155-157 |
| `payments` polymorphic via `payable_type` + `payable_id` | Same table will later serve replacement charge-backs | 122-124 |
| Morph map: `'order' => Order::class` registered in `AppServiceProvider::boot()` | Store readable `"order"` in `payable_type`, not FQN class name | — |
| All FKs use `CASCADE` on parent delete | When order is hard-deleted, all children wipe | — |
| `inventory_serial_id` on `order_lines` is **UNIQUE** | One serial can only be on one order_line | 89 |
| Indexes: `(name, created_at)` on `order_line_fees` | Fast fee-revenue reports | — |
| Indexes: `(order_id, created_at)` on `order_events` | Fast timeline queries per order | — |

---

## File locations

```
database/migrations/2026_05_25_000001_create_orders_table.php
database/migrations/2026_05_25_000002_create_order_lines_table.php
database/migrations/2026_05_25_000003_create_order_line_fees_table.php
database/migrations/2026_05_25_000004_create_order_events_table.php
database/migrations/2026_05_25_000005_create_payments_table.php
```

---

## Migration: `create_orders_table`

```php
<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('source', 20);
            $table->string('status', 30)->default('pending');
            $table->string('payment_status', 10)->default('unpaid');

            // Totals — only these two
            $table->decimal('shipping', 12, 2)->default(0.00);
            $table->decimal('grand_total', 12, 2)->default(0.00);

            // Billing snapshot — 10 nullable columns
            $table->string('billing_first_name')->nullable();
            $table->string('billing_last_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_phone', 20)->nullable();
            $table->string('billing_address_line1')->nullable();
            $table->string('billing_address_line2')->nullable();
            $table->string('billing_city', 100)->nullable();
            $table->string('billing_state', 50)->nullable();
            $table->string('billing_postal_code', 20)->nullable();
            $table->string('billing_country', 2)->nullable();

            // Shipping snapshot — 10 nullable columns
            $table->string('shipping_first_name')->nullable();
            $table->string('shipping_last_name')->nullable();
            $table->string('shipping_email')->nullable();
            $table->string('shipping_phone', 20)->nullable();
            $table->string('shipping_address_line1')->nullable();
            $table->string('shipping_address_line2')->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_state', 50)->nullable();
            $table->string('shipping_postal_code', 20)->nullable();
            $table->string('shipping_country', 2)->nullable();

            // Shipping lifecycle (NULL for ex-19 in-store pickup)
            $table->timestamp('shipped_at')->nullable();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('source');
            $table->index('payment_status');
            $table->index('created_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

**ex-19 ref:** line 73 (column shape), lines 77-78 (billing snapshot data), lines 80-82 (shipping snapshot NULL).

---

## Migration: `create_order_lines_table`

```php
<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_listing_id')->constrained()->restrictOnDelete();
            $table->string('sku', 100);           // snapshot from listing→product.sku
            $table->string('product_name', 255);  // snapshot from listing→product.name
            $table->foreignId('inventory_serial_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('line_total', 10, 2);  // unit_price + tax_amount
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
```

**ex-19 ref:** line 89 (column shape), line 90 (data row), line 93 (`line_total` formula).

**Edge case:** `inventory_serial_id` is `UNIQUE` — DB-level guarantee one serial belongs to at most one order_line.

---

## Migration: `create_order_line_fees_table` (NEW)

```php
<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_line_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_line_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);           // 'Programming Fee', 'Gas Tuning Fee', etc.
            $table->decimal('amount', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('fee_total', 10, 2);   // amount + tax_amount
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Indexes
            $table->index(['name', 'created_at']);  // fee-revenue reports
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_line_fees');
    }
};
```

**ex-19 ref:** lines 97-99 (Programming Fee + Gas Tuning Fee rows), line 102 (`fee_total = amount + tax_amount`).

**Edge case:** when `order_lines` row is deleted, its fees CASCADE-delete automatically.

---

## Migration: `create_order_events_table`

```php
<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('event', 50);          // cast to OrderEvent enum
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            // ⚠️ NO updated_at — append-only table

            // Indexes
            $table->index(['order_id', 'created_at']);  // timeline query
            $table->index('event');                     // filter by event type
            $table->index('created_by');                // staff activity reports
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
    }
};
```

**ex-19 ref:** lines 152-157 (3 event rows).

**Edge cases:**
- Append-only — never `UPDATE` or `DELETE` rows from this table (enforced by service convention, not DB)
- `metadata` is JSON — shape varies per event (defined in `14-events-inventory.md`)
- Migration uses `timestamp('created_at')->useCurrent()` — NOT `timestamps()` (which adds `updated_at`)

---

## Migration: `create_payments_table`

```php
<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('payable_type', 50);   // 'order' (via morph map)
            $table->unsignedBigInteger('payable_id');
            $table->string('method', 30);         // cast to PaymentMethod enum — 'cash' for ex-19
            $table->decimal('amount', 12, 2);
            $table->string('status', 10);         // cast to PaymentStatus enum — 'paid' for ex-19
            $table->timestamp('cash_received_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Indexes
            $table->index(['payable_type', 'payable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
```

**ex-19 ref:** line 123 (column shape), line 124 (cash payment row).

**Edge cases:**
- `order_id` is always set (FK + CASCADE) — even though `payable_type/payable_id` is the polymorphic pair, `order_id` gives a direct query path
- `cash_received_at` set ONLY for cash payments — NULL for future payment methods
- Future plans add `stripe_*`, `cheque_*` columns as needed (separate migrations)

---

## `AppServiceProvider` morph map registration

**File:** `app/Providers/AppServiceProvider.php`

Add to `boot()` method:

```php
use App\Models\Order;
use Illuminate\Database\Eloquent\Relations\Relation;

public function boot(): void
{
    Relation::enforceMorphMap([
        'order' => Order::class,
    ]);
}
```

**Why:** Stores `"order"` in `payments.payable_type` instead of `"App\Models\Order"`. Greppable, refactor-safe, matches all system-design examples.

---

## Migration run order

```
1. orders               (depends on: customers, users)
2. order_lines          (depends on: orders, product_listings, inventory_serials)
3. order_line_fees      (depends on: order_lines, users)
4. order_events         (depends on: orders, users)
5. payments             (depends on: orders, users)
```

Run via `php artisan migrate:fresh --seed` during development.

---

## Dependencies

**Depends on:**
- `01-enums.md` — column types cast to `OrderStatus`, `OrderSource`, `PaymentMethod`, `PaymentStatus`, `OrderEvent`
- Existing tables: `customers`, `users`, `product_listings`, `inventory_serials`

**Depended on by:**
- `04-models.md` — model `casts()` and relationships
- `05-factories.md` — factories generate rows in these tables
- `07-service.md` — `OrderService` reads/writes these tables
- `15-tests.md` — `assertDatabaseHas(...)` calls reference these column names

---

## Validation gates

- [ ] Every column in every ex-19 schema block is in the migration
- [ ] No columns in migration that aren't in ex-19 (within scope)
- [ ] No `deleted_at` on any table (hard delete)
- [ ] `order_lines.inventory_serial_id` is UNIQUE
- [ ] All FK to parent (`order_id`, `order_line_id`) use CASCADE
- [ ] All FK to users use `restrictOnDelete` or `nullOnDelete` (never CASCADE — users shouldn't wipe history)
- [ ] `order_events` has `created_at` only (no `updated_at`)
- [ ] Indexes match queries the service will run (timeline, fee reports, status filters)
- [ ] `AppServiceProvider` morph map line added
- [ ] All migrations use `declare(strict_types=1)`

---

## Cross-check vs ex-19

| ex-19 column | Migration | Line |
|--------------|-----------|------|
| `orders.id, number, customer_id, source, status, payment_status, shipping, grand_total, ...billing, ...shipping, shipped_at, shipped_by, delivered_at, delivered_by, created_by` | `create_orders_table` | 73 |
| `orders.billing_first_name = NPC Sales Pro LLC, ...` | nullable text columns | 77-78 |
| `orders.shipping_first_name = NULL, ...` | nullable text columns | 80-82 |
| `order_lines.id, order_id, product_listing_id, sku, product_name, inventory_serial_id, unit_price, tax_amount, line_total` | `create_order_lines_table` | 89-90 |
| `order_line_fees.id, order_line_id, name, amount, tax_amount, fee_total, created_by, created_at` | `create_order_line_fees_table` (NEW) | 97-99 |
| `payments.id, order_id, payable_type, payable_id, method, amount, status, cash_received_at, created_by` | `create_payments_table` | 123-124 |
| `order_events.id, order_id, event, metadata, created_by, created_at` | `create_order_events_table` | 153-157 |

All ex-19 columns mapped. No extras, no gaps.
