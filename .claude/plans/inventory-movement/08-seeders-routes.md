# InventoryMovement Module — Seeders & Routes

## Permission Seeder

```php
<?php
// database/seeders/InventoryMovementPermissionSeeder.php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\LaravelPermission\Models\Role;
use Spatie\LaravelPermission\Models\Permission as SpatiePermission;

class InventoryMovementPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions
        $permissions = [
            Permission::INVENTORY_MOVEMENTS_VIEW,
            Permission::INVENTORY_MOVEMENTS_TRANSFER,
            Permission::INVENTORY_MOVEMENTS_SELL,
            Permission::INVENTORY_MOVEMENTS_ADJUST,
        ];

        foreach ($permissions as $permission) {
            SpatiePermission::firstOrCreate(['name' => $permission]);
        }

        // Assign to roles
        // super-admin: all permissions (null-safe — may not exist in all envs)
        Role::where('name', 'super-admin')->first()?->givePermissionTo($permissions);

        // admin: all permissions
        Role::where('name', 'admin')->first()?->givePermissionTo($permissions);

        // manager: all permissions (same as admin)
        Role::where('name', 'manager')->first()?->givePermissionTo($permissions);

        // sales: view, transfer, sell — NOT adjust
        Role::where('name', 'sales')->first()?->givePermissionTo([
            Permission::INVENTORY_MOVEMENTS_VIEW,
            Permission::INVENTORY_MOVEMENTS_TRANSFER,
            Permission::INVENTORY_MOVEMENTS_SELL,
            // Note: ADJUST is admin/manager only
        ]);
    }
}
```

---

## Routes

```php
// routes/web.php — inside the admin route group
// (The admin group already has: middleware(['auth', 'load_perms', 'verified', 'active']), prefix('admin'), name('admin.'))

use App\Http\Controllers\InventoryMovementController;

// ── Inventory Movement Routes ────────────────────────────────────────────────

Route::prefix('inventory-movements')
     ->name('inventory-movements.')
     ->group(function () {

    // Movement history list (all roles with view permission)
    Route::get('/', [InventoryMovementController::class, 'index'])
         ->name('index');

    // Create form (transfer, sale, adjustment)
    Route::get('/create', [InventoryMovementController::class, 'create'])
         ->name('create');

    // Store new movement
    Route::post('/', [InventoryMovementController::class, 'store'])
         ->name('store');

    // NO edit, update, destroy — movements are immutable
});

// Serial timeline — nested under inventory-serials (inside admin group, so gets admin. prefix)
Route::get(
    'inventory-serials/{inventorySerial}/movements',
    [InventoryMovementController::class, 'forSerial']
)->name('inventory-serials.movements');
// Named: admin.inventory-serials.movements (admin. prefix from the enclosing admin group)
```

### Named Routes Reference

| Name | Method | URI | Controller Action |
|------|--------|-----|-------------------|
| `admin.inventory-movements.index` | GET | `/admin/inventory-movements` | `index()` |
| `admin.inventory-movements.create` | GET | `/admin/inventory-movements/create` | `create()` |
| `admin.inventory-movements.store` | POST | `/admin/inventory-movements` | `store()` |
| `admin.inventory-serials.movements` | GET | `/admin/inventory-serials/{inventorySerial}/movements` | `forSerial()` |
| `inventory-serials.movements` | GET | `/admin/inventory-serials/{inventorySerial}/movements` | `forSerial()` |

> There is NO `show`, `edit`, `update`, or `destroy` route. This is intentional — movements are immutable.

---

## DatabaseSeeder Registration

```php
// Add to DatabaseSeeder.php after InventorySerialSeeder::class:
InventoryMovementPermissionSeeder::class,
// InventoryMovementSeeder is optional dev data — run manually:
// php artisan db:seed --class=InventoryMovementSeeder
// inventory (stock view) InventoryPermissionSeeder follows in its own module
```

---

## Development Seed Data (optional, for local testing)

```php
<?php
// database/seeders/InventoryMovementSeeder.php
// Run manually: php artisan db:seed --class=InventoryMovementSeeder

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerial;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventoryMovementSeeder extends Seeder
{
    public function run(): void
    {
        $admin   = User::first();
        $serials = InventorySerial::with('product')->inRandomOrder()->take(10)->get();
        $locations = InventoryLocation::where('is_active', true)->get();

        if ($serials->isEmpty() || $locations->isEmpty() || ! $admin) {
            $this->command->warn('InventoryMovementSeeder: requires users, serials, and locations to exist first.');
            return;
        }

        foreach ($serials as $serial) {
            // Create a receive movement for each serial
            InventoryMovement::factory()->receive()->create([
                'inventory_serial_id' => $serial->id,
                'to_location_id'      => $locations->random()->id,
                'user_id'             => $admin->id,
            ]);

            // 50% chance of a follow-up transfer
            if (rand(0, 1)) {
                InventoryMovement::factory()->transfer()->create([
                    'inventory_serial_id' => $serial->id,
                    'from_location_id'    => $locations->random()->id,
                    'to_location_id'      => $locations->random()->id,
                    'user_id'             => $admin->id,
                ]);
            }
        }

        $this->command->info('InventoryMovementSeeder: seeded movements for ' . $serials->count() . ' serials.');
    }
}
```

---

## Policy Registration in AppServiceProvider

```php
// app/Providers/AppServiceProvider.php

use App\Models\InventoryMovement;
use App\Policies\InventoryMovementPolicy;

// Inside the $policies array or boot() method:
protected $policies = [
    // ... existing policies ...
    InventoryMovement::class => InventoryMovementPolicy::class,
];
```

---

## Artisan Commands to Run After Implementation

```bash
# 1. Run the migration
php artisan migrate

# 2. Seed roles (if not already done)
php artisan db:seed --class=RoleSeeder

# 3. Seed movement permissions
php artisan db:seed --class=InventoryMovementPermissionSeeder

# 4. (Optional) Seed sample movement data
php artisan db:seed --class=InventoryMovementSeeder

# 5. Clear permission cache after seeding
php artisan permission:cache-reset

# 6. Run the tests
php artisan test tests/Feature/InventoryMovementControllerTest.php
php artisan test tests/Unit/Services/InventoryMovementServiceTest.php
```

---

## Developer Checklist — Before Marking Complete

### PHP & Code Style
- [ ] `declare(strict_types=1)` on every PHP file (model, service, controller, request, policy, seeder)
- [ ] Full type hints on every method — no missing return types or parameter types
- [ ] No raw permission strings anywhere — always `Permission::CONSTANT`

### Database & Model
- [ ] Migration has NO `softDeletes()` column — movements are immutable
- [ ] `InventoryMovement` model has NO `SoftDeletes` trait
- [ ] `$fillable` set to all writable columns (no `product_id` — derived via serial)
- [ ] `casts()` returns `['type' => MovementType::class]`
- [ ] Relations: `serial()`, `user()`, `fromLocation()`, `toLocation()` — all typed `BelongsTo`
- [ ] No `LogsActivity` trait — the movement row itself is the audit entry

### Service Layer
- [ ] `adjustment()` guard at top: `throw_if($serial->status !== SerialStatus::InStock, DomainException::class, ...)`
- [ ] `transfer()`, `sale()`, `adjustment()` all wrapped in `DB::transaction()`
- [ ] `$serial->refresh()` called inside the transaction (TOCTOU protection)
- [ ] All service methods accept Eloquent models — not raw IDs
- [ ] `receive()` is private/internal — no route or UI entry point
- [ ] No `markDamaged()` or `markMissing()` on `InventorySerialService` — goes through here

### FormRequest
- [ ] `StoreInventoryMovementRequest::authorize()` checks per-type permission via `match()`
- [ ] `receive` type blocked via `Rule::notIn([MovementType::Receive->value])`
- [ ] `after()` hook validates serial is in_stock AND from_location matches serial's current location
- [ ] `adjustment_status` required when type=adjustment; prohibited for other types
- [ ] `$request->validated()` used in controller — never `$request->all()`
- [ ] Conditional rules via `Rule::when()` — not manual if/else in rules()

### Controller
- [ ] Every action calls `$this->authorize()` using the Policy
- [ ] `store()` catches `\DomainException` and returns `back()->withErrors(['error' => $e->getMessage()])`
- [ ] `create()` reads `$request->query('serial_id')` and `$request->query('type', 'transfer')` for pre-population
- [ ] Constructor injects both `InventoryMovementService` and `InventoryLocationService`
- [ ] No `edit`, `update`, or `destroy` actions exist on the controller

### Policy
- [ ] `update()` returns `false` unconditionally
- [ ] `delete()` returns `false` unconditionally
- [ ] `create()` returns true if user has ANY of transfer/sell/adjust permission
- [ ] Policy registered in `AppServiceProvider`

### Views
- [ ] `@csrf` on the create form
- [ ] All output uses `{{ }}` — never `{!! !!}`
- [ ] `@can('create', App\Models\InventoryMovement::class)` guards the "Record Movement" button
- [ ] Radio buttons use `@can('inventory-movements.transfer')` etc. for per-type visibility (permission strings, not policy — no per-type policy methods)
- [ ] `adjustment_status` dropdown shown/hidden via JavaScript based on selected type
- [ ] `to_location_id` row hidden when type=sale (serial leaves warehouse)
- [ ] `old('type', $selectedType)` used on radios to repopulate after validation failure
- [ ] All 3 view templates use `<x-app-layout>` (not `x-layouts.admin`)

### Routes & Seeders
- [ ] 3 movement routes: GET index, GET create, POST store — NO edit/update/destroy
- [ ] Serial timeline route registered separately: `GET inventory-serials/{inventorySerial}/movements`
- [ ] `InventoryMovementPermissionSeeder` runs after `RoleSeeder` and `InventorySerialPermissionSeeder`
- [ ] Seeder uses null-safe `Role::where('name', ...)->first()?->givePermissionTo()`
- [ ] `sales` role does NOT get `INVENTORY_MOVEMENTS_ADJUST` permission

### Tests
- [ ] Feature test for every controller action: `index`, `create`, `store`, `forSerial`
- [ ] Feature tests for each movement type (transfer, sale, adjustment) with: happy path + validation failure + auth failure
- [ ] `sales` role gets 403 on adjustment POST (tested explicitly)
- [ ] Unit test: `transfer()` — serial location updated, movement row created
- [ ] Unit test: `sale()` — serial status = sold, movement row created
- [ ] Unit test: `adjustment(damaged)` — serial status = damaged, movement row created
- [ ] Unit test: `adjustment()` on non-in_stock serial — throws DomainException, no movement row created (rollback verified)
- [ ] Unit test: `$serial->refresh()` inside transaction (TOCTOU) — assert status re-read from DB
- [ ] `RefreshDatabase` trait on every test class
- [ ] All test data via factories — no hardcoded IDs or values
