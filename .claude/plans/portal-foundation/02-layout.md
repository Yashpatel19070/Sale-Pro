# Portal Foundation — Layouts

Two layout files. All portal views use `@extends`. No Blade components.

---

## 1. Authenticated Layout

**File:** `resources/views/portal/layouts/app.blade.php`

Used by: dashboard, and all future portal modules (profile, orders, etc.)

```html
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — My Account</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- Navigation -->
    <nav class="bg-white border-b shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-3 flex justify-between items-center">
            <a href="{{ route('portal.dashboard') }}" class="text-lg font-bold text-gray-800">
                {{ config('app.name') }}
            </a>
            <div class="flex items-center gap-6 text-sm text-gray-600">
                <a href="{{ route('portal.dashboard') }}" class="hover:text-gray-900">Home</a>
                {{-- Future module nav links added here --}}
                <form method="POST" action="{{ route('portal.logout') }}">
                    @csrf
                    <button type="submit" class="hover:text-gray-900">Logout</button>
                </form>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="max-w-5xl mx-auto px-4 mt-4">
            <div class="bg-green-100 text-green-800 px-4 py-3 rounded text-sm">
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if(session('status'))
        <div class="max-w-5xl mx-auto px-4 mt-4">
            <div class="bg-blue-100 text-blue-800 px-4 py-3 rounded text-sm">
                {{ session('status') }}
            </div>
        </div>
    @endif

    <!-- Page Content -->
    <main class="max-w-5xl mx-auto px-4 py-8">
        @yield('content')
    </main>

</body>
</html>
```

---

## 2. Guest Layout

**File:** `resources/views/portal/layouts/guest.blade.php`

Used by: login, register, forgot password, reset password, verify email.

```html
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

    <div class="w-full max-w-md">

        <!-- App Name -->
        <div class="text-center mb-8">
            <a href="/" class="text-2xl font-bold text-gray-800">
                {{ config('app.name') }}
            </a>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-lg shadow px-8 py-8">
            @yield('content')
        </div>

    </div>

</body>
</html>
```

---

## How Every Portal View Uses These Layouts

**Authenticated pages** (dashboard, profile, orders, etc.):
```html
@extends('portal.layouts.app')

@section('content')
    {{-- page content here --}}
@endsection
```

**Guest pages** (login, register, forgot password, etc.):
```html
@extends('portal.layouts.guest')

@section('content')
    {{-- page content here --}}
@endsection
```

---

## Notes
- No Blade components — just `@extends` and `@section('content')`
- All portal layout files live in `resources/views/portal/layouts/` only
- Admin layouts are completely separate — never mix them
