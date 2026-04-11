# Portal Foundation — Rate Limiting

Configure in `app/Providers/AppServiceProvider.php` inside the `boot()` method.
Uses Laravel's built-in `RateLimiter` facade — no packages needed.

---

## Configuration

**File:** `app/Providers/AppServiceProvider.php` — add to `boot()`.

**IMPORTANT:** `09-middleware.md` also adds code to this same `boot()` method (notification URL fixes).
Both sets of code go in the same method — do NOT overwrite one with the other.
Final `boot()` must contain: rate limiters (below) + ResetPassword + VerifyEmail (from 09-middleware.md).

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    // Login — 5 attempts per minute per email + IP
    RateLimiter::for('login', function (Request $request) {
        return Limit::perMinute(5)
            ->by($request->input('email') . '|' . $request->ip());
    });

    // Register — 3 attempts per minute per IP
    RateLimiter::for('register', function (Request $request) {
        return Limit::perMinute(3)
            ->by($request->ip());
    });

    // Forgot password — 3 attempts per minute per IP
    RateLimiter::for('forgot-password', function (Request $request) {
        return Limit::perMinute(3)
            ->by($request->ip());
    });
}
```

---

## Applying to Routes

Rate limiters are applied per route via `middleware('throttle:name')`:

```php
Route::post('/login',          ...)->middleware('throttle:login');
Route::post('/register',       ...)->middleware('throttle:register');
Route::post('/forgot-password',...)->middleware('throttle:forgot-password');
```

Already included in the routes in `00-overview.md`.

---

## What Happens When Limit is Hit

Laravel automatically returns a `429 Too Many Requests` response.
The user sees the default Laravel throttle error message.
No extra code needed.

---

## Notes
- Login is keyed by `email + IP` — prevents targeting a specific account from one IP
- Register and forgot-password are keyed by IP only — no email available yet
- These limits are per minute and reset automatically
- Do NOT add throttle to logout — no need to rate limit logout
