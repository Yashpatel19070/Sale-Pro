# Database Design Reference

## Naming Conventions
- Tables: `snake_case`, plural (`orders`, `order_items`, `product_variants`)
- Foreign keys: `{singular_table}_id` (`user_id`, `order_id`)
- Pivot tables: alphabetical order, singular (`order_product`, not `product_order`)
- Booleans: `is_` or `has_` prefix (`is_active`, `has_verified_email`)
- Timestamps: `_at` suffix (`published_at`, `cancelled_at`)

---

## Primary Keys
- Default: `$table->id()` (bigIncrements) — fine for internal tables
- Public-facing / exposed in URLs: `$table->ulid('id')->primary()` — prevents enumeration
- UUIDs: avoid unless external system requires them (slower index performance)

---

## Standard Column Patterns

### Soft Deletes
```php
$table->softDeletes(); // adds deleted_at
// In model: use SoftDeletes;
```
Use on: users, orders, products.
Avoid on: audit logs, events, high-volume append-only tables.

### Status Enums
Always use a backed PHP Enum — never raw strings, never MySQL ENUM type:
```php
// app/Enums/OrderStatus.php
enum OrderStatus: string
{
    case Pending   = 'pending';
    case Active    = 'active';
    case Cancelled = 'cancelled';
}

// Migration — store as string column
$table->string('status')->default(OrderStatus::Pending->value);

// Model — casts() method (Laravel 12)
protected function casts(): array
{
    return ['status' => OrderStatus::class];
}

// Usage
$order->status === OrderStatus::Pending;
$order->update(['status' => OrderStatus::Cancelled]);
```

### JSON Columns
```php
$table->json('metadata')->nullable();
// Use for: flexible attributes, external API payloads, feature flags
// Avoid for: anything you filter/sort/join by — use a real column
```

### Money / Prices
```php
$table->unsignedInteger('price_cents'); // always store in cents, never float
// Display: $order->price_cents / 100
```

### Decimal Columns (prices stored as decimal, not cents)
```php
// ✅ CORRECT — unsignedDecimal() was removed in Laravel 10
$table->decimal('price', 10, 2)->unsigned();
$table->decimal('price', 10, 2)->unsigned()->nullable();

// ❌ WRONG — does not exist in Laravel 10+, throws BadMethodCallException
$table->unsignedDecimal('price', 10, 2);
```

---

## Indexes — Deep Dive

### Rules
- **Always** index every foreign key column — Laravel does not do this automatically
- **Always** index columns used in `WHERE`, `ORDER BY`, or `JOIN`
- **Never** index columns with very low cardinality (e.g. boolean `is_active` alone — useless)
- **Composite indexes**: most selective column first, then filter columns, then sort columns
- Too many indexes slow down writes — only add what queries actually need

### Single column index
```php
$table->index('status');           // simple filter
$table->index('created_at');       // date range queries
$table->unique('email');           // unique constraint + implicit index
$table->unique('slug');
```

### Composite index — column order matters
```php
// Query: WHERE user_id = ? AND status = ? ORDER BY created_at DESC
// Index column order must match: filter columns first, sort column last
$table->index(['user_id', 'status', 'created_at']);

// Query: WHERE status = 'active' ORDER BY created_at DESC
$table->index(['status', 'created_at']);

// WRONG — putting low-cardinality column first wastes the index
$table->index(['is_active', 'user_id']); // ❌ is_active only has 2 values
$table->index(['user_id', 'is_active']); // ✅ user_id is selective
```

### Unique index
```php
// Single column
$table->unique('email');

// Composite unique — combination must be unique
$table->unique(['user_id', 'post_id']); // a user can only like a post once

// Unique with soft deletes — use a partial unique in raw SQL or handle in app logic
// MySQL doesn't support partial indexes natively — use unique + check in service layer
```

### Full-text index
```php
// For LIKE '%search%' replacement — much faster on large tables
$table->fullText(['title', 'body']); // MySQL FULLTEXT index

// Query
Post::whereFullText(['title', 'body'], 'laravel tips')->get();

// For serious search — use Meilisearch via Laravel Scout instead
```

### When NOT to index
- Boolean columns alone (`is_active` — too few distinct values)
- Very short tables (< 1000 rows — full scan is faster)
- Columns only ever written, never queried
- More than 5–6 indexes on a write-heavy table — slows inserts/updates

### Adding indexes to existing tables (alter migration)
```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'created_at'], 'orders_user_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_user_status_created_index');
        });
    }
};
```

---

## Relationship Patterns

### One-to-Many
```php
// Migration
$table->foreignId('user_id')->constrained()->cascadeOnDelete();

// Model
public function orders(): HasMany
{
    return $this->hasMany(Order::class);
}

public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

### Many-to-Many with Pivot Data
```php
// Pivot migration
Schema::create('order_product', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('quantity');
    $table->unsignedInteger('unit_price_cents');
    $table->timestamps();

    $table->unique(['order_id', 'product_id']); // prevent duplicates
});

// Model
public function products(): BelongsToMany
{
    return $this->belongsToMany(Product::class)
                ->withPivot('quantity', 'unit_price_cents')
                ->withTimestamps();
}
```

### Polymorphic
```php
$table->morphs('commentable'); // adds commentable_id + commentable_type + index

public function commentable(): MorphTo
{
    return $this->morphTo();
}
```

### Self-Referencing (Categories, Trees)
```php
$table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
```

---

## No N+1 Queries — Non-Negotiable Rule

**N+1 is never acceptable. Every query touching a relation must use eager loading. No exceptions.**

If you access a relation inside a loop without eager loading, you are firing one DB query
per row. 20 posts = 21 queries. 1000 posts = 1001 queries. This kills performance silently.

### Why it happens
```php
// ❌ N+1 — looks innocent, causes 1 + N queries
$posts = Post::all();                  // query 1: SELECT * FROM posts
foreach ($posts as $post) {
    echo $post->user->name;            // query 2,3,4... one per post
}

// ✅ Eager load — always exactly 2 queries, 2 or 2000 posts
$posts = Post::with('user')->get();    // query 1: SELECT * FROM posts
                                       // query 2: SELECT * FROM users WHERE id IN (...)
foreach ($posts as $post) {
    echo $post->user->name;            // memory — zero queries
}
```

### Rule: eager load in the query, never access relations in loops
```php
// ❌ Never — relation accessed in loop without eager loading
foreach ($orders as $order) {
    $order->user->name;        // query per order
    $order->items->count();    // query per order
    $order->status->label();   // fine — enum, not a relation
}

// ✅ Always — declare everything you need upfront
$orders = Order::with(['user', 'items'])->get();
foreach ($orders as $order) {
    $order->user->name;        // memory
    $order->items->count();    // memory
}
```

### All eager load patterns
```php
// Single relation
Post::with('user')->get();

// Multiple relations
Post::with(['user', 'category', 'tags'])->get();

// Nested relations — load as deep as the view needs
Post::with('user.profile')->get();
Order::with('items.product.category')->get();

// With constraints — filter what gets loaded
Post::with(['comments' => function ($query) {
    $query->where('approved', true)->latest()->limit(5);
}])->get();

// Count without loading the relation — use withCount
Post::withCount('comments')->get();
$post->comments_count; // integer, no query

// Count + load together
Post::with('user')->withCount('comments')->get();

// Load after the fact — when model already retrieved
$post->load('comments.user');

// Load only if not already loaded
$post->loadMissing('user');
```

### Controller rule — declare ALL relations the view needs
```php
// ✅ One place, all relations declared — view gets everything from memory
public function index(): View
{
    $orders = Order::with(['user', 'items.product'])
        ->withCount('items')
        ->latest()
        ->paginate(20);

    return view('orders.index', compact('orders'));
}

public function show(Order $order): View
{
    // Route model binding gives you $order — load relations explicitly
    $order->load(['user', 'items.product', 'payments']);

    return view('orders.show', compact('order'));
}
```

### Blade rule — never trigger queries in views
```blade
{{-- ❌ Never — accesses relation without guarantee it's loaded --}}
{{ $order->user->name }}
{{ $order->items->count() }}

{{-- ✅ Always — controller loaded it, Blade just renders --}}
{{ $order->user->name }}        {{-- fine — loaded with with('user') --}}
{{ $order->items_count }}       {{-- fine — loaded with withCount('items') --}}
```

### Service / Action rule — pass loaded models, don't re-query
```php
// ❌ Service re-queries what the controller already has
class OrderService
{
    public function process(int $orderId): void
    {
        $order = Order::with('items')->find($orderId); // unnecessary — caller has it
    }
}

// ✅ Accept the model, load what's missing
class OrderService
{
    public function process(Order $order): void
    {
        $order->loadMissing('items'); // only queries if not already loaded
    }
}
```

### Enforce it automatically — `preventLazyLoading()`
Turn on in local and staging. Any lazy load = exception thrown immediately.
You catch N+1 during development, never in production.

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    // Throws exception the moment a lazy load happens
    // local: exception — fix it now
    // staging: exception — catch before production
    // production: log only — never crash production over this
    Model::preventLazyLoading(! app()->environment('production'));

    // In production — log instead of throwing
    Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
        Log::warning("N+1 detected: lazy loading [{$relation}] on model [" . get_class($model) . "]");
    });
}
```

### withCount vs count() — know the difference
```php
// ❌ count() inside a loop — query per model
foreach ($posts as $post) {
    echo $post->comments()->count(); // SELECT COUNT(*) per post
}

// ✅ withCount — one extra query total, result on the model
$posts = Post::withCount('comments')->get();
foreach ($posts as $post) {
    echo $post->comments_count; // integer, no query
}
```

### Quick N+1 detection checklist
Before shipping any feature ask:
- Does any controller method return a collection? → are all relations eager loaded?
- Does any Blade view access `->relation`? → is it in the controller's `with()`?
- Does any service/action accept a model and access relations? → is `loadMissing()` called?
- Is `Model::preventLazyLoading()` enabled locally? → are there any exceptions?

---

## DB Transactions

Use transactions whenever you write to **more than one table** in a single operation.
If any step fails, everything rolls back — database stays consistent.

### Basic transaction
```php
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($data, $user) {
    $order = Order::create([
        'user_id' => $user->id,
        'status'  => OrderStatus::Pending,
        'total_cents' => $data['total_cents'],
    ]);

    foreach ($data['items'] as $item) {
        $order->items()->create([
            'product_id'      => $item['product_id'],
            'quantity'        => $item['quantity'],
            'unit_price_cents' => $item['unit_price_cents'],
        ]);

        // Decrement stock — if this fails, order + items roll back too
        Product::where('id', $item['product_id'])
            ->decrement('stock', $item['quantity']);
    }
});
// Any exception inside = full rollback automatically
```

### Transaction with return value
```php
$order = DB::transaction(function () use ($data, $user) {
    $order = Order::create([...]);
    $order->items()->createMany($data['items']);
    return $order; // returned from DB::transaction()
});

// $order is available here
OrderPlaced::dispatch($order->fresh(['items']));
```

### Manual transaction control
```php
// Use when you need fine-grained control or conditional rollback
DB::beginTransaction();

try {
    $order = Order::create([...]);
    $payment = Payment::create([...]);

    if ($payment->status === PaymentStatus::Failed) {
        DB::rollBack();
        return back()->withErrors('Payment failed.');
    }

    DB::commit();
    return redirect()->route('orders.show', $order);

} catch (\Throwable $e) {
    DB::rollBack();
    Log::error('Order creation failed', ['error' => $e->getMessage()]);
    throw $e;
}
```

### Transaction rules
- **Always** use transactions for multi-table writes
- **Never** fire events or dispatch jobs inside a transaction — if the transaction rolls back, the job already ran
- Fire events **after** `DB::transaction()` completes successfully
- Keep transactions short — long transactions lock rows and block other queries

```php
// ✅ Fire events AFTER transaction
$order = DB::transaction(function () use ($data) {
    return Order::create([...]);
});

OrderPlaced::dispatch($order); // outside transaction — safe
```

---

## Query Scopes

Scopes encapsulate reusable query logic on the model — keep controllers and services clean.

### Local scopes
```php
// app/Models/Order.php
public function scopePending(Builder $query): Builder
{
    return $query->where('status', OrderStatus::Pending);
}

public function scopeForUser(Builder $query, User $user): Builder
{
    return $query->where('user_id', $user->id);
}

public function scopeRecent(Builder $query, int $days = 30): Builder
{
    return $query->where('created_at', '>=', now()->subDays($days));
}

public function scopeWithMinTotal(Builder $query, int $cents): Builder
{
    return $query->where('total_cents', '>=', $cents);
}
```

Usage — chainable, reads like English:
```php
// In a service or controller
Order::pending()->forUser($user)->recent(7)->get();
Order::pending()->withMinTotal(5000)->latest()->paginate(20);
```

### Global scopes — apply to every query on the model
Use sparingly — they're invisible and can surprise you:
```php
// app/Models/Scopes/ActiveScope.php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ActiveScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('is_active', true);
    }
}

// app/Models/Product.php — register global scope
protected static function booted(): void
{
    static::addGlobalScope(new ActiveScope);
}

// Remove global scope when needed
Product::withoutGlobalScope(ActiveScope::class)->get(); // includes inactive
Product::withoutGlobalScopes()->get(); // removes ALL global scopes
```

### Scope vs raw where — when to use which
```php
// ❌ Raw where scattered in controllers/services — duplicated, fragile
Order::where('status', 'pending')->where('user_id', $user->id)->get();
Order::where('status', 'pending')->where('user_id', $user->id)->count();

// ✅ Scope — defined once, used everywhere, refactored in one place
Order::pending()->forUser($user)->get();
Order::pending()->forUser($user)->count();
```

---

## Factories & Seeders

### Factory — define once, use everywhere in tests
```php
// database/factories/OrderFactory.php
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'status'      => OrderStatus::Pending->value,
            'total_cents' => fake()->numberBetween(1000, 100000),
            'created_at'  => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    // States — named variations for specific test scenarios
    public function pending(): static
    {
        return $this->state(['status' => OrderStatus::Pending->value]);
    }

    public function completed(): static
    {
        return $this->state(['status' => OrderStatus::Active->value]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => OrderStatus::Cancelled->value]);
    }

    public function highValue(): static
    {
        return $this->state(['total_cents' => fake()->numberBetween(50000, 500000)]);
    }

    // With relations
    public function withItems(int $count = 3): static
    {
        return $this->has(OrderItem::factory()->count($count), 'items');
    }
}
```

Factory usage in tests:
```php
// Single model
$order = Order::factory()->create();

// With state
$order = Order::factory()->completed()->create();

// With relations
$order = Order::factory()->withItems(5)->create();

// Multiple
$orders = Order::factory()->count(10)->pending()->create();

// Override specific fields
$order = Order::factory()->create(['user_id' => $user->id, 'total_cents' => 9900]);

// Make (no DB save — for unit tests)
$order = Order::factory()->make();
```

### DatabaseSeeder — orchestrates all seeders
```php
// database/seeders/DatabaseSeeder.php
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Always run in dependency order
        $this->call([
            RolesAndPermissionsSeeder::class, // roles before users
            UserSeeder::class,                // users before orders
            ProductSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
```

### Environment-aware seeders
```php
// database/seeders/UserSeeder.php
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Superadmin — always created
        $superadmin = User::firstOrCreate(
            ['email' => 'superadmin@app.com'],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make(config('app.admin_password', 'password')),
            ]
        );
        $superadmin->assignRole('superadmin');

        // Demo data — local and staging only, never production
        if (app()->environment(['local', 'staging'])) {
            User::factory()->count(20)->create()->each(function ($user) {
                $user->assignRole('viewer');
            });

            User::factory()->count(3)->create()->each(function ($user) {
                $user->assignRole('editor');
            });
        }
    }
}
```

### Artisan seeder commands
```bash
# Run all seeders
php artisan db:seed

# Run specific seeder
php artisan db:seed --class=UserSeeder

# Fresh migration + seed (local dev only — destroys all data)
php artisan migrate:fresh --seed

# Production — never use migrate:fresh, only:
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
```

---

## Migration Best Practices

### The Golden Rule — One Table, One Migration, Everything In It

**One migration file per table. Put everything in it — columns, foreign keys, indexes, constraints, soft deletes. Done.**

Never split a table across multiple migration files at creation time. If you need `orders` with 10 columns, 3 indexes, and 2 foreign keys — that is one file, not four.

```
❌ Wrong — unnecessary split:
2024_01_01_000001_create_orders_table.php         ← just the columns
2024_01_01_000002_add_indexes_to_orders_table.php ← indexes added separately
2024_01_01_000003_add_foreign_keys_to_orders.php  ← foreign keys added separately

✅ Correct — everything in one file:
2024_01_01_000001_create_orders_table.php         ← columns + indexes + FK + constraints
```

**Why it matters:**
- `migrate:fresh` replays every file in order — split files are fragile and break easily
- One file = one place to read the full table structure
- Fewer files = cleaner `migrations` table history
- Alter migrations are only for changes **after** the table is in production

### When to use an alter migration
Only when the table already exists in production and you need to change it:
```
✅ Valid alter migrations:
2024_06_01_add_notes_to_orders_table.php    ← new column after go-live
2024_07_15_add_index_to_orders_table.php    ← new index after performance issue found
2024_08_01_drop_legacy_column_from_orders.php
```

### Other rules
- Always write `down()` that fully reverses `up()` — drop columns in reverse order
- Never edit a migration that has already run in production — create a new one
- Use `->after('column_name')` in alter migrations for column ordering
- Use `->constrained()` with explicit cascade rules:
  - `->cascadeOnDelete()` — owned data (order_items when order deleted)
  - `->nullOnDelete()` — optional foreign relation
  - `->restrictOnDelete()` — block deletion (safest default, be explicit)

## Full Migration Stub
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // --- foreign keys (always index these) ---
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // --- columns ---
            $table->string('status')->default(OrderStatus::Pending->value);
            $table->unsignedInteger('total_cents')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            // --- soft deletes + timestamps ---
            $table->softDeletes();
            $table->timestamps();
            // --- indexes ---
            $table->index(['user_id', 'status', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

## Alter Migration Stub (adding to existing table)
```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('notes')->nullable()->after('total_cents');
            $table->index(['status', 'created_at'], 'orders_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_created_index');
            $table->dropColumn('notes');
        });
    }
};
```
