# Portal Foundation — Auth Controllers

Four controllers in `App\Http\Controllers\Portal\Auth` namespace.

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

    /**
     * GET /portal/register
     */
    public function create(): View
    {
        return view('portal.auth.register');
    }

    /**
     * POST /portal/register
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $customer = $this->service->register($request->validated());

        // Load user relationship explicitly — never lazy load
        $customer->load('user');

        Auth::login($customer->user);

        $customer->user->sendEmailVerificationNotification();

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
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    /**
     * GET /portal/login
     */
    public function create(): View
    {
        return view('portal.auth.login');
    }

    /**
     * POST /portal/login
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Attempt login
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        $user = Auth::user();

        // Must have customer role
        if (! $user->hasRole('customer')) {
            Auth::logout();
            return back()
                ->withErrors(['email' => 'This login is for customers only.'])
                ->onlyInput('email');
        }

        // Check customer status — blocked/inactive cannot login
        $customer = $this->service->getByUser($user);

        if ($customer->status !== CustomerStatus::Active) {
            Auth::logout();
            return back()
                ->withErrors(['email' => 'Your account has been deactivated. Please contact support.'])
                ->onlyInput('email');
        }

        // Prevent session fixation
        $request->session()->regenerate();

        return redirect()->intended(route('portal.dashboard'));
    }

    /**
     * POST /portal/logout
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
```

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
    /**
     * GET /portal/forgot-password
     */
    public function create(): View
    {
        return view('portal.auth.forgot-password');
    }

    /**
     * POST /portal/forgot-password
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        // Always return success — never reveal if email exists
        return back()->with('status', 'If that email exists, a reset link has been sent.');
    }
}
```

**Security note:** Always return the same message regardless of whether the email exists. This prevents email enumeration attacks.

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
    /**
     * GET /portal/reset-password/{token}
     */
    public function create(Request $request, string $token): View
    {
        return view('portal.auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    /**
     * POST /portal/reset-password
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token'                 => ['required'],
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('portal.login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)])->onlyInput('email');
    }
}
```

---

## 5. EmailVerificationController

**File:** `app/Http/Controllers/Portal/Auth/EmailVerificationController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    /**
     * GET /portal/email/verify
     * Show "please verify your email" notice.
     */
    public function notice(Request $request): RedirectResponse|View
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('portal.dashboard');
        }

        return view('portal.auth.verify-email');
    }

    /**
     * GET /portal/email/verify/{id}/{hash}
     * Handle the email verification link click.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('portal.dashboard');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->route('portal.dashboard')
            ->with('success', 'Email verified. Welcome!');
    }

    /**
     * POST /portal/email/verification-notification
     * Resend verification email.
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

---

## User Model Requirement

`User` model must implement `MustVerifyEmail`:

```php
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    ...
}
```

Check if this is already set before adding it.
