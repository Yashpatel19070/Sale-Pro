# Product Slug Feature — Models

## Dependencies

- **Requires:** `01-schema.md` (redirect table migrated)
- **Requires:** `spatie/laravel-sluggable` installed
- **Modifies:** `app/Models/ProductListing.php` — add HasSlug (base model in `product-list/02-model.md`)
- **Creates:** `app/Models/ProductListingSlugRedirect.php`

> The complete `ProductListing` model, `ListingVisibility` enum, and factory are in `product-list/02-model.md`.
> That file already includes HasSlug fully built in — **do not add it a second time**.
> This file explains what was added for slug support and why, as a reference.

---

## ProductListing — Slug Additions

### 1. Add imports

```php
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
```

### 2. Add HasSlug to the use statement

```php
use HasFactory, SoftDeletes, HasSlug;
```

### 3. slug must NOT be in $fillable

```php
protected $fillable = [
    'product_id',
    'title',
    // 'slug' intentionally absent — managed by spatie/laravel-sluggable
    'visibility',
    'is_active',
];
```

### 4. Add getSlugOptions()

```php
/**
 * Slug is generated from product.sku + title.
 *
 * doNotGenerateSlugsOnUpdate() — the service controls regen manually.
 * Sluggable fires automatically on CREATE only. On title change the
 * service calls $listing->generateSlug() explicitly after capturing
 * the old slug for the redirects table.
 *
 * IMPORTANT: product relation must be loaded before save() on create.
 * See ProductListingService::create() — uses setRelation() for this.
 */
public function getSlugOptions(): SlugOptions
{
    return SlugOptions::create()
        ->generateSlugsFrom(function (ProductListing $listing): string {
            return ($listing->product->sku ?? '') . ' ' . $listing->title;
        })
        ->saveSlugsTo('slug')
        ->doNotGenerateSlugsOnUpdate(); // service controls regen — no auto regen on update
}
```

### 5. Add slugRedirects() relation

```php
public function slugRedirects(): HasMany
{
    return $this->hasMany(ProductListingSlugRedirect::class, 'listing_id');
}
```

---

## Slug Generation — Step by Step

```
Input:  product.sku = "TSHIRT-001", title = "Blue / M"

Step 1: closure returns "TSHIRT-001 Blue / M"
Step 2: sluggable slugifies → "tshirt-001-blue-m"
Step 3: uniqueness check → if "tshirt-001-blue-m" exists → "tshirt-001-blue-m-2"
Step 4: writes to slug column
```

---

## ProductListingSlugRedirect Model

`app/Models/ProductListingSlugRedirect.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductListingSlugRedirect extends Model
{
    /**
     * This table only has created_at (set via useCurrent() in migration).
     * Setting $timestamps = false disables Eloquent's automatic
     * created_at/updated_at management — the DB default handles created_at.
     */
    public $timestamps = false;

    protected $fillable = ['listing_id', 'old_slug'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ProductListing::class, 'listing_id');
    }
}
```

---

## Checklist

- [ ] `HasSlug` trait added to `ProductListing` use statement
- [ ] `slug` NOT in `ProductListing::$fillable`
- [ ] `getSlugOptions()` closure reads `$listing->product->sku`
- [ ] `doNotGenerateSlugsOnUpdate()` set in `getSlugOptions()`
- [ ] `slugRedirects()` HasMany relation added to `ProductListing`
- [ ] `ProductListingSlugRedirect::$timestamps = false`
- [ ] `ProductListingSlugRedirect::$fillable` = `['listing_id', 'old_slug']`
- [ ] `created_at` cast to `datetime` in `ProductListingSlugRedirect`
