# Auth Reference (Laravel Breeze)

## Stack
**Laravel Breeze** — auth scaffolding (login, register, password reset, email verify)

---

## Laravel Breeze Setup (Blade Stack)

```bash
composer require laravel/breeze --dev
php artisan breeze:install blade
php artisan migrate
npm install && npm run dev
```

Breeze scaffolds these routes automatically:
| Route | Description |
|-------|-------------|
| `GET /login` | Login form |
| `POST /login` | Authenticate |
| `POST /logout` | Logout |
| `GET /register` | Register form |
| `POST /register` | Create account |
| `GET /forgot-password` | Password reset request |
| `GET /reset-password/{token}` | Password reset form |
| `GET /verify-email` | Email verification notice |
| `GET /dashboard` | Authenticated landing page |

All Breeze views live in `resources/views/auth/`. Customize freely — Breeze is just a starting point.

### Breeze-Generated Files to Know
```
app/Http/Controllers/Auth/
├── AuthenticatedSessionController.php   // login / logout
├── RegisteredUserController.php         // registration  ← assign default role here
├── PasswordResetLinkController.php      // forgot password
├── NewPasswordController.php            // reset password
├── EmailVerificationController.php      // verify email
└── ConfirmablePasswordController.php    // sudo-mode confirm

resources/views/
├── auth/
│   ├── login.blade.php
│   ├── register.blade.php
│   └── ...
├── layouts/
│   ├── app.blade.php        // authenticated layout
│   └── guest.blade.php      // guest layout
└── dashboard.blade.php
```

---

## Default Role on Registration

Override Breeze's `RegisteredUserController` to assign `viewer` on every new registration:

```php
// app/Http/Controllers/Auth/RegisteredUserController.php
public function store(Request $request): RedirectResponse
{
    $request->validate([
        'name'     => ['required', 'string', 'max:255'],
        'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
        'password' => ['required', 'confirmed', Rules\Password::defaults()],
    ]);

    $user = User::create([
        'name'     => $request->name,
        'email'    => $request->email,
        'password' => Hash::make($request->password),
    ]);

    $user->assignRole('viewer'); // ← default role

    event(new Registered($user));
    Auth::login($user);

    return redirect(route('dashboard', absolute: false));
}
```

---

## Blade Directives — Auth State

```blade
@auth
    <span>Hello, {{ auth()->user()->name }}</span>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button>Logout</button>
    </form>
@endauth

@guest
    <a href="{{ route('login') }}">Login</a>
    <a href="{{ route('register') }}">Register</a>
@endguest

{{-- Email verified check --}}
@auth
    @if(auth()->user()->hasVerifiedEmail())
        <x-dashboard />
    @else
        <p>Please verify your email.
            <a href="{{ route('verification.send') }}">Resend link</a>
        </p>
    @endif
@endauth
```

---

## Rate Limiting on Login

Laravel 12 has built-in login throttling via `RateLimiter` in `AppServiceProvider`. Breeze does
NOT wire this up automatically — you must add it manually.

### Register the Rate Limiter

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    // Login rate limiter — 5 attempts per minute per email+IP combo
    RateLimiter::for('login', function (Request $request) {
        $throttleKey = str()->lower($request->input('email')) . '|' . $request->ip();

        return Limit::perMinute(5)->by($throttleKey);
    });
}
```

### Apply It in the Login Controller

Breeze's `AuthenticatedSessionController` does not use this limiter by default. Override it:

```php
// app/Http/Controllers/Auth/AuthenticatedSessionController.php
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

public function store(Request $request): RedirectResponse
{
    $request->validate([
        'email'    => ['required', 'string', 'email'],
        'password' => ['required', 'string'],
    ]);

    $throttleKey = Str::lower($request->input('email')) . '|' . $request->ip();

    if (RateLimiter::tooManyAttempts('login:' . $throttleKey, 5)) {
        $seconds = RateLimiter::availableIn('login:' . $throttleKey);

        throw ValidationException::withMessages([
            'email' => __('Too many login attempts. Please try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]),
        ]);
    }

    if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
        RateLimiter::hit('login:' . $throttleKey, 60);

        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }

    RateLimiter::clear('login:' . $throttleKey);
    $request->session()->regenerate();

    return redirect()->intended(route('dashboard', absolute: false));
}
```

### Key Options

```php
Limit::perMinute(5)->by($throttleKey);
Limit::perMinutes(10, 10)->by($throttleKey);
```

### Also Protect Password Reset

```php
RateLimiter::for('password-reset', function (Request $request) {
    return Limit::perMinute(3)->by($request->ip());
});
```
