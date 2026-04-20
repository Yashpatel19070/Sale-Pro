<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Suppliers</h2>
            @cannot('create', App\Models\Supplier::class)
            @else
                <a href="{{ route('suppliers.create') }}"
                   class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Add Supplier
                </a>
            @endcannot
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 rounded-md bg-green-100 px-4 py-3 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 rounded-md bg-red-100 px-4 py-3 text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            <form method="GET" action="{{ route('suppliers.index') }}" class="mb-6 flex flex-wrap gap-3">
                <input type="text"
                       name="search"
                       value="{{ $filters['search'] ?? '' }}"
                       placeholder="Search name, email, contact..."
                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />

                <select name="status"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>

                <button type="submit"
                        class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white hover:bg-gray-700">
                    Search
                </button>
                <a href="{{ route('suppliers.index') }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                    Clear
                </a>
            </form>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                @if($suppliers->isEmpty())
                    <div class="py-16 text-center text-gray-500">No suppliers found.</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Phone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Payment Terms</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($suppliers as $supplier)
                                <tr class="{{ $supplier->trashed() ? 'opacity-50' : '' }}">
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                        {{ $supplier->name }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                        {{ $supplier->contact_name ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                        {{ $supplier->email }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                        {{ $supplier->phone }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                        {{ $supplier->payment_terms ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        @if(! $supplier->trashed())
                                            @php $color = $supplier->status->color(); @endphp
                                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold
                                                {{ $color === 'green' ? 'bg-green-100 text-green-800' : '' }}
                                                {{ $color === 'yellow' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                                {{ $supplier->status->label() }}
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-800">Deleted</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        <div class="flex items-center gap-3">
                                            @if(! $supplier->trashed())
                                                <a href="{{ route('suppliers.show', $supplier) }}"
                                                   class="text-indigo-600 hover:text-indigo-900">View</a>

                                                @can('update', $supplier)
                                                    <a href="{{ route('suppliers.edit', $supplier) }}"
                                                       class="text-gray-600 hover:text-gray-900">Edit</a>
                                                @endcan

                                                @can('delete', $supplier)
                                                    <form method="POST"
                                                          action="{{ route('suppliers.destroy', $supplier) }}"
                                                          onsubmit="return confirm('Are you sure you want to delete this supplier?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                                class="text-red-600 hover:text-red-900">Delete</button>
                                                    </form>
                                                @endcan
                                            @else
                                                @can('restore', $supplier)
                                                    <form method="POST"
                                                          action="{{ route('suppliers.restore', $supplier->id) }}">
                                                        @csrf
                                                        <button type="submit"
                                                                class="text-green-600 hover:text-green-900">Restore</button>
                                                    </form>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="border-t border-gray-200 px-6 py-4">
                        {{ $suppliers->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
