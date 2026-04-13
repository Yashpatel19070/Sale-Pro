# ProductList Module — Model

## File
`app/Models/ProductListing.php`

## Full Implementation

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ListingVisibility;
use Database\Factories\ProductListingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class ProductListing extends Model
{
    /** @use HasFactory<ProductListingFactory> */
    use HasFactory, SoftDeletes, HasSlug;

    protected $fillable = [
        'product_id',
        'title',
        // 'slug' intentionally absent — managed by spatie/laravel-sluggable
        'visibility',
        'is_active',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function (ProductListing $listing): string {
                // Requires product to be loaded before save
                return ($listing->product->sku ?? '') . ' ' . $listing->title;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate(); // we control regen manually in service
    }

    protected function casts(): array
    {
        return [
            'visibility' => ListingVisibility::class,
            'is_active'  => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function slugRedirects(): HasMany
    {
        return $this->hasMany(ProductListingSlugRedirect::class, 'listing_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', ListingVisibility::Public->value)
                     ->where('is_active', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where('title', 'like', "%{$term}%");
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Returns the active selling price from the parent product.
     * Requires the `product` relationship to be eager-loaded.
     */
    public function currentPrice(): string
    {
        return $this->product->sale_price ?? $this->product->regular_price;
    }

    public function isOnSale(): bool
    {
        return $this->product->sale_price !== null;
    }
}
```

---

## Enum: ListingVisibility
`app/Enums/ListingVisibility.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum ListingVisibility: string
{
    case Public  = 'public';
    case Private = 'private';
    case Draft   = 'draft';

    public function label(): string
    {
        return match($this) {
            self::Public  => 'Public',
            self::Private => 'Private (admin only)',
            self::Draft   => 'Draft',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Public  => 'badge-green',
            self::Private => 'badge-yellow',
            self::Draft   => 'badge-gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
```

---

## Factory
`database/factories/ProductListingFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ListingVisibility;
use App\Models\Product;
use App\Models\ProductListing;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductListingFactory extends Factory
{
    public function definition(): array
    {
        $product = Product::factory()->create();
        $title   = $this->faker->words(2, true);

        return [
            'product_id' => $product->id,
            'title'      => $title,
            // slug intentionally absent — HasSlug generates it on create
            'visibility' => ListingVisibility::Draft->value,
            'is_active'  => true,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state([
            'product_id' => $product->id,
            // slug intentionally absent — HasSlug generates it on create
        ]);
    }

    public function public(): static
    {
        return $this->state([
            'visibility' => ListingVisibility::Public->value,
            'is_active'  => true,
        ]);
    }
}
```

---

> **ProductListingSlugRedirect model:** See `product-slug/02-model.md`

---

## Checklist
- [ ] `$fillable`: only `product_id`, `title`, `visibility`, `is_active` (no `slug`)
- [ ] `HasSlug` trait added — see `product-slug/02-model.md` for slug config details
- [ ] `visibility` cast to `ListingVisibility::class`
- [ ] `product()` BelongsTo Product
- [ ] `slugRedirects()` HasMany relation present
- [ ] `scopePublic()` filters by visibility + is_active
- [ ] `scopeSearch()` searches title only
- [ ] `currentPrice()` proxies to `product->sale_price ?? product->regular_price` (requires eager load)
- [ ] `isOnSale()` proxies to `product->sale_price !== null`
- [ ] `ListingVisibility` enum has `options()` and `badgeClass()`
- [ ] Factory `forProduct()` state does NOT set slug — HasSlug generates it on create
- [ ] `ProductListingSlugRedirect` model — see `product-slug/02-model.md`
- [ ] No price, stock, or attribute fields on this model
