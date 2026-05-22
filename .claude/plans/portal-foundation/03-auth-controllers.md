# Portal Foundation — Auth Controllers

Five controllers in `App\Http\Controllers\Portal\Auth` namespace.
All use the `customer` guard — never the `web` guard.

---

## 1. RegisteredUserController

**File:** `app/Http/Controllers/Portal/Auth/RegisteredUserController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\Auth\RegisterRequest;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function create(): View
    {
        return view('portal.auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $customer = $this->service->register($request->validated());

        Auth::guard('customer')->login($customer);

        $customer->sendEmailVerificationNotification();

        return redirect()->route('portal.verification.notice');
    }
}
```

---

## 2. AuthenticatedSessionController

**File:** `app/Http/Controllers/Portal/Auth/AuthenticatedSessionController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Auth;

use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('portal.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('customer')->attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        /** @var \App\Models\Customer $customer */
        $customer = Auth::guard('customer')->user();

        if ($customer->status !== CustomerStatus::Active) {
            Auth::guard('customer')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Your account has been deactivated. Please contact support.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('portal.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
```

**Key changes from old design:**
- `Auth::guard('customer')->attempt()` — not the web guard
- No `hasRole('customer')` check — guard enforces this automatically
- No `getByUser()` lookup — `Auth::guard('customer')->user()` IS the Customer model directly

---

## 3. PasswordResetLinkController

**File:** `app/Http/Controllers/Portal/Auth/PasswordResetLinkController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('portal.auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::broker('customers')->sendResetLink($request->only('email'));

        // Always return success — never reveal if email exists
        return back()->with('status', 'If that email exists, a reset link has been sent.');
    }
}
```

**Key:** `Password::broker('customers')` — uses the `customers` broker from config/auth.php.

---

## 4. NewPasswordController

**File:** `app/Http/Controllers/Portal/Auth/NewPasswordController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('portal.auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker('customers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($customer, $password) {
                $customer->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($customer));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('portal.login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)])->onlyInput('email');
    }
}
```

**Key:** `Password::broker('customers')` on both `sendResetLink()` and `reset()`.

---

## 5. EmailVerificationController

**File:** `app/Http/Controllers/Portal/Auth/EmailVerificationController.php`

> **Do NOT use `EmailVerificationRequest`** — it resolves `$this->user()` from the default web guard.
> Use plain `Request` and verify manually.

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function notice(Request $request): RedirectResponse|View
    {
        /** @var \App\Models\Customer $customer */
        $customer = $request->user('customer');

        if ($customer->hasVerifiedEmail()) {
            return redirect()->route('portal.dashboard');
        }

        return view('portal.auth.verify-email');
    }

    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        /** @var \App\Models\Customer $customer */
        $customer = $request->user('customer');

        abort_unless((string) $customer->getKey() === $id, 403);
        abort_unless(hash_equals($hash, sha1($customer->getEmailForVerification())), 403);
        abort_unless($request->hasValidSignature(), 403);

        if (! $customer->hasVerifiedEmail()) {
            $customer->markEmailAsVerified();
            event(new Verified($customer));
        }

        return redirect()->route('portal.dashboard')->with('success', 'Email verified. Welcome!');
    }

    public function resend(Request $request): RedirectResponse
    {
        /** @var \App\Models\Customer $customer */
        $customer = $request->user('customer');

        if ($customer->hasVerifiedEmail()) {
            return redirect()->route('portal.dashboard');
        }

        $customer->sendEmailVerificationNotification();

        return back()->with('status', 'Verification link sent.');
    }
}
```

**Why manual verification:** `EmailVerificationRequest` calls `$this->user()` which uses the web guard — returns null for customers. Manual verification is safe and explicit.

---

## Summary of Guard Changes

| Before | After |
|--------|-------|
| `Auth::attempt()` | `Auth::guard('customer')->attempt()` |
| `Auth::user()` | `Auth::guard('customer')->user()` |
| `Auth::logout()` | `Auth::guard('customer')->logout()` |
| `$request->user()` | `$request->user('customer')` |
| `Password::sendResetLink()` | `Password::broker('customers')->sendResetLink()` |
| `Password::reset()` | `Password::broker('customers')->reset()` |
| `hasRole('customer')` check | Removed — guard handles this |
| `getByUser($user)` | Removed — guard user IS the Customer directly |
