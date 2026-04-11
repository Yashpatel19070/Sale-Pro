# Blade Components & Layouts Reference

## Structure — Breeze Default + Custom Components

```
resources/views/
├── layouts/
│   ├── app.blade.php          // authenticated layout
│   └── guest.blade.php        // guest/auth pages layout
├── components/
│   ├── layouts/
│   │   ├── app.blade.php      // x-layouts.app
│   │   └── guest.blade.php    // x-layouts.guest
│   ├── ui/
│   │   ├── button.blade.php   // x-ui.button
│   │   ├── input.blade.php    // x-ui.input
│   │   ├── badge.blade.php    // x-ui.badge
│   │   └── card.blade.php     // x-ui.card
│   └── forms/
│       ├── input.blade.php    // x-forms.input
│       ├── select.blade.php   // x-forms.select
│       └── error.blade.php    // x-forms.error
├── auth/                      // Breeze auth views
├── admin/
│   ├── users/
│   │   ├── index.blade.php
│   │   └── edit.blade.php
│   └── dashboard.blade.php
└── dashboard.blade.php
```

---

## Layouts

### Authenticated layout — `x-layouts.app`

```blade
{{-- resources/views/components/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100">

    {{-- Navigation --}}
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('dashboard') }}" class="font-semibold text-gray-800">
                        {{ config('app.name') }}
                    </a>
                </div>
                <div class="flex items-center gap-4">
                    @auth
                        <span class="text-sm text-gray-600">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="text-sm text-gray-600 hover:text-gray-900">Logout</button>
                        </form>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if(session('error') || $errors->any())
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">
                {{ session('error') ?? $errors->first() }}
            </div>
        </div>
    @endif

    {{-- Page content --}}
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot }}
    </main>

</body>
</html>
```

### Guest layout — `x-layouts.guest`

```blade
{{-- resources/views/components/layouts/guest.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center">
    <div class="w-full max-w-md">
        {{ $slot }}
    </div>
</body>
</html>
```

### Using layouts in views

```blade
{{-- Any authenticated page --}}
<x-layouts.app title="Orders">
    <h1 class="text-2xl font-bold">Orders</h1>
    {{-- page content --}}
</x-layouts.app>

{{-- Error pages --}}
<x-layouts.guest>
    <h1>404</h1>
</x-layouts.guest>
```

---

## Anonymous Components — Simple UI Primitives

No PHP class needed — just a Blade file. Best for simple, stateless UI.

### Button — `x-ui.button`

```blade
{{-- resources/views/components/ui/button.blade.php --}}
@props([
    'variant' => 'primary',
    'type'    => 'button',
])

@php
$classes = match($variant) {
    'primary'   => 'bg-blue-600 hover:bg-blue-700 text-white',
    'secondary' => 'bg-gray-200 hover:bg-gray-300 text-gray-800',
    'danger'    => 'bg-red-600 hover:bg-red-700 text-white',
    default     => 'bg-blue-600 hover:bg-blue-700 text-white',
};
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => "px-4 py-2 rounded font-medium text-sm {$classes}"]) }}
>
    {{ $slot }}
</button>
```

Usage:
```blade
<x-ui.button>Save</x-ui.button>
<x-ui.button variant="danger" type="submit">Delete</x-ui.button>
<x-ui.button variant="secondary" wire:click="cancel">Cancel</x-ui.button>
```

### Badge — `x-ui.badge`

```blade
{{-- resources/views/components/ui/badge.blade.php --}}
@props(['variant' => 'gray'])

@php
$classes = match($variant) {
    'green'  => 'bg-green-100 text-green-800',
    'red'    => 'bg-red-100 text-red-800',
    'yellow' => 'bg-yellow-100 text-yellow-800',
    'blue'   => 'bg-blue-100 text-blue-800',
    default  => 'bg-gray-100 text-gray-800',
};
@endphp

<span {{ $attributes->merge(['class' => "inline-flex px-2 py-1 text-xs font-medium rounded-full {$classes}"]) }}>
    {{ $slot }}
</span>
```

Usage:
```blade
<x-ui.badge variant="green">Active</x-ui.badge>
<x-ui.badge variant="red">Cancelled</x-ui.badge>

{{-- Dynamic based on enum --}}
<x-ui.badge variant="{{ $order->status === OrderStatus::Active ? 'green' : 'red' }}">
    {{ $order->status->value }}
</x-ui.badge>
```

### Card — `x-ui.card`

```blade
{{-- resources/views/components/ui/card.blade.php --}}
@props(['title' => null])

<div {{ $attributes->merge(['class' => 'bg-white rounded-lg shadow-sm border border-gray-200 p-6']) }}>
    @if($title)
        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ $title }}</h2>
    @endif
    {{ $slot }}
</div>
```

Usage:
```blade
<x-ui.card title="Order Details">
    <p>{{ $order->shipping_address }}</p>
</x-ui.card>

<x-ui.card class="mt-6">
    {{-- no title --}}
</x-ui.card>
```

---

## Form Components

### Input — `x-forms.input`

```blade
{{-- resources/views/components/forms/input.blade.php --}}
@props([
    'label'    => null,
    'name'     => '',
    'type'     => 'text',
    'required' => false,
])

<div>
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1">
            {{ $label }}
            @if($required) <span class="text-red-500">*</span> @endif
        </label>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ old($name) }}"
        {{ $attributes->merge(['class' => 'w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm' . ($errors->has($name) ? ' border-red-500' : '')]) }}
    >

    @error($name)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
```

Usage:
```blade
<x-forms.input name="email" label="Email" type="email" required />
<x-forms.input name="name" label="Full Name" required />
<x-forms.input name="notes" label="Notes" />
```

### Error — `x-forms.error`

```blade
{{-- resources/views/components/forms/error.blade.php --}}
@props(['name'])

@error($name)
    <p {{ $attributes->merge(['class' => 'mt-1 text-sm text-red-600']) }}>
        {{ $message }}
    </p>
@enderror
```

---

## Class-Based Components — When You Need PHP Logic

Use when the component needs data from the DB or complex logic before rendering:

```bash
php artisan make:component UserAvatar
```

```php
// app/View/Components/UserAvatar.php
<?php

namespace App\View\Components;

use App\Models\User;
use Illuminate\View\Component;
use Illuminate\View\View;

class UserAvatar extends Component
{
    public function __construct(
        public readonly User $user,
        public readonly string $size = 'md',
    ) {}

    public function render(): View
    {
        return view('components.user-avatar');
    }
}
```

```blade
{{-- resources/views/components/user-avatar.blade.php --}}
@php
$sizeClass = match($size) {
    'sm' => 'w-6 h-6 text-xs',
    'md' => 'w-8 h-8 text-sm',
    'lg' => 'w-12 h-12 text-base',
};
@endphp

<div class="rounded-full bg-gray-200 flex items-center justify-center {{ $sizeClass }}">
    {{ strtoupper(substr($user->name, 0, 1)) }}
</div>
```

Usage:
```blade
<x-user-avatar :user="$user" />
<x-user-avatar :user="auth()->user()" size="lg" />
```

---

## Rules

- **Anonymous component** — just a Blade file, no PHP class. Use for stateless UI primitives (button, badge, input, card).
- **Class-based component** — PHP class + Blade file. Use only when you need DB data or complex logic before rendering.
- **`$attributes->merge()`** — always use this on the root element so callers can add classes/attributes.
- **`@props()`** — declare all expected props with defaults. Never access `$attributes` for named props.
- **Flash messages** — handle in the layout, not in individual views.
- **`old($name)`** — always use on form inputs so values persist after validation failure.

---

## Quick Reference

```
x-layouts.app       → authenticated pages
x-layouts.guest     → error pages, auth pages

x-ui.button         → variant: primary / secondary / danger
x-ui.badge          → variant: green / red / yellow / blue / gray
x-ui.card           → optional title prop

x-forms.input       → name, label, type, required — handles old() and @error
x-forms.error       → standalone error message for a field

Anonymous component → Blade file only, no class, stateless UI
Class component     → PHP class + Blade, use when DB/logic needed before render
```
