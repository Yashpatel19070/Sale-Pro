# Product Module — Model

## File
`app/Models/Product.php`

## Full Implementation

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'sku',
        'name',
        'description',
        'purchase_price',
        'regular_price',
        'sale_price',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'regular_price'  => 'decimal:2',
            'sale_price'     => 'decimal:2',
            'is_active'      => 'boolean',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(ProductListing::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('sku', 'like', "%{$term}%");
        });
    }

    public function scopeForDropdown(Builder $query): Builder
    {
        return $query->active()
            ->orderBy('name')
            ->select(['id', 'sku', 'name']);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function hasActiveListings(): bool
    {
        return $this->listings()->where('is_active', true)->exists();
    }

    public function getListingsCountAttribute(): int
    {
        return $this->listings()->count();
    }

    /** Returns the active selling price (sale_price if set, otherwise regular_price). */
    public function currentPrice(): string
    {
        return $this->sale_price ?? $this->regular_price;
    }

    public function isOnSale(): bool
    {
        return $this->sale_price !== null;
    }
}
```

---

## Factory
`database/factories/ProductFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id'    => ProductCategory::factory(),
            'sku'            => strtoupper($this->faker->unique()->bothify('???-####')),
            'name'           => $this->faker->words(3, true),
            'description'    => $this->faker->optional()->paragraph(),
            'purchase_price' => $this->faker->optional()->randomFloat(2, 1, 100),
            'regular_price'  => $this->faker->randomFloat(2, 5, 500),
            'sale_price'     => null,
            'notes'          => $this->faker->optional()->sentence(),
            'is_active'      => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function uncategorised(): static
    {
        return $this->state(['category_id' => null]);
    }

    public function onSale(float $salePrice): static
    {
        return $this->state(['sale_price' => $salePrice]);
    }

    public function withPrices(float $purchase, float $regular, ?float $sale = null): static
    {
        return $this->state([
            'purchase_price' => $purchase,
            'regular_price'  => $regular,
            'sale_price'     => $sale,
        ]);
    }
}
```

## Checklist
- [ ] `sku` in `$fillable`; immutable (never updated via service after creation)
- [ ] `purchase_price`, `regular_price`, `sale_price` all cast to `decimal:2`
- [ ] `category()` BelongsTo ProductCategory
- [ ] `listings()` HasMany ProductListing
- [ ] `scopeSearch()` searches name and sku
- [ ] `scopeForDropdown()` returns id, sku, name for select inputs
- [ ] `hasActiveListings()` used by service to guard delete
- [ ] `currentPrice()` returns sale_price when set, else regular_price
- [ ] `isOnSale()` returns true when sale_price is set
- [ ] Factory `sku` is uppercase + unique; `sale_price` defaults to null
