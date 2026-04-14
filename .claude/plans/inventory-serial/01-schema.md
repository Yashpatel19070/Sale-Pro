# InventorySerial — Schema

## Migration

```php
<?php

declare(strict_types=1);

use App\Enums\SerialStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_serials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('inventory_location_id')
                ->nullable()
                ->constrained('inventory_locations')
                ->nullOnDelete();

            $table->string('serial_number', 100)->unique();

            $table->decimal('purchase_price', 10, 2);

            $table->date('received_at');

            $table->string('supplier_name', 150)->nullable();

            $table->foreignId('received_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->enum('status', array_column(SerialStatus::cases(), 'value'))
                ->default(SerialStatus::InStock->value);

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Useful query patterns
            $table->index(['product_id', 'status']);
            $table->index(['inventory_location_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_serials');
    }
};
```

**File path:** `database/migrations/xxxx_create_inventory_serials_table.php`

> The migration must run **after** `create_inventory_locations_table` and `create_products_table`.
> Adjust the timestamp prefix accordingly.

---

## SerialStatus Enum

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum SerialStatus: string
{
    case InStock  = 'in_stock';
    case Sold     = 'sold';
    case Damaged  = 'damaged';
    case Missing  = 'missing';

    public function label(): string
    {
        return match ($this) {
            self::InStock  => 'In Stock',
            self::Sold     => 'Sold',
            self::Damaged  => 'Damaged',
            self::Missing  => 'Missing',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStock  => 'green',
            self::Sold     => 'blue',
            self::Damaged  => 'red',
            self::Missing  => 'yellow',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::InStock  => 'bg-green-100 text-green-800',
            self::Sold     => 'bg-blue-100 text-blue-800',
            self::Damaged  => 'bg-red-100 text-red-800',
            self::Missing  => 'bg-yellow-100 text-yellow-800',
        };
    }

    /** Returns statuses that indicate the item is no longer on a shelf. */
    public function isOffShelf(): bool
    {
        return match ($this) {
            self::InStock  => false,
            self::Sold, self::Damaged, self::Missing => true,
        };
    }

    /** Returns all cases as [value => label] for select dropdowns. */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $carry, self $case) => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
```

**File path:** `app/Enums/SerialStatus.php`

---

## Notes

- `inventory_location_id` is `nullable` — null means the item is not on any shelf (sold, missing, or damaged).
- `purchase_price` uses `decimal(10,2)` — same precision as products.
- `received_at` is a `date` (not `datetime`) — just the calendar date, no time component.
- `softDeletes` allows recovery of accidentally created records without data loss.
- Composite indexes on `(product_id, status)` and `(inventory_location_id, status)` cover
  the most common filter queries (stock at a location, all units of a SKU).
