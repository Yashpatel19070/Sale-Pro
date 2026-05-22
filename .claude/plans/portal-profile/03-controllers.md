# Customer Portal — ProfileController

Auth controllers (register, login, logout, forgot/reset password, email verify) live in
`portal-foundation/03-auth-controllers.md` — do NOT redefine them here.

This file covers only the profile-management controller for logged-in customers.

---

## ProfileController

**File:** `app/Http/Controllers/Portal/ProfileController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ChangePortalPasswordRequest;
use App\Http\Requests\Portal\UpdatePortalProfileRequest;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function show(Request $request): View
    {
        $customer = $request->user('customer');

        return view('portal.profile.show', compact('customer'));
    }

    public function edit(Request $request): View
    {
        $customer = $request->user('customer');

        return view('portal.profile.edit', compact('customer'));
    }

    public function update(UpdatePortalProfileRequest $request): RedirectResponse
    {
        $customer = $request->user('customer');

        $this->service->updateProfile($customer, $request->validated());

        return redirect()
            ->route('portal.profile.show')
            ->with('success', 'Profile updated successfully.');
    }

    public function passwordForm(): View
    {
        return view('portal.profile.password');
    }

    public function updatePassword(ChangePortalPasswordRequest $request): RedirectResponse
    {
        $customer = $request->user('customer');

        // changePassword() throws ValidationException if current password is wrong.
        // Laravel auto-converts ValidationException → redirect back with errors.
        $this->service->changePassword(
            $customer,
            $request->validated('current_password'),
            $request->validated('password'),
        );

        return redirect()
            ->route('portal.profile.show')
            ->with('success', 'Password changed successfully.');
    }
}
```

---

## Action Summary

| Method | Route | Description |
|--------|-------|-------------|
| `show` | GET /profile | View own profile (read-only) |
| `edit` | GET /profile/edit | Edit profile form |
| `update` | PUT /profile | Save profile changes |
| `passwordForm` | GET /profile/password | Change password form |
| `updatePassword` | PUT /profile/password | Save new password |

---

## Rules

- Every action uses `$request->user('customer')` — never `auth()->user()` or `getByUser()`
- No `$this->authorize()` — all portal routes are behind `auth:customer` middleware
- `updatePassword()` has no manual error check — `changePassword()` throws `ValidationException`
  and Laravel auto-redirects with session errors on `current_password`
- Dashboard route is a closure in `routes/web.php`, not a controller action:
  ```php
  Route::get('/dashboard', fn () => view('portal.dashboard'))->name('dashboard');
  ```
