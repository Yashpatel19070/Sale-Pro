# 04 — Models

> **Layer 3 — Models.** Depends on `01-enums.md`, `03-schema.md`, `14-events-inventory.md`, `15-tests.md`.

## Scope

Skeleton-only model definitions for 5 classes:

- `Order`
- `OrderLine`
- `OrderLineFee`
- `OrderEvent`
- `Payment`

Each model file contains:
- `$fillable` array
- `casts()` method
- Relationship method **signatures** (return type + relation type only — no body logic beyond `return $this->X(...)`)
- Trait usage (e.g., `HasFactory`)
- Constants where needed (e.g., disabling `updated_at`)

**No business logic in models.** No scopes, no accessors with logic, no computed mutators. Tests drive what methods exist.

---

## Decisions LOCKED

| Decision | Rationale |
|----------|-----------|
| No `SoftDeletes` trait on any model | Hard delete only (per `02-permissions.md`) |
| `OrderEvent` model uses `const UPDATED_AT = null` | Append-only table, no `updated_at` column |
| All decimal columns cast `decimal:2` | Stable precision for money fields |
| `OrderEvent::metadata` cast as `array` | JSON column |
| `Payment::payable` is `morphTo()` (via morph map → `'order' => Order::class`) | Reuses table for future replacement charge-backs |
| `Payment::order` is a direct `belongsTo(Order::class)` | Always-set `order_id` gives fast non-polymorphic query path |
| Relationship method names use camelCase (`createdBy`, `lineFees`) | Laravel convention |
| `Order` has separate `createdBy`/`shippedBy`/`deliveredBy` relations | Three different FK columns to `users` |

---

## File locations

```
app/Models/Order.php
app/Models/OrderLine.php
app/Models/OrderLineFee.php
app/Models/OrderEvent.php
app/Models/Payment.php
```

---

## `Order`

```php
<?php
declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'customer_id',
        'source',
        'status',
        'payment_status',
        'shipping',
        'grand_total',
        // billing snapshot
        'billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone',
        'billing_address_line1', 'billing_address_line2',
        'billing_city', 'billing_state', 'billing_postal_code', 'billing_country',
        // shipping snapshot
        'shipping_first_name', 'shipping_last_name', 'shipping_email', 'shipping_phone',
        'shipping_address_line1', 'shipping_address_line2',
        'shipping_city', 'shipping_state', 'shipping_postal_code', 'shipping_country',
        // lifecycle
        'shipped_at', 'shipped_by', 'delivered_at', 'delivered_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'source'         => OrderSource::class,
            'status'         => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'shipping'       => 'decimal:2',
            'grand_total'    => 'decimal:2',
            'shipped_at'     => 'datetime',
            'delivered_at'   => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shippedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }
}
```

**ex-19 ref:** line 73 (all column data), lines 77-82 (billing/shipping snapshots).

---

## `OrderLine`

```php
<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_listing_id',
        'sku',
        'product_name',
        'inventory_serial_id',
        'unit_price',
        'tax_amount',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function productListing(): BelongsTo
    {
        return $this->belongsTo(ProductListing::class);
    }

    public function inventorySerial(): BelongsTo
    {
        return $this->belongsTo(InventorySerial::class);
    }

    public function lineFees(): HasMany
    {
        return $this->hasMany(OrderLineFee::class);
    }
}
```

**ex-19 ref:** line 89-90 (column data), line 93 (line_total formula — computed in service, stored here).

---

## `OrderLineFee`

```php
<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLineFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_line_id',
        'name',
        'amount',
        'tax_amount',
        'fee_total',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'     => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'fee_total'  => 'decimal:2',
        ];
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

**ex-19 ref:** lines 97-99 (Programming Fee + Gas Tuning Fee rows), line 102 (fee_total formula — computed in service, stored here).

---

## `OrderEvent`

```php
<?php
declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderEvent as OrderEventEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEvent extends Model
{
    use HasFactory;

    /**
     * Append-only — no updated_at column.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'event',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'event'    => OrderEventEnum::class,
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

**ex-19 ref:** lines 153-157 (3 event rows with metadata).

**Edge cases:**
- `const UPDATED_AT = null` tells Eloquent not to manage `updated_at` (matches `03-schema.md` migration which omits the column)
- Class name `OrderEvent` collides with enum `OrderEvent` — import aliased as `OrderEventEnum` in the model file

---

## `Payment`

```php
<?php
declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payable_type',
        'payable_id',
        'method',
        'amount',
        'status',
        'cash_received_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'method'           => PaymentMethod::class,
            'status'           => PaymentStatus::class,
            'amount'           => 'decimal:2',
            'cash_received_at' => 'datetime',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

**ex-19 ref:** lines 123-124 (cash payment row).

**Edge cases:**
- `payable_type` stores `"order"` (not `"App\Models\Order"`) — relies on the morph map registered in `AppServiceProvider::boot()` (per `03-schema.md`)
- `order` relation is a redundant-but-handy direct FK path (every payment has `order_id` regardless of `payable_type`)

---

## Dependencies

**Depends on:**
- `01-enums.md` — `OrderSource`, `OrderStatus`, `PaymentStatus`, `PaymentMethod`, `OrderEvent` enums
- `03-schema.md` — table columns + types
- Existing models: `Customer`, `User`, `ProductListing`, `InventorySerial`

**Depended on by:**
- `05-factories.md` — factories generate model instances
- `06-policy.md` — policy receives `Order` instances
- `07-service.md` — service mutates these models inside transactions
- `11-controller.md` — controller resolves `Order` via route model binding
- `15-tests.md` — every test uses these models
- `16-audit-log.md` — `AuditLogService::log($order, ...)` receives `Order`

---

## Validation gates

- [ ] Every model file uses `declare(strict_types=1);`
- [ ] Every `$fillable` lists exactly the columns from `03-schema.md` (no extras, no gaps)
- [ ] Every enum-typed column has a matching cast
- [ ] Every decimal column casts to `decimal:2`
- [ ] Every `datetime` column has a matching cast
- [ ] No `SoftDeletes` trait on any model
- [ ] `OrderEvent` has `const UPDATED_AT = null;`
- [ ] Relationship method names match what tests access (`$order->lines`, `$line->lineFees`, etc.)
- [ ] No method bodies beyond `return $this->relation(...)` calls (no logic in models)
- [ ] No scopes (tests drive query needs, service layer holds query logic)
- [ ] `OrderEvent` model imports the enum aliased to avoid name collision

---

## Cross-check vs Layer 1 + Layer 2

| Source | Asserts |
|--------|---------|
| `03-schema.md` orders columns | All present in `Order::$fillable` |
| `03-schema.md` order_lines columns | All present in `OrderLine::$fillable` |
| `03-schema.md` order_line_fees columns | All present in `OrderLineFee::$fillable` |
| `03-schema.md` order_events columns | All present in `OrderEvent::$fillable` |
| `03-schema.md` payments columns | All present in `Payment::$fillable` |
| `01-enums.md` 5 enums | All bound via casts |
| `14-events-inventory.md` metadata as JSON | `OrderEvent::metadata` cast `array` |
| `15-tests.md` `$order->lines` access | `Order::lines()` defined |
| `15-tests.md` `$order->events` access | `Order::events()` defined |
| `15-tests.md` `$order->payments` access | `Order::payments()` defined |
| `15-tests.md` `$line->lineFees` access | `OrderLine::lineFees()` defined |
| `15-tests.md` `it_sets_payable_type_to_order` | Relies on morph map (`AppServiceProvider`) |
| `15-tests.md` `it_cascades_delete_fees_when_line_deleted` | Relies on DB CASCADE (per `03-schema.md`) |

No gaps. Every test target has a model + relation defined.
