# Customer Portal — Controllers

Two controllers in the `Portal` namespace.

---

## 1. RegisterController

**File:** `app/Http/Controllers/Portal/RegisterController.php`

Handles register, login, and logout.

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\RegisterCustomerRequest;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    /**
     * GET /portal/register
     * Show the registration form.
     */
    public function create(): View
    {
        return view('portal.auth.register');
    }

    /**
     * POST /portal/register
     * Register a new customer account.
     */
    public function store(RegisterCustomerRequest $request): RedirectResponse
    {
        $customer = $this->service->register($request->validated());

        Auth::login($customer->user);

        return redirect()
            ->route('portal.dashboard')
            ->with('success', 'Welcome! Your account has been created.');
    }

    /**
     * GET /portal/login
     * Show the portal login form.
     */
    public function loginForm(): View
    {
        return view('portal.auth.login');
    }

    /**
     * POST /portal/login
     * Authenticate a customer.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        // Ensure the logged-in user has the customer role
        if (! Auth::user()->hasRole('customer')) {
            Auth::logout();
            return back()->withErrors(['email' => 'This account is not a customer account.']);
        }

        $request->session()->regenerate();

        return redirect()->route('portal.dashboard');
    }

    /**
     * POST /portal/logout
     * Log the customer out.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
```

---

## 2. ProfileController

**File:** `app/Http/Controllers/Portal/ProfileController.php`

Handles dashboard, view profile, edit profile, change password.
All actions require the logged-in customer's profile via `$this->service->getByUser()`.

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ChangePortalPasswordRequest;
use App\Http\Requests\Portal\UpdatePortalProfileRequest;
use App\Services\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    /**
     * GET /portal/dashboard
     * Customer dashboard / home page.
     */
    public function dashboard(): View
    {
        $customer = $this->service->getByUser(auth()->user());

        return view('portal.dashboard', compact('customer'));
    }

    /**
     * GET /portal/profile
     * View own profile.
     */
    public function show(): View
    {
        $customer = $this->service->getByUser(auth()->user());

        return view('portal.profile.show', compact('customer'));
    }

    /**
     * GET /portal/profile/edit
     * Show edit profile form.
     */
    public function edit(): View
    {
        $customer = $this->service->getByUser(auth()->user());

        return view('portal.profile.edit', compact('customer'));
    }

    /**
     * PUT /portal/profile
     * Update own profile.
     */
    public function update(UpdatePortalProfileRequest $request): RedirectResponse
    {
        $customer = $this->service->getByUser(auth()->user());

        $this->service->updateProfile($customer, $request->validated());

        return redirect()
            ->route('portal.profile.show')
            ->with('success', 'Profile updated successfully.');
    }

    /**
     * GET /portal/profile/password
     * Show change password form.
     */
    public function passwordForm(): View
    {
        return view('portal.profile.password');
    }

    /**
     * PUT /portal/profile/password
     * Update password.
     */
    public function updatePassword(ChangePortalPasswordRequest $request): RedirectResponse
    {
        $changed = $this->service->changePassword(
            auth()->user(),
            $request->validated('current_password'),
            $request->validated('password')
        );

        if (! $changed) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        return redirect()
            ->route('portal.profile.show')
            ->with('success', 'Password changed successfully.');
    }
}
```

---

## Action Summary

| Controller | Method | Route | What it does |
|------------|--------|-------|--------------|
| RegisterController | `create` | GET /portal/register | Show register form |
| RegisterController | `store` | POST /portal/register | Create account + login |
| RegisterController | `loginForm` | GET /portal/login | Show login form |
| RegisterController | `login` | POST /portal/login | Authenticate customer |
| RegisterController | `logout` | POST /portal/logout | Logout customer |
| ProfileController | `dashboard` | GET /portal/dashboard | Welcome page |
| ProfileController | `show` | GET /portal/profile | View profile |
| ProfileController | `edit` | GET /portal/profile/edit | Show edit form |
| ProfileController | `update` | PUT /portal/profile | Save profile changes |
| ProfileController | `passwordForm` | GET /portal/profile/password | Show password form |
| ProfileController | `updatePassword` | PUT /portal/profile/password | Save new password |

---

## Rules
- No `$this->authorize()` needed — all portal routes are already behind `role:customer` middleware
- `getByUser(auth()->user())` is called at the start of every ProfileController action
- `login()` checks `hasRole('customer')` — prevents admin/staff from logging in via portal
- `store()` calls `Auth::login($customer->user)` after registration — logs in immediately
