<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $order->number }}</h2>
                <span class="rounded-full px-2 py-1 text-xs font-medium bg-{{ $order->status->color() }}-100 text-{{ $order->status->color() }}-700">
                    {{ $order->status->label() }}
                </span>
                <span class="rounded-full px-2 py-1 text-xs font-medium bg-{{ $order->payment_status->color() }}-100 text-{{ $order->payment_status->color() }}-700">
                    {{ $order->payment_status->label() }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                @if($order->status === \App\Enums\OrderStatus::Pending)
                    @can('update', $order)
                        <a href="{{ route('orders.edit', $order) }}"
                           class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Edit</a>
                    @endcan
                    @can('delete', $order)
                        <form method="POST" action="{{ route('orders.destroy', $order) }}"
                              onsubmit="return confirm('Delete this order?')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Delete</button>
                        </form>
                    @endcan
                @endif

                @if($order->payment_status === \App\Enums\PaymentStatus::Unpaid)
                    @can('recordCashPayment', $order)
                        <button type="button"
                                onclick="document.getElementById('cash-payment-form').classList.toggle('hidden')"
                                class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                            Record Cash Payment
                        </button>
                    @endcan
                @endif

                @if($order->status === \App\Enums\OrderStatus::Processing)
                    @can('complete', $order)
                        <form method="POST" action="{{ route('orders.complete', $order) }}"
                              onsubmit="return confirm('Mark this order as complete?')">
                            @csrf
                            <button type="submit"
                                    class="rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">
                                Complete Order
                            </button>
                        </form>
                    @endcan
                @endif

                <a href="{{ route('orders.index') }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="rounded-md bg-green-100 px-4 py-3 text-green-800">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-red-800">
                    <ul class="list-inside list-disc space-y-1 text-sm">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- Cash payment inline form --}}
            @can('recordCashPayment', $order)
                @if($order->payment_status === \App\Enums\PaymentStatus::Unpaid)
                    <div id="cash-payment-form" class="hidden rounded-lg bg-white shadow p-5">
                        <h3 class="mb-4 text-base font-semibold text-gray-900">Record Cash Payment</h3>
                        <form method="POST" action="{{ route('orders.cash-payment', $order) }}" class="flex items-end gap-4">
                            @csrf
                            <div>
                                <label for="amount" class="block text-sm font-medium text-gray-700">Amount</label>
                                <input type="number"
                                       id="amount"
                                       name="amount"
                                       step="0.01"
                                       min="0.01"
                                       value="{{ number_format($order->grand_total, 2) }}"
                                       class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                       required />
                            </div>
                            <button type="submit"
                                    class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                                Confirm Payment
                            </button>
                        </form>
                    </div>
                @endif
            @endcan

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                {{-- Order Info --}}
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Order Details</h3>
                    </div>
                    <dl class="divide-y divide-gray-200">
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Customer</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $order->customer->name ?? '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Source</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $order->source->label() }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Created By</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $order->createdBy->name ?? '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Created At</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $order->created_at->format('M d, Y g:i A') }}</dd>
                        </div>
                        @if($order->shipped_at)
                            <div class="grid grid-cols-3 gap-4 px-6 py-3">
                                <dt class="text-sm font-medium text-gray-500">Shipped At</dt>
                                <dd class="col-span-2 text-sm text-gray-900">{{ $order->shipped_at->format('M d, Y') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Totals --}}
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Totals</h3>
                    </div>
                    <dl class="divide-y divide-gray-200">
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Subtotal</dt>
                            <dd class="col-span-2 text-sm text-gray-900">${{ number_format($order->subtotal, 2) }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Fees</dt>
                            <dd class="col-span-2 text-sm text-gray-900">${{ number_format($order->fees, 2) }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Shipping</dt>
                            <dd class="col-span-2 text-sm text-gray-900">${{ number_format($order->shipping, 2) }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-6 py-3 bg-gray-50">
                            <dt class="text-sm font-bold text-gray-700">Grand Total</dt>
                            <dd class="col-span-2 text-sm font-bold text-gray-900">${{ number_format($order->grand_total, 2) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Line Items --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Line Items</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">SKU</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Serial</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Unit Price</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Tax</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($order->lines as $line)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $line->product_name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $line->sku }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">
                                    @if($line->inventorySerial)
                                        <a href="{{ route('inventory-serials.show', $line->inventorySerial) }}"
                                           class="font-mono text-xs text-indigo-600 hover:underline">
                                            {{ $line->inventorySerial->serial_number }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900">${{ number_format($line->unit_price, 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-500">{{ number_format($line->tax_rate, 2) }}%</td>
                                <td class="px-4 py-3 text-right text-sm font-medium text-gray-900">${{ number_format($line->line_total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">No line items.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Fees --}}
            @if($order->orderFees->isNotEmpty())
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Additional Fees</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Description</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($order->orderFees as $fee)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $fee->name }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-900">${{ number_format($fee->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Payments --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Payments</h3>
                </div>
                @if($order->payments->isEmpty())
                    <div class="py-8 text-center text-sm text-gray-500">No payments recorded.</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Method</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Recorded By</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($order->payments as $payment)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $payment->method->label() }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-900">${{ number_format($payment->amount, 2) }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="rounded-full px-2 py-1 text-xs font-medium bg-{{ $payment->status->color() }}-100 text-{{ $payment->status->color() }}-700">
                                            {{ $payment->status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $payment->createdBy->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $payment->created_at->format('M d, Y g:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Event Timeline --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Activity</h3>
                </div>
                @if($order->events->isEmpty())
                    <div class="py-8 text-center text-sm text-gray-500">No activity recorded.</div>
                @else
                    <ul class="divide-y divide-gray-200">
                        @foreach($order->events as $event)
                            <li class="px-6 py-4 flex items-start gap-4">
                                <div class="mt-1 h-2 w-2 flex-shrink-0 rounded-full bg-indigo-400"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900">
                                        {{ str_replace('_', ' ', ucfirst($event->event)) }}
                                    </p>
                                    @if($event->metadata)
                                        <p class="mt-0.5 text-xs text-gray-500">
                                            @foreach($event->metadata as $key => $val)
                                                <span class="mr-3"><span class="font-medium">{{ str_replace('_', ' ', $key) }}:</span> {{ $val }}</span>
                                            @endforeach
                                        </p>
                                    @endif
                                </div>
                                <span class="flex-shrink-0 text-xs text-gray-400">{{ $event->created_at->format('M d, Y g:i A') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
