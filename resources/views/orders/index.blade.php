<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Orders</h2>
            @can('create', App\Models\Order::class)
                <a href="{{ route('orders.create') }}"
                   class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    New Order
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

            {{-- Filter bar --}}
            <form method="GET" action="{{ route('orders.index') }}" class="mb-6 flex flex-wrap gap-3">
                <input type="text"
                       name="search"
                       value="{{ $filters['search'] ?? '' }}"
                       placeholder="Search order number, customer..."
                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />

                <select name="status"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}"
                                {{ ($filters['status'] ?? '') === $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>

                <button type="submit"
                        class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white hover:bg-gray-700">
                    Filter
                </button>
                <a href="{{ route('orders.index') }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                    Clear
                </a>
            </form>

            {{-- Table --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                @if($orders->isEmpty())
                    <div class="py-16 text-center text-gray-500">No orders found.</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Source</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Payment</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Grand Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($orders as $order)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                        <a href="{{ route('orders.show', $order) }}"
                                           class="text-indigo-600 hover:text-indigo-900">
                                            {{ $order->number }}
                                        </a>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                        {{ $order->customer->name }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                        {{ $order->source->label() }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        @php $badgeColor = $order->status->badgeColor(); @endphp
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold
                                            {{ $badgeColor === 'green' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $badgeColor === 'yellow' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                            {{ $badgeColor === 'red' ? 'bg-red-100 text-red-800' : '' }}
                                            {{ $badgeColor === 'blue' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $badgeColor === 'gray' ? 'bg-gray-100 text-gray-800' : '' }}">
                                            {{ $order->status->label() }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                        {{ $order->payment_status }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                        {{ number_format($order->grand_total, 2) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                        {{ $order->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        <a href="{{ route('orders.show', $order) }}"
                                           class="text-indigo-600 hover:text-indigo-900">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="border-t border-gray-200 px-6 py-4">
                        {{ $orders->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
