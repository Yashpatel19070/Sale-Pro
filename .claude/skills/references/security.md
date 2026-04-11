# Security Checklist Reference

## CSRF Protection

```blade
{{-- Every form must have @csrf --}}
<form method="POST" action="{{ route('orders.store') }}">
    @csrf
    ...
</form>

{{-- PUT/PATCH/DELETE need @method too --}}
<form method="POST" action="{{ route('orders.update', $order) }}">
    @csrf
    @method('PATCH')
    ...
</form>
```

**Rule:** every HTML form that submits POST/PUT/PATCH/DELETE must have `@csrf`. No exceptions.

---

## Output Escaping

```blade
{{-- ✅ Always use {{ }} — auto-escapes HTML --}}
{{ $user->name }}
{{ $order->shipping_address }}

{{-- ❌ Never use {!! !!} unless you explicitly trust the content --}}
{!! $user->bio !!}  {{-- XSS risk — only use for known-safe HTML --}}

{{-- ✅ If you must render HTML — sanitize first --}}
{!! clean($post->content) !!}  {{-- using an HTML purifier package --}}
```

---

## No Raw SQL

```php
// ❌ SQL injection risk
DB::select("SELECT * FROM orders WHERE user_id = $userId");
DB::statement("UPDATE orders SET status = '$status' WHERE id = $id");

// ✅ Always use Eloquent or query builder with bindings
Order::where('user_id', $userId)->get();
Order::where('user_id', '=', $userId)->where('status', $status)->get();

// ✅ Raw expressions — only for computed values, never user input
Order::selectRaw('SUM(total_cents) as revenue')->groupBy('status')->get();

// ✅ If raw SQL is unavoidable — always use bindings
DB::select('SELECT * FROM orders WHERE user_id = ?', [$userId]);
```

---

## Mass Assignment Protection

```php
// ✅ Always explicit $fillable — never $guarded = []
protected $fillable = ['name', 'email', 'status'];

// ✅ Always $request->validated() — never $request->all()
Order::create($request->validated());

// ❌ Never
Order::create($request->all());  // exposes all input including injected fields
```

Columns that must NEVER be in `$fillable`:
```
id, password, email_verified_at
is_admin, is_super
created_at, updated_at, deleted_at
remember_token
```

---

## Authentication & Authorization

```php
// ✅ Every route behind auth + verified
Route::middleware(['auth', 'verified'])->group(function () { ... });

// ✅ Permission check in FormRequest
public function authorize(): bool
{
    return $this->user()->can(Permission::ORDERS_CREATE);
}

// ✅ Never trust user-supplied IDs without scoping to the user
// ❌ Wrong — any user can access any order by guessing the ID
$order = Order::find($request->order_id);

// ✅ Correct — scoped to authenticated user
$order = Order::where('user_id', $request->user()->id)->findOrFail($request->order_id);

// ✅ Better — route model binding handles 404 automatically
public function show(Order $order): View  // Laravel resolves and 404s if not found
```

---

## Password Security

```php
// ✅ Always hash passwords — Laravel 12 'hashed' cast does this automatically
protected function casts(): array
{
    return ['password' => 'hashed'];
}

// Never store or log plain text passwords
// Never compare passwords with ==, always use Hash::check()
Hash::check($plainText, $user->password);
```

---

## Rate Limiting

```php
// ✅ Login — 5 attempts per minute per email+IP (see auth-breeze.md)
// ✅ Password reset — 3 per minute per IP
// ✅ Heavy routes — throttle middleware (see middleware.md)

// Verify Breeze rate limiting is wired — it is NOT automatic
// Check AuthenticatedSessionController::store() has RateLimiter::tooManyAttempts()
```

---

## Email Verification

```php
// ✅ User model implements MustVerifyEmail
class User extends Authenticatable implements MustVerifyEmail { }

// ✅ Routes protected by 'verified' middleware
Route::middleware(['auth', 'verified'])->group(function () { ... });
```

---

## Sensitive Data in Logs

```php
// ❌ Never log sensitive fields
Log::info('User registered', ['password' => $data['password']]); // never
Log::info('Payment', ['card_number' => $card]);                  // never

// ✅ Log only safe identifiers
Log::channel('auth')->info('User registered', ['user_id' => $user->id]);
Log::channel('payments')->info('Payment received', ['order_id' => $order->id]);
```

---

## Environment Config

```bash
# Production .env — strict settings
APP_DEBUG=false          # never true in production — exposes stack traces
APP_ENV=production
LOG_LEVEL=error          # errors only
SESSION_SECURE_COOKIE=true  # HTTPS only
SESSION_SAME_SITE=strict
```

---

## Security Checklist — Pre-Deploy

```
Forms:
  [ ] Every form has @csrf
  [ ] PUT/PATCH/DELETE forms have @method()
  [ ] All output uses {{ }} not {!! !!}

Database:
  [ ] No raw SQL with user input — always bindings
  [ ] $fillable explicit on every model — no $guarded = []
  [ ] $request->validated() used everywhere — never $request->all()

Auth:
  [ ] All routes behind auth + verified middleware
  [ ] Permission check in every FormRequest authorize()
  [ ] User-supplied IDs scoped to authenticated user
  [ ] MustVerifyEmail on User model

Rate limiting:
  [ ] Login rate limiting wired in AuthenticatedSessionController
  [ ] Password reset rate limiting wired
  [ ] Heavy routes have throttle middleware

Config:
  [ ] APP_DEBUG=false in production
  [ ] LOG_LEVEL=error in production
  [ ] No secrets hardcoded — all in .env
  [ ] SESSION_SECURE_COOKIE=true if HTTPS

Logs:
  [ ] No passwords, tokens, or card numbers logged
  [ ] Per-feature log channels configured
```
