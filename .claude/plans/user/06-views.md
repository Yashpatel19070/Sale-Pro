# User Module — Views

## View Tree

```
resources/views/users/
├── index.blade.php       -- paginated table + filters
├── create.blade.php      -- create form
├── edit.blade.php        -- edit form (admin)
├── show.blade.php        -- user detail card
└── _form.blade.php       -- shared form partial (admin fields)

resources/views/profile/
├── edit.blade.php        -- self-service profile (Breeze, updated)
└── partials/
    ├── update-profile-information-form.blade.php
    └── update-password-form.blade.php
```

---

## index.blade.php — Key Sections

```blade
@extends('layouts.app')
@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Users</h1>
        @can('create', App\Models\User::class)
            <a href="{{ route('users.create') }}" class="btn-primary">+ New User</a>
        @endcan
    </div>

    @include('partials.flash')

    {{-- Filter bar --}}
    <form method="GET" class="flex flex-wrap gap-3 mb-4">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Name, email, or employee ID…" class="input-field w-72" />

        <select name="status" class="input-field w-40">
            <option value="">All status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>

        <select name="department_id" class="input-field w-48">
            <option value="">All departments</option>
            @foreach ($departments as $dept)
                <option value="{{ $dept->id }}" @selected(request('department_id') == $dept->id)>
                    {{ $dept->name }}
                </option>
            @endforeach
        </select>

        <select name="role" class="input-field w-36">
            <option value="">All roles</option>
            @foreach ($roles as $role)
                <option value="{{ $role }}" @selected(request('role') === $role)>
                    {{ ucfirst($role) }}
                </option>
            @endforeach
        </select>

        <button type="submit" class="btn-secondary">Filter</button>
        <a href="{{ route('users.index') }}" class="btn-ghost">Clear</a>
    </form>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th>Department</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Hired</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($users as $user)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}"
                                 class="w-8 h-8 rounded-full object-cover" />
                            <div>
                                <a href="{{ route('users.show', $user) }}"
                                   class="text-indigo-600 hover:underline font-medium text-sm">
                                    {{ $user->name }}
                                </a>
                                <div class="text-xs text-gray-400">{{ $user->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                        {{ $user->department?->name ?? '—' }}
                    </td>
                    <td class="px-4 py-3">
                        @foreach ($user->roles as $role)
                            <span class="badge-gray text-xs">{{ $role->name }}</span>
                        @endforeach
                    </td>
                    <td class="px-4 py-3">
                        <span class="{{ $user->status->badgeClass() }}">
                            {{ $user->status->label() }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500">
                        {{ $user->hired_at?->format('M Y') ?? '—' }}
                    </td>
                    <td class="px-4 py-3 flex gap-2">
                        @can('update', $user)
                            <a href="{{ route('users.edit', $user) }}" class="btn-xs-secondary">Edit</a>
                        @endcan
                        @can('delete', $user)
                            <form method="POST" action="{{ route('users.destroy', $user) }}"
                                  onsubmit="return confirm('Delete {{ $user->name }}?')">
                                @csrf @method('DELETE')
                                <button class="btn-xs-danger">Delete</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-10 text-gray-400">No users found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</div>
@endsection
```

---

## _form.blade.php — Shared Admin Form Partial

Fields grouped into sections:

### Personal Information
- `name` (text, required)
- `email` (email, required)
- `password` + `password_confirmation` (only on create — omit on edit)
- `phone` (text)
- `avatar` (file input — shows current avatar preview on edit)

### Job Details
- `job_title` (text)
- `employee_id` (text)
- `department_id` (select — loaded from `$departments`)
- `hired_at` (date)
- `timezone` (select — PHP timezone list)

### Account
- `role` (select — loaded from `$roles`, single selection)
- `status` (select — from `UserStatus::cases()`)

### Avatar Preview (edit only)

```blade
@if(isset($user) && $user->avatar)
    <div class="mb-2">
        <img src="{{ $user->avatar_url }}" class="w-16 h-16 rounded-full object-cover" />
        <p class="text-xs text-gray-500 mt-1">Current avatar — upload new to replace.</p>
    </div>
@endif
<input type="file" name="avatar" accept="image/*" class="input-field" />
```

---

## show.blade.php — Key Sections

```blade
{{-- Profile card --}}
<div class="flex items-start gap-6">
    <img src="{{ $user->avatar_url }}" class="w-20 h-20 rounded-full object-cover shadow" />
    <div>
        <h2 class="text-xl font-bold">{{ $user->name }}</h2>
        <p class="text-gray-500 text-sm">{{ $user->job_title ?? 'No title' }}</p>
        <p class="text-gray-400 text-sm">{{ $user->email }}</p>
        <div class="flex gap-2 mt-2">
            <span class="{{ $user->status->badgeClass() }}">{{ $user->status->label() }}</span>
            @foreach($user->roles as $role)
                <span class="badge-gray">{{ $role->name }}</span>
            @endforeach
        </div>
    </div>
</div>

{{-- Details grid --}}
<dl class="grid grid-cols-2 gap-4 mt-6">
    <div><dt class="text-xs text-gray-500">Department</dt>
         <dd>{{ $user->department?->name ?? '—' }}</dd></div>
    <div><dt class="text-xs text-gray-500">Employee ID</dt>
         <dd>{{ $user->employee_id ?? '—' }}</dd></div>
    <div><dt class="text-xs text-gray-500">Phone</dt>
         <dd>{{ $user->phone ?? '—' }}</dd></div>
    <div><dt class="text-xs text-gray-500">Hired</dt>
         <dd>{{ $user->hired_at?->format('M d, Y') ?? '—' }}</dd></div>
    <div><dt class="text-xs text-gray-500">Timezone</dt>
         <dd>{{ $user->timezone }}</dd></div>
    <div><dt class="text-xs text-gray-500">Created by</dt>
         <dd>{{ $user->createdBy?->name ?? 'System' }}</dd></div>
</dl>

{{-- Admin actions --}}
@can('update', $user)
    <a href="{{ route('users.edit', $user) }}" class="btn-primary">Edit</a>
@endcan
@can('resetPassword', $user)
    <form method="POST" action="{{ route('users.send-password-reset', $user) }}">
        @csrf <button class="btn-secondary">Send Password Reset</button>
    </form>
@endcan
@can('changeStatus', $user)
    <form method="POST" action="{{ route('users.change-status', $user) }}">
        @csrf
        <select name="status" onchange="this.form.submit()" class="input-field">
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected($user->status === $status)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
    </form>
@endcan
```
