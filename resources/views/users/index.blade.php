<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Users</h2>
            @can('create', App\Models\User::class)
                <a href="{{ route('users.create') }}"
                   class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    + New User
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            {{-- Filters --}}
            <form method="GET" class="mb-4 flex flex-wrap gap-3">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Name, email, or employee ID…"
                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-72" />

                <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-36">
                    <option value="">All status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>

                <select name="department_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-44">
                    <option value="">All departments</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}" @selected(request('department_id') == $dept->id)>
                            {{ $dept->name }}
                        </option>
                    @endforeach
                </select>

                <select name="role" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-32">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(request('role') === $role)>{{ ucfirst($role) }}</option>
                    @endforeach
                </select>

                <button type="submit" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Filter</button>
                <a href="{{ route('users.index') }}" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</a>
            </form>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">User</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Department</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Role</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Hired</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($users as $user)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}"
                                             class="w-8 h-8 rounded-full object-cover" />
                                        <div>
                                            <a href="{{ route('users.show', $user) }}"
                                               class="font-medium text-indigo-600 hover:underline text-sm">
                                                {{ $user->name }}
                                            </a>
                                            <div class="text-xs text-gray-400">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $user->department?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @foreach ($user->roles as $role)
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800">
                                            {{ $role->name }}
                                        </span>
                                    @endforeach
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $user->status->badgeClass() }}">
                                        {{ $user->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $user->hired_at?->format('M Y') ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        @can('update', $user)
                                            <a href="{{ route('users.edit', $user) }}" class="text-xs text-gray-600 hover:text-gray-900">Edit</a>
                                        @endcan
                                        @can('delete', $user)
                                            <form method="POST" action="{{ route('users.destroy', $user) }}"
                                                  onsubmit="return confirm('Delete ' + {{ Js::from($user->name) }} + '?')">
                                                @csrf @method('DELETE')
                                                <button class="text-xs text-red-600 hover:text-red-800">Delete</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $users->links() }}</div>

        </div>
    </div>
</x-app-layout>
