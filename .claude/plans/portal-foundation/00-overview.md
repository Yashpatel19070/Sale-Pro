# Customer Portal Foundation — Overview

## Purpose
Set up the complete frontend portal infrastructure.
Auth, layout, middleware, routes — everything a module needs to plug into.
No feature modules here — just the foundation.

## Features
| # | Feature |
|---|---------|
| 1 | Portal layout — separate frontend layout (nav, footer) |
| 2 | Register — customer self-registration |
| 3 | Login — portal login with status check |
| 4 | Logout — destroy session securely |
| 5 | Forgot password — send reset link via email |
| 6 | Reset password — set new password via signed link |
| 7 | Email verification — verify email after register |
| 8 | Route groups — `/portal` prefix with full middleware stack |

## File Map
| File | Path |
|------|------|
| Migration | `database/migrations/xxxx_add_user_id_to_customers_table.php` |
| Portal Layout | `resources/views/portal/layouts/app.blade.php` |
| Guest Layout | `resources/views/portal/layouts/guest.blade.php` |
| Authenticate Middleware | `app/Http/Middleware/Authenticate.php` — update `redirectTo()` |
| RedirectIfAuthenticated Middleware | `app/Http/Middleware/RedirectIfAuthenticated.php` — update `handle()` |
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
| CustomerRoleSeeder | `database/seeders/CustomerRoleSeeder.php` |
| Feature Test | `tests/Feature/Portal/Auth/PortalAuthTest.php` |

## Dependency — CustomerService
The portal auth controllers use two methods that must be added to the existing `CustomerService`.
These are documented in: `.claude/plans/customer/03-service.md` → **Portal Methods** section.

Methods required:
- `register(array $data): Customer`
- `getByUser(User $user): Customer`

Add these to `app/Services/CustomerService.php` BEFORE implementing the portal controllers.

## Implementation Order
1. Migration — add `user_id` to customers table → run migrate
2. Update `Customer` model — add `user_id` to `$fillable` + `belongsTo(User::class)`
3. Update `User` model — add `hasOne(Customer::class)` + implement `MustVerifyEmail`
4. Add portal methods to `CustomerService` (see `.claude/plans/customer/03-service.md`)
5. CustomerRoleSeeder — create `customer` role → run seeder
6. Update `Authenticate` middleware — portal routes redirect to `/portal/login`
7. Update `RedirectIfAuthenticated` middleware — portal routes redirect to `/portal/dashboard`
8. Portal layouts (app + guest)
9. Auth controllers (Register → Login → Logout → ForgotPassword → ResetPassword → EmailVerify)
10. Auth views
11. Dashboard view
12. Routes — add to `web.php`
13. Rate limiting — configure in `AppServiceProvider`
14. Tests

## Routes (add to routes/web.php)
```php
use App\Http\Controllers\Portal\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Portal\Auth\EmailVerificationController;
use App\Http\Controllers\Portal\Auth\NewPasswordController;
use App\Http\Controllers\Portal\Auth\PasswordResetLinkController;
use App\Http\Controllers\Portal\Auth\RegisteredUserController;

// -------------------------------------------------------
// Portal Guest Routes (only accessible when NOT logged in)
// -------------------------------------------------------
Route::middleware('guest')->prefix('portal')->name('portal.')->group(function () {
    Route::get('/register',              [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register',             [RegisteredUserController::class, 'store'])->name('register.store')->middleware('throttle:register');
    Route::get('/login',                 [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login',                [AuthenticatedSessionController::class, 'store'])->name('login.store')->middleware('throttle:login');
    Route::get('/forgot-password',       [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password',      [PasswordResetLinkController::class, 'store'])->name('password.email')->middleware('throttle:forgot-password');
    Route::get('/reset-password/{token}',[NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password',       [NewPasswordController::class, 'store'])->name('password.update');
});

// -------------------------------------------------------
// Portal Authenticated Routes
// -------------------------------------------------------
Route::middleware(['auth', 'verified:portal.verification.notice', 'role:customer'])
    ->prefix('portal')->name('portal.')->group(function () {

    // Email verification (must be before verified middleware kicks in)
    Route::get('/email/verify',                     [EmailVerificationController::class, 'notice'])->name('verification.notice')->withoutMiddleware('verified:portal.verification.notice');
    Route::get('/email/verify/{id}/{hash}',         [EmailVerificationController::class, 'verify'])->name('verification.verify')->middleware('signed')->withoutMiddleware('verified:portal.verification.notice');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])->name('verification.send')->middleware('throttle:6,1')->withoutMiddleware('verified:portal.verification.notice');

    // Logout
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Dashboard
    Route::get('/dashboard', fn () => view('portal.dashboard'))->name('dashboard');

    // ← Feature modules plug in here (profile, orders, etc.)
});
```

## Implementation Checklist

Complete every item in order. Do not skip ahead.

### Pre-requisite — Customer Admin Module
- [ ] Customer admin module fully implemented first (see `.claude/plans/customer/`)
- [ ] `CustomerService` has `register()` and `getByUser()` methods added (see `customer/03-service.md` Portal Methods section)

### Migration
- [ ] `add_user_id_to_customers_table` migration created
- [ ] `user_id` is nullable, unique, foreignId constrained to users, nullOnDelete
- [ ] `php artisan migrate` runs without error

### Model Updates
- [ ] `Customer` model — `user_id` added to `$fillable`
- [ ] `Customer` model — `user(): BelongsTo` relationship added
- [ ] `User` model — `customer(): HasOne` relationship added
- [ ] `User` model — implements `MustVerifyEmail` interface (check before adding)

### Role Seeder
- [ ] `CustomerRoleSeeder` creates `customer` role with `firstOrCreate`
- [ ] Registered in `DatabaseSeeder`
- [ ] `php artisan db:seed --class=CustomerRoleSeeder` runs without error

### Middleware
- [ ] `Authenticate::redirectTo()` — portal routes → `route('portal.login')`, others → `route('login')`
- [ ] `RedirectIfAuthenticated::handle()` — portal routes → `route('portal.dashboard')`, others → `route('dashboard')`
- [ ] No reference to `RouteServiceProvider` anywhere

### AppServiceProvider
- [ ] Rate limiters configured: `login` (5/min), `register` (3/min), `forgot-password` (3/min)
- [ ] `ResetPassword::createUrlUsing()` points to `portal.password.reset`
- [ ] `VerifyEmail::createUrlUsing()` points to `portal.verification.verify`
- [ ] All 3 additions are in the same `boot()` method — not split across files

### Layouts
- [ ] `resources/views/portal/layouts/app.blade.php` — authenticated layout with nav + flash messages + `@yield('content')`
- [ ] `resources/views/portal/layouts/guest.blade.php` — centered card layout with `@yield('content')`
- [ ] No Blade components used for layouts — pure `@extends`

### Controllers (all in `App\Http\Controllers\Portal\Auth` namespace)
- [ ] `RegisteredUserController` — `create()` + `store()`
- [ ] `store()` calls `$customer->load('user')` before `Auth::login()` — no lazy load
- [ ] `store()` calls `sendEmailVerificationNotification()` then redirects to `portal.verification.notice`
- [ ] `AuthenticatedSessionController` — `create()` + `store()` + `destroy()`
- [ ] `store()` checks `hasRole('customer')` — rejects non-customers
- [ ] `store()` checks `customer->status === CustomerStatus::Active` — rejects blocked/inactive
- [ ] `store()` calls `$request->session()->regenerate()`
- [ ] `destroy()` calls `invalidate()` + `regenerateToken()`
- [ ] `PasswordResetLinkController` — `create()` + `store()`
- [ ] `store()` always returns same success message (no email enumeration)
- [ ] `NewPasswordController` — `create()` + `store()`
- [ ] `EmailVerificationController` — `notice()` + `verify()` + `resend()`

### FormRequest
- [ ] `RegisterRequest` at `app/Http/Requests/Portal/Auth/RegisterRequest.php`
- [ ] Email unique in both `users` AND `customers` tables
- [ ] Password uses `confirmed` rule

### Views (all use `@extends('portal.layouts.guest')` or `@extends('portal.layouts.app')`)
- [ ] `portal/auth/register.blade.php` — all fields, old(), validation errors, link to login
- [ ] `portal/auth/login.blade.php` — email, password, remember me, forgot password link, link to register
- [ ] `portal/auth/forgot-password.blade.php` — email field, success message
- [ ] `portal/auth/reset-password.blade.php` — hidden token, email, password, confirm
- [ ] `portal/auth/verify-email.blade.php` — resend button + logout button
- [ ] `portal/dashboard.blade.php` — welcome message, extends app layout

### Routes
- [ ] Guest group: register, register.store, login, login.store, password.request, password.email, password.reset, password.update
- [ ] Authenticated group uses `['auth', 'verified:portal.verification.notice', 'role:customer']`
- [ ] Verification routes use `withoutMiddleware('verified:portal.verification.notice')`
- [ ] Throttle applied: `throttle:login` on login.store, `throttle:register` on register.store, `throttle:forgot-password` on password.email
- [ ] Run `php artisan route:list | grep portal` — verify all routes exist

### Tests
- [ ] `beforeEach` seeds `CustomerRoleSeeder` + role seeder for admin/staff roles
- [ ] Register tests: success, duplicate email, mismatched passwords, missing fields
- [ ] Login tests: success, wrong password, admin rejected, blocked rejected, inactive rejected
- [ ] Logout test: session destroyed, redirected to portal.login
- [ ] Email verification tests: notice shown, verify link works, resend works
- [ ] Forgot password test: always returns success
- [ ] Dashboard tests: guest redirected, admin forbidden, customer can access
- [ ] `php artisan test --filter PortalAuthTest` — all pass

### Final Smoke Test
- [ ] Visit `/portal/register` — see register form
- [ ] Register a new account — redirected to verify email page
- [ ] Click verification link in email — redirected to `/portal/dashboard`
- [ ] Logout — redirected to `/portal/login`
- [ ] Login again — redirected to `/portal/dashboard`
- [ ] Visit `/portal/login` while logged in — redirected to `/portal/dashboard`
- [ ] Admin tries `/portal/dashboard` — gets 403
- [ ] Guest tries `/portal/dashboard` — redirected to `/portal/login`
- [ ] Try forgot password flow — receives email with correct reset link

---

## Security Summary
| Concern | Solution |
|---------|----------|
| Unauthenticated portal access | `auth` middleware → redirects to `/portal/login` (custom) |
| Unverified email | `verified:portal.verification.notice` middleware |
| Wrong role (admin/staff on portal) | `role:customer` middleware → 403 |
| Already logged in visiting portal login | `guest` middleware → redirects to `/portal/dashboard` (custom) |
| Login brute force | `throttle:login` — 5/min |
| Register spam | `throttle:register` — 3/min |
| Forgot password spam | `throttle:forgot-password` — 3/min |
| Blocked/inactive customer | Status check in login controller only |
| Session fixation | `session()->regenerate()` on login |
| Session hijack after logout | `invalidate()` + `regenerateToken()` on logout |
| CSRF | `@csrf` on every form (Laravel auto) |
| Password reset link abuse | Signed URL — expires, one-time use |
| Email enumeration on forgot password | Always return same success message |
