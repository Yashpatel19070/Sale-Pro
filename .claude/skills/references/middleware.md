# Middleware Reference

## What Middleware Is For

Middleware intercepts HTTP requests before they reach the controller.
Use it for: auth checks, permission gates, rate limiting, active user checks, redirects, CSRF exclusions.
**Never put business logic in middleware.**

---

## Laravel 12 — No More Kernel.php

All middleware registered in `bootstrap/app.php`:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {

    // --- Aliases ---
    $middleware->alias([
        // Laravel built-ins
        'auth'              => \Illuminate\Auth\Middleware\Authenticate::class,
        'guest'             => \Illuminate\Auth\Middleware\RedirectIfAuthenticated::class,
        'verified'          => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'throttle'          => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'signed'            => \Illuminate\Routing\Middleware\ValidateSignedRoute::class,
        'password.confirm'  => \Illuminate\Auth\Middleware\RequirePassword::class,

        // Spatie — must register manually
        'role'               => \Spatie\LaravelPermission\Middleware\RoleMiddleware::class,
        'permission'         => \Spatie\LaravelPermission\Middleware\PermissionMiddleware::class,
        'role_or_permission' => \Spatie\LaravelPermission\Middleware\RoleOrPermissionMiddleware::class,

        // Custom
        'active'           => \App\Http\Middleware\EnsureUserIsActive::class,
        'load_permissions' => \App\Http\Middleware\LoadUserPermissions::class,
        'admin'            => \App\Http\Middleware\EnsureIsAdmin::class,
        'superadmin'       => \App\Http\Middleware\EnsureSuperAdmin::class,
    ]);

    // --- Redirect behavior ---
    $middleware->redirectGuestsTo('/login');
    $middleware->redirectUsersTo('/dashboard');

    // --- CSRF exclusions (webhooks, payment callbacks) ---
    $middleware->validateCsrfTokens(except: [
        'webhooks/*',
        'payments/callback',
    ]);

})
```

---

## Middleware Execution Order

```
Request
  └── Global (TrustProxies, PreventRequestsDuringMaintenance)
       └── Web group (EncryptCookies, StartSession, ValidateCsrf, SubstituteBindings)
            └── Route middleware (auth → verified → active → admin/superadmin → permission)
                 └── Controller
            ↑ response flows back up through same stack
```

**Rule:** always stack route middleware in this order:
`auth` → `load_permissions` → `verified` → `active` → `admin` or `superadmin` → `permission`

`load_permissions` runs second — right after `auth` loads `$user`. Everything after it gets
roles + permissions from memory. Zero extra DB queries for the rest of the request.

---

## The Three Route Stacks

> Always import at top of `routes/web.php`:
> ```php
> use App\Enums\Permission;
> ```

### Guest Stack — unauthenticated only
```php
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});
```

### Frontend Stack — authenticated users
```php
Route::middleware(['auth', 'load_permissions', 'verified', 'active'])->group(function () {

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::patch('/profile', [ProfileController::class, 'update']);

    // Sensitive — require password re-confirmation
    Route::middleware('password.confirm')->group(function () {
        Route::get('/profile/delete', [ProfileController::class, 'confirmDelete']);
        Route::delete('/profile', [ProfileController::class, 'destroy']);
    });

    // Permission-gated content — use Permission constants, never raw strings
    Route::get('/posts', [PostController::class, 'index'])->middleware('permission:' . Permission::POSTS_VIEW);
    Route::get('/posts/create', [PostController::class, 'create'])->middleware('permission:' . Permission::POSTS_CREATE);
    Route::post('/posts', [PostController::class, 'store'])->middleware('permission:' . Permission::POSTS_CREATE);
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->middleware('permission:' . Permission::POSTS_EDIT);
    Route::patch('/posts/{post}', [PostController::class, 'update'])->middleware('permission:' . Permission::POSTS_EDIT);
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->middleware('permission:' . Permission::POSTS_DELETE);
});
```

### Admin Stack — DB-driven role gate, then permission per action
```php
// 'admin' middleware queries is_admin=true from DB — no hardcoded role names
Route::middleware(['auth', 'load_permissions', 'verified', 'active', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/', AdminDashboardController::class)->name('dashboard');

        // Users
        Route::get('users', [Admin\UserController::class, 'index'])->middleware('permission:' . Permission::USERS_VIEW);
        Route::get('users/create', [Admin\UserController::class, 'create'])->middleware('permission:' . Permission::USERS_CREATE);
        Route::post('users', [Admin\UserController::class, 'store'])->middleware('permission:' . Permission::USERS_CREATE);
        Route::get('users/{user}/edit', [Admin\UserController::class, 'edit'])->middleware('permission:' . Permission::USERS_EDIT);
        Route::patch('users/{user}', [Admin\UserController::class, 'update'])->middleware('permission:' . Permission::USERS_EDIT);
        Route::delete('users/{user}', [Admin\UserController::class, 'destroy'])->middleware('permission:' . Permission::USERS_DELETE);

        // Roles — permission-gated, not role-gated
        Route::resource('roles', Admin\RoleController::class)->middleware('permission:' . Permission::ROLES_MANAGE);

        // Settings
        Route::get('settings', [Admin\SettingController::class, 'index'])->middleware('permission:' . Permission::SETTINGS_VIEW);
        Route::patch('settings', [Admin\SettingController::class, 'update'])->middleware('permission:' . Permission::SETTINGS_EDIT);
    });

// 'superadmin' middleware queries is_super=true from DB
Route::middleware(['auth', 'load_permissions', 'verified', 'active', 'superadmin'])
    ->prefix('system')
    ->name('system.')
    ->group(function () {
        Route::resource('system-settings', SystemSettingController::class);
    });
```

---

## Custom Middleware

### 1. `EnsureUserIsActive` — block deactivated accounts

Sits after `auth` — logs the user out and redirects with an error message.

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been deactivated.']);
        }

        return $next($request);
    }
}
```

---

### 2. `LoadUserPermissions` — eager load roles + permissions once

Runs immediately after `auth`. Loads roles and permissions onto `$user` in **one query**.
Every middleware and controller after this point reads from memory — zero extra DB queries.

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoadUserPermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            // Single query — loads roles AND their permissions
            // hasAnyRole(), can(), hasPermissionTo() all read from this — free
            $request->user()->load('roles.permissions', 'permissions');
        }

        return $next($request);
    }
}
```

> Without this, every `hasAnyRole()` or `can()` call in middleware fires its own DB query.
> With this, the entire request lifecycle — all middleware + controller — has zero role/permission queries.

---

### 3. `EnsureIsAdmin` — admin area gate, fully DB-driven

Reads `is_admin` flag from the `roles` table. No role names hardcoded anywhere.
Adding a new admin role = set `is_admin=true` in DB. Zero code changes.

```php
<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsAdmin
{
    // Static — survives across middleware instantiations within the same request
    // Cache::remember only called once per process when cache is cold
    private static ?Collection $adminRoles = null;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            abort(403);
        }

        if (static::$adminRoles === null) {
            static::$adminRoles = Cache::remember('roles.admin', now()->addHours(6), function () {
                return Role::where('is_admin', true)->pluck('name');
            });
        }

        // $user->roles already loaded by LoadUserPermissions — zero DB query
        if (! $request->user()->hasAnyRole(static::$adminRoles)) {
            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}
```

---

### 3. `EnsureSuperAdmin` — system-level gate, fully DB-driven

Same pattern using `is_super` flag.

```php
<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    private static ?Collection $superRoles = null;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            abort(403);
        }

        if (static::$superRoles === null) {
            static::$superRoles = Cache::remember('roles.super', now()->addHours(6), function () {
                return Role::where('is_super', true)->pluck('name');
            });
        }

        // $user->roles already loaded by LoadUserPermissions — zero DB query
        if (! $request->user()->hasAnyRole(static::$superRoles)) {
            abort(403, 'Superadmin access required.');
        }

        return $next($request);
    }
}
```

**Cache invalidation** — call this in `RolesAndPermissionsSeeder::run()` after any role changes:
```php
Cache::forget('roles.admin');
Cache::forget('roles.super');
```

---

## `password.confirm` — Sudo Mode for Sensitive Actions

Forces password re-entry before a sensitive route. Breeze scaffolds the confirm view automatically.

```php
Route::middleware(['auth', 'password.confirm'])->group(function () {
    Route::get('profile/delete', [ProfileController::class, 'confirmDelete']);
    Route::delete('profile', [ProfileController::class, 'destroy']);
    Route::get('profile/email/change', [ProfileController::class, 'changeEmail']);
    Route::patch('profile/email', [ProfileController::class, 'updateEmail']);
});
```

Laravel remembers confirmation for 3 hours. Change in `config/auth.php`:
```php
'password_timeout' => 3600, // seconds
```

---

## CSRF Exclusions

Webhooks and payment callbacks POST without a CSRF token — exclude them:

```php
// bootstrap/app.php
$middleware->validateCsrfTokens(except: [
    'webhooks/*',
    'payments/callback',
    'stripe/webhook',
]);
```

Always replace CSRF with your own signature verification on these routes (e.g. Stripe webhook secret).

---

## Throttle on Specific Routes

```php
// Named limiter — registered in AppServiceProvider
RateLimiter::for('exports', function (Request $request) {
    return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
});

// Search — 30/min
Route::get('search', SearchController::class)->middleware(['auth', 'throttle:30,1']);

// Exports — named limiter
Route::get('reports/export', [ReportController::class, 'export'])
    ->middleware(['auth', 'permission:' . Permission::REPORTS_VIEW, 'throttle:exports']);

// Contact form (guest)
Route::post('contact', ContactController::class)->middleware('throttle:3,1');
```

---

## `withoutMiddleware` — Skip on One Route

```php
Route::middleware(['auth', 'verified'])->group(function () {

    // Verify-email page must skip 'verified' or it redirects itself forever
    Route::get('email/verify', [EmailVerificationController::class, 'notice'])
        ->withoutMiddleware('verified')
        ->name('verification.notice');
});
```

---

## Terminate Middleware — After Response Is Sent

Use for logging and analytics that shouldn't add latency for the user:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request); // browser gets response immediately
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->user()) {
            activity()->causedBy($request->user())->log("Visited: {$request->path()}");
        }
    }
}
```

Register globally in `bootstrap/app.php`:
```php
$middleware->append(\App\Http\Middleware\LogPageView::class);
```

---

## Maintenance Mode

```bash
php artisan down                              # 503 for everyone
php artisan down --allow=127.0.0.1           # allow your IP through
php artisan down --secret="my-token"         # visit /my-token to bypass via cookie
php artisan up                               # bring back online
```

Custom view: `resources/views/errors/503.blade.php`

---

## Quick Reference — Which Middleware Goes Where

| Middleware | Frontend | Admin | System | Guest | Global |
|------------|:--------:|:-----:|:------:|:-----:|:------:|
| `auth` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `guest` | ❌ | ❌ | ❌ | ✅ | ❌ |
| `verified` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `active` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `admin` | ❌ | ✅ | ❌ | ❌ | ❌ |
| `superadmin` | ❌ | ❌ | ✅ | ❌ | ❌ |
| `permission:*` | ✅ per route | ✅ per route | ❌ | ❌ | ❌ |
| `password.confirm` | ✅ sensitive | ✅ sensitive | ❌ | ❌ | ❌ |
| `throttle` | ✅ heavy | ✅ heavy | ❌ | ✅ forms | ❌ |
| `signed` | ✅ email links | ❌ | ❌ | ❌ | ❌ |
| `TrustProxies` | ❌ | ❌ | ❌ | ❌ | ✅ |
| `LogPageView` | ❌ | ❌ | ❌ | ❌ | ✅ |
