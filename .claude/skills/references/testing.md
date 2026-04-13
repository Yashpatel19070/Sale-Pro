# Testing Reference

## Stack
- **Pest** — test runner (built on PHPUnit)
- **RefreshDatabase** — fresh DB state per test
- **Factories** — test data generation

---

## Rules

- Every controller action → Feature test
- Every Service class → Unit test
- Use `RefreshDatabase` in all feature tests
- Use factories for all test data — never hardcode IDs or raw inserts
- Never test implementation details — test behavior and outcomes
- Tests must pass before any PR merge

---

## Setup

```bash
# Create feature test
php artisan make:test OrderTest --pest

# Create unit test
php artisan make:test OrderServiceTest --unit --pest
```

---

## Feature Test — Controller Actions

Feature tests hit the full HTTP stack: middleware, FormRequest, controller, service, DB.

```php
<?php

use App\Models\Order;
use App\Models\User;
use App\Enums\OrderStatus;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Create a user with permission and act as them
    $this->user = User::factory()->create();
    $this->user->assignRole('viewer');
    $this->actingAs($this->user);
});

// index
it('lists orders for authenticated user', function () {
    Order::factory()->count(3)->create(['user_id' => $this->user->id]);

    $response = $this->get(route('orders.index'));

    $response->assertOk();
    $response->assertViewHas('orders');
});

// store
it('creates an order with valid data', function () {
    $this->user->givePermissionTo('orders.create');

    $response = $this->post(route('orders.store'), [
        'shipping_address' => '123 Main St',
        'items' => [
            ['product_id' => Product::factory()->create()->id, 'quantity' => 2],
        ],
    ]);

    $response->assertRedirect(route('orders.show', Order::first()));
    $this->assertDatabaseHas('orders', ['user_id' => $this->user->id]);
});

// validation
it('rejects order with missing shipping address', function () {
    $this->user->givePermissionTo('orders.create');

    $response = $this->post(route('orders.store'), []);

    $response->assertSessionHasErrors(['shipping_address']);
});

// authorization
it('blocks order creation without permission', function () {
    // viewer role has no orders.create permission
    $response = $this->post(route('orders.store'), [
        'shipping_address' => '123 Main St',
    ]);

    $response->assertForbidden();
});

// show
it('shows an order', function () {
    $order = Order::factory()->create(['user_id' => $this->user->id]);

    $response = $this->get(route('orders.show', $order));

    $response->assertOk();
    $response->assertViewHas('order');
});

// update
it('updates an order', function () {
    $this->user->givePermissionTo('orders.edit');
    $order = Order::factory()->create(['user_id' => $this->user->id]);

    $response = $this->patch(route('orders.update', $order), [
        'shipping_address' => 'New Address',
    ]);

    $response->assertRedirect(route('orders.show', $order));
    $this->assertDatabaseHas('orders', ['shipping_address' => 'New Address']);
});

// destroy
it('deletes an order', function () {
    $this->user->givePermissionTo('orders.delete');
    $order = Order::factory()->create(['user_id' => $this->user->id]);

    $response = $this->delete(route('orders.destroy', $order));

    $response->assertRedirect(route('orders.index'));
    $this->assertSoftDeleted('orders', ['id' => $order->id]);
});
```

---

## Unit Test — Service Methods

Unit tests test the service in isolation — no HTTP, no middleware.

```php
<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Enums\OrderStatus;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(OrderService::class);
    $this->user = User::factory()->create();
});

it('creates an order and decrements stock', function () {
    $product = Product::factory()->create(['stock' => 10, 'price_cents' => 1000]);

    $order = $this->service->create([
        'shipping_address' => '123 Main St',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 3],
        ],
    ], $this->user);

    expect($order)->toBeInstanceOf(Order::class);
    expect($order->status)->toBe(OrderStatus::Pending);
    $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'quantity' => 3]);
    expect($product->fresh()->stock)->toBe(7);
});

it('throws DomainException when stock is insufficient', function () {
    $product = Product::factory()->create(['stock' => 1]);

    expect(fn() => $this->service->create([
        'shipping_address' => '123 Main St',
        'items' => [['product_id' => $product->id, 'quantity' => 5]],
    ], $this->user))->toThrow(\DomainException::class);

    // Stock must not have changed
    expect($product->fresh()->stock)->toBe(1);
});

it('wraps creation in a transaction — rolls back on failure', function () {
    $product = Product::factory()->create(['stock' => 1]);

    try {
        $this->service->create([
            'shipping_address' => '123 Main St',
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ], $this->user);
    } catch (\DomainException) {}

    // No order should exist
    $this->assertDatabaseCount('orders', 0);
});

it('cancels a pending order', function () {
    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'status'  => OrderStatus::Pending,
    ]);

    $cancelled = $this->service->cancel($order);

    expect($cancelled->status)->toBe(OrderStatus::Cancelled);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
});

it('throws DomainException when cancelling a shipped order', function () {
    $order = Order::factory()->create([
        'user_id' => $this->user->id,
        'status'  => OrderStatus::Shipped,
    ]);

    expect(fn() => $this->service->cancel($order))
        ->toThrow(\DomainException::class, 'Cannot cancel a shipped order');
});
```

---

## Testing with Permissions

```php
// Assign role in test
$user->assignRole('admin');

// Assign direct permission
$user->givePermissionTo('orders.create');

// Act as user with specific role
$this->actingAs(User::factory()->create()->assignRole('editor'));

// Assert forbidden
$response->assertForbidden(); // 403

// Assert redirect to login (unauthenticated)
$response->assertRedirect(route('login'));
```

**Seed permissions before every test that uses roles:**
```php
beforeEach(function () {
    $this->seed(RoleSeeder::class);         // creates roles first
    $this->seed(ModulePermissionSeeder::class); // then module permissions
});
```
RoleSeeder must run before any module permission seeder — permissions are assigned to roles inside
the module seeder, which needs the roles to already exist.

**Roles available after RoleSeeder:** `super-admin`, `admin`, `manager`, `sales`
When you need a role that is **forbidden** from a resource, use `sales` — it has only customer/user
view permissions and no access to products, departments, etc.

---

## Stub Future Module Dependencies Before Testing

If the module you're implementing references a model or service from a **not-yet-built** module
(e.g. Product → ProductListing), create minimal stubs so tests don't fail with class-not-found errors.

**Required stub files:**
```
app/Models/XyzListing.php           ← minimal model: fillable, casts, SoftDeletes, BelongsTo back
app/Services/XyzListingService.php  ← stub service: method signatures with no-op bodies
database/migrations/xxxx_create_xyz_listings_table.php  ← minimal columns for FKs + assertions
database/factories/XyzListingFactory.php  ← definition() + states the tests actually use
```

**Stub model example:**
```php
// app/Models/ProductListing.php
// Stub — full implementation in the product-list module.
class ProductListing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['product_id', 'title', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
```

**Stub service example:**
```php
// app/Services/ProductListingService.php
// Stub — full implementation in the product-list module.
class ProductListingService
{
    public function regenerateSlugsForProduct(Product $product): void
    {
        // no-op until product-list module is implemented
    }
}
```

Mark all stub files with a `// Stub — full implementation in the <module> module.` comment so
they're easy to identify and replace later.

---

## Useful Pest Assertions

```php
// HTTP
$response->assertOk();                          // 200
$response->assertRedirect(route('orders.index')); // redirect to route
$response->assertForbidden();                   // 403
$response->assertNotFound();                    // 404
$response->assertSessionHasErrors(['field']);    // validation failed
$response->assertSessionHas('success');         // flash message
$response->assertViewHas('orders');             // view has variable

// Database
$this->assertDatabaseHas('orders', ['status' => 'pending']);
$this->assertDatabaseMissing('orders', ['id' => $order->id]);
$this->assertSoftDeleted('orders', ['id' => $order->id]);
$this->assertDatabaseCount('orders', 3);

// Pest expectations
expect($order)->toBeInstanceOf(Order::class);
expect($order->status)->toBe(OrderStatus::Pending);
expect($product->fresh()->stock)->toBe(7);
expect(fn() => $service->cancel($order))->toThrow(\DomainException::class);
```

---

## Factory Usage in Tests

```php
// Basic
$order = Order::factory()->create();

// With state
$order = Order::factory()->pending()->create();
$order = Order::factory()->cancelled()->create();

// With specific fields
$order = Order::factory()->create(['user_id' => $this->user->id]);

// Multiple
$orders = Order::factory()->count(5)->create();

// With relations
$order = Order::factory()->withItems(3)->create();

// Without DB save — for unit tests that don't need persistence
$order = Order::factory()->make();
```

---

## Quick Reference

```
Feature test  → tests full HTTP stack (routes, middleware, controller, DB)
Unit test     → tests service in isolation (no HTTP)

Every controller action  → feature test
Every service method     → unit test

Always:
- RefreshDatabase on every test class
- Factories for all data — never hardcode
- Test happy path + validation failure + authorization failure
- assertDatabaseHas to verify writes
- assertSoftDeleted for soft deletes
- toThrow() for DomainException cases
```
