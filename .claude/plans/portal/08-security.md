# Customer Portal — Security & Middleware

---

## Full Middleware Stack Per Route Group

```php
// Guest routes (login, register) — NOT accessible when already logged in
Route::middleware(['guest'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/register',  ...)->name('register');
    Route::post('/register', ...)->name('register.store');
    Route::get('/login',     ...)->name('login');
    Route::post('/login',    ...)->name('login.store')->middleware('throttle:login');
});

// Authenticated routes
Route::middleware(['auth', 'verified', 'role:customer', 'customer.active'])
    ->prefix('portal')->name('portal.')->group(function () {
    Route::get('/dashboard',         ...)->name('dashboard');
    Route::get('/profile',           ...)->name('profile.show');
    Route::get('/profile/edit',      ...)->name('profile.edit');
    Route::put('/profile',           ...)->name('profile.update');
    Route::get('/profile/password',  ...)->name('profile.password');
    Route::put('/profile/password',  ...)->name('profile.password.update');
    Route::post('/logout',           ...)->name('logout');
});
```

### Middleware Explanation

| Middleware | Purpose |
|------------|---------|
| `guest` | Redirect to dashboard if already logged in |
| `auth` | Redirect to portal login if not logged in |
| `verified` | Require email verification before accessing portal |
| `role:customer` | 403 if user does not have `customer` role (Spatie) |
| `customer.active` | 403 + logout if customer status is blocked or inactive |
| `throttle:login` | Rate limit login attempts (built-in Laravel) |

---

## 1. Custom Middleware — EnsureCustomerIsActive

**File:** `app/Http/Middleware/EnsureCustomerIsActive.php`

Checks if the logged-in customer's status is `active`.
If status is `inactive` or `blocked` → log them out and redirect to login with error.

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\CustomerStatus;
use App\Services\CustomerService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerIsActive
{
    public function __construct(private readonly CustomerService $service) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        $customer = $this->service->getByUser($user);

        if ($customer->status !== CustomerStatus::Active) {
            Auth::logout();

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

### Register the Middleware

In `bootstrap/app.php` (Laravel 11+) or `app/Http/Kernel.php` (Laravel 10):

**Laravel 11+ — bootstrap/app.php:**
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'customer.active' => \App\Http\Middleware\EnsureCustomerIsActive::class,
    ]);
})
```

**Laravel 10 — app/Http/Kernel.php:**
```php
protected $middlewareAliases = [
    // ...
    'customer.active' => \App\Http\Middleware\EnsureCustomerIsActive::class,
];
```

---

## 2. Rate Limiting on Login

Laravel has built-in rate limiting. Configure in `bootstrap/app.php` or `RouteServiceProvider`:

```php
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by(
        $request->input('email') . '|' . $request->ip()
    );
});
```

- Max **5 login attempts per minute** per email + IP combination
- After limit hit → 429 Too Many Requests response
- Apply to login POST route via `throttle:login` middleware

---

## 3. Password Reset (Forgot Password)

Use Laravel's built-in password reset. It works with the `users` table automatically.

### Routes to Add (inside `guest` middleware group)

```php
Route::get('/forgot-password',          [PasswordResetController::class, 'request'])->name('password.request');
Route::post('/forgot-password',         [PasswordResetController::class, 'email'])->name('password.email');
Route::get('/reset-password/{token}',   [PasswordResetController::class, 'reset'])->name('password.reset');
Route::post('/reset-password',          [PasswordResetController::class, 'update'])->name('password.update');
```

### Controller

**File:** `app/Http/Controllers/Portal/PasswordResetController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    /**
     * GET /portal/forgot-password
     * Show forgot password form.
     */
    public function request(): View
    {
        return view('portal.auth.forgot-password');
    }

    /**
     * POST /portal/forgot-password
     * Send password reset email.
     */
    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    /**
     * GET /portal/reset-password/{token}
     * Show reset password form.
     */
    public function reset(string $token): View
    {
        return view('portal.auth.reset-password', ['token' => $token]);
    }

    /**
     * POST /portal/reset-password
     * Update the password.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token'                 => ['required'],
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])
                     ->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('portal.login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
```

### Views Needed
- `resources/views/portal/auth/forgot-password.blade.php` — email input form
- `resources/views/portal/auth/reset-password.blade.php` — new password form

---

## 4. Email Verification

Laravel's built-in email verification works with the `users` table.
`MustVerifyEmail` interface on the `User` model triggers verification emails on registration.

### Step 1 — User model must implement MustVerifyEmail

Check `app/Models/User.php` — ensure it has:
```php
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
```

### Step 2 — Add verification routes (inside `auth` middleware, before `verified`)

```php
Route::middleware('auth')->prefix('portal')->name('portal.')->group(function () {
    Route::get('/email/verify',                     [VerifyEmailController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}',         [VerifyEmailController::class, 'verify'])->name('verification.verify')->middleware('signed');
    Route::post('/email/verification-notification', [VerifyEmailController::class, 'resend'])->name('verification.send')->middleware('throttle:6,1');
});
```

### Step 3 — VerifyEmailController

**File:** `app/Http/Controllers/Portal/VerifyEmailController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerifyEmailController extends Controller
{
    /**
     * GET /portal/email/verify
     * Show "please verify your email" notice.
     */
    public function notice(): View
    {
        return view('portal.auth.verify-email');
    }

    /**
     * GET /portal/email/verify/{id}/{hash}
     * Verify the email via signed URL.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('portal.dashboard');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->route('portal.dashboard')->with('success', 'Email verified successfully.');
    }

    /**
     * POST /portal/email/verification-notification
     * Resend the verification email.
     */
    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('portal.dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'Verification link sent.');
    }
}
```

### View Needed
- `resources/views/portal/auth/verify-email.blade.php` — "check your email" page with resend button

---

## 5. CSRF Protection

All forms must include `@csrf`. This is handled automatically by Laravel's `VerifyCsrfToken` middleware.

**Checklist — every form must have:**
```html
<form method="POST" ...>
    @csrf
    ...
</form>
```

PUT/DELETE forms additionally need:
```html
@method('PUT')   <!-- or @method('DELETE') -->
```

---

## 6. Session Security

Already handled in `RegisterController` — ensure these are present:

**On login:**
```php
$request->session()->regenerate(); // Prevents session fixation
```

**On logout:**
```php
Auth::logout();
$request->session()->invalidate();       // Destroy session data
$request->session()->regenerateToken();  // Regenerate CSRF token
```

---

## Complete Security Checklist

Before marking portal as complete, verify:

- [ ] `guest` middleware on login/register routes — logged-in users redirected
- [ ] `auth` middleware on all portal pages — guests redirected to login
- [ ] `verified` middleware on authenticated routes — unverified users see verify notice
- [ ] `role:customer` middleware — admins/staff get 403
- [ ] `customer.active` middleware — blocked/inactive customers auto-logged-out
- [ ] `throttle:login` on POST /portal/login — max 5 attempts/minute
- [ ] `EnsureCustomerIsActive` registered as `customer.active` alias
- [ ] `User` model implements `MustVerifyEmail`
- [ ] All forms have `@csrf`
- [ ] Login calls `$request->session()->regenerate()`
- [ ] Logout calls `invalidate()` and `regenerateToken()`
- [ ] Password reset routes working
- [ ] Email verification routes working
