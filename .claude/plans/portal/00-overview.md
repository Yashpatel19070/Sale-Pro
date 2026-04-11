# Customer Portal — Profile Module — Overview

## Purpose
Lets a logged-in customer view and edit their own profile and change their password.
Auth, layout, routes, and middleware are all handled by the portal foundation.
This module plugs into the foundation — do not re-implement anything from it.

## Pre-requisite
**Portal Foundation must be fully implemented before this module.**
See: `.claude/plans/portal-foundation/`

## Features
| # | Feature |
|---|---------|
| 1 | View profile — customer sees their own details (read only) |
| 2 | Edit profile — update name, phone, company, address fields |
| 3 | Change password — update their own password |

## What Customer Cannot Change
- Email — admin only
- Status — admin only

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
Two new methods must be added to the existing `CustomerService`.
Documented in: `.claude/plans/customer/03-service.md` — add after the portal `register()` and `getByUser()` methods.

```php
updateProfile(Customer $customer, array $data): Customer
changePassword(User $user, string $currentPassword, string $newPassword): bool
```

See `portal/02-service.md` for full method code.

## Implementation Order
1. Add `updateProfile()` and `changePassword()` to `CustomerService`
2. Create `UpdatePortalProfileRequest`
3. Create `ChangePortalPasswordRequest`
4. Create `ProfileController`
5. Add routes to existing portal authenticated group in `web.php`
6. Create views (show → edit → password)
7. Tests

## Routes (add inside existing portal authenticated route group in web.php)
```php
use App\Http\Controllers\Portal\ProfileController;

// Add these inside the existing authenticated portal route group:
Route::get('/profile',          [ProfileController::class, 'show'])->name('profile.show');
Route::get('/profile/edit',     [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('/profile',          [ProfileController::class, 'update'])->name('profile.update');
Route::get('/profile/password', [ProfileController::class, 'passwordForm'])->name('profile.password');
Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
```

## Implementation Checklist

Complete every item in order.

### Pre-requisite Check
- [ ] Portal foundation fully implemented and all tests passing
- [ ] `CustomerService` already has `register()` and `getByUser()` methods

### Service Methods
- [ ] `updateProfile(Customer, array)` added to `CustomerService`
  - Updates: name, phone, company_name, address, city, state, postal_code, country
  - Does NOT update: email, status
  - Also syncs `name` on linked `User` record
  - Returns fresh Customer
- [ ] `changePassword(User, string $current, string $new)` added to `CustomerService`
  - Verifies current password with `Hash::check()`
  - Returns `false` if current password wrong — does NOT throw
  - Updates password with `Hash::make($new)`
  - Returns `true` on success

### FormRequests
- [ ] `UpdatePortalProfileRequest` at `app/Http/Requests/Portal/UpdatePortalProfileRequest.php`
  - Required: name, phone, address, city, state, postal_code, country
  - Optional: company_name
  - NO email field, NO status field
- [ ] `ChangePortalPasswordRequest` at `app/Http/Requests/Portal/ChangePortalPasswordRequest.php`
  - Required: current_password, password (min:8, confirmed)

### Controller
- [ ] `ProfileController` at `app/Http/Controllers/Portal/ProfileController.php`
- [ ] Constructor injects `CustomerService`
- [ ] Every action calls `$this->service->getByUser(auth()->user())` — no lazy load
- [ ] `show()` — returns `portal.profile.show` view with `$customer`
- [ ] `edit()` — returns `portal.profile.edit` view with `$customer`
- [ ] `update()` — calls `updateProfile()`, redirects to `portal.profile.show` with success
- [ ] `passwordForm()` — returns `portal.profile.password` view
- [ ] `updatePassword()` — calls `changePassword()`, if false → back with error on `current_password`, if true → redirect to `portal.profile.show` with success

### Views (all extend `portal.layouts.app`)
- [ ] `portal/profile/show.blade.php` — displays all fields (read only), Edit Profile button, Change Password button
- [ ] `portal/profile/edit.blade.php` — form pre-filled with `old('field', $customer->field)`, NO email/status fields, Save + Cancel buttons
- [ ] `portal/profile/password.blade.php` — current_password, password, password_confirmation fields, error on current_password, Save + Cancel buttons
- [ ] All forms have `@csrf`
- [ ] PUT forms have `@method('PUT')`
- [ ] All inputs use `old()` to repopulate after validation errors
- [ ] Validation errors shown under each field

### Routes
- [ ] 5 routes added inside existing portal authenticated group
- [ ] Route names: portal.profile.show, portal.profile.edit, portal.profile.update, portal.profile.password, portal.profile.password.update
- [ ] Run `php artisan route:list | grep profile` — verify all 5 exist

### Tests
- [ ] `beforeEach` seeds `CustomerRoleSeeder`
- [ ] Test: customer can view profile
- [ ] Test: customer can see edit form
- [ ] Test: customer can update profile — name, phone, address updated in DB
- [ ] Test: profile update does NOT change email
- [ ] Test: profile update fails with missing required field
- [ ] Test: customer can see change password form
- [ ] Test: customer can change password successfully
- [ ] Test: change password fails with wrong current password
- [ ] Test: change password fails with mismatched confirmation
- [ ] Test: guest cannot access any profile route
- [ ] `php artisan test --filter ProfileControllerTest` — all pass

### Final Smoke Test
- [ ] Login as customer → visit `/portal/profile` — see all details
- [ ] Click Edit Profile → update name → saved correctly
- [ ] Verify email and status did NOT change after profile update
- [ ] Change password → login again with new password — works
- [ ] Change password with wrong current password → see error message
