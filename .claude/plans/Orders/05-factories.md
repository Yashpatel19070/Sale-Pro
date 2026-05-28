# 05 — Factories

> **Layer 3 — Models.** Depends on `03-schema.md`, `04-models.md`, `15-tests.md`.

## Scope

Factory classes for 5 models with named states needed by `15-tests.md`:

- `OrderFactory` + states: `pending`, `processing`, `complete`, `paid`, `walkInCash`, `withShopBilling`
- `OrderLineFactory` + states: `withEcm`, `withSerial`
- `OrderLineFeeFactory` + states: `programming`, `gasTuning`
- `OrderEventFactory` + states: `orderPlaced`, `paymentReceived`, `completed`
- `PaymentFactory` + states: `cash`, `paid`

Each factory is **data-definition only** — no business logic, no totals calculation. Service layer handles all derived values; factories just generate plausible defaults for tests that don't care about exact values.

---

## Decisions LOCKED

| Decision | Rationale |
|----------|-----------|
| Default factory state matches ex-19 shape | Tests can `Order::factory()->create()` and get something close to ex-19 |
| Named states are method-chainable (`->pending()`, `->complete()`) | Pest/Eloquent convention |
| Factories DO NOT compute `line_total`, `fee_total`, `grand_total` | Service layer owns derivation; factory just sets explicit values |
| `created_by` field defaults to `User::factory()` if not specified | Required NOT NULL — factory must provide a user |
| `OrderEventFactory` default = `order_placed` with metadata matching ex-19 | Most-common event in tests |
| `PaymentFactory` default = cash, paid, full amount | Matches ex-19 |
| Numeric defaults use ex-19 amounts (`200.00`, `40.00`, `25.00`, etc.) | Greppable in test output |

---

## File locations

```
database/factories/OrderFactory.php
database/factories/OrderLineFactory.php
database/factories/OrderLineFeeFactory.php
database/factories/OrderEventFactory.php
database/factories/PaymentFactory.php
```

---

## `OrderFactory`

```php
<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderSource;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'number'         => 'ORD-2026-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'customer_id'    => Customer::factory(),
            'source'         => OrderSource::WalkIn,
            'status'         => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'shipping'       => 0.00,
            'grand_total'    => 286.86,           // ex-19 default
            // billing snapshot — shop address (default for cash walk-in)
            'billing_first_name'     => 'NPC Sales Pro LLC',
            'billing_last_name'      => null,
            'billing_email'          => 'sales@npcsalespro.com',
            'billing_phone'          => '713-555-0100',
            'billing_address_line1'  => '5426 N Shepherd Dr',
            'billing_address_line2'  => null,
            'billing_city'           => 'Houston',
            'billing_state'          => 'TX',
            'billing_postal_code'    => '77091',
            'billing_country'        => 'US',
            // shipping snapshot — NULL (pickup default)
            'shipping_first_name'    => null,
            'shipping_last_name'     => null,
            'shipping_email'         => null,
            'shipping_phone'         => null,
            'shipping_address_line1' => null,
            'shipping_address_line2' => null,
            'shipping_city'          => null,
            'shipping_state'         => null,
            'shipping_postal_code'   => null,
            'shipping_country'       => null,
            // lifecycle
            'shipped_at'   => null,
            'shipped_by'   => null,
            'delivered_at' => null,
            'delivered_by' => null,
            'created_by'   => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => OrderStatus::Pending, 'payment_status' => PaymentStatus::Unpaid]);
    }

    public function processing(): static
    {
        return $this->state(['status' => OrderStatus::Processing, 'payment_status' => PaymentStatus::Paid]);
    }

    public function complete(): static
    {
        return $this->state(['status' => OrderStatus::Complete, 'payment_status' => PaymentStatus::Paid]);
    }

    public function paid(): static
    {
        return $this->state(['payment_status' => PaymentStatus::Paid]);
    }

    public function walkInCash(): static
    {
        return $this->state(['source' => OrderSource::WalkIn]);
    }

    public function withShopBilling(): static
    {
        return $this->state([
            'billing_first_name'    => 'NPC Sales Pro LLC',
            'billing_address_line1' => '5426 N Shepherd Dr',
            'billing_city'          => 'Houston',
            'billing_state'         => 'TX',
            'billing_postal_code'   => '77091',
            'billing_country'       => 'US',
        ]);
    }
}
```

**ex-19 ref:** line 73 (default values), lines 77-78 (shop billing).

---

## `OrderLineFactory`

```php
<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\ProductListing;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderLineFactory extends Factory
{
    protected $model = OrderLine::class;

    public function definition(): array
    {
        return [
            'order_id'            => Order::factory(),
            'product_listing_id'  => ProductListing::factory(),
            'sku'                 => 'ECM-2024',
            'product_name'        => 'Engine Control Module',
            'inventory_serial_id' => null,                  // explicitly allocated by service
            'unit_price'          => 200.00,
            'tax_amount'          => 16.50,
            'line_total'          => 216.50,                // unit_price + tax_amount
        ];
    }

    public function withSerial(InventorySerial $serial): static
    {
        return $this->state(['inventory_serial_id' => $serial->id]);
    }

    public function withEcm(): static
    {
        $product = Product::factory()->create(['sku' => 'ECM-2024', 'name' => 'Engine Control Module']);
        $listing = ProductListing::factory()->active()->for($product)->create();

        return $this->state([
            'product_listing_id' => $listing->id,
            'sku'                => 'ECM-2024',
            'product_name'       => 'Engine Control Module',
        ]);
    }
}
```

**ex-19 ref:** lines 89-90 (default values).

---

## `OrderLineFeeFactory`

```php
<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Models\OrderLine;
use App\Models\OrderLineFee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderLineFeeFactory extends Factory
{
    protected $model = OrderLineFee::class;

    public function definition(): array
    {
        return [
            'order_line_id' => OrderLine::factory(),
            'name'          => 'Programming Fee',
            'amount'        => 40.00,
            'tax_amount'    => 3.30,
            'fee_total'     => 43.30,            // amount + tax_amount
            'created_by'    => User::factory(),
        ];
    }

    public function programming(): static
    {
        return $this->state([
            'name'       => 'Programming Fee',
            'amount'     => 40.00,
            'tax_amount' => 3.30,
            'fee_total'  => 43.30,
        ]);
    }

    public function gasTuning(): static
    {
        return $this->state([
            'name'       => 'Gas Tuning Fee',
            'amount'     => 25.00,
            'tax_amount' => 2.06,
            'fee_total'  => 27.06,
        ]);
    }
}
```

**ex-19 ref:** lines 97-99 (Programming Fee + Gas Tuning Fee rows).

---

## `OrderEventFactory`

```php
<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderEvent as OrderEventEnum;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderEventFactory extends Factory
{
    protected $model = OrderEvent::class;

    public function definition(): array
    {
        return [
            'order_id'   => Order::factory(),
            'event'      => OrderEventEnum::OrderPlaced,
            'metadata'   => [
                'sku'           => 'ECM-2024',
                'product_name'  => 'Engine Control Module',
                'grand_total'   => '286.86',
            ],
            'created_by' => User::factory(),
        ];
    }

    public function orderPlaced(): static
    {
        return $this->state([
            'event'    => OrderEventEnum::OrderPlaced,
            'metadata' => [
                'sku'          => 'ECM-2024',
                'product_name' => 'Engine Control Module',
                'grand_total'  => '286.86',
            ],
        ]);
    }

    public function paymentReceived(): static
    {
        return $this->state([
            'event'    => OrderEventEnum::PaymentReceived,
            'metadata' => [
                'method'   => 'cash',
                'amount'   => '286.86',
                'shipping' => '0.00',
            ],
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'event'    => OrderEventEnum::Completed,
            'metadata' => [],
        ]);
    }
}
```

**ex-19 ref:** lines 155-157 (3 event rows with exact metadata).

---

## `PaymentFactory`

```php
<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $order = Order::factory()->create();

        return [
            'order_id'         => $order->id,
            'payable_type'     => 'order',          // morph map alias
            'payable_id'       => $order->id,
            'method'           => PaymentMethod::Cash,
            'amount'           => 286.86,
            'status'           => PaymentStatus::Paid,
            'cash_received_at' => now(),
            'created_by'       => User::factory(),
        ];
    }

    public function cash(): static
    {
        return $this->state([
            'method'           => PaymentMethod::Cash,
            'cash_received_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(['status' => PaymentStatus::Paid]);
    }
}
```

**ex-19 ref:** lines 123-124 (cash payment row).

**Edge case:** `payable_type` defaults to `'order'` (morph map alias), matching what the service stores in production.

---

## Dependencies

**Depends on:**
- `01-enums.md` — enum cases used in factory state values
- `03-schema.md` — column names match `$fillable`
- `04-models.md` — factories reference `$model` class
- Existing factories: `CustomerFactory`, `UserFactory`, `ProductFactory`, `ProductListingFactory`, `InventorySerialFactory`

**Depended on by:**
- `13-seeders.md` — `OrderSeeder` uses these factories
- `15-tests.md` — every test instantiates models via factories

---

## Validation gates

- [ ] Every factory file uses `declare(strict_types=1);`
- [ ] Every factory's `definition()` returns ALL `$fillable` keys from the corresponding model
- [ ] No factory uses any column NOT in `$fillable`
- [ ] Defaults match ex-19 amounts (200.00, 40.00, 25.00, etc.) — greppable
- [ ] Named states match what `15-tests.md` calls (e.g., `->processing()`, `->paid()`, `->programming()`, `->gasTuning()`)
- [ ] `OrderEventFactory` default = `OrderPlaced` event
- [ ] `PaymentFactory` default = `cash`, `paid`
- [ ] `OrderLineFactory.inventory_serial_id` defaults to `null` (service allocates)
- [ ] `OrderFactory` default billing = shop address (not customer address)
- [ ] `OrderFactory` default shipping snapshot = all NULL (pickup default)

---

## Cross-check vs Layer 1 + Layer 2

| Source | Factory provides |
|--------|------------------|
| `15-tests.md` `ex19Customer()` helper | Uses `Customer::factory()->create([...])` — relies on existing `CustomerFactory` |
| `15-tests.md` `ex19Listing()` helper | Uses `Product::factory()` + `ProductListing::factory()->active()` — existing factories |
| `15-tests.md` `ex19Serial()` helper | Uses `InventorySerial::factory()->inStock()->atLocation()->forProduct()` — existing factory |
| `15-tests.md` integration tests | Use `Order::factory()` with chained states like `->pending()`, `->paid()`, `->complete()` |
| `15-tests.md` `it_sets_orders_status_from_processing_to_complete` | Uses `Order::factory()->processing()->create()` |
| `15-tests.md` `it_creates_order_line_fee_row_per_payload_fee` | Uses `OrderLineFee::factory()->programming()->create()` + `->gasTuning()->create()` |
| `15-tests.md` `it_inserts_payment_received_event_in_same_transaction` | Asserts event via `OrderEvent::factory()->paymentReceived()` shape |

No gaps. Every factory state requested by tests is defined.
