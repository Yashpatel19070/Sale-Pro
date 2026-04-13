# Product Slug Feature — Schema

## Dependencies

- **Requires:** `product_listings` table exists first (see `product-list/01-schema.md`)
- **Requires:** `spatie/laravel-sluggable` installed

> Note: the `slug` column on `product_listings` is defined in `product-list/01-schema.md`.
> This file only covers the redirect history table.

---

## Migration — product_listing_slug_redirects

`database/migrations/YYYY_MM_DD_000002_create_product_listing_slug_redirects_table.php`

> Run **after** `create_product_listings_table`.

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
        Schema::create('product_listing_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')
                ->constrained('product_listings')
                ->cascadeOnDelete(); // redirects deleted when listing is hard-deleted

            $table->string('old_slug', 220)->unique(); // the URL that must keep working as a 301
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — records are append-only, never modified

            $table->index('listing_id');
            // old_slug unique index already covers lookup performance
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_listing_slug_redirects');
    }
};
```

---

## Column Reference

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | bigint | PK auto-increment | |
| `listing_id` | bigint | NOT NULL FK → product_listings.id cascadeOnDelete | |
| `old_slug` | varchar(220) | UNIQUE NOT NULL | The previous slug — portal serves 301 to listing's current slug |
| `created_at` | timestamp | NOT NULL useCurrent() | When the slug changed — **no `updated_at`** |

---

## Key Design Decisions

### No updated_at
Records are append-only. Once a slug is retired it never changes — we only need to know when it was created.

### cascadeOnDelete on listing_id
When a listing is hard-deleted (not the normal path — soft delete is standard), its full redirect history is cleaned up by the DB automatically. On soft-delete the redirects remain so old URLs can still 404 gracefully via the portal route.

### old_slug is UNIQUE
Each retired slug can only point to one listing. If the same slug somehow becomes available again (e.g. after hard-delete + recreate), `firstOrCreate()` in the service handles it idempotently.

---

## Checklist

- [ ] Migration runs after `create_product_listings_table`
- [ ] `old_slug` has UNIQUE constraint
- [ ] `listing_id` FK uses `cascadeOnDelete()`
- [ ] Only `created_at` — no `updated_at`, no `$table->timestamps()`
- [ ] `php artisan migrate` runs clean
