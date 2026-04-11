# Model Reference

## Rules — What a Model Does and Doesn't Do

| ✅ Model owns | ❌ Never in a Model |
|--------------|---------------------|
| `$fillable` whitelist | Business logic |
| `casts()` method | Service calls |
| Relations | HTTP concerns |
| Local & global scopes | `$request` |
| Plain methods (formatting) | Multi-step writes |
| `$hidden` fields | Business logic |
| `booted()` simple hooks | Complex side effects (use Observers) |

**One-liner:** data structure only. Relations, scopes, casts, plain methods. Nothing that belongs in a service.

---

## Full Production Model Pattern

```php
<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    // ── Mass Assignment ──────────────────────────────────────────────────────

    // ✅ Always explicit whitelist — never $guarded = []
    protected $fillable = [
        'user_id',
        'status',
        'shipping_address',
        'notes',
        'metadata',
        'total_cents',
    ];

    // ✅ Hide from toArray() / JSON — internal fields, sensitive data
    protected $hidden = [
        'metadata',
    ];

    // ── Casts ────────────────────────────────────────────────────────────────

    // ✅ Laravel 12 — casts() is a method, not a $casts property
    protected function casts(): array
    {
        return [
            'status'      => OrderStatus::class,  // Enum cast
            'metadata'    => 'array',              // JSON → array
            'total_cents' => 'integer',
            'is_active'   => 'boolean',
            'settings'    => 'collection',         // JSON → Collection
            'verified_at' => 'datetime',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    // Always typed return types
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ── Local Scopes ─────────────────────────────────────────────────────────

    // Chainable, readable query logic — defined once, used everywhere
    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', OrderStatus::Pending);
    }

    public function scopeForUser(Builder $q, User $user): Builder
    {
        return $q->where('user_id', $user->id);
    }

    public function scopeRecent(Builder $q, int $days = 30): Builder
    {
        return $q->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeWithMinTotal(Builder $q, int $cents): Builder
    {
        return $q->where('total_cents', '>=', $cents);
    }

    // ── Plain Methods — formatting and computed values ───────────────────────

    // Simple, explicit, zero overhead — called only when needed
    public function formattedTotal(): string
    {
        return '$' . number_format($this->total_cents / 100, 2);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [OrderStatus::Pending, OrderStatus::Processing]);
    }

    // ── Model Events ─────────────────────────────────────────────────────────

    // booted() for simple automatic behavior only
    // Complex side effects → use Observers or Events instead
    protected static function booted(): void
    {
        // Auto-set default status on create
        static::creating(function (Order $order) {
            $order->status ??= OrderStatus::Pending;
        });
    }
}
```

---

## $fillable — Mass Assignment Rules

```php
// ✅ Explicit whitelist
protected $fillable = ['user_id', 'status', 'total_cents'];

// ❌ Never — disables all protection
protected $guarded = [];

// Columns that must NEVER be in $fillable:
// id, password, email_verified_at
// is_admin, is_super (role flags)
// created_at, updated_at, deleted_at
// remember_token
```

---

## casts() — Cast Every Non-String Column

```php
protected function casts(): array
{
    return [
        // Enums
        'status'         => OrderStatus::class,

        // JSON
        'metadata'       => 'array',
        'settings'       => 'collection',

        // Booleans — always cast, never rely on 0/1
        'is_active'      => 'boolean',
        'is_featured'    => 'boolean',

        // Numbers
        'total_cents'    => 'integer',
        'score'          => 'float',

        // Dates
        'published_at'   => 'datetime',
        'cancelled_at'   => 'datetime',

        // Encrypted storage
        'secret_token'   => 'encrypted',
    ];
}
```

---

## Relations — Always Typed

```php
// One-to-Many
public function items(): HasMany
{
    return $this->hasMany(OrderItem::class);
}

// Belongs-To
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

// Many-to-Many
public function products(): BelongsToMany
{
    return $this->belongsToMany(Product::class)
                ->withPivot('quantity', 'unit_price_cents')
                ->withTimestamps();
}

// Has-One
public function latestPayment(): HasOne
{
    return $this->hasOne(Payment::class)->latestOfMany();
}

// Has-Many-Through
public function reviews(): HasManyThrough
{
    return $this->hasManyThrough(Review::class, OrderItem::class);
}

// Polymorphic
public function comments(): MorphMany
{
    return $this->morphMany(Comment::class, 'commentable');
}
```

---

## Scopes — Define Once, Use Everywhere

```php
// Usage — chainable
Order::pending()->forUser($user)->recent(7)->paginate(20);
Order::pending()->withMinTotal(5000)->latest()->get();

// ❌ Without scopes — duplicated, fragile
Order::where('status', 'pending')
     ->where('user_id', $user->id)
     ->where('created_at', '>=', now()->subDays(7))
     ->paginate(20);
```

---

## $hidden — Always Hide Sensitive Fields

```php
protected $hidden = [
    'password',          // User model
    'remember_token',    // User model
    'secret_key',        // Any sensitive token
    'metadata',          // Internal data not for API/views
    'two_factor_secret', // 2FA
];
```

---

## Global Scopes — Use Sparingly

```php
// Only when the filter truly applies to EVERY query on this model
// Common use: multi-tenant (always filter by company_id)
protected static function booted(): void
{
    static::addGlobalScope('active', function (Builder $q) {
        $q->where('is_active', true);
    });
}

// Remove when needed
Product::withoutGlobalScope('active')->get();
Product::withoutGlobalScopes()->get(); // removes all
```

---

## User Model — Full Pattern

```php
<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\LaravelPermission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',  // Laravel 12 — auto-hashes on set
        ];
    }
}
```

---

## Quick Reference

```
$fillable   → explicit whitelist always. Never $guarded = [].
casts()     → method not property. Cast every non-string column.
$hidden     → hide password, tokens, internal fields.
relations   → always typed return types.
scopes      → query logic on model, used in services/controllers.
methods     → plain methods for formatting/computation. No accessor overhead.
casts()     → type conversion always. Enums, booleans, JSON, dates.
booted()    → simple auto-behavior only. Complex side effects → Observer.

Never in a Model:
- Business logic
- Service calls
- $request access
- Multi-step writes
- abort() or redirect()
```
