# Supplier Module — Schema

## Table: `suppliers`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| code | string(10) | No | — | Auto-generated `SUP-0001`. Unique. Never editable. |
| name | string(150) | No | — | Unique among non-deleted suppliers. |
| contact_name | string(150) | Yes | null | Primary contact person |
| contact_email | string(150) | Yes | null | Contact email |
| contact_phone | string(50) | Yes | null | Contact phone number |
| address | text | Yes | null | Physical / mailing address |
| notes | text | Yes | null | Internal notes |
| is_active | boolean | No | true | Set false on deactivate, true on restore |
| created_at | timestamp | Yes | — | Auto by Laravel |
| updated_at | timestamp | Yes | — | Auto by Laravel |
| deleted_at | timestamp | Yes | null | Soft delete (deactivation) |

---

## Migration — Exact Code

```php
<?php
// database/migrations/xxxx_create_suppliers_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 150)->unique();
            $table->string('contact_name', 150)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
            // is_active intentionally not indexed — boolean cardinality too low to be useful
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
```

---

## Follow-up Migration — Fix Unique for Soft-Delete Reuse

```php
<?php
// database/migrations/xxxx_fix_suppliers_name_unique_for_soft_delete.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['name', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['name', 'deleted_at']);
            $table->unique(['name']);
        });
    }
};
```

---

## After Creating Migrations

```bash
php artisan migrate
```

---

## Code Auto-Generation

- Format: `SUP-XXXX` where XXXX is zero-padded to 4 digits.
- Generated in `SupplierService::generateCode()`.
- Takes `MAX(id)` of all rows (including soft-deleted), adds 1, formats with `sprintf('SUP-%04d', $next)`.
- Code is written once at creation and is never editable afterward.
- `code` column has a plain `UNIQUE` index (not composite with `deleted_at`) because codes are
  never reused — once `SUP-0001` exists (even deleted), the next code is `SUP-0002`.

---

## Permission Enum Constants to Add

```php
// app/Enums/Permission.php — add these constants

const SUPPLIERS_VIEW_ANY = 'suppliers.viewAny';
const SUPPLIERS_VIEW     = 'suppliers.view';
const SUPPLIERS_CREATE   = 'suppliers.create';
const SUPPLIERS_UPDATE   = 'suppliers.update';
const SUPPLIERS_DELETE   = 'suppliers.delete';
const SUPPLIERS_RESTORE  = 'suppliers.restore';
```

---

## Notes

- `code` unique index is plain (not composite with `deleted_at`) — supplier codes never reuse.
- `name` unique uses composite `(name, deleted_at)` — a deactivated supplier name can be reused.
- `is_active = false` on deactivate, `is_active = true` on restore. Both happen alongside soft delete/restore.
- No foreign keys in this table — other tables (purchase_orders) will FK into `suppliers.id`.
- Supplier cannot be deactivated if it has open Purchase Orders — service must guard this.
