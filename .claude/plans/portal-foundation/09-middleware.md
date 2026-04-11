# Portal Foundation — Middleware & Notification URL Fixes

Three files need updating. All files already exist — do NOT create new ones, just update them.

---

## 1. Authenticate Middleware

**File:** `app/Http/Middleware/Authenticate.php`

Runs when an unauthenticated user hits a protected route.
Portal routes → redirect to `/portal/login`. Admin routes → redirect to `/login`.

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

### Result
| Who hits what | Redirect |
|---------------|----------|
| Guest hits `/portal/dashboard` | → `/portal/login` |
| Guest hits `/dashboard` (admin) | → `/login` |

---

## 2. RedirectIfAuthenticated Middleware

**File:** `app/Http/Middleware/RedirectIfAuthenticated.php`

Runs on `guest` routes (login, register pages).
Logged-in user visiting a guest page → redirect to their own dashboard.

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
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // Customer visiting portal guest page → portal dashboard
                if ($request->routeIs('portal.*')) {
                    return redirect()->route('portal.dashboard');
                }

                // Admin/Staff visiting admin guest page → admin dashboard
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
| Logged-in customer hits `/portal/login` or `/portal/register` | → `/portal/dashboard` |
| Logged-in admin hits `/login` | → `/dashboard` |

---

## 3. AppServiceProvider — Fix Notification URLs

**File:** `app/Providers/AppServiceProvider.php` — add to `boot()` method.

By default Laravel generates email verification and password reset links using
`verification.verify` and `password.reset` route names. Our portal uses
`portal.verification.verify` and `portal.password.reset`. Without this fix,
both email links would 404.

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Fix: password reset email links to portal route
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return route('portal.password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        // Fix: email verification links to portal route
        VerifyEmail::createUrlUsing(function ($notifiable) {
            return URL::temporarySignedRoute(
                'portal.verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                [
                    'id'   => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );
        });
    }
}
```

### Result
| Email | Link points to |
|-------|---------------|
| Password reset email | `/portal/reset-password/{token}` ✅ |
| Email verification email | `/portal/email/verify/{id}/{hash}` ✅ |

---

## Notes
- Do NOT import or reference `RouteServiceProvider` — it does not exist in Laravel 11/12
- `$request->routeIs('portal.*')` matches any route with the `portal.` name prefix
- `AppServiceProvider` already exists — only add the two `createUrlUsing` calls inside `boot()`
- These 3 changes are all that's needed — no new classes or middleware
