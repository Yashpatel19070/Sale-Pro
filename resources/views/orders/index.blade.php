<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Orders</h2>
            @can('create', \App\Models\Order::class)
                <a href="{{ route('orders.create') }}"
                   class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    + New Order
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            {{-- Filters --}}
            <form method="GET" action="{{ route('orders.index') }}" class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-5">
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Order # or customer"
                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
                <select name="source" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All sources</option>
                    @foreach($sources as $s)
                        <option value="{{ $s->value }}" @selected(($filters['source'] ?? '') === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-md border-gray-300 text-sm shadow-sm" />
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-md border-gray-300 text-sm shadow-sm" />
            </form>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Order #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Source</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Payment</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Grand Total</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($orders as $order)
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    <a href="{{ route('orders.show', $order) }}" class="text-indigo-600 hover:text-indigo-800">{{ $order->number }}</a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $order->customer->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $order->source->label() }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $order->status->label() }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $order->payment_status->label() }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-gray-800">${{ number_format((float) $order->grand_total, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $order->created_at->format('M j, Y') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('orders.show', $order) }}" class="text-sm text-indigo-600 hover:text-indigo-800">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500">No orders found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $orders->withQueryString()->links() }}</div>
        </div>
    </div>
</x-app-layout>
