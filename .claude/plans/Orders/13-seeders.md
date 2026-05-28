# 13 — Seeders

> **Layer 5 — Presentation.** Depends on `02-permissions.md`, `04-models.md`, `05-factories.md`.

## Scope

Two seeders for the Orders module:

- `OrderPermissionSeeder` — creates the 7 Spatie permissions and assigns to the 3 roles (admin/manager/sales)
- `OrderSeeder` — optional demo data; creates one ex-19-shaped completed order for development/staging

Plus a registration line in `DatabaseSeeder`.

**Code-complete file** — seeders are pure data declarations.

---

## Decisions LOCKED

| Decision | Rationale |
|----------|-----------|
| Permission seeder registers all 7 slugs with guard `web` | Matches Spatie convention used by `CustomerPermissionSeeder` |
| Roles assigned via `Role::findByName(...)->givePermissionTo([...])` | Standard Spatie API |
| Demo `OrderSeeder` uses factories from `05-factories.md` — NOT direct service calls | Service has validation that complicates seeding final-state orders; factory composition is cleaner |
| Demo seeder is registered in `DatabaseSeeder` but can be commented out for production | Demo data shouldn't ship to production |
| Demo seeder skips if no `admin` user exists | Defensive — depends on User seeder running first |
| Demo seeder produces order with `status=Complete` matching ex-19's end state | Shows the full lifecycle in dev |

---

## File locations

```
database/seeders/OrderPermissionSeeder.php
database/seeders/OrderSeeder.php
database/seeders/DatabaseSeeder.php    (modified — add 2 lines)
```

---

## `OrderPermissionSeeder`

```php
<?php
declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class OrderPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'orders.viewAny',
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.delete',
            'orders.recordPayment',
            'orders.complete',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate([
                'name'       => $name,
                'guard_name' => 'web',
            ]);
        }

        // admin and manager get everything
        foreach (['admin', 'manager'] as $roleName) {
            $role = Role::findByName($roleName, 'web');
            $role->givePermissionTo($permissions);
        }

        // sales gets everything EXCEPT orders.delete
        $sales = Role::findByName('sales', 'web');
        $sales->givePermissionTo(array_filter(
            $permissions,
            fn ($p) => $p !== 'orders.delete'
        ));
    }
}
```

**ex-19 ref:** N/A (data underlying authorization, not in ex-19 rows).

**Dependencies:**
- `admin`, `manager`, `sales` roles must exist (created by `RoleSeeder` — runs first)
- `Spatie\Permission` package installed

---

## `OrderSeeder` (demo data)

```php
<?php
declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrderEvent as OrderEventEnum;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SerialStatus;
use App\Models\Customer;
use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderLine;
use App\Models\OrderLineFee;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role('admin')->first();
        if ($admin === null) {
            return;  // defensive — depends on User seeder running first
        }

        // ex-19 fixture: Rachel Park, ECM-2024, SN-200
        $customer = Customer::factory()->create([
            'name'       => 'Rachel Park',
            'email'      => 'rachel@example.com',
            'phone'      => '555-190-0001',
            'tax_exempt' => false,
        ]);

        $product  = Product::factory()->create(['sku' => 'ECM-2024', 'name' => 'Engine Control Module']);
        $listing  = ProductListing::factory()->active()->for($product)->create();
        $location = InventoryLocation::factory()->create(['name' => 'Warehouse A']);
        $serial   = InventorySerial::factory()
            ->forProduct($product)
            ->atLocation($location)
            ->create([
                'serial_number' => 'SN-200',
                'status'        => SerialStatus::Sold,  // ex-19 end state
            ]);

        // Order — complete state
        $order = Order::factory()->complete()->withShopBilling()->create([
            'number'      => 'ORD-2026-0019',
            'customer_id' => $customer->id,
            'shipping'    => 0.00,
            'grand_total' => 286.86,
            'created_by'  => $admin->id,
        ]);

        $line = OrderLine::factory()->withSerial($serial)->create([
            'order_id'           => $order->id,
            'product_listing_id' => $listing->id,
            'sku'                => 'ECM-2024',
            'product_name'       => 'Engine Control Module',
            'unit_price'         => 200.00,
            'tax_amount'         => 16.50,
            'line_total'         => 216.50,
        ]);

        OrderLineFee::factory()->programming()->create([
            'order_line_id' => $line->id,
            'created_by'    => $admin->id,
        ]);
        OrderLineFee::factory()->gasTuning()->create([
            'order_line_id' => $line->id,
            'created_by'    => $admin->id,
        ]);

        // Payment
        Payment::factory()->cash()->paid()->create([
            'order_id'         => $order->id,
            'payable_type'     => 'order',
            'payable_id'       => $order->id,
            'amount'           => 286.86,
            'cash_received_at' => now()->subDay(),
            'created_by'       => $admin->id,
        ]);

        // 3 lifecycle events
        OrderEvent::factory()->orderPlaced()->create([
            'order_id'   => $order->id,
            'created_by' => $admin->id,
            'created_at' => now()->subDay()->subHour(),
        ]);
        OrderEvent::factory()->paymentReceived()->create([
            'order_id'   => $order->id,
            'created_by' => $admin->id,
            'created_at' => now()->subDay(),
        ]);
        OrderEvent::factory()->completed()->create([
            'order_id'   => $order->id,
            'created_by' => $admin->id,
            'created_at' => now(),
        ]);
    }
}
```

**ex-19 ref:** entire file (Rachel Park, ECM-2024, SN-200, $286.86, Programming Fee + Gas Tuning Fee, 3 events, etc.).

**What this seeder does NOT do:**
- Does NOT create an `inventory_movement` row (would require InventoryMovementService call; demo skips this — service-driven creation can be triggered manually for live testing)
- Does NOT seed customer addresses for Rachel (in-store pickup, no address needed per ex-19)
- Does NOT seed shipment row (none in ex-19)

### Additional fixture — Texas Test Buyer (for AvaTax verification)

`OrderSeeder` ALSO creates a second customer used for end-to-end AvaTax testing. This customer is **not part of ex-19** but exists so a developer can verify the AvaTax integration returns non-zero tax from a real nexus state.

```php
// Texas Test Buyer — used to verify AvaTax tax calculation works end-to-end
$tx = Customer::factory()->create([
    'name'       => 'Texas Test Buyer',
    'email'      => 'texas@example.com',
    'phone'      => '713-555-0199',
    'tax_exempt' => false,
]);

CustomerAddress::factory()->create([
    'customer_id'   => $tx->id,
    'label'         => 'Home',
    'first_name'    => 'Texas',
    'last_name'     => 'Buyer',
    'address_line1' => '1100 Congress Ave',
    'city'          => 'Austin',
    'state'         => 'TX',
    'postal_code'   => '78701',
    'country'       => 'US',
    'is_default'    => true,
]);
```

| Field | Value |
|-------|-------|
| Customer name | Texas Test Buyer |
| Address | 1100 Congress Ave, Austin, TX 78701 |
| Purpose | Verifies AvaTax returns non-zero tax for TX (nexus state); contrast with Wyoming (no nexus, returns 0) |
| Use | Create an order with this customer + a shipping address, watch `tax_amount` populate |

### Tests covered
- `texas_test_buyer_exists_with_tx_address` (in `tests/Feature/OrderSeederTest.php` or similar — verifies the seeder fixture)

---

## `DatabaseSeeder` registration

Add to the existing `DatabaseSeeder::run()` `call()` array:

```php
$this->call([
    RoleSeeder::class,
    DepartmentSeeder::class,
    // ... existing seeders ...
    CustomerPermissionSeeder::class,
    CustomerSeeder::class,
    CustomerAddressSeeder::class,
    // ... product, inventory, etc. ...

    OrderPermissionSeeder::class,     // ← NEW (required)
    OrderSeeder::class,                // ← NEW (optional demo — can comment out for production)
]);
```

> Order matters: `OrderPermissionSeeder` must run AFTER `RoleSeeder` (roles must exist). `OrderSeeder` must run AFTER `CustomerSeeder`, `ProductSeeder`, `InventoryLocationSeeder`, `InventorySerialSeeder`.

---

## Dependencies

**Depends on:**
- `02-permissions.md` — 7 permission slugs
- `04-models.md` — `Order`, `OrderLine`, `OrderLineFee`, `OrderEvent`, `Payment` models
- `05-factories.md` — factories + named states (`complete`, `withShopBilling`, `withSerial`, `programming`, `gasTuning`, `cash`, `paid`, etc.)
- Existing seeders: `RoleSeeder`, `CustomerPermissionSeeder` (already exists)
- Existing factories: `Customer`, `Product`, `ProductListing`, `InventoryLocation`, `InventorySerial`, `User`

**Depended on by:**
- `15-tests.md` — `beforeEach(fn() => $this->seed(OrderPermissionSeeder::class))`
- Development workflow — `php artisan migrate:fresh --seed` reproduces ex-19 in DB

---

## Validation gates

- [ ] `OrderPermissionSeeder` creates all 7 permissions
- [ ] `OrderPermissionSeeder` assigns all 7 to `admin` + `manager`
- [ ] `OrderPermissionSeeder` assigns 6 to `sales` (NOT `orders.delete`)
- [ ] Uses `firstOrCreate` (idempotent — safe to run multiple times)
- [ ] `OrderSeeder` checks for admin user existence (defensive)
- [ ] `OrderSeeder` produces an order matching ex-19's data verbatim
- [ ] Demo seeder uses factory states, NOT direct DB inserts
- [ ] `DatabaseSeeder` `call()` array updated
- [ ] Demo seeder runs AFTER all its dependency seeders
- [ ] Both files use `declare(strict_types=1)`

---

## Cross-check vs Layer 1 + 2 + 3 + 4

| Source | Seeder provides |
|--------|-----------------|
| `02-permissions.md` 7 permissions | All 7 in `OrderPermissionSeeder` |
| `02-permissions.md` sales lacks orders.delete | Array filter excludes it from sales |
| `05-factories.md` named states | Demo seeder chains `->complete()`, `->withShopBilling()`, `->programming()`, etc. |
| `15-tests.md` `beforeEach($this->seed(OrderPermissionSeeder::class))` | Permission seeder is callable in tests |
| ex-19 lines 73, 89-90, 97-99, 122-124, 155-157 | Demo seeder reproduces every row |

No gaps. Permissions cover policy needs; demo seeder reproduces ex-19 verbatim.
