# Portal Foundation — Middleware & Notification URL Fixes

---

## 0. SecurityHeaders Middleware (NEW FILE)

**File:** `app/Http/Middleware/SecurityHeaders.php`

Create this file — it does not exist yet.

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        return $response;
    }
}
```

Register in `bootstrap/app.php` — `prepend` so it fires before all other middleware on every request:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->prepend(\App\Http\Middleware\SecurityHeaders::class);

    $middleware->alias([
        'customer.active' => \App\Http\Middleware\EnsureCustomerIsActive::class,
    ]);
})
```

---

Five more files need updating. All files already exist — do NOT create new ones, just update them.

---

## 1. Authenticate Middleware

**File:** `app/Http/Middleware/Authenticate.php`

Unauthenticated user hits a protected route.
Portal routes (`auth:customer`) → redirect to `/portal/login`.
Admin routes (`auth`) → redirect to `/login`.

No change needed — route name check still works regardless of which guard fires:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        if (! $request->expectsJson()) {
            if ($request->routeIs('portal.*')) {
                return route('portal.login');
            }

            return route('login');
        }

        return null;
    }
}
```

---

## 2. RedirectIfAuthenticated Middleware

**File:** `app/Http/Middleware/RedirectIfAuthenticated.php`

Must check the `customer` guard for portal routes — not just the default web guard.

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        // Portal guest routes — check customer guard
        if ($request->routeIs('portal.*') && Auth::guard('customer')->check()) {
            return redirect()->route('portal.dashboard');
        }

        // Admin/staff guest routes — check web guard
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect()->route('dashboard');
            }
        }

        return $next($request);
    }
}
```

### Result

| Who hits what | Redirect |
|---------------|----------|
| Logged-in customer hits `/portal/login` | → `/portal/dashboard` |
| Logged-in admin hits `/login` | → `/dashboard` |

---

## 3. AppServiceProvider — Fix Notification URLs

**File:** `app/Providers/AppServiceProvider.php` — update in `boot()` method.

Two brokers now (users + customers). Must route notification URLs to the correct portal/admin route
based on the notifiable model type.

```php
use App\Models\Customer;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;

public function boot(): void
{
    // Password reset link — customer → portal route, staff → admin route
    ResetPassword::createUrlUsing(function ($notifiable, string $token) {
        if ($notifiable instanceof Customer) {
            return route('portal.password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        }

        return route('password.reset', [
            'token' => $token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    });

    // Email verification link — customer → portal route, staff → admin route
    VerifyEmail::createUrlUsing(function ($notifiable) {
        $routeName = $notifiable instanceof Customer
            ? 'portal.verification.verify'
            : 'verification.verify';

        return URL::temporarySignedRoute(
            $routeName,
            now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id'   => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    });
}
```

### Result

| Who gets email | Link points to |
|----------------|---------------|
| Customer — password reset | `/portal/reset-password/{token}` ✅ |
| Staff — password reset | `/reset-password/{token}` ✅ |
| Customer — email verify | `/portal/email/verify/{id}/{hash}` ✅ |
| Staff — email verify | `/email/verify/{id}/{hash}` ✅ |

---

## 4. EnsureCustomerIsActive Middleware

**File:** `app/Http/Middleware/EnsureCustomerIsActive.php`

Use `Auth::guard('customer')` — no `getByUser()` needed, guard user IS the Customer.

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\CustomerStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\Customer|null $customer */
        $customer = Auth::guard('customer')->user();

        if (! $customer) {
            return $next($request);
        }

        if ($customer->status !== CustomerStatus::Active) {
            Auth::guard('customer')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('portal.login')
                ->withErrors(['email' => 'Your account has been deactivated. Please contact support.']);
        }

        return $next($request);
    }
}
```

Remove the `CustomerService` dependency — no longer needed here.

---

## 5. Portal Route Middleware Stack

**File:** `routes/web.php`

```php
// Before
Route::middleware(['auth', 'verified:portal.verification.notice', 'role:customer', 'active', 'customer.active'])

// After
Route::middleware(['auth:customer', 'verified:portal.verification.notice', 'customer.active'])
```

**Removed:**
- `auth` → replaced by `auth:customer` (customer guard)
- `role:customer` → not needed, guard enforces customer-only access
- `active` → was `EnsureUserIsActive` which checks `UserStatus` enum — Customer uses `CustomerStatus`, would crash

---

## Notes

- `auth:customer` on portal routes means only `customers` table rows can authenticate — staff cannot access portal even if they try
- `EnsureCustomerIsActive` no longer needs `CustomerService` injection — simpler
- `AppServiceProvider` uses `instanceof Customer` check — both brokers can coexist safely
- Do NOT reference `RouteServiceProvider` — it does not exist in Laravel 11/12

---

## 6. LogAuthActivity Listener — Customer Guard Compatibility

If the project has a `LogAuthActivity` listener registered on `Illuminate\Auth\Events\Login`,
it fires for **all guards** — including the `customer` guard.

After the refactor, `$event->user` may be a `Customer` instead of a `User`.
Any listener that casts `$event->user` to `User`, or calls `$event->user->roles`, will fail.

**Fix pattern:**

```php
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class LogAuthActivity
{
    public function handle(Login $event): void
    {
        // Customer logins are handled by the customer guard — skip if not a staff User
        if (! $event->user instanceof User) {
            return;
        }

        // ... existing staff login logging logic ...
    }
}
```

Check `app/Listeners/` for any listener registered on the `Login` or `Logout` events
and add the `instanceof User` guard before touching User-specific properties.
