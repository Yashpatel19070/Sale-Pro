<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Suppliers</h2>
            @can('create', App\Models\Supplier::class)
                <a href="{{ route('suppliers.create') }}"
                   class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Add Supplier
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
            <form method="GET" action="{{ route('suppliers.index') }}" class="mb-6 flex flex-wrap gap-3">
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Search name or code..."
                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />

                <select name="status"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    <option value="active"   @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>

                <button type="submit"
                        class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white hover:bg-gray-700">
                    Filter
                </button>
                <a href="{{ route('suppliers.index') }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                    Clear
                </a>
            </form>

            {{-- Table --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                @if($suppliers->isEmpty())
                    <div class="py-16 text-center text-gray-500">No suppliers found.</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach($suppliers as $supplier)
                                <tr class="{{ $supplier->trashed() ? 'bg-gray-50 opacity-75' : '' }}">
                                    <td class="px-6 py-4 text-sm font-mono text-gray-600">{{ $supplier->code }}</td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                        <a href="{{ route('suppliers.show', $supplier) }}" class="hover:underline">
                                            {{ $supplier->name }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ $supplier->contact_name ?? '—' }}
                                        @if($supplier->contact_email)
                                            <br><span class="text-xs">{{ $supplier->contact_email }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($supplier->trashed())
                                            <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700">Inactive</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">Active</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <a href="{{ route('suppliers.show', $supplier) }}"
                                           class="mr-3 text-indigo-600 hover:underline">View</a>

                                        @if($supplier->trashed())
                                            @can('restore', $supplier)
                                                <form method="POST" action="{{ route('suppliers.restore', $supplier) }}" class="inline"
                                                      x-data @submit.prevent="if(confirm('Restore this supplier?')) $el.submit()">
                                                    @csrf
                                                    <button class="text-green-600 hover:underline">Restore</button>
                                                </form>
                                            @endcan
                                        @else
                                            @can('update', $supplier)
                                                <a href="{{ route('suppliers.edit', $supplier) }}"
                                                   class="mr-3 text-blue-600 hover:underline">Edit</a>
                                            @endcan
                                            @can('delete', $supplier)
                                                <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" class="inline"
                                                      x-data @submit.prevent="if(confirm('Deactivate this supplier?')) $el.submit()">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="text-red-600 hover:underline">Deactivate</button>
                                                </form>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($suppliers->hasPages())
                        <div class="border-t border-gray-100 px-6 py-4">
                            {{ $suppliers->links() }}
                        </div>
                    @endif
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
