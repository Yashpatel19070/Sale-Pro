# Portal Foundation — Views

All guest views extend `portal.layouts.guest`.
All authenticated views extend `portal.layouts.app`.
No Blade components — pure `@extends` + `@section('content')`.

---

## 1. register.blade.php

**File:** `resources/views/portal/auth/register.blade.php`

```html
@extends('portal.layouts.guest')

@section('content')
    <h2 class="text-xl font-bold text-gray-800 mb-6">Create Account</h2>

    <form method="POST" action="{{ route('portal.register.store') }}">
        @csrf

        {{-- name --}}
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full border rounded px-3 py-2 text-sm @error('name') border-red-500 @enderror">
            @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- email --}}
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   class="w-full border rounded px-3 py-2 text-sm @error('email') border-red-500 @enderror">
            @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- password --}}
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input type="password" name="password" required
                   class="w-full border rounded px-3 py-2 text-sm @error('password') border-red-500 @enderror">
            @error('password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- password confirmation --}}
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input type="password" name="password_confirmation" required
                   class="w-full border rounded px-3 py-2 text-sm">
        </div>

        {{-- phone --}}
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
            <input type="text" name="phone" value="{{ old('phone') }}" required
                   class="w-full border rounded px-3 py-2 text-sm @error('phone') border-red-500 @enderror">
            @error('phone') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- company_name (optional) --}}
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Company Name <span class="text-gray-400">(optional)</span>
            </label>
            <input type="text" name="company_name" value="{{ old('company_name') }}"
                   class="w-full border rounded px-3 py-2 text-sm">
        </div>

        {{-- address --}}
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
            <input type="text" name="address" value="{{ old('address') }}" required
                   class="w-full border rounded px-3 py-2 text-sm @error('address') border-red-500 @enderror">
            @error('address') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- city + state --}}
        <div class="mb-4 grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                <input type="text" name="city" value="{{ old('city') }}" required
                       class="w-full border rounded px-3 py-2 text-sm @error('city') border-red-500 @enderror">
                @error('city') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                <input type="text" name="state" value="{{ old('state') }}" required
                       class="w-full border rounded px-3 py-2 text-sm @error('state') border-red-500 @enderror">
                @error('state') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- postal_code + country --}}
        <div class="mb-6 grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code</label>
                <input type="text" name="postal_code" value="{{ old('postal_code') }}" required
                       class="w-full border rounded px-3 py-2 text-sm @error('postal_code') border-red-500 @enderror">
                @error('postal_code') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                <input type="text" name="country" value="{{ old('country') }}" required
                       class="w-full border rounded px-3 py-2 text-sm @error('country') border-red-500 @enderror">
                @error('country') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded font-medium hover:bg-blue-700">
            Create Account
        </button>
    </form>

    <p class="text-sm text-center text-gray-500 mt-4">
        Already have an account?
        <a href="{{ route('portal.login') }}" class="text-blue-600 hover:underline">Login</a>
    </p>
@endsection
```

---

## 2. login.blade.php

**File:** `resources/views/portal/auth/login.blade.php`

```html
@extends('portal.layouts.guest')

@section('content')
    <h2 class="text-xl font-bold text-gray-800 mb-6">Login</h2>

    @if(session('status'))
        <div class="bg-blue-100 text-blue-800 px-4 py-3 rounded text-sm mb-4">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('portal.login.store') }}">
        @csrf

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full border rounded px-3 py-2 text-sm @error('email') border-red-500 @enderror">
            @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input type="password" name="password" required
                   class="w-full border rounded px-3 py-2 text-sm">
        </div>

        <div class="mb-6 flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="remember"> Remember me
            </label>
            <a href="{{ route('portal.password.request') }}" class="text-sm text-blue-600 hover:underline">
                Forgot password?
            </a>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded font-medium hover:bg-blue-700">
            Login
        </button>
    </form>

    <p class="text-sm text-center text-gray-500 mt-4">
        Don't have an account?
        <a href="{{ route('portal.register') }}" class="text-blue-600 hover:underline">Register</a>
    </p>
@endsection
```

---

## 3. forgot-password.blade.php

**File:** `resources/views/portal/auth/forgot-password.blade.php`

```html
@extends('portal.layouts.guest')

@section('content')
    <h2 class="text-xl font-bold text-gray-800 mb-2">Forgot Password</h2>
    <p class="text-sm text-gray-500 mb-6">Enter your email and we'll send a reset link.</p>

    @if(session('status'))
        <div class="bg-green-100 text-green-800 px-4 py-3 rounded text-sm mb-4">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('portal.password.email') }}">
        @csrf

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full border rounded px-3 py-2 text-sm @error('email') border-red-500 @enderror">
            @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded font-medium hover:bg-blue-700">
            Send Reset Link
        </button>
    </form>

    <p class="text-sm text-center text-gray-500 mt-4">
        <a href="{{ route('portal.login') }}" class="text-blue-600 hover:underline">Back to login</a>
    </p>
@endsection
```

---

## 4. reset-password.blade.php

**File:** `resources/views/portal/auth/reset-password.blade.php`

```html
@extends('portal.layouts.guest')

@section('content')
    <h2 class="text-xl font-bold text-gray-800 mb-6">Reset Password</h2>

    <form method="POST" action="{{ route('portal.password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email', $email) }}" required
                   class="w-full border rounded px-3 py-2 text-sm @error('email') border-red-500 @enderror">
            @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
            <input type="password" name="password" required
                   class="w-full border rounded px-3 py-2 text-sm @error('password') border-red-500 @enderror">
            @error('password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input type="password" name="password_confirmation" required
                   class="w-full border rounded px-3 py-2 text-sm">
        </div>

        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded font-medium hover:bg-blue-700">
            Reset Password
        </button>
    </form>
@endsection
```

---

## 5. verify-email.blade.php

**File:** `resources/views/portal/auth/verify-email.blade.php`

```html
@extends('portal.layouts.guest')

@section('content')
    <h2 class="text-xl font-bold text-gray-800 mb-2">Verify Your Email</h2>
    <p class="text-sm text-gray-500 mb-6">
        We sent a verification link to your email. Click the link to activate your account.
    </p>

    @if(session('status') === 'verification-link-sent')
        <div class="bg-green-100 text-green-800 px-4 py-3 rounded text-sm mb-4">
            A new verification link has been sent.
        </div>
    @endif

    <form method="POST" action="{{ route('portal.verification.send') }}">
        @csrf
        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded font-medium hover:bg-blue-700">
            Resend Verification Email
        </button>
    </form>

    <form method="POST" action="{{ route('portal.logout') }}" class="mt-3">
        @csrf
        <button type="submit" class="w-full text-sm text-gray-500 hover:underline">
            Logout
        </button>
    </form>
@endsection
```

---

## 6. dashboard.blade.php

**File:** `resources/views/portal/dashboard.blade.php`

```html
@extends('portal.layouts.app')

@section('content')
    <h1 class="text-2xl font-bold text-gray-800 mb-2">
        Welcome, {{ auth()->user()->name }}
    </h1>
    <p class="text-gray-500 text-sm">You are logged in to your account.</p>

    {{-- Future module widgets plug in here --}}
@endsection
```
