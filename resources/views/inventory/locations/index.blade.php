<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Inventory Locations</h2>
            @can('create', App\Models\InventoryLocation::class)
                <a href="{{ route('inventory-locations.create') }}"
                   class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Add Location
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 rounded-md bg-green-100 px-4 py-3 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 rounded-md bg-red-100 px-4 py-3 text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Filter bar --}}
            <form method="GET" action="{{ route('inventory-locations.index') }}" class="mb-6 flex flex-wrap gap-3">
                <input type="text"
                       name="search"
                       value="{{ $filters['search'] ?? '' }}"
                       placeholder="Search code or name..."
                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />

                <select name="status"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    <option value="active"   {{ ($filters['status'] ?? '') === 'active'   ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>

                <button type="submit"
                        class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white hover:bg-gray-700">
                    Filter
                </button>
                <a href="{{ route('inventory-locations.index') }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                    Clear
                </a>
            </form>

            {{-- Table --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                @if($locations->isEmpty())
                    <div class="py-16 text-center text-gray-500">No inventory locations found.</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($locations as $location)
                                <tr class="{{ $location->trashed() ? 'opacity-50' : '' }}">
                                    <td class="px-6 py-4 text-sm font-mono font-medium text-gray-900">
                                        {{ $location->code }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $location->name }}
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        @if($location->is_active && ! $location->trashed())
                                            <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">Active</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-800">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ $location->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex items-center gap-3">
                                            @if(! $location->trashed())
                                                <a href="{{ route('inventory-locations.show', $location) }}"
                                                   class="text-indigo-600 hover:text-indigo-900">View</a>

                                                @can('update', $location)
                                                    <a href="{{ route('inventory-locations.edit', $location) }}"
                                                       class="text-yellow-600 hover:text-yellow-900">Edit</a>
                                                @endcan

                                                @can('delete', $location)
                                                    <form method="POST"
                                                          action="{{ route('inventory-locations.destroy', $location) }}"
                                                          onsubmit="return confirm('Deactivate this location?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                                class="text-red-600 hover:text-red-900">
                                                            Deactivate
                                                        </button>
                                                    </form>
                                                @endcan
                                            @else
                                                @can('restore', $location)
                                                    <form method="POST"
                                                          action="{{ route('inventory-locations.restore', $location->id) }}">
                                                        @csrf
                                                        <button type="submit"
                                                                class="text-green-600 hover:text-green-900">
                                                            Restore
                                                        </button>
                                                    </form>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $locations->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
