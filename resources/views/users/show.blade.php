<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('users.index') }}" class="text-sm text-gray-400 hover:text-gray-600">Users</a>
                <span class="text-gray-300">/</span>
                <span class="text-sm font-medium text-gray-700">{{ $user->name }}</span>
            </div>
            <div class="flex items-center gap-2">
                @can('update', $user)
                    <a href="{{ route('users.edit', $user) }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Edit
                    </a>
                @endcan
                @can('resetPassword', $user)
                    <form method="POST" action="{{ route('users.send-password-reset', $user) }}">
                        @csrf
                        <button class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Reset Password
                        </button>
                    </form>
                @endcan
                @can('delete', $user)
                    <form method="POST" action="{{ route('users.destroy', $user) }}"
                          onsubmit="return confirm('Delete {{ addslashes($user->name) }}?')">
                        @csrf @method('DELETE')
                        <button class="inline-flex items-center rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">
                            Delete
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                {{-- ── Left column ─────────────────────────────────────── --}}
                <div class="space-y-5 lg:col-span-1">

                    {{-- Profile card --}}
                    <div class="overflow-hidden rounded-xl bg-white shadow">
                        <div class="bg-gradient-to-r from-indigo-500 to-purple-600 h-20"></div>
                        <div class="px-5 pb-5">
                            <div class="-mt-10 mb-3">
                                <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}"
                                     class="w-20 h-20 rounded-full ring-4 ring-white object-cover shadow" />
                            </div>
                            <h3 class="text-lg font-bold text-gray-900">{{ $user->name }}</h3>
                            <p class="text-sm text-gray-500">{{ $user->job_title ?? 'No title set' }}</p>
                            <p class="text-sm text-gray-400 mt-0.5">{{ $user->email }}</p>

                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $user->status->badgeClass() }}">
                                    {{ $user->status->label() }}
                                </span>
                                @foreach ($user->roles as $role)
                                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-800 capitalize">
                                        {{ $role->name }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Change status --}}
                    @can('changeStatus', $user)
                        <div class="overflow-hidden rounded-xl bg-white shadow">
                            <div class="border-b border-gray-100 px-5 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Change Status</p>
                            </div>
                            <div class="px-5 py-4">
                                <form method="POST" action="{{ route('users.change-status', $user) }}" class="flex items-center gap-3">
                                    @csrf
                                    <select name="status"
                                            class="flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @foreach (\App\Enums\UserStatus::cases() as $status)
                                            <option value="{{ $status->value }}" @selected($user->status === $status)>
                                                {{ $status->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit"
                                            class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                                        Save
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endcan

                    {{-- Audit trail --}}
                    <div class="overflow-hidden rounded-xl bg-white shadow">
                        <div class="border-b border-gray-100 px-5 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Audit</p>
                        </div>
                        <dl class="divide-y divide-gray-50">
                            <div class="flex justify-between px-5 py-3 text-sm">
                                <dt class="text-gray-500">Created by</dt>
                                <dd class="font-medium text-gray-900">{{ $user->createdBy?->name ?? 'System' }}</dd>
                            </div>
                            <div class="flex justify-between px-5 py-3 text-sm">
                                <dt class="text-gray-500">Updated by</dt>
                                <dd class="font-medium text-gray-900">{{ $user->updatedBy?->name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between px-5 py-3 text-sm">
                                <dt class="text-gray-500">Member since</dt>
                                <dd class="font-medium text-gray-900">{{ $user->created_at->format('M d, Y') }}</dd>
                            </div>
                        </dl>
                    </div>

                </div>

                {{-- ── Right column ────────────────────────────────────── --}}
                <div class="space-y-5 lg:col-span-2">

                    {{-- Contact & job details --}}
                    <div class="overflow-hidden rounded-xl bg-white shadow">
                        <div class="border-b border-gray-100 px-6 py-4">
                            <h3 class="text-sm font-semibold text-gray-700">Details</h3>
                        </div>
                        <dl class="grid grid-cols-2 gap-px bg-gray-100 sm:grid-cols-3">
                            @php
                                $fields = [
                                    'Department'  => $user->department?->name ?? '—',
                                    'Employee ID' => $user->employee_id ?? '—',
                                    'Phone'       => $user->phone ?? '—',
                                    'Hired'       => $user->hired_at?->format('M d, Y') ?? '—',
                                    'Timezone'    => $user->timezone ?? '—',
                                    'Email'       => $user->email,
                                ];
                            @endphp
                            @foreach ($fields as $label => $value)
                                <div class="bg-white px-5 py-4">
                                    <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">{{ $label }}</dt>
                                    <dd class="mt-1 text-sm font-medium text-gray-900 truncate" title="{{ $value }}">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>

                    {{-- Role & permissions --}}
                    <div class="overflow-hidden rounded-xl bg-white shadow">
                        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                            <h3 class="text-sm font-semibold text-gray-700">Role & Permissions</h3>
                            @can('roles.view')
                                @foreach ($user->roles as $role)
                                    <a href="{{ route('roles.show', $role) }}"
                                       class="text-xs text-indigo-600 hover:underline capitalize">
                                        View {{ $role->name }} role →
                                    </a>
                                @endforeach
                            @endcan
                        </div>

                        @if ($user->roles->isEmpty())
                            <p class="px-6 py-8 text-center text-sm text-gray-400">No role assigned.</p>
                        @else
                            @php
                                $allPerms = $user->roles->flatMap(fn ($r) => $r->permissions)
                                    ->unique('name')
                                    ->sortBy('name')
                                    ->groupBy(fn ($p) => explode('.', $p->name)[0]);
                            @endphp

                            @if ($allPerms->isEmpty())
                                <p class="px-6 py-8 text-center text-sm text-gray-400">This role has no permissions assigned.</p>
                            @else
                                <div class="divide-y divide-gray-50">
                                    @foreach ($allPerms as $resource => $perms)
                                        <div class="px-6 py-4">
                                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ $resource }}</p>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($perms as $perm)
                                                    @php $action = explode('.', $perm->name, 2)[1] ?? $perm->name; @endphp
                                                    <span class="inline-flex items-center gap-1 rounded-md bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                                        {{ $action }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>

                </div>
            </div>

        </div>
    </div>
</x-app-layout>
