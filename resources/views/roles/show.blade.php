<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 capitalize">
                Role: {{ $role->name }}
            </h2>
            @can('roles.manage')
                <a href="{{ route('roles.edit', $role) }}"
                   class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Edit Permissions
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            {{-- Flags --}}
            <div class="flex gap-2">
                @if ($role->is_super)
                    <span class="inline-flex items-center rounded-full bg-purple-100 px-3 py-1 text-sm font-medium text-purple-800">Superadmin</span>
                @endif
                @if ($role->is_admin)
                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-sm font-medium text-indigo-800">Admin</span>
                @endif
            </div>

            {{-- Permissions grouped by resource --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-medium text-gray-700">Permissions ({{ $role->permissions->count() }})</h3>
                </div>
                @php
                    $grouped = $role->permissions->groupBy(fn ($p) => explode('.', $p->name)[0]);
                @endphp
                @if ($grouped->isEmpty())
                    <p class="px-4 py-6 text-sm text-gray-400">No permissions assigned.</p>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach ($grouped as $resource => $perms)
                            <div class="px-4 py-3">
                                <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $resource }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($perms->sortBy('name') as $perm)
                                        <span class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                            {{ $perm->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Users with this role --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-medium text-gray-700">Users with this role ({{ $role->users->count() }})</h3>
                </div>
                @if ($role->users->isEmpty())
                    <p class="px-4 py-6 text-sm text-gray-400">No users assigned.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach ($role->users as $user)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm">
                                        @can('users.view')
                                            <a href="{{ route('users.show', $user) }}"
                                               class="font-medium text-indigo-600 hover:underline">{{ $user->name }}</a>
                                        @else
                                            <span class="font-medium text-gray-900">{{ $user->name }}</span>
                                        @endcan
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $user->email }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                            {{ $user->status->value === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $user->status->value }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div>
                <a href="{{ route('roles.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Back to Roles</a>
            </div>

        </div>
    </div>
</x-app-layout>
