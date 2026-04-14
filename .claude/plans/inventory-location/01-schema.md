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

---

## After Creating Migration

```bash
php artisan migrate
```

---

## Notes

- `code` has a unique index — duplicate codes are rejected at the DB level.
  The unique rule uses `Rule::unique('inventory_locations', 'code')->withoutTrashed()`
  which means uniqueness is checked among non-deleted locations only — a soft-deleted location's
  code CAN be reused. Remove `withoutTrashed()` if you want to permanently block code reuse.
- `is_active` is stored as a boolean. The service sets it to `false` on deactivation
  alongside soft delete. Restore sets it back to `true`.
- `deleted_at` enables soft delete. Records are never permanently removed.
- No foreign keys in this table — locations are standalone lookup records.
  Future modules (`inventory_movements`) will reference this table via FK.
