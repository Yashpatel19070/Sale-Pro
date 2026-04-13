<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductListingSlugRedirect extends Model
{
    /**
     * This table only has created_at (set via useCurrent() in migration).
     * Disabling Eloquent's automatic timestamp management — the DB default handles created_at.
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
