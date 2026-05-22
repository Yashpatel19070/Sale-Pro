# Customer Portal — Profile Module — Views

Three views. All extend `portal.layouts.app` via `@extends` — no Blade components.
Tailwind CSS v3 only. No inline styles. No JavaScript frameworks.

---

## View Files

| File | Route |
|------|-------|
| `resources/views/portal/profile/show.blade.php` | GET /profile |
| `resources/views/portal/profile/edit.blade.php` | GET /profile/edit |
| `resources/views/portal/profile/password.blade.php` | GET /profile/password |

---

## 1. show.blade.php

**Purpose:** Read-only view of own profile.

```html
@extends('portal.layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">My Profile</h1>
        <div class="flex gap-3">
            <a href="{{ route('portal.profile.edit') }}"
               class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">
                Edit Profile
            </a>
            <a href="{{ route('portal.profile.password') }}"
               class="bg-gray-100 text-gray-700 px-4 py-2 rounded text-sm hover:bg-gray-200">
                Change Password
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500">Name</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $customer->name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Email</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $customer->email }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Phone</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $customer->phone }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Company</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $customer->company_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Status</dt>
                <dd class="mt-1">
                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                        bg-{{ $customer->status->color() }}-100
                        text-{{ $customer->status->color() }}-800">
                        {{ $customer->status->label() }}
                    </span>
                </dd>
            </div>
        </dl>
    </div>
@endsection
```

**Notes:**
- Email shown read-only — no edit link for email
- Status shown read-only — no change option
- No address fields — managed via customer-addresses module

---

## 2. edit.blade.php

**Purpose:** Edit own profile — name, phone, company_name only.

```html
@extends('portal.layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Edit Profile</h1>
        <a href="{{ route('portal.profile.show') }}" class="text-sm text-gray-500 hover:underline">
            Cancel
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('portal.profile.update') }}">
            @csrf
            @method('PUT')

            {{-- name --}}
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                <input type="text" name="name" value="{{ old('name', $customer->name) }}" required
                       class="w-full border rounded px-3 py-2 text-sm @error('name') border-red-500 @enderror">
                @error('name')
                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- phone --}}
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}" required
                       class="w-full border rounded px-3 py-2 text-sm @error('phone') border-red-500 @enderror">
                @error('phone')
                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- company_name (optional) --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Company Name <span class="text-gray-400">(optional)</span>
                </label>
                <input type="text" name="company_name" value="{{ old('company_name', $customer->company_name) }}"
                       class="w-full border rounded px-3 py-2 text-sm @error('company_name') border-red-500 @enderror">
                @error('company_name')
                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        class="bg-blue-600 text-white px-6 py-2 rounded text-sm font-medium hover:bg-blue-700">
                    Save Changes
                </button>
                <a href="{{ route('portal.profile.show') }}"
                   class="bg-gray-100 text-gray-700 px-6 py-2 rounded text-sm font-medium hover:bg-gray-200">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
```

**Rules:**
- NO `email` field — customer cannot change email
- NO `status` field — admin-only
- NO address fields — managed via customer-addresses module
- All inputs use `old('field', $customer->field)` for pre-fill
- `@csrf` + `@method('PUT')` required

---

## 3. password.blade.php

**Purpose:** Change own password — requires current password.

```html
@extends('portal.layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Change Password</h1>
        <a href="{{ route('portal.profile.show') }}" class="text-sm text-gray-500 hover:underline">
            Cancel
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 max-w-md">
        <form method="POST" action="{{ route('portal.profile.password.update') }}">
            @csrf
            @method('PUT')

            {{-- current password --}}
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                <input type="password" name="current_password" required
                       class="w-full border rounded px-3 py-2 text-sm @error('current_password') border-red-500 @enderror">
                @error('current_password')
                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- new password --}}
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                <input type="password" name="password" required
                       class="w-full border rounded px-3 py-2 text-sm @error('password') border-red-500 @enderror">
                @error('password')
                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- confirm new password --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                <input type="password" name="password_confirmation" required
                       class="w-full border rounded px-3 py-2 text-sm">
            </div>

            <div class="flex gap-3">
                <button type="submit"
                        class="bg-blue-600 text-white px-6 py-2 rounded text-sm font-medium hover:bg-blue-700">
                    Change Password
                </button>
                <a href="{{ route('portal.profile.show') }}"
                   class="bg-gray-100 text-gray-700 px-6 py-2 rounded text-sm font-medium hover:bg-gray-200">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
```

**Rules:**
- `current_password` error shown when `changePassword()` throws `ValidationException`
- Laravel auto-redirects back with `current_password` error — no manual check in controller
- `password_confirmation` matches `confirmed` rule in `ChangePortalPasswordRequest`
- No `old()` on password fields — never repopulate password inputs

---

## General Rules

- All views `@extends('portal.layouts.app')` — never `<x-portal-layout>` or admin layout
- All forms have `@csrf`
- PUT forms have `@method('PUT')`
- `old('field', $customer->field)` for pre-fill — never `$customer->field` alone
- Validation errors shown under each field via `@error('field')`
