# Inventory Module — Tests

## Test Files

```
tests/
├── Feature/
│   └── InventoryControllerTest.php
└── Unit/
    └── Services/
        └── InventoryServiceTest.php
```

---

## Feature Test
`tests/Feature/InventoryControllerTest.php`

```php
<?php

declare(strict_types=1);

use App\Enums\SerialStatus;
use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\InventoryPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(InventoryPermissionSeeder::class);
});

// ── Helpers ────────────────────────────────────────────────────────────────────

function makeSerial(array $attributes = []): InventorySerial
{
    return InventorySerial::factory()->create(array_merge([
        'status' => SerialStatus::InStock,
    ], $attributes));
}

// ── Authorization: index ───────────────────────────────────────────────────────

it('redirects unauthenticated users from stock dashboard', function () {
    $this->get(route('inventory.index'))
        ->assertRedirect(route('login'));
});

it('denies access to stock dashboard for users without inventory permission', function () {
    // A user with no roles / no inventory permission
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('inventory.index'))
        ->assertForbidden();
});

it('allows super-admin to access stock dashboard', function () {
    $user = User::factory()->create()->assignRole('super-admin');

    $this->actingAs($user)
        ->get(route('inventory.index'))
        ->assertOk();
});

it('allows admin to access stock dashboard', function () {
    $user = User::factory()->create()->assignRole('admin');

    $this->actingAs($user)
        ->get(route('inventory.index'))
        ->assertOk();
});

it('allows sales to access stock dashboard', function () {
    $user = User::factory()->create()->assignRole('sales');

    $this->actingAs($user)
        ->get(route('inventory.index'))
        ->assertOk();
});

// ── Index / Overview ───────────────────────────────────────────────────────────

it('shows stock overview with correct on-hand counts', function () {
    $admin    = User::factory()->create()->assignRole('admin');
    $product  = Product::factory()->create(['sku' => 'TEST-001', 'name' => 'Test Widget']);
    $location = InventoryLocation::factory()->create(['code' => 'L1']);

    makeSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id]);
    makeSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id]);

    $this->actingAs($admin)
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertViewIs('inventory.index')
        ->assertViewHas('stockOverview')
        ->assertSee('TEST-001')
        ->assertSee('Test Widget');
});

it('does not count sold serials in overview', function () {
    $admin    = User::factory()->create()->assignRole('admin');
    $product  = Product::factory()->create(['sku' => 'SOLD-SKU']);
    $location = InventoryLocation::factory()->create();

    // 1 in_stock, 2 sold
    makeSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'status' => SerialStatus::InStock]);
    InventorySerial::factory()->create(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'status' => SerialStatus::Sold]);
    InventorySerial::factory()->create(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'status' => SerialStatus::Sold]);

    $response = $this->actingAs($admin)->get(route('inventory.index'));
    $stockOverview = $response->viewData('stockOverview');

    expect($stockOverview->get($product->id)->count())->toBe(1);
});

it('shows empty state when no stock on hand', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('inventory.index'))
        ->assertOk()
        ->assertSee('No stock on hand');
});

// ── Authorization: showBySku ───────────────────────────────────────────────────

it('redirects unauthenticated users from stock by sku', function () {
    $product = Product::factory()->create();

    $this->get(route('inventory.by-sku', $product))
        ->assertRedirect(route('login'));
});

it('denies stock by sku for users without inventory permission', function () {
    $user    = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user)
        ->get(route('inventory.by-sku', $product))
        ->assertForbidden();
});

it('allows sales role to view stock by sku', function () {
    $sales = User::factory()->create()->assignRole('sales');
    $product = Product::factory()->create();

    $this->actingAs($sales)
        ->get(route('inventory.by-sku', $product))
        ->assertOk();
});

// ── showBySku ─────────────────────────────────────────────────────────────────

it('shows stock by sku for a product with serials at multiple locations', function () {
    $admin     = User::factory()->create()->assignRole('admin');
    $product   = Product::factory()->create(['sku' => 'MULTI-SKU', 'name' => 'Multi Widget']);
    $locationA = InventoryLocation::factory()->create(['code' => 'L1', 'name' => 'Shelf L1']);
    $locationB = InventoryLocation::factory()->create(['code' => 'L2', 'name' => 'Shelf L2']);

    makeSerial(['product_id' => $product->id, 'inventory_location_id' => $locationA->id, 'serial_number' => 'SN-001']);
    makeSerial(['product_id' => $product->id, 'inventory_location_id' => $locationB->id, 'serial_number' => 'SN-002']);

    $this->actingAs($admin)
        ->get(route('inventory.by-sku', $product))
        ->assertOk()
        ->assertViewIs('inventory.show-by-sku')
        ->assertViewHas('product')
        ->assertViewHas('stockByLocation')
        ->assertSee('MULTI-SKU')
        ->assertSee('L1')
        ->assertSee('L2')
        ->assertSee('SN-001')
        ->assertSee('SN-002');
});

it('excludes non-in_stock serials from stock by sku view', function () {
    $admin    = User::factory()->create()->assignRole('admin');
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    makeSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'GOOD-SN']);
    InventorySerial::factory()->create([
        'product_id' => $product->id,
        'inventory_location_id' => $location->id,
        'serial_number' => 'SOLD-SN',
        'status' => SerialStatus::Sold,
    ]);

    $this->actingAs($admin)
        ->get(route('inventory.by-sku', $product))
        ->assertSee('GOOD-SN')
        ->assertDontSee('SOLD-SN');
});

it('shows empty state on stock by sku when product has no in_stock serials', function () {
    $admin   = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->get(route('inventory.by-sku', $product))
        ->assertOk()
        ->assertSee('No in_stock serials found');
});

it('returns 404 for stock by sku with non-existent product', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('inventory.by-sku', 9999))
        ->assertNotFound();
});

// ── Authorization: showBySkuAtLocation ────────────────────────────────────────

it('redirects unauthenticated users from sku-at-location view', function () {
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $this->get(route('inventory.by-sku-at-location', [$product, $location]))
        ->assertRedirect(route('login'));
});

it('denies sku-at-location view for users without inventory permission', function () {
    $user     = User::factory()->create();
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $this->actingAs($user)
        ->get(route('inventory.by-sku-at-location', [$product, $location]))
        ->assertForbidden();
});

// ── showBySkuAtLocation ───────────────────────────────────────────────────────

it('admin can view serials for a SKU at a location', function () {
    $admin    = User::factory()->create()->assignRole('admin');
    $product  = Product::factory()->create(['sku' => 'LOC-SKU', 'name' => 'Loc Widget']);
    $location = InventoryLocation::factory()->create(['code' => 'L99', 'name' => 'Shelf L99']);

    makeSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'SN-LOC-001']);

    $this->actingAs($admin)
        ->get(route('inventory.by-sku-at-location', [$product, $location]))
        ->assertOk()
        ->assertViewIs('inventory.show-by-sku-at-location')
        ->assertViewHas('product')
        ->assertViewHas('location')
        ->assertViewHas('serials')
        ->assertSee('LOC-SKU')
        ->assertSee('L99')
        ->assertSee('SN-LOC-001');
});

it('sales can view serials for a SKU at a location', function () {
    $sales    = User::factory()->create()->assignRole('sales');
    $product  = Product::factory()->create(['sku' => 'SALES-SKU']);
    $location = InventoryLocation::factory()->create(['code' => 'SL1']);

    makeSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'SN-SALES-001']);

    $this->actingAs($sales)
        ->get(route('inventory.by-sku-at-location', [$product, $location]))
        ->assertOk()
        ->assertSee('SN-SALES-001');
});

it('only in_stock serials are shown on sku-at-location view', function () {
    $admin    = User::factory()->create()->assignRole('admin');
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    makeSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'GOOD-SN']);
    InventorySerial::factory()->create([
        'product_id' => $product->id,
        'inventory_location_id' => $location->id,
        'serial_number' => 'SOLD-SN',
        'status' => SerialStatus::Sold,
    ]);

    $this->actingAs($admin)
        ->get(route('inventory.by-sku-at-location', [$product, $location]))
        ->assertSee('GOOD-SN')
        ->assertDontSee('SOLD-SN');
});

it('returns 404 for sku-at-location with non-existent product', function () {
    $admin    = User::factory()->create()->assignRole('admin');
    $location = InventoryLocation::factory()->create();

    $this->actingAs($admin)
        ->get(route('inventory.by-sku-at-location', [9999, $location]))
        ->assertNotFound();
});

it('returns 404 for sku-at-location with non-existent location', function () {
    $admin   = User::factory()->create()->assignRole('admin');
    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->get(route('inventory.by-sku-at-location', [$product, 9999]))
        ->assertNotFound();
});

it('shows serial numbers in the sku-at-location table', function () {
    $admin    = User::factory()->create()->assignRole('admin');
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    makeSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'AAA-001']);
    makeSerial(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'AAA-002']);

    $response = $this->actingAs($admin)
        ->get(route('inventory.by-sku-at-location', [$product, $location]));

    $serials = $response->viewData('serials');

    expect($serials->count())->toBe(2);
    $response->assertSee('AAA-001')->assertSee('AAA-002');
});
```

---

## Unit Test
`tests/Unit/Services/InventoryServiceTest.php`

```php
<?php

declare(strict_types=1);

use App\Enums\SerialStatus;
use App\Models\InventoryLocation;
use App\Models\InventorySerial;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ────────────────────────────────────────────────────────────────────

function inStock(array $attrs = []): InventorySerial
{
    return InventorySerial::factory()->create(array_merge(
        ['status' => SerialStatus::InStock],
        $attrs,
    ));
}

function service(): InventoryService
{
    return new InventoryService();
}

// ── overview() ────────────────────────────────────────────────────────────────

it('overview returns empty collection when no in_stock serials exist', function () {
    $result = service()->overview();

    expect($result)->toBeEmpty();
});

it('overview groups in_stock serials by product_id', function () {
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    inStock(['product_id' => $product1->id, 'inventory_location_id' => $location->id]);
    inStock(['product_id' => $product1->id, 'inventory_location_id' => $location->id]);
    inStock(['product_id' => $product2->id, 'inventory_location_id' => $location->id]);

    $result = service()->overview();

    expect($result)->toHaveCount(2)
        ->and($result->get($product1->id)->count())->toBe(2)
        ->and($result->get($product2->id)->count())->toBe(1);
});

it('overview excludes sold serials', function () {
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id]);
    InventorySerial::factory()->create([
        'product_id' => $product->id,
        'inventory_location_id' => $location->id,
        'status' => SerialStatus::Sold,
    ]);

    $result = service()->overview();

    expect($result->get($product->id)->count())->toBe(1);
});

it('overview excludes damaged and missing serials', function () {
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    InventorySerial::factory()->create([
        'product_id' => $product->id, 'inventory_location_id' => $location->id,
        'status' => SerialStatus::Damaged,
    ]);
    InventorySerial::factory()->create([
        'product_id' => $product->id, 'inventory_location_id' => $location->id,
        'status' => SerialStatus::Missing,
    ]);

    $result = service()->overview();

    expect($result)->toBeEmpty();
});

it('overview eager loads the product relation', function () {
    $product  = Product::factory()->create(['sku' => 'TEST-SKU']);
    $location = InventoryLocation::factory()->create();

    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id]);

    $result  = service()->overview();
    $serial  = $result->first()->first();

    expect($serial->relationLoaded('product'))->toBeTrue()
        ->and($serial->product->sku)->toBe('TEST-SKU');
});

// ── stockBySku() ──────────────────────────────────────────────────────────────

it('stockBySku returns empty collection when product has no in_stock serials', function () {
    $product = Product::factory()->create();

    $result = service()->stockBySku($product);

    expect($result)->toBeEmpty();
});

it('stockBySku groups in_stock serials by inventory_location_id', function () {
    $product   = Product::factory()->create();
    $locationA = InventoryLocation::factory()->create();
    $locationB = InventoryLocation::factory()->create();

    inStock(['product_id' => $product->id, 'inventory_location_id' => $locationA->id]);
    inStock(['product_id' => $product->id, 'inventory_location_id' => $locationA->id]);
    inStock(['product_id' => $product->id, 'inventory_location_id' => $locationB->id]);

    $result = service()->stockBySku($product);

    expect($result)->toHaveCount(2)
        ->and($result->get($locationA->id)->count())->toBe(2)
        ->and($result->get($locationB->id)->count())->toBe(1);
});

it('stockBySku excludes serials from other products', function () {
    $productA  = Product::factory()->create();
    $productB  = Product::factory()->create();
    $location  = InventoryLocation::factory()->create();

    inStock(['product_id' => $productA->id, 'inventory_location_id' => $location->id]);
    inStock(['product_id' => $productB->id, 'inventory_location_id' => $location->id]);

    $result = service()->stockBySku($productA);

    expect($result->flatten()->count())->toBe(1);
});

it('stockBySku excludes non-in_stock serials', function () {
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id]);
    InventorySerial::factory()->create([
        'product_id' => $product->id,
        'inventory_location_id' => $location->id,
        'status' => SerialStatus::Sold,
    ]);

    $result = service()->stockBySku($product);

    expect($result->get($location->id)->count())->toBe(1);
});

it('stockBySku eager loads the location relation', function () {
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create(['code' => 'ZONE-X']);

    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id]);

    $result = service()->stockBySku($product);
    $serial = $result->first()->first();

    expect($serial->relationLoaded('location'))->toBeTrue()
        ->and($serial->location->code)->toBe('ZONE-X');
});

// ── stockBySkuAtLocation() ────────────────────────────────────────────────────

it('stockBySkuAtLocation returns empty collection when no in_stock serials match', function () {
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    $result = service()->stockBySkuAtLocation($product, $location);

    expect($result)->toBeEmpty();
});

it('stockBySkuAtLocation returns in_stock serials for the given product and location', function () {
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'SN-001']);
    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'SN-002']);

    $result = service()->stockBySkuAtLocation($product, $location);

    expect($result->count())->toBe(2);
});

it('stockBySkuAtLocation excludes serials from other products', function () {
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    inStock(['product_id' => $productA->id, 'inventory_location_id' => $location->id]);
    inStock(['product_id' => $productB->id, 'inventory_location_id' => $location->id]);

    $result = service()->stockBySkuAtLocation($productA, $location);

    expect($result->count())->toBe(1);
});

it('stockBySkuAtLocation excludes serials from other locations', function () {
    $product   = Product::factory()->create();
    $locationA = InventoryLocation::factory()->create();
    $locationB = InventoryLocation::factory()->create();

    inStock(['product_id' => $product->id, 'inventory_location_id' => $locationA->id]);
    inStock(['product_id' => $product->id, 'inventory_location_id' => $locationB->id]);

    $result = service()->stockBySkuAtLocation($product, $locationA);

    expect($result->count())->toBe(1);
});

it('stockBySkuAtLocation excludes non-in_stock serials', function () {
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'LIVE-SN']);
    InventorySerial::factory()->create([
        'product_id' => $product->id,
        'inventory_location_id' => $location->id,
        'serial_number' => 'SOLD-SN',
        'status' => SerialStatus::Sold,
    ]);

    $result = service()->stockBySkuAtLocation($product, $location);

    expect($result->count())->toBe(1)
        ->and($result->first()->serial_number)->toBe('LIVE-SN');
});

it('stockBySkuAtLocation orders results by serial_number', function () {
    $product  = Product::factory()->create();
    $location = InventoryLocation::factory()->create();

    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'ZZZ-003']);
    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id, 'serial_number' => 'AAA-001']);

    $result = service()->stockBySkuAtLocation($product, $location);

    expect($result->first()->serial_number)->toBe('AAA-001')
        ->and($result->last()->serial_number)->toBe('ZZZ-003');
});

it('stockBySkuAtLocation eager loads product and location relations', function () {
    $product  = Product::factory()->create(['sku' => 'EAGER-SKU']);
    $location = InventoryLocation::factory()->create(['code' => 'EAGER-LOC']);

    inStock(['product_id' => $product->id, 'inventory_location_id' => $location->id]);

    $result = service()->stockBySkuAtLocation($product, $location);
    $serial = $result->first();

    expect($serial->relationLoaded('product'))->toBeTrue()
        ->and($serial->product->sku)->toBe('EAGER-SKU')
        ->and($serial->relationLoaded('location'))->toBeTrue()
        ->and($serial->location->code)->toBe('EAGER-LOC');
});
```

---

## Developer Checklist — Before Marking Complete

### PHP & Code Style
- [ ] `declare(strict_types=1)` on every PHP file (service, controller, policy, seeder)
- [ ] Full type hints on every method — no missing return types or parameter types
- [ ] No raw permission strings anywhere — always `Permission::CONSTANT`

### Service Layer
- [ ] `overview()` uses `with('product')` — no lazy loading
- [ ] `stockBySku()` uses `with('location')` — no lazy loading
- [ ] `stockBySkuAtLocation()` uses `with(['product', 'location'])` — no lazy loading
- [ ] All 3 methods filter `where('status', SerialStatus::InStock)` — never raw string `'in_stock'`
- [ ] No write methods exist on `InventoryService` — read-only module
- [ ] No `DB::transaction()` — no writes at all

### Controller
- [ ] `index()` calls `$this->authorize('viewAny', InventorySerial::class)`
- [ ] `showBySku()` calls `$this->authorize('viewBySku', InventorySerial::class)`
- [ ] `showBySkuAtLocation()` calls `$this->authorize('viewBySkuAtLocation', InventorySerial::class)`
- [ ] Controller passes `$stockOverview` to index view (not `$stockByProduct`)
- [ ] No FormRequest classes — read-only, no user input beyond route model binding
- [ ] Constructor injects `InventoryService` only

### Policy & Permissions
- [ ] 3 permission constants in `Permission` enum: `INVENTORY_VIEW_ANY`, `INVENTORY_VIEW_BY_SKU`, `INVENTORY_VIEW_BY_SKU_AT_LOCATION`
- [ ] `InventoryPolicy` has 3 methods: `viewAny`, `viewBySku`, `viewBySkuAtLocation`
- [ ] Policy registered in `AppServiceProvider` via `Gate::policy(InventorySerial::class, InventoryPolicy::class)`
- [ ] `admin`, `manager`, `sales` all get all 3 permissions via seeder

### Views
- [ ] All 3 views use `<x-app-layout>` — not `x-layouts.admin`
- [ ] All output uses `{{ }}` — never `{!! !!}`
- [ ] No forms in this module — no `@csrf` needed
- [ ] `show-by-sku-at-location` links to `route('inventory-serials.show', $serial)` (cross-module — inventory-serial must be built first)
- [ ] Drill-down breadcrumb links work: index → by-sku → by-sku-at-location → serial show

### Routes & Seeders
- [ ] 3 GET-only routes: `inventory.index`, `inventory.by-sku`, `inventory.by-sku-at-location`
- [ ] NO POST, PUT, PATCH, DELETE routes — read-only module
- [ ] `Route::resource()` NOT used — explicit routes only
- [ ] `InventoryPermissionSeeder` runs after `RoleSeeder` and `InventoryMovementPermissionSeeder`
- [ ] Seeder uses null-safe `Role::where('name', ...)->first()?->givePermissionTo()`

### Tests
- [ ] Feature test for every controller action: `index`, `showBySku`, `showBySkuAtLocation`
- [ ] Each feature test covers: happy path + unauthenticated redirect + authorization failure
- [ ] `admin`, `manager`, `sales` all pass authorization (all 3 have view permissions)
- [ ] Unit test: `overview()` — only in_stock serials grouped by product_id
- [ ] Unit test: `stockBySku()` — only in_stock serials grouped by location
- [ ] Unit test: `stockBySkuAtLocation()` — only in_stock serials for one product+location
- [ ] Unit test: sold/damaged/missing serials excluded from all 3 service methods
- [ ] `RefreshDatabase` trait on every test class
- [ ] All test data via factories — no hardcoded IDs

### Test Coverage (previously documented)

| Area | Covered |
|------|---------|
| Unauthenticated redirect (all 3 routes) | ✅ |
| Forbidden (user without permission) | ✅ |
| admin, manager, sales access | ✅ |
| Overview shows correct products | ✅ |
| Overview excludes sold/damaged/missing | ✅ |
| Overview empty state | ✅ |
| Stock by SKU groups by location | ✅ |
| Stock by SKU excludes non-in_stock | ✅ |
| Stock by SKU empty state | ✅ |
| Stock by SKU 404 on bad product | ✅ |
| SKU at Location: only in_stock shown | ✅ |
| SKU at Location: 404 on bad product | ✅ |
| SKU at Location: 404 on bad location | ✅ |
| SKU at Location: serial numbers visible | ✅ |
| Eager loading (no N+1) | ✅ |
