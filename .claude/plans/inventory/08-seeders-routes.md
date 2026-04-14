# Inventory Module — Seeders & Routes

## No Data Seeder Required

This module is read-only and derives all data from `inventory_serials`. There is no
`InventorySeeder` — stock data comes from the inventory serials module.

The only seeder work is running `InventoryPermissionSeeder`.
See `05-requests-policy.md` for the full `InventoryPermissionSeeder` code.

---

## DatabaseSeeder Integration

```php
// Add to DatabaseSeeder.php after InventoryMovementPermissionSeeder::class:
InventoryPermissionSeeder::class,

// Final complete inventory seeder order in DatabaseSeeder.php:
// InventoryLocationPermissionSeeder::class,
// InventoryLocationSeeder::class,
// InventorySerialPermissionSeeder::class,
// InventorySerialSeeder::class,
// InventoryMovementPermissionSeeder::class,
// InventoryPermissionSeeder::class,
```

---

## Routes

Add to `routes/web.php` inside the existing admin middleware group:

```php
use App\Http\Controllers\InventoryController;

// Inside: Route::middleware(['auth', 'load_perms', 'verified', 'active'])->group(function () {

// Inventory — stock visibility (read only)
Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', [InventoryController::class, 'index'])
        ->name('index');

    Route::get('/{product}', [InventoryController::class, 'showBySku'])
        ->name('by-sku');

    Route::get('/{product}/{location}', [InventoryController::class, 'showBySkuAtLocation'])
        ->name('by-sku-at-location');
});
```

---

## Named Routes

| Name | URL | Controller action | Description |
|------|-----|-------------------|-------------|
| `inventory.index` | `GET /admin/inventory` | `InventoryController@index` | Stock overview dashboard |
| `inventory.by-sku` | `GET /admin/inventory/{product}` | `InventoryController@showBySku` | Locations holding that SKU + count |
| `inventory.by-sku-at-location` | `GET /admin/inventory/{product}/{location}` | `InventoryController@showBySkuAtLocation` | Serials for one SKU at one location |

---

## Route Model Binding

| Parameter | Resolves to | 404 on |
|-----------|------------|--------|
| `{product}` | `App\Models\Product` | Unknown product ID |
| `{location}` | `App\Models\InventoryLocation` | Unknown location ID (on `by-sku-at-location` route) |

No `->withTrashed()` needed — this module shows live stock only.
Soft-deleted products or locations will return 404 automatically, which is correct behaviour:
a deleted product or location should not have a stock view.

---

## No Resource Routes

Do NOT use `Route::resource()` for this module. It is not a CRUD resource.
Only 3 explicit GET routes are defined. There are no POST, PUT, PATCH, or DELETE routes.

---

## Middleware

All three routes inherit the admin group middleware:
- `auth` — must be logged in
- `load_perms` — loads Spatie permissions into the gate
- `verified` — email must be verified
- `active` — user account must be active

Per-route `->middleware('permission:...')` guards are NOT added here because authorization
is handled inside the controller via `$this->authorize()` and `InventoryPolicy`. Both
approaches work; policy-based authorization is chosen for consistency with other controllers.

---

## Full routes/web.php Diff (for reference)

Only the inventory block needs to be added. Place it after the Product Listings block:

```php
// ── (existing) Product Listings ──────────────────────────────────────────────
Route::resource('product-listings', ProductListingController::class);

// ── Inventory (stock visibility — read only) ──────────────────────────────────
Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', [InventoryController::class, 'index'])
        ->name('index');

    Route::get('/{product}', [InventoryController::class, 'showBySku'])
        ->name('by-sku');

    Route::get('/{product}/{location}', [InventoryController::class, 'showBySkuAtLocation'])
        ->name('by-sku-at-location');
});
```

---

## Blade Route Helpers (quick reference for views)

```blade
{{-- Dashboard --}}
<a href="{{ route('inventory.index') }}">Stock Overview</a>

{{-- Drill down: by product (route model binding — pass the model) --}}
<a href="{{ route('inventory.by-sku', $product) }}">View by SKU</a>

{{-- Drill down: serials for a SKU at a specific location --}}
<a href="{{ route('inventory.by-sku-at-location', [$product, $location]) }}">View Serials</a>
```
