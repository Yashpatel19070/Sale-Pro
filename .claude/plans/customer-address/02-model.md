# Customer Address Module — Model

**File:** `app/Models/CustomerAddress.php`

No enum needed — no status field. `is_default` is a plain boolean.

---

## Full Model Code

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerAddress extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'label',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    // --- Relationships ---

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // Shipment relationship deferred — added when shipment module is built.

    // --- Scopes ---

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}
```

---

## Notes

- `customer_id` in `$fillable` — service passes full validated array including FK when not using relationship method
- `is_default` cast to `'boolean'` — `$address->is_default` returns `true/false`, not `1/0`
- `scopeDefault()` — used by service to find/unset current default address per customer
- `HasFactory` required for tests — factory created in `08-tests.md`
- `SoftDeletes` required — hard delete blocked by `shipments.customer_address_id` FK
- No status, no enum — this model has no lifecycle state beyond soft delete
- Shipment `hasMany` relationship added later when shipment module is built

---

## Migration

**File:** `database/migrations/xxxx_create_customer_addresses_table.php`

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
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('label', 50);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address_line1', 255);
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 100);
            $table->string('state', 10);
            $table->string('postal_code', 20);
            $table->char('country', 2)->default('US');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('customer_id');
            $table->index(['customer_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
```

### Migration Notes
- `cascadeOnDelete()` — deleting a customer soft-deletes their addresses via cascade
- `char('country', 2)` — enforces exactly 2-character country code at DB level
- `index('customer_id')` — listed explicitly even though FK creates one; kept for clarity
- `index(['customer_id', 'is_default'])` — composite for fast default lookup per customer
- No unique constraint on `is_default` — DB does NOT enforce one-default rule (service does)
