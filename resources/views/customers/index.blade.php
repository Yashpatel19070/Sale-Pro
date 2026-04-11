<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Customers</h2>
            @can('create', App\Models\Customer::class)
                <a href="{{ route('customers.create') }}"
                   class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 sm:w-auto">
                    + New Customer
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            {{-- Filters --}}
            <form method="GET" class="mb-5 flex flex-wrap items-center gap-3">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                    </span>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Name, email, or company…"
                           class="rounded-md border-gray-300 pl-9 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-64" />
                </div>

                <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>

                <select name="source" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All sources</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->value }}" @selected(request('source') === $source->value)>
                            {{ $source->label() }}
                        </option>
                    @endforeach
                </select>

                @if(auth()->user()->hasAnyRole(['admin', 'manager']))
                    <select name="assigned_to" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All reps</option>
                        @foreach ($salesUsers as $rep)
                            <option value="{{ $rep->id }}" @selected(request('assigned_to') == $rep->id)>
                                {{ $rep->name }}
                            </option>
                        @endforeach
                    </select>
                @endif

                @if(auth()->user()->hasRole('admin'))
                    <select name="department_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All departments</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept->id }}" @selected(request('department_id') == $dept->id)>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                @endif

                <button type="submit"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Filter
                </button>
                @if (request()->hasAny(['search', 'status', 'source', 'assigned_to', 'department_id']))
                    <a href="{{ route('customers.index') }}"
                       class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                        Clear
                    </a>
                @endif
            </form>

            {{-- Table --}}
            <div class="overflow-x-auto overflow-hidden rounded-xl bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Company</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Source</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Assigned To</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($customers as $customer)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('customers.show', $customer) }}"
                                       class="block text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                                        {{ $customer->full_name }}
                                    </a>
                                    @if($customer->email)
                                        <span class="block text-xs text-gray-400">{{ $customer->email }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-sm text-gray-600">
                                    {{ $customer->company_name ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5">
                                    @php $color = $customer->status->color(); @endphp
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                        {{ $color === 'blue'   ? 'bg-blue-100 text-blue-800'     : '' }}
                                        {{ $color === 'yellow' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                        {{ $color === 'green'  ? 'bg-green-100 text-green-800'   : '' }}
                                        {{ $color === 'gray'   ? 'bg-gray-100 text-gray-700'     : '' }}">
                                        {{ $customer->status->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-sm text-gray-600">
                                    {{ $customer->source->label() }}
                                </td>
                                <td class="px-5 py-3.5 text-sm text-gray-600">
                                    {{ $customer->assignedTo?->name ?? '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('customers.show', $customer) }}"
                                           class="text-xs font-medium text-indigo-600 hover:text-indigo-800">View</a>
                                        @can('update', $customer)
                                            <a href="{{ route('customers.edit', $customer) }}"
                                               class="text-xs font-medium text-gray-600 hover:text-gray-900">Edit</a>
                                        @endcan
                                        @can('delete', $customer)
                                            <form method="POST" action="{{ route('customers.destroy', $customer) }}"
                                                  onsubmit="return confirm('Delete {{ addslashes($customer->full_name) }}?')">
                                                @csrf @method('DELETE')
                                                <button class="text-xs font-medium text-red-500 hover:text-red-700">Delete</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center">
                                    <div class="text-gray-400">
                                        <svg class="mx-auto mb-3 h-10 w-10 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <p class="text-sm">No customers found.</p>
                                        @if (request()->hasAny(['search', 'status', 'source', 'assigned_to', 'department_id']))
                                            <a href="{{ route('customers.index') }}" class="mt-1 inline-block text-xs text-indigo-500 hover:underline">Clear filters</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $customers->links() }}</div>

        </div>
    </div>
</x-app-layout>
