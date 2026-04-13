<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductListingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// Stub model — full implementation in the product-list module.
class ProductListing extends Model
{
    /** @use HasFactory<ProductListingFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['product_id', 'title', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
