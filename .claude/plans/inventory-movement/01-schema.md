# InventoryMovement Module — Schema

## Migration

```php
<?php
// database/migrations/xxxx_create_inventory_movements_table.php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            // The serial this movement belongs to
            $table->foreignId('inventory_serial_id')
                  ->constrained('inventory_serials')
                  ->cascadeOnDelete(); // if a serial is ever hard-deleted (should never happen), clean up

            // Movement type — backed by MovementType enum
            $table->enum('type', ['receive', 'transfer', 'sale', 'adjustment']);

            // Source location — NULL means "came from outside" (receive) or no source (adjustment)
            $table->foreignId('from_location_id')
                  ->nullable()
                  ->constrained('inventory_locations')
                  ->nullOnDelete();

            // Destination location — NULL means "left warehouse" (sale) or no destination (damage/missing)
            $table->foreignId('to_location_id')
                  ->nullable()
                  ->constrained('inventory_locations')
                  ->nullOnDelete();

            // Only populated on type = receive — purchase cost of the unit
            $table->decimal('purchase_price', 10, 2)->nullable();

            // Free-form reference: order number, PO number, reason code
            $table->string('reference', 150)->nullable();

            // Optional long-form notes
            $table->text('notes')->nullable();

            // Who recorded this movement
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete(); // should never happen; guard in app layer

            $table->timestamps();

            // NO softDeletes — movements are immutable. A correction = a new adjustment row.

            // Indexes for common query patterns
            $table->index('inventory_serial_id');       // serial timeline
            $table->index('type');                      // filter by type
            $table->index('from_location_id');          // filter by source
            $table->index('to_location_id');            // filter by destination
            $table->index('user_id');                   // filter by actor
            $table->index('created_at');                // date range filters
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
```

---

## MovementType Enum

```php
<?php
// app/Enums/MovementType.php

declare(strict_types=1);

namespace App\Enums;

enum MovementType: string
{
    case Receive    = 'receive';     // NULL → location (new stock arrives)
    case Transfer   = 'transfer';    // location → location (shelf move)
    case Sale       = 'sale';        // location → NULL (shipped to customer)
    case Adjustment = 'adjustment';  // status change: damaged or missing

    public function label(): string
    {
        return match($this) {
            self::Receive    => 'Received',
            self::Transfer   => 'Transferred',
            self::Sale       => 'Sold',
            self::Adjustment => 'Adjustment',
        };
    }

    public function badgeColor(): string
    {
        return match($this) {
            self::Receive    => 'green',
            self::Transfer   => 'blue',
            self::Sale       => 'purple',
            self::Adjustment => 'yellow',
        };
    }
}
```

---

## Permission Enum Constants to Add

```php
// app/Enums/Permission.php — add these constants alongside existing ones

const INVENTORY_MOVEMENTS_VIEW     = 'inventory-movements.view';
const INVENTORY_MOVEMENTS_TRANSFER = 'inventory-movements.transfer';
const INVENTORY_MOVEMENTS_SELL     = 'inventory-movements.sell';
const INVENTORY_MOVEMENTS_ADJUST   = 'inventory-movements.adjust';
```

---

## Movement Type Matrix

| Type | `from_location_id` | `to_location_id` | Effect on `InventorySerial` |
|------|--------------------|------------------|----------------------------|
| `receive` | `NULL` | shelf | `inventory_location_id = to_location_id`, `status = in_stock` |
| `transfer` | shelf A | shelf B | `inventory_location_id = to_location_id` |
| `sale` | shelf | `NULL` | `inventory_location_id = NULL`, `status = sold` |
| `adjustment` | nullable | nullable | `status = damaged` or `status = missing` |

---

## Notes

- No `SoftDeletes` — this is intentional. Movement rows must never disappear.
- The `cascadeOnDelete` on `inventory_serial_id` is a safety net only — the application
  layer must prevent serial deletion when movements exist.
- `purchase_price` is only meaningful on `receive` type. Service validates this.
- `reference` max 150 chars — enough for order numbers (e.g. `ORD-2024-00123`) and PO numbers.
