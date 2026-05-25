<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Order {{ $order->number }}
                </h2>
                @php $badgeColor = $order->status->badgeColor(); @endphp
                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold
                    {{ $badgeColor === 'green' ? 'bg-green-100 text-green-800' : '' }}
                    {{ $badgeColor === 'yellow' ? 'bg-yellow-100 text-yellow-800' : '' }}
                    {{ $badgeColor === 'red' ? 'bg-red-100 text-red-800' : '' }}
                    {{ $badgeColor === 'blue' ? 'bg-blue-100 text-blue-800' : '' }}
                    {{ $badgeColor === 'gray' ? 'bg-gray-100 text-gray-800' : '' }}">
                    {{ $order->status->label() }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                @can('update', $order)
                    @if($order->status === \App\Enums\OrderStatus::Pending)
                        <a href="{{ route('orders.edit', $order) }}"
                           class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                            Edit Order
                        </a>
                    @endif
                @endcan

                @can('cancel', $order)
                    @if(in_array($order->status, [\App\Enums\OrderStatus::Pending, \App\Enums\OrderStatus::Processing]))
                        <form method="POST" action="{{ route('orders.cancel', $order) }}"
                              x-data @submit.prevent="if(confirm('Cancel this order?')) $el.submit()">
                            @csrf
                            <button type="submit"
                                    class="rounded-md border border-yellow-300 bg-yellow-50 px-4 py-2 text-sm text-yellow-700 hover:bg-yellow-100">
                                Cancel Order
                            </button>
                        </form>
                    @endif
                @endcan

                @can('delete', $order)
                    @if($order->status === \App\Enums\OrderStatus::Cancelled)
                        <form method="POST" action="{{ route('orders.destroy', $order) }}"
                              x-data @submit.prevent="if(confirm('Permanently delete this order? This cannot be undone.')) $el.submit()">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="rounded-md border border-red-300 bg-red-50 px-4 py-2 text-sm text-red-700 hover:bg-red-100">
                                Delete Order
                            </button>
                        </form>
                    @endif
                @endcan

                <a href="{{ route('orders.index') }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                    Back to List
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="rounded-md bg-green-100 px-4 py-3 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Top two-column layout --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                {{-- Order Details --}}
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="px-6 py-5">
                        <h3 class="mb-4 text-sm font-medium text-gray-700">Order Details</h3>
                        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Number</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $order->number }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                <dd class="mt-1 text-sm">
                                    @php $badgeColor = $order->status->badgeColor(); @endphp
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold
                                        {{ $badgeColor === 'green' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $badgeColor === 'yellow' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                        {{ $badgeColor === 'red' ? 'bg-red-100 text-red-800' : '' }}
                                        {{ $badgeColor === 'blue' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $badgeColor === 'gray' ? 'bg-gray-100 text-gray-800' : '' }}">
                                        {{ $order->status->label() }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Payment Status</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $order->payment_status }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Source</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $order->source->label() }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Grand Total</dt>
                                <dd class="mt-1 text-sm font-medium text-gray-900">{{ number_format($order->grand_total, 2) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Created At</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $order->created_at->format('M d, Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Shipped At</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ $order->shipped_at ? $order->shipped_at->format('M d, Y') : '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Delivered At</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ $order->delivered_at ? $order->delivered_at->format('M d, Y') : '—' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- Customer --}}
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="px-6 py-5">
                        <h3 class="mb-4 text-sm font-medium text-gray-700">Customer</h3>
                        <dl class="grid grid-cols-1 gap-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Name</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <a href="{{ route('customers.show', $order->customer) }}"
                                       class="text-indigo-600 hover:text-indigo-900">
                                        {{ $order->customer->name }}
                                    </a>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Email</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $order->customer->email }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

            </div>

            {{-- Order Lines --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="px-6 py-5">
                    <h3 class="mb-4 text-sm font-medium text-gray-700">Order Lines</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">SKU</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Serial #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Unit Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tax Rate</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tax Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($order->lines as $line)
                            <tr>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                    {{ $line->sku ?: '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                    {{ $line->product_name ?: '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                    {{ $line->serial->serial_number ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                    {{ number_format($line->unit_price, 2) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                    {{ number_format($line->tax_rate * 100, 2) }}%
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                    {{ number_format($line->tax_amount, 2) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ number_format($line->line_total, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">No line items.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Fees --}}
            @if($order->orderFees->isNotEmpty())
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="px-6 py-5">
                        <h3 class="mb-4 text-sm font-medium text-gray-700">Fees</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($order->orderFees as $fee)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ $fee->name }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ number_format($fee->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Totals --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="px-6 py-5">
                    <h3 class="mb-4 text-sm font-medium text-gray-700">Totals</h3>
                    <dl class="divide-y divide-gray-100">
                        <div class="flex justify-between py-2 text-sm">
                            <dt class="text-gray-500">Subtotal (incl. tax)</dt>
                            <dd class="text-gray-900">{{ number_format($order->subtotal, 2) }}</dd>
                        </div>
                        <div class="flex justify-between py-2 text-sm">
                            <dt class="text-gray-500">Fees</dt>
                            <dd class="text-gray-900">{{ number_format($order->fees, 2) }}</dd>
                        </div>
                        <div class="flex justify-between py-2 text-sm">
                            <dt class="text-gray-500">Shipping</dt>
                            <dd class="text-gray-900">{{ number_format($order->shipping, 2) }}</dd>
                        </div>
                        <div class="flex justify-between py-2 text-sm font-semibold">
                            <dt class="text-gray-900">Grand Total</dt>
                            <dd class="text-gray-900">{{ number_format($order->grand_total, 2) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Shipping Address --}}
            @if($order->shipping_address_line1)
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="px-6 py-5">
                        <h3 class="mb-4 text-sm font-medium text-gray-700">Shipping Address</h3>
                        <address class="not-italic text-sm text-gray-900 leading-6">
                            {{ $order->shipping_first_name }} {{ $order->shipping_last_name }}<br>
                            {{ $order->shipping_address_line1 }}<br>
                            @if($order->shipping_address_line2)
                                {{ $order->shipping_address_line2 }}<br>
                            @endif
                            {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_postal_code }}<br>
                            {{ $order->shipping_country }}
                        </address>
                    </div>
                </div>
            @endif

            {{-- Billing Address --}}
            @if($order->billing_address_line1)
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="px-6 py-5">
                        <h3 class="mb-4 text-sm font-medium text-gray-700">Billing Address</h3>
                        <address class="not-italic text-sm text-gray-900 leading-6">
                            {{ $order->billing_first_name }} {{ $order->billing_last_name }}<br>
                            {{ $order->billing_address_line1 }}<br>
                            @if($order->billing_address_line2)
                                {{ $order->billing_address_line2 }}<br>
                            @endif
                            {{ $order->billing_city }}, {{ $order->billing_state }} {{ $order->billing_postal_code }}<br>
                            {{ $order->billing_country }}
                        </address>
                    </div>
                </div>
            @endif

            {{-- Payments --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="px-6 py-5">
                    <h3 class="mb-4 text-sm font-medium text-gray-700">Payments</h3>
                </div>

                @if($order->payments->isNotEmpty())
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Method</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Received At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($order->payments as $payment)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                        {{ method_exists($payment->method, 'label') ? $payment->method->label() : $payment->method }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                        {{ number_format($payment->amount, 2) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                        {{ $payment->status->label() }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                        {{ $payment->cash_received_at ? $payment->cash_received_at->format('M d, Y') : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="px-6 pb-4 text-sm text-gray-500">No payments recorded.</div>
                @endif

                @if($order->payment_status === 'unpaid')
                    @can('pay', $order)
                        <div class="border-t border-gray-200 px-6 py-5">
                            <h4 class="mb-4 text-sm font-medium text-gray-700">Record Payment</h4>
                            <form method="POST" action="{{ route('orders.pay', $order) }}" class="flex flex-wrap items-end gap-4">
                                @csrf
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Amount</label>
                                    <input type="number"
                                           name="amount"
                                           value="{{ old('amount', $order->grand_total) }}"
                                           step="0.01"
                                           min="0"
                                           class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    @error('amount')
                                        <p class="mt-1 text-xs text-red-600">{{ $errors->first('amount') }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Received At</label>
                                    <input type="date"
                                           name="cash_received_at"
                                           value="{{ old('cash_received_at', now()->format('Y-m-d')) }}"
                                           class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    @error('cash_received_at')
                                        <p class="mt-1 text-xs text-red-600">{{ $errors->first('cash_received_at') }}</p>
                                    @enderror
                                </div>
                                <button type="submit"
                                        class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                                    Record Payment
                                </button>
                            </form>
                        </div>
                    @endcan
                @endif
            </div>

            {{-- Shipments --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="px-6 py-5">
                    <h3 class="mb-4 text-sm font-medium text-gray-700">Shipments</h3>
                </div>

                @if($order->shipments->isNotEmpty())
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Carrier</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tracking</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Shipped At</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Delivered At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($order->shipments as $shipment)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ $shipment->carrier }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ $shipment->tracking ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">{{ $shipment->status->label() }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                        {{ $shipment->shipped_at ? $shipment->shipped_at->format('M d, Y') : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                        {{ $shipment->delivered_at ? $shipment->delivered_at->format('M d, Y') : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="px-6 pb-4 text-sm text-gray-500">No shipments recorded.</div>
                @endif

                {{-- Ship action --}}
                @if($order->status === \App\Enums\OrderStatus::Processing)
                    @can('ship', $order)
                        <div class="border-t border-gray-200 px-6 py-5">
                            <h4 class="mb-4 text-sm font-medium text-gray-700">Mark as Shipped</h4>
                            <form method="POST" action="{{ route('orders.ship', $order) }}" class="flex flex-wrap items-end gap-4">
                                @csrf
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Carrier</label>
                                    <input type="text"
                                           name="carrier"
                                           value="{{ old('carrier') }}"
                                           placeholder="e.g. FedEx"
                                           class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    @error('carrier')
                                        <p class="mt-1 text-xs text-red-600">{{ $errors->first('carrier') }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Tracking Number</label>
                                    <input type="text"
                                           name="tracking"
                                           value="{{ old('tracking') }}"
                                           class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    @error('tracking')
                                        <p class="mt-1 text-xs text-red-600">{{ $errors->first('tracking') }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Label Cost</label>
                                    <input type="number"
                                           name="label_cost"
                                           value="{{ old('label_cost') }}"
                                           step="0.01"
                                           min="0"
                                           placeholder="0.00"
                                           class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    @error('label_cost')
                                        <p class="mt-1 text-xs text-red-600">{{ $errors->first('label_cost') }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Shipped At</label>
                                    <input type="date"
                                           name="shipped_at"
                                           value="{{ old('shipped_at', now()->format('Y-m-d')) }}"
                                           class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    @error('shipped_at')
                                        <p class="mt-1 text-xs text-red-600">{{ $errors->first('shipped_at') }}</p>
                                    @enderror
                                </div>
                                <button type="submit"
                                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                    Mark Shipped
                                </button>
                            </form>
                        </div>
                    @endcan
                @endif

                {{-- Deliver action --}}
                @if($order->status === \App\Enums\OrderStatus::Shipped && !$order->delivered_at)
                    @can('deliver', $order)
                        <div class="border-t border-gray-200 px-6 py-5">
                            <h4 class="mb-4 text-sm font-medium text-gray-700">Mark as Delivered</h4>
                            <form method="POST" action="{{ route('orders.deliver', $order) }}" class="flex flex-wrap items-end gap-4">
                                @csrf
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Delivered At</label>
                                    <input type="date"
                                           name="delivered_at"
                                           value="{{ old('delivered_at', now()->format('Y-m-d')) }}"
                                           class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    @error('delivered_at')
                                        <p class="mt-1 text-xs text-red-600">{{ $errors->first('delivered_at') }}</p>
                                    @enderror
                                </div>
                                <button type="submit"
                                        class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                                    Mark Delivered
                                </button>
                            </form>
                        </div>
                    @endcan
                @endif

            </div>

        </div>
    </div>
</x-app-layout>
