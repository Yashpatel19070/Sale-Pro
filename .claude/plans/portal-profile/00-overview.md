# Customer Portal — Profile Module — Overview

## Purpose

Lets a logged-in customer view and edit their own profile and change their password.
Auth, layout, routes, and middleware are handled by the portal foundation.
This module plugs into the foundation — do not re-implement anything from it.

## Pre-requisite

**Portal Foundation must be fully implemented before this module.**
See: `.claude/plans/portal-foundation/`

## Features

| # | Feature |
|---|---------|
| 1 | View profile — customer sees their own details (read only) |
| 2 | Edit profile — update name, phone, company_name only |
| 3 | Change password — update their own password (requires current password) |

## What Customer Cannot Change

- Email — admin only
- Status — admin only
- Addresses — managed via customer-addresses module

## File Map

| File | Path |
|------|------|
| Profile Controller | `app/Http/Controllers/Portal/ProfileController.php` |
| Update Profile Request | `app/Http/Requests/Portal/UpdatePortalProfileRequest.php` |
| Change Password Request | `app/Http/Requests/Portal/ChangePortalPasswordRequest.php` |
| View: profile show | `resources/views/portal/profile/show.blade.php` |
| View: profile edit | `resources/views/portal/profile/edit.blade.php` |
| View: change password | `resources/views/portal/profile/password.blade.php` |
| Feature Test | `tests/Feature/Portal/ProfileControllerTest.php` |

## Service Methods Required

Two methods in `CustomerService`. See `.claude/plans/customer/03-service.md` and `portal-profile/02-service.md`.

```php
updateProfile(Customer $customer, array $data): Customer   // name, phone, company_name only
changePassword(Customer $customer, string $current, string $new): void  // throws ValidationException if wrong
```

## Implementation Order

1. Add `updateProfile()` and `changePassword()` to `CustomerService`
2. Create `UpdatePortalProfileRequest`
3. Create `ChangePortalPasswordRequest`
4. Create `ProfileController`
5. Add routes inside existing portal authenticated group in `web.php`
6. Create views (show → edit → password)
7. Tests

## Routes (add inside existing portal authenticated route group in web.php)

```php
use App\Http\Controllers\Portal\ProfileController;

Route::get('/profile',          [ProfileController::class, 'show'])->name('profile.show');
Route::get('/profile/edit',     [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('/profile',          [ProfileController::class, 'update'])->name('profile.update');
Route::get('/profile/password', [ProfileController::class, 'passwordForm'])->name('profile.password');
Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
```

## Implementation Checklist

### Pre-requisite Check

- [ ] Portal foundation fully implemented and all tests passing
- [ ] `CustomerService` has `register()`, `updateProfile()`, `changePassword()` methods

### Service Methods

- [ ] `updateProfile(Customer, array)` added to `CustomerService`
  - Updates: name, phone, company_name
  - Does NOT update: email, status, addresses
  - No User sync — Customer is its own auth record
  - Returns updated Customer
- [ ] `changePassword(Customer, string $current, string $new)` added to `CustomerService`
  - Verifies current password with `Hash::check()`
  - Throws `ValidationException` on `current_password` key if wrong
  - Updates password with `Hash::make($new)`
  - Returns void

### FormRequests

- [ ] `UpdatePortalProfileRequest` — fields: name (required), phone (required), company_name (nullable)
- [ ] `ChangePortalPasswordRequest` — fields: current_password (required), password (min:8, confirmed)

### Controller

- [ ] `ProfileController` at `app/Http/Controllers/Portal/ProfileController.php`
- [ ] Constructor injects `CustomerService`
- [ ] Every action: `$customer = $request->user('customer')` — never `auth()->user()`
- [ ] `show()` — returns `portal.profile.show` with `$customer`
- [ ] `edit()` — returns `portal.profile.edit` with `$customer`
- [ ] `update()` — calls `updateProfile()`, redirects to `portal.profile.show` with success
- [ ] `passwordForm()` — returns `portal.profile.password`
- [ ] `updatePassword()` — calls `changePassword()` (throws on bad current password — Laravel auto-converts ValidationException to redirect with errors)

### Views (all extend `portal.layouts.app`)

- [ ] `portal/profile/show.blade.php` — name, email (read-only), phone, company_name, Edit button, Change Password button
- [ ] `portal/profile/edit.blade.php` — form pre-filled with `old('field', $customer->field)`, NO email/status fields, Save + Cancel
- [ ] `portal/profile/password.blade.php` — current_password, password, password_confirmation fields, Save + Cancel
- [ ] All forms: `@csrf`
- [ ] PUT forms: `@method('PUT')`
- [ ] All inputs use `old()` to repopulate after validation errors
- [ ] Validation errors shown under each field

### Tests

- [ ] No `CustomerRoleSeeder` needed — no Spatie role for customers
- [ ] `beforeEach`: `Customer::factory()->create([...])` + `actingAs($customer, 'customer')`
- [ ] Test: customer can view profile
- [ ] Test: customer can see edit form
- [ ] Test: customer can update profile — name, phone saved in DB
- [ ] Test: profile update does NOT change email
- [ ] Test: profile update fails with missing required field
- [ ] Test: customer can see change password form
- [ ] Test: customer can change password successfully
- [ ] Test: change password fails with wrong current password → `assertSessionHasErrors('current_password')`
- [ ] Test: change password fails with mismatched confirmation
- [ ] Test: guest cannot access any profile route → redirected to `portal.login`
- [ ] `php artisan test --filter ProfileControllerTest` — all pass

### Final Smoke Test

- [ ] Login as customer → visit `/profile` — see all details
- [ ] Click Edit Profile → update name → saved correctly
- [ ] Verify email did NOT change after profile update
- [ ] Change password → login again with new password — works
- [ ] Change password with wrong current password → see error on `current_password` field
