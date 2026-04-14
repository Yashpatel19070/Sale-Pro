<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Inventory Serials</h2>
            @can('create', App\Models\InventorySerial::class)
                <a href="{{ route('inventory-serials.create') }}"
                   class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    + Receive Serial
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
                       placeholder="Serial number or SKU…"
                       class="w-64 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />

                <select name="status"
                        class="w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="product_id"
                        class="w-52 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All products</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>
                            [{{ $product->sku }}] {{ $product->name }}
                        </option>
                    @endforeach
                </select>

                <select name="location_id"
                        class="w-44 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All locations</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" @selected(request('location_id') == $loc->id)>
                            {{ $loc->code }} — {{ $loc->name }}
                        </option>
                    @endforeach
                </select>

                <button type="submit"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Filter
                </button>
                <a href="{{ route('inventory-serials.index') }}"
                   class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</a>
            </form>

            {{-- Table --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Serial #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Location</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Received</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($serials as $serial)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('inventory-serials.show', $serial) }}"
                                       class="font-mono text-sm font-medium text-indigo-600 hover:underline">
                                        {{ $serial->serial_number }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                        {{ $serial->product->sku }}
                                    </span>
                                    <span class="ml-1 text-sm text-gray-700">{{ $serial->product->name }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $serial->location?->code ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $serial->status->badgeClasses() }}">
                                        {{ $serial->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $serial->received_at->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        @can('view', $serial)
                                            <a href="{{ route('inventory-serials.show', $serial) }}"
                                               class="text-xs text-indigo-600 hover:underline">View</a>
                                        @endcan
                                        @can('update', $serial)
                                            <a href="{{ route('inventory-serials.edit', $serial) }}"
                                               class="text-xs text-gray-600 hover:text-gray-900">Edit</a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">
                                    No serials found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $serials->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
