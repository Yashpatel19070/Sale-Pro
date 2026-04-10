<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Departments</h2>
            @can('create', App\Models\Department::class)
                <a href="{{ route('departments.create') }}"
                   class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    + New Department
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
                       placeholder="Name or code…"
                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-64" />

                <select name="active"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-40">
                    <option value="">All status</option>
                    <option value="1" @selected(request('active') === '1')>Active</option>
                    <option value="0" @selected(request('active') === '0')>Inactive</option>
                </select>

                <button type="submit"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Filter
                </button>
                <a href="{{ route('departments.index') }}"
                   class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</a>
            </form>

            {{-- Table --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Code</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Manager</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Members</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($departments as $dept)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('departments.show', $dept) }}"
                                       class="font-medium text-indigo-600 hover:underline">
                                        {{ $dept->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800">
                                        {{ $dept->code }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $dept->manager?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $dept->users_count }}</td>
                                <td class="px-4 py-3">
                                    @if ($dept->is_active)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Active</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        @can('view', $dept)
                                            <a href="{{ route('departments.show', $dept) }}"
                                               class="text-xs text-indigo-600 hover:underline">View</a>
                                        @endcan
                                        @can('update', $dept)
                                            <a href="{{ route('departments.edit', $dept) }}"
                                               class="text-xs text-gray-600 hover:text-gray-900">Edit</a>
                                            <form method="POST"
                                                  action="{{ route('departments.toggle-active', $dept) }}">
                                                @csrf
                                                <button class="text-xs text-gray-500 hover:text-gray-700">
                                                    {{ $dept->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                        @endcan
                                        @can('delete', $dept)
                                            <form method="POST"
                                                  action="{{ route('departments.destroy', $dept) }}"
                                                  onsubmit="return confirm('Delete ' + {{ Js::from($dept->name) }} + '?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-xs text-red-600 hover:text-red-800">Delete</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">
                                    No departments found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $departments->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
