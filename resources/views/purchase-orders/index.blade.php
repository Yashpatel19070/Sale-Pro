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

            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>
            @endif

            {{-- Filters --}}
            <form method="GET" action="{{ route('purchase-orders.index') }}" class="mb-6 flex flex-wrap gap-3">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="PO number or supplier…"
                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    @foreach (\App\Enums\PoStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <select name="type" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All types</option>
                    @foreach (\App\Enums\PoType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
                <button type="submit"
                        class="rounded-md bg-gray-100 px-4 py-2 text-sm text-gray-700 hover:bg-gray-200">Filter</button>
                <a href="{{ route('purchase-orders.index') }}"
                   class="rounded-md px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</a>
            </form>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">PO Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Supplier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Lines</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($pos as $po)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-medium">
                                    <a href="{{ route('purchase-orders.show', $po) }}"
                                       class="text-indigo-600 hover:text-indigo-900">{{ $po->po_number }}</a>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $po->type->label() }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $po->supplier->name }}</td>
                                <td class="px-6 py-4 text-sm">
                                    @include('purchase-orders._status_badge', ['status' => $po->status])
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $po->lines_count }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $po->createdBy?->name ?? 'Unknown' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $po->created_at->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-400">No purchase orders found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $pos->links() }}</div>
        </div>
    </div>
</x-app-layout>
