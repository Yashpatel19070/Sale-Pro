<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Purchase Orders</h2>
            @can('create', App\Models\PurchaseOrder::class)
                <a href="{{ route('purchase-orders.create') }}"
                   class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    New Purchase Order
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

            @if(session('error'))
                <div class="mb-4 rounded-md bg-red-100 px-4 py-3 text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            <form method="GET" action="{{ route('purchase-orders.index') }}" class="mb-6 flex flex-wrap gap-3">
                <input type="text"
                       name="search"
                       value="{{ $filters['search'] ?? '' }}"
                       placeholder="Search PO number, supplier..."
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

                <select name="supplier_id"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? '') == $supplier->id)>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>

                <input type="date"
                       name="date_from"
                       value="{{ $filters['date_from'] ?? '' }}"
                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />

                <input type="date"
                       name="date_to"
                       value="{{ $filters['date_to'] ?? '' }}"
                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />

                <button type="submit"
                        class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white hover:bg-gray-700">
                    Filter
                </button>
                <a href="{{ route('purchase-orders.index') }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                    Clear
                </a>
            </form>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                @if($pos->isEmpty())
                    <div class="py-16 text-center text-gray-500">No purchase orders found.</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">PO Number</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Supplier</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Grand Total</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Expected Delivery</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created By</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created At</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($pos as $po)
                                <tr class="{{ $po->trashed() ? 'opacity-50' : '' }}">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        <a href="{{ route('purchase-orders.show', $po->id) }}"
                                           class="{{ $po->trashed() ? 'line-through text-gray-500' : 'text-indigo-600 hover:text-indigo-900' }}">
                                            {{ $po->po_number }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ $po->supplier->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        @if($po->trashed())
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700">Deleted</span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-{{ $po->status->color() }}-100 text-{{ $po->status->color() }}-700">
                                                {{ $po->status->label() }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ number_format($po->grand_total, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ $po->expected_delivery_date ? $po->expected_delivery_date->format('M d, Y') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ $po->createdBy->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ $po->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center gap-3">
                                            @if(! $po->trashed())
                                                <a href="{{ route('purchase-orders.show', $po) }}"
                                                   class="text-indigo-600 hover:text-indigo-900">View</a>

                                                @if($po->status->isEditable())
                                                    @can('update', $po)
                                                        <a href="{{ route('purchase-orders.edit', $po) }}"
                                                           class="text-gray-600 hover:text-gray-900">Edit</a>
                                                    @endcan
                                                @endif

                                                @can('delete', $po)
                                                    <form method="POST"
                                                          action="{{ route('purchase-orders.destroy', $po) }}"
                                                          onsubmit="return confirm('Are you sure you want to delete this purchase order?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                                class="text-red-600 hover:text-red-900">Delete</button>
                                                    </form>
                                                @endcan
                                            @else
                                                @can('restore', $po)
                                                    <form method="POST"
                                                          action="{{ route('purchase-orders.restore', $po->id) }}">
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
                        {{ $pos->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
