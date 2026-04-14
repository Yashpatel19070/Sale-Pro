<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stub model — minimal implementation required by InventorySerialService::receive().
 * The full inventory-movement module will expand this model.
 */
class InventoryMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_serial_id',
        'from_location_id',
        'to_location_id',
        'type',
        'quantity',
        'reference',
        'notes',
        'user_id',
    ];

    public function serial(): BelongsTo
    {
        return $this->belongsTo(InventorySerial::class, 'inventory_serial_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
