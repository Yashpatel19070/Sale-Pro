# Admin Views Reference

## Structure

```
resources/views/
├── components/
│   └── layouts/
│       └── admin.blade.php        // x-layouts.admin — admin shell
├── admin/
│   ├── dashboard.blade.php        // /admin
│   ├── users/
│   │   ├── index.blade.php        // list all users
│   │   ├── create.blade.php       // new user form
│   │   └── edit.blade.php         // edit user form
│   ├── roles/
│   │   ├── index.blade.php
│   │   └── edit.blade.php
│   └── settings/
│       └── index.blade.php
```

**Rule:** every admin page uses `<x-layouts.admin>`. Never `<x-layouts.app>` in admin views.

---

## Admin Layout — `x-layouts.admin`

Left sidebar + top bar. Permission-gated nav items.

```blade
{{-- resources/views/components/layouts/admin.blade.php --}}
@props(['title' => 'Admin'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ config('app.name') }} Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100">

    <div class="flex h-screen overflow-hidden">

        {{-- Sidebar --}}
        <aside class="w-64 bg-gray-900 text-white flex flex-col flex-shrink-0">

            {{-- Logo --}}
            <div class="h-16 flex items-center px-6 border-b border-gray-700">
                <a href="{{ route('admin.dashboard') }}" class="font-bold text-lg">
                    {{ config('app.name') }}
                </a>
            </div>

            {{-- Nav --}}
            <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">

                <x-admin.nav-item route="admin.dashboard" icon="grid">
                    Dashboard
                </x-admin.nav-item>

                @can('users.view')
                <x-admin.nav-item route="admin.users.index" icon="users">
                    Users
                </x-admin.nav-item>
                @endcan

                @can('roles.view')
                <x-admin.nav-item route="admin.roles.index" icon="shield">
                    Roles
                </x-admin.nav-item>
                @endcan

                @can('settings.view')
                <x-admin.nav-item route="admin.settings.index" icon="cog">
                    Settings
                </x-admin.nav-item>
                @endcan

            </nav>

            {{-- User info --}}
            <div class="px-4 py-4 border-t border-gray-700">
                <p class="text-sm text-gray-400">{{ auth()->user()->name }}</p>
                <p class="text-xs text-gray-500">{{ auth()->user()->getRoleNames()->first() }}</p>
            </div>

        </aside>

        {{-- Main --}}
        <div class="flex-1 flex flex-col overflow-hidden">

            {{-- Top bar --}}
            <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6">
                <h1 class="text-lg font-semibold text-gray-800">{{ $title }}</h1>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-sm text-gray-500 hover:text-gray-800">Logout</button>
                </form>
            </header>

            {{-- Flash messages --}}
            @if(session('success'))
                <div class="mx-6 mt-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error') || $errors->any())
                <div class="mx-6 mt-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">
                    {{ session('error') ?? $errors->first() }}
                </div>
            @endif

            {{-- Page content --}}
            <main class="flex-1 overflow-y-auto p-6">
                {{ $slot }}
            </main>

        </div>
    </div>

</body>
</html>
```

---

## Nav Item Component — `x-admin.nav-item`

Highlights active route automatically:

```blade
{{-- resources/views/components/admin/nav-item.blade.php --}}
@props(['route', 'icon' => null])

@php
$active = request()->routeIs($route . '*');
$classes = $active
    ? 'bg-gray-800 text-white'
    : 'text-gray-400 hover:bg-gray-800 hover:text-white';
@endphp

<a
    href="{{ route($route) }}"
    {{ $attributes->merge(['class' => "flex items-center gap-3 px-3 py-2 rounded text-sm font-medium {$classes}"]) }}
>
    {{ $slot }}
</a>
```

Usage in layout:
```blade
<x-admin.nav-item route="admin.users.index">Users</x-admin.nav-item>
```

---

## Page Structure — Every Admin Page

Every admin page follows this pattern:

```blade
<x-layouts.admin title="Users">

    {{-- Page header --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-gray-800">Users</h2>
        @can('users.create')
            <a href="{{ route('admin.users.create') }}">
                <x-ui.button>Add User</x-ui.button>
            </a>
        @endcan
    </div>

    {{-- Page content --}}
    {{ $slot ?? '' }}

</x-layouts.admin>
```

---

## Index Page — Table with Actions

```blade
{{-- resources/views/admin/users/index.blade.php --}}
<x-layouts.admin title="Users">

    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-gray-800">Users</h2>
        @can('users.create')
            <a href="{{ route('admin.users.create') }}">
                <x-ui.button>Add User</x-ui.button>
            </a>
        @endcan
    </div>

    <x-ui.card>
        <table class="w-full text-sm text-left">
            <thead class="text-xs text-gray-500 uppercase border-b border-gray-200">
                <tr>
                    <th class="pb-3 pr-4">Name</th>
                    <th class="pb-3 pr-4">Email</th>
                    <th class="pb-3 pr-4">Role</th>
                    <th class="pb-3 pr-4">Status</th>
                    <th class="pb-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($users as $user)
                    <tr>
                        <td class="py-3 pr-4 font-medium text-gray-900">{{ $user->name }}</td>
                        <td class="py-3 pr-4 text-gray-500">{{ $user->email }}</td>
                        <td class="py-3 pr-4">
                            <x-ui.badge>{{ $user->getRoleNames()->first() ?? '—' }}</x-ui.badge>
                        </td>
                        <td class="py-3 pr-4">
                            <x-ui.badge variant="{{ $user->is_active ? 'green' : 'red' }}">
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </x-ui.badge>
                        </td>
                        <td class="py-3 text-right space-x-2">
                            @can('users.edit')
                                <a href="{{ route('admin.users.edit', $user) }}"
                                   class="text-blue-600 hover:underline text-sm">Edit</a>
                            @endcan
                            @can('users.delete')
                                <form method="POST"
                                      action="{{ route('admin.users.destroy', $user) }}"
                                      class="inline"
                                      x-data
                                      @submit.prevent="if(confirm('Delete this user?')) $el.submit()">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:underline text-sm">Delete</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-gray-400">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pagination --}}
        @if($users->hasPages())
            <div class="mt-4 border-t border-gray-100 pt-4">
                {{ $users->links() }}
            </div>
        @endif
    </x-ui.card>

</x-layouts.admin>
```

---

## Create / Edit Form Page

```blade
{{-- resources/views/admin/users/create.blade.php --}}
<x-layouts.admin title="Add User">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.users.index') }}" class="text-gray-400 hover:text-gray-600">← Back</a>
        <h2 class="text-xl font-semibold text-gray-800">Add User</h2>
    </div>

    <x-ui.card class="max-w-xl">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf

            <div class="space-y-4">
                <x-forms.input name="name"  label="Full Name" required />
                <x-forms.input name="email" label="Email" type="email" required />
                <x-forms.input name="password" label="Password" type="password" required />

                {{-- Role select --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Role <span class="text-red-500">*</span>
                    </label>
                    <select name="role"
                            class="w-full rounded border-gray-300 shadow-sm text-sm
                                   focus:border-blue-500 focus:ring-blue-500
                                   {{ $errors->has('role') ? 'border-red-500' : '' }}">
                        <option value="">Select role</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}"
                                    {{ old('role') === $role->name ? 'selected' : '' }}>
                                {{ ucfirst($role->name) }}
                            </option>
                        @endforeach
                    </select>
                    <x-forms.error name="role" />
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-ui.button type="submit">Save User</x-ui.button>
                <a href="{{ route('admin.users.index') }}">
                    <x-ui.button variant="secondary">Cancel</x-ui.button>
                </a>
            </div>
        </form>
    </x-ui.card>

</x-layouts.admin>
```

Edit page — same as create but with prefilled values:
```blade
{{-- resources/views/admin/users/edit.blade.php --}}
<x-layouts.admin title="Edit User">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.users.index') }}" class="text-gray-400 hover:text-gray-600">← Back</a>
        <h2 class="text-xl font-semibold text-gray-800">Edit User</h2>
    </div>

    <x-ui.card class="max-w-xl">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PATCH')

            <div class="space-y-4">
                <x-forms.input name="name"  label="Full Name"
                               :value="old('name', $user->name)" required />
                <x-forms.input name="email" label="Email" type="email"
                               :value="old('email', $user->email)" required />

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select name="role" class="w-full rounded border-gray-300 shadow-sm text-sm">
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}"
                                {{ old('role', $user->getRoleNames()->first()) === $role->name ? 'selected' : '' }}>
                                {{ ucfirst($role->name) }}
                            </option>
                        @endforeach
                    </select>
                    <x-forms.error name="role" />
                </div>

                {{-- Active toggle --}}
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="is_active" id="is_active" value="1"
                           {{ old('is_active', $user->is_active) ? 'checked' : '' }}
                           class="rounded border-gray-300">
                    <label for="is_active" class="text-sm text-gray-700">Active</label>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-ui.button type="submit">Update User</x-ui.button>
                <a href="{{ route('admin.users.index') }}">
                    <x-ui.button variant="secondary">Cancel</x-ui.button>
                </a>
            </div>
        </form>
    </x-ui.card>

</x-layouts.admin>
```

---

## Admin Dashboard

```blade
{{-- resources/views/admin/dashboard.blade.php --}}
<x-layouts.admin title="Dashboard">

    {{-- Stats row --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <x-ui.card>
            <p class="text-sm text-gray-500">Total Users</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['users'] }}</p>
        </x-ui.card>
        <x-ui.card>
            <p class="text-sm text-gray-500">Total Orders</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['orders'] }}</p>
        </x-ui.card>
    </div>

    {{-- Recent activity --}}
    <x-ui.card title="Recent Users">
        {{-- table content --}}
    </x-ui.card>

</x-layouts.admin>
```

---

## Rules

- Every admin page uses `<x-layouts.admin>` — never `<x-layouts.app>`
- Nav items wrapped in `@can` — users only see what they have permission for
- Delete actions use Alpine.js `confirm()` — no native browser `confirm()`
- Always `@csrf` + `@method('DELETE'/'PATCH')` on forms
- Always `old()` on form inputs — values persist after validation failure
- Roles dropdown populated from DB — never hardcoded in Blade
- Pagination always via `$model->links()` — never manual page links
- Back link on create/edit pages — always route to index

---

## Quick Reference

```
x-layouts.admin     → all admin pages
x-admin.nav-item    → sidebar nav, auto-highlights active route

Admin page pattern:
  1. x-layouts.admin title="..."
  2. Page header with title + @can action button
  3. x-ui.card wrapping the content
  4. Table (index) or form (create/edit) inside card
  5. Pagination below table if $model->hasPages()

Forms:
  create → POST to admin.resource.store
  edit   → PATCH to admin.resource.update  (@method PATCH)
  delete → DELETE to admin.resource.destroy (@method DELETE + Alpine confirm)
```
