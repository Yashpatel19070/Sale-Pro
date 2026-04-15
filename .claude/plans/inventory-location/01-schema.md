# InventoryLocation Module — Schema

## Table: `inventory_locations`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | Primary key |
| code | string(20) | No | — | Short machine code e.g. "L1", "L99", "ZONE-A". Unique. |
| name | string(100) | No | — | Human label e.g. "Shelf L1 Row A" |
| description | text | Yes | null | Optional longer description |
| is_active | boolean | No | true | Active flag — used for dropdown scopes |
| created_at | timestamp | Yes | — | Auto by Laravel |
| updated_at | timestamp | Yes | — | Auto by Laravel |
| deleted_at | timestamp | Yes | null | Soft delete (deactivation) |

---

## Migration File — Exact Code

The initial migration creates the table with a plain `->unique()` on `code`.
A follow-up migration replaces it with a composite unique on `(code, deleted_at)`
so that a soft-deleted code can be reused (see **Soft-Delete Code Reuse** note below).

### Initial migration
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
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_locations');
    }
};
```

### Follow-up migration — fix unique constraint for soft-delete reuse

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
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['code', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->dropUnique(['code', 'deleted_at']);
            $table->unique(['code']);
        });
    }
};
```

---

## After Creating Migration

```bash
php artisan migrate
```

---

## Notes

- **Soft-Delete Code Reuse:** The composite `UNIQUE(code, deleted_at)` index enables code reuse after
  soft-deletion. In MySQL, `NULL` values in a unique index are treated as **distinct** from each other.
  This means:
  - Two active rows with the same code → **blocked** (both have `deleted_at = NULL`)
  - One active + one soft-deleted row with the same code → **allowed** (`NULL` vs a timestamp)
  - Two soft-deleted rows with the same code → **allowed** (different timestamps)
  The FormRequest already uses `Rule::unique('inventory_locations', 'code')->withoutTrashed()`,
  which skips soft-deleted rows during validation. The composite DB index makes the DB constraint
  match that intent so no 500 error occurs on insert.
- `is_active` is stored as a boolean. The service sets it to `false` on deactivation
  alongside soft delete. Restore sets it back to `true`.
- `deleted_at` enables soft delete. Records are never permanently removed.
- No foreign keys in this table — locations are standalone lookup records.
  Future modules (`inventory_movements`) will reference this table via FK.
