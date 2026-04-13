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
    use HasFactory, HasSlug, SoftDeletes;

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
                return ($listing->product->sku ?? '').' '.$listing->title;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate(); // service controls regen manually
    }

    protected function casts(): array
    {
        return [
            'visibility' => ListingVisibility::class,
            'is_active' => 'boolean',
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
        return $query->where('visibility', ListingVisibility::Public)
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
