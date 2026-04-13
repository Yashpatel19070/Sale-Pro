# ProductSEO Module — Schema

## Migration: Add SEO Columns to `product_listings`

`database/migrations/YYYY_MM_DD_000004_add_seo_columns_to_product_listings_table.php`

> Run **after** `create_product_listings_table` (product-list) AND `create_product_listing_slug_redirects_table` (product-slug) migrations.
> Migration order: products(1) → product_listings(2) → product_listing_slug_redirects(3) → this(4)

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
        Schema::table('product_listings', function (Blueprint $table) {
            $table->string('meta_title', 160)->nullable()->after('slug');
            $table->string('meta_description', 320)->nullable()->after('meta_title');
        });
    }

    public function down(): void
    {
        Schema::table('product_listings', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description']);
        });
    }
};
```

---

## New Columns: `product_listings`

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| meta_title | varchar(160) | nullable | Admin-set SEO title; fallback: `listing.title` |
| meta_description | varchar(320) | nullable | Admin-set SEO description; fallback: truncated title + SKU |

---

## Key Design Decisions

### Nullable — always fall back gracefully
Both columns are nullable. If not set by admin, the portal renders sensible defaults:
- `meta_title` → `$listing->title`
- `meta_description` → `Str::limit($listing->title . ' — ' . $listing->product->sku, 160)`

### No index needed
These are read-only at render time — one row per request. No filtering or sorting on these columns.

### varchar, not text
320 chars max. No need for TEXT type — keeps schema lean and consistent with length validation in FormRequest.

---

## Model Update: `$fillable`

**Modify** `app/Models/ProductListing.php` (defined in product-list/02-model.md) — add the two new columns:

```php
// Before (product-list/02-model.md):
protected $fillable = [
    'product_id',
    'title',
    'visibility',
    'is_active',
];

// After (product-seo adds):
protected $fillable = [
    'product_id',
    'title',
    'visibility',
    'is_active',
    'meta_title',
    'meta_description',
];
```

> `ProductListing` model is owned by product-list. product-seo extends it by appending two columns. Do not remove or reorder existing entries.

---

## Checklist
- [ ] Migration file created with correct numbering (000004 — after products, product_listings, slug_redirects)
- [ ] `meta_title` varchar(160) nullable added
- [ ] `meta_description` varchar(320) nullable added
- [ ] `down()` method drops both columns cleanly
- [ ] `php artisan migrate` runs clean
- [ ] `ProductListing::$fillable` updated to include `meta_title` and `meta_description`
