# Customer Portal Foundation — Overview

## Purpose

Set up the complete frontend portal infrastructure.
Auth, layout, middleware, routes — everything a module needs to plug into.
No feature modules here — just the foundation.

## Architecture

`customers` table IS the portal auth table. Separate `customer` guard.
`users` table is backend staff only — no link between tables.

```
users      → backend (admin/manager/sales) → web guard
customers  → portal                        → customer guard
```

## Features

| # | Feature |
|---|---------|
| 1 | Portal layout — separate frontend layout (nav, footer) |
| 2 | Register — customer self-registration (creates Customer with password) |
| 3 | Login — portal login via customer guard, with status check |
| 4 | Logout — destroy customer guard session securely |
| 5 | Forgot password — send reset link via `customers` broker |
| 6 | Reset password — set new password via signed link |
| 7 | Email verification — verify email after register |
| 8 | Route groups — portal prefix with full middleware stack |

## File Map

| File | Path |
|------|------|
| Migration: drop user_id | `database/migrations/xxxx_drop_user_id_from_customers_table.php` |
| Migration: add auth columns | `database/migrations/xxxx_add_auth_columns_to_customers_table.php` |
| Migration: password resets | `database/migrations/xxxx_create_customer_password_resets_table.php` |
| config/auth.php | Add `customer` guard, provider, password broker |
| Portal Layout | `resources/views/portal/layouts/app.blade.php` |
| Guest Layout | `resources/views/portal/layouts/guest.blade.php` |
| SecurityHeaders Middleware | `app/Http/Middleware/SecurityHeaders.php` — new, registered via prepend |
| Authenticate Middleware | `app/Http/Middleware/Authenticate.php` — update `redirectTo()` |
| RedirectIfAuthenticated | `app/Http/Middleware/RedirectIfAuthenticated.php` — update `handle()` |
| EnsureCustomerIsActive | `app/Http/Middleware/EnsureCustomerIsActive.php` — use customer guard |
| Register Controller | `app/Http/Controllers/Portal/Auth/RegisteredUserController.php` |
| Login Controller | `app/Http/Controllers/Portal/Auth/AuthenticatedSessionController.php` |
| Forgot Password Controller | `app/Http/Controllers/Portal/Auth/PasswordResetLinkController.php` |
| Reset Password Controller | `app/Http/Controllers/Portal/Auth/NewPasswordController.php` |
| Email Verify Controller | `app/Http/Controllers/Portal/Auth/EmailVerificationController.php` |
| Register View | `resources/views/portal/auth/register.blade.php` |
| Login View | `resources/views/portal/auth/login.blade.php` |
| Forgot Password View | `resources/views/portal/auth/forgot-password.blade.php` |
| Reset Password View | `resources/views/portal/auth/reset-password.blade.php` |
| Verify Email View | `resources/views/portal/auth/verify-email.blade.php` |
| Dashboard View | `resources/views/portal/dashboard.blade.php` |
| Feature Test | `tests/Feature/Portal/Auth/PortalAuthTest.php` |

**Deleted:**
- `database/seeders/CustomerRoleSeeder.php` — no Spatie role needed for customers

## Dependency — CustomerService

Portal auth uses `CustomerService::register()`.
Method documented in: `.claude/plans/customer/03-service.md` → **Portal Methods** section.

## Implementation Order

1. Migrations (3): drop user_id → add auth columns → create customer_password_resets
2. `config/auth.php` — add customer guard + provider + password broker
3. `Customer` model — add Authenticatable, MustVerifyEmail, Notifiable; remove user() relationship
4. `User` model — remove customer() HasOne
5. `CustomerFactory` — add password, email_verified_at
6. Add `register()` to `CustomerService` (see customer/03-service.md)
7. Delete `CustomerRoleSeeder`, remove from DatabaseSeeder and E2ESeeder
8. Create `SecurityHeaders` middleware, register via `prepend` in `bootstrap/app.php`
9. Update `Authenticate` middleware
10. Update `RedirectIfAuthenticated` middleware
11. Update `EnsureCustomerIsActive` middleware
12. Update `AppServiceProvider` — guard-aware notification URLs + rate limiters
13. Portal layouts (app + guest)
13. Auth controllers (Register → Login → Logout → ForgotPassword → ResetPassword → EmailVerify)
14. Auth views
15. Dashboard view
16. Update routes/web.php — portal middleware stack
17. Tests

## Routes

All portal routes share the `portal.` name prefix via an outer group.
Guest routes check the `customer` guard specifically via `guest:customer`.

```php
use App\Http\Controllers\Portal\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Portal\Auth\EmailVerificationController;
use App\Http\Controllers\Portal\Auth\NewPasswordController;
use App\Http\Controllers\Portal\Auth\PasswordResetLinkController;
use App\Http\Controllers\Portal\Auth\RegisteredUserController;

// All portal routes — share portal. name prefix
Route::name('portal.')->group(function () {

    // Guest only — redirect to dashboard if already logged in as customer
    Route::middleware('guest:customer')->group(function () {
        Route::get('/login',                    [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login',                   [AuthenticatedSessionController::class, 'store'])->name('login.store')->middleware('throttle:login');
        Route::get('/register',                 [RegisteredUserController::class, 'create'])->name('register');
        Route::post('/register',                [RegisteredUserController::class, 'store'])->name('register.store')->middleware('throttle:register');
        Route::get('/forgot-password',          [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('/forgot-password',         [PasswordResetLinkController::class, 'store'])->name('password.email')->middleware('throttle:forgot-password');
        Route::get('/reset-password/{token}',   [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('/reset-password',          [NewPasswordController::class, 'store'])->name('password.update');
    });

    // Authenticated — all feature modules plug in here
    Route::middleware(['auth:customer', 'verified:portal.verification.notice', 'customer.active'])->group(function () {

        // Email verify routes — exempt from verified check
        Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
            ->name('verification.notice')
            ->withoutMiddleware('verified:portal.verification.notice');
        Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->name('verification.verify')
            ->middleware('signed')
            ->withoutMiddleware('verified:portal.verification.notice');
        Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
            ->name('verification.send')
            ->middleware('throttle:6,1')
            ->withoutMiddleware('verified:portal.verification.notice');

        Route::post('/logout',   [AuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::get('/dashboard', fn () => view('portal.dashboard', ['customer' => auth('customer')->user()]))->name('dashboard');

        // ← Feature modules plug in here (profile, addresses, orders, etc.)
    });

});
```

**Middleware change:** `auth:customer` replaces `auth` + `role:customer` + `active`.
**Route name fix:** outer `Route::name('portal.')` gives ALL portal routes the `portal.` prefix — guest and authenticated alike.
**Guard fix:** `guest:customer` checks the customer guard — a logged-in customer is correctly redirected to dashboard.

## Security Summary

| Concern | Solution |
|---------|----------|
| Clickjacking / XSS headers | `SecurityHeaders` middleware — prepended, fires on every request |
| Unauthenticated portal access | `auth:customer` → redirects to `/login` (portal.login) |
| Unverified email | `verified:portal.verification.notice` |
| Staff accessing portal | `auth:customer` checks `customers` table — staff User model never matches |
| Blocked/inactive customer | `EnsureCustomerIsActive` checks `customers.status` |
| Session fixation | `session()->regenerate()` on login |
| Session hijack after logout | `invalidate()` + `regenerateToken()` on logout |
| Login brute force | `throttle:login` — 5/min |
| Register spam | `throttle:register` — 3/min |
| Forgot password spam | `throttle:forgot-password` — 3/min |
| Password reset link abuse | Signed URL — expires, one-time use |
| Email enumeration on forgot password | Always return same success message |
| Email collision in password resets | Separate `customer_password_resets` table |
