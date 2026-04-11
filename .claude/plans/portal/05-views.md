# Customer Portal — Views

All portal views use a dedicated portal layout — NOT the admin app layout.
Frontend design — clean, customer-facing. Tailwind CSS v3 only.

---

## View Files
| File | Route |
|------|-------|
| `resources/views/portal/layouts/app.blade.php` | Portal layout |
| `resources/views/portal/auth/register.blade.php` | GET /portal/register |
| `resources/views/portal/auth/login.blade.php` | GET /portal/login |
| `resources/views/portal/dashboard.blade.php` | GET /portal/dashboard |
| `resources/views/portal/profile/show.blade.php` | GET /portal/profile |
| `resources/views/portal/profile/edit.blade.php` | GET /portal/profile/edit |
| `resources/views/portal/profile/password.blade.php` | GET /portal/profile/password |

---

## 1. Portal Layout — layouts/app.blade.php

**Purpose:** Wrapper for all authenticated portal pages.

**Structure:**
```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — My Account</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- Nav bar -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-4xl mx-auto px-4 py-3 flex justify-between items-center">
            <a href="{{ route('portal.dashboard') }}" class="font-bold text-lg">My Account</a>
            <div class="flex gap-4 text-sm">
                <a href="{{ route('portal.profile.show') }}">Profile</a>
                <form method="POST" action="{{ route('portal.logout') }}">
                    @csrf
                    <button type="submit">Logout</button>
                </form>
            </div>
        </div>
    </nav>

    <!-- Flash message -->
    @if(session('success'))
        <div class="max-w-4xl mx-auto px-4 mt-4">
            <div class="bg-green-100 text-green-800 px-4 py-3 rounded">
                {{ session('success') }}
            </div>
        </div>
    @endif

    <!-- Page content -->
    <main class="max-w-4xl mx-auto px-4 py-8">
        {{ $slot }}
    </main>

</body>
</html>
```

---

## 2. Register — auth/register.blade.php

**Purpose:** Customer self-registration form.
**Does NOT extend portal layout** — uses a minimal guest layout (no nav).

**Structure:**
```html
<!-- Guest page — centered card, no nav -->
<form method="POST" action="{{ route('portal.register.store') }}">
    @csrf
    <!-- Fields: name, email, password, password_confirmation,
                 phone, company_name, address, city, state, postal_code, country -->
    <!-- All required except company_name -->
    <!-- Show @error() below each field -->
    <!-- Submit: "Create Account" button -->
    <!-- Link: "Already have an account? Login" → route('portal.login') -->
</form>
```

**Field inputs:**
| Field | Input Type | Required |
|-------|-----------|----------|
| name | text | Yes |
| email | email | Yes |
| password | password | Yes |
| password_confirmation | password | Yes |
| phone | text | Yes |
| company_name | text | No |
| address | text | Yes |
| city | text | Yes |
| state | text | Yes |
| postal_code | text | Yes |
| country | text | Yes |

**All inputs use `old()` to repopulate after errors (except password fields).**

---

## 3. Login — auth/login.blade.php

**Purpose:** Portal login page.
**Does NOT extend portal layout** — minimal guest layout.

**Structure:**
```html
<form method="POST" action="{{ route('portal.login.store') }}">
    @csrf
    <!-- email input -->
    <!-- password input -->
    <!-- remember me checkbox -->
    <!-- @error('email') show error @enderror -->
    <!-- Submit: "Login" button -->
    <!-- Link: "Don't have an account? Register" → route('portal.register') -->
</form>
```

---

## 4. Dashboard — dashboard.blade.php

**Purpose:** Welcome page after login.
**Extends:** `<x-portal-layout>` (portal app layout)

**Structure:**
```html
<x-portal-layout>
    <h1>Welcome, {{ $customer->name }}</h1>

    <!-- Summary card: name, email, phone, status badge -->
    <div class="bg-white rounded shadow p-6 mt-4">
        <p><strong>Email:</strong> {{ $customer->email }}</p>
        <p><strong>Phone:</strong> {{ $customer->phone }}</p>
        <p><strong>Status:</strong>
            <span class="...badge using $customer->status->color()...">
                {{ $customer->status->label() }}
            </span>
        </p>
    </div>

    <!-- Quick links -->
    <div class="mt-6 flex gap-4">
        <a href="{{ route('portal.profile.show') }}">View Profile</a>
        <a href="{{ route('portal.profile.edit') }}">Edit Profile</a>
        <a href="{{ route('portal.profile.password') }}">Change Password</a>
    </div>
</x-portal-layout>
```

---

## 5. View Profile — profile/show.blade.php

**Purpose:** Read-only view of the customer's own profile.
**Extends:** `<x-portal-layout>`

**Displays all fields:**
| Label | Value |
|-------|-------|
| Name | `{{ $customer->name }}` |
| Email | `{{ $customer->email }}` |
| Phone | `{{ $customer->phone }}` |
| Company | `{{ $customer->company_name ?? '—' }}` |
| Address | `{{ $customer->address }}` |
| City | `{{ $customer->city }}` |
| State | `{{ $customer->state }}` |
| Postal Code | `{{ $customer->postal_code }}` |
| Country | `{{ $customer->country }}` |
| Status | Badge |

**Buttons:**
- "Edit Profile" → `route('portal.profile.edit')`
- "Change Password" → `route('portal.profile.password')`

---

## 6. Edit Profile — profile/edit.blade.php

**Purpose:** Form to update own profile.
**Extends:** `<x-portal-layout>`

```html
<form method="POST" action="{{ route('portal.profile.update') }}">
    @csrf
    @method('PUT')
    <!-- Fields: name, phone, company_name, address, city, state, postal_code, country -->
    <!-- NO email field — customer cannot change email -->
    <!-- NO status field — customer cannot change status -->
    <!-- All use old('field', $customer->field) for pre-fill -->
    <!-- Submit: "Save Changes" button -->
    <!-- Cancel: link back to route('portal.profile.show') -->
</form>
```

**Pre-fill pattern:**
```html
value="{{ old('name', $customer->name) }}"
```

---

## 7. Change Password — profile/password.blade.php

**Purpose:** Form to change password.
**Extends:** `<x-portal-layout>`

```html
<form method="POST" action="{{ route('portal.profile.password.update') }}">
    @csrf
    @method('PUT')

    <!-- current_password: password input, required -->
    @error('current_password')
        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
    @enderror

    <!-- password: password input, required (new password) -->
    @error('password')
        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
    @enderror

    <!-- password_confirmation: password input, required -->

    <!-- Submit: "Change Password" button -->
    <!-- Cancel: link back to route('portal.profile.show') -->
</form>
```

---

## Portal Layout Component Registration

Register `x-portal-layout` as a Blade component.
Create: `app/View/Components/PortalLayout.php`

```php
<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PortalLayout extends Component
{
    public function render(): View
    {
        return view('portal.layouts.app');
    }
}
```

Or use anonymous component by placing the layout at:
`resources/views/components/portal-layout.blade.php` (copy of layouts/app.blade.php using `$slot`).

---

## Notes
- Auth pages (login, register) do NOT use portal layout — they are full-page centered forms
- Authenticated pages (dashboard, profile) use `<x-portal-layout>`
- Customer cannot see or edit: `email`, `status` — these are admin-only fields
- Flash messages are handled by the portal layout automatically
