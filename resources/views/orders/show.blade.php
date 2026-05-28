<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Order {{ $order->number }}
            </h2>
            <div class="flex gap-2">
                @if($order->status === \App\Enums\OrderStatus::Pending)
                    @can('update', $order)
                        <a href="{{ route('orders.edit', $order) }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Edit</a>
                    @endcan
                    @can('delete', $order)
                        <form method="POST" action="{{ route('orders.destroy', $order) }}" class="inline" onsubmit="return confirm('Permanently delete this order?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm text-red-700 hover:bg-red-50">Delete</button>
                        </form>
                    @endcan
                    @can('recordCashPayment', $order)
                        @if($order->payment_status === \App\Enums\PaymentStatus::Unpaid)
                            <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-pay-modal'))" class="rounded-md bg-green-600 px-3 py-1.5 text-sm text-white hover:bg-green-700">Record Cash Payment</button>
                        @endif
                    @endcan
                @endif
                @if($order->status === \App\Enums\OrderStatus::Processing)
                    @can('complete', $order)
                        <form method="POST" action="{{ route('orders.complete', $order) }}" class="inline">
                            @csrf
                            <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">Mark Complete</button>
                        </form>
                    @endcan
                @endif
                @if($order->status === \App\Enums\OrderStatus::Complete)
                    <a href="{{ route('orders.receipt', $order) }}" target="_blank" class="rounded-md bg-gray-700 px-3 py-1.5 text-sm text-white hover:bg-gray-800">Print Receipt</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
                    @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
                </div>
            @endif

            {{-- Header card --}}
            <div class="rounded-lg bg-white p-5 shadow">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div><div class="text-xs text-gray-500">Customer</div><div class="font-medium">{{ $order->customer->name }}</div></div>
                    <div><div class="text-xs text-gray-500">Source</div><div class="font-medium">{{ $order->source->label() }}</div></div>
                    <div><div class="text-xs text-gray-500">Status</div><div class="font-medium">{{ $order->status->label() }} · {{ $order->payment_status->label() }}</div></div>
                    <div><div class="text-xs text-gray-500">Grand Total</div><div class="text-lg font-bold">${{ number_format((float) $order->grand_total, 2) }}</div></div>
                </div>
            </div>

            {{-- Billing + Shipping snapshots --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-lg bg-white p-5 shadow">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">Billing</h3>
                    @if($order->billing_first_name)
                        <div class="text-sm text-gray-700">
                            <p>{{ $order->billing_first_name }} {{ $order->billing_last_name }}</p>
                            <p>{{ $order->billing_address_line1 }}</p>
                            <p>{{ $order->billing_city }}, {{ $order->billing_state }} {{ $order->billing_postal_code }}</p>
                            @if($order->billing_email)<p>{{ $order->billing_email }}</p>@endif
                        </div>
                    @else
                        <p class="text-sm italic text-gray-400">Not provided</p>
                    @endif
                </div>
                <div class="rounded-lg bg-white p-5 shadow">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">Shipping</h3>
                    @if($order->shipping_first_name)
                        <div class="text-sm text-gray-700">
                            <p>{{ $order->shipping_first_name }} {{ $order->shipping_last_name }}</p>
                            <p>{{ $order->shipping_address_line1 }}</p>
                            <p>{{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_postal_code }}</p>
                        </div>
                    @else
                        <p class="text-sm italic text-gray-400">In-store pickup</p>
                    @endif
                </div>
            </div>

            {{-- Line items --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-100 px-5 py-3 text-sm font-semibold text-gray-700">Line Items</div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Product</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">SKU</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Serial</th>
                            <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Unit</th>
                            <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Tax</th>
                            <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($order->lines as $line)
                            <tr>
                                <td class="px-4 py-2 text-sm">{{ $line->product_name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500">{{ $line->sku }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500">{{ $line->inventorySerial?->serial_number ?? '—' }}</td>
                                <td class="px-4 py-2 text-right text-sm">${{ number_format((float) $line->unit_price, 2) }}</td>
                                <td class="px-4 py-2 text-right text-sm">${{ number_format((float) $line->tax_amount, 2) }}</td>
                                <td class="px-4 py-2 text-right text-sm font-semibold">${{ number_format((float) $line->line_total, 2) }}</td>
                            </tr>
                            @foreach($line->lineFees as $fee)
                                <tr class="bg-gray-50">
                                    <td colspan="3" class="px-4 py-1 pl-8 text-xs text-gray-600">└ {{ $fee->name }}</td>
                                    <td class="px-4 py-1 text-right text-xs text-gray-600">${{ number_format((float) $fee->amount, 2) }}</td>
                                    <td class="px-4 py-1 text-right text-xs text-gray-600">${{ number_format((float) $fee->tax_amount, 2) }}</td>
                                    <td class="px-4 py-1 text-right text-xs font-semibold text-gray-700">${{ number_format((float) $fee->fee_total, 2) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Payment --}}
            @if($order->payments->isNotEmpty())
                <div class="rounded-lg bg-white p-5 shadow">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">Payment</h3>
                    @foreach($order->payments as $p)
                        <p class="text-sm text-gray-700">{{ $p->method->label() }} · ${{ number_format((float) $p->amount, 2) }} · {{ $p->status->label() }} · {{ $p->cash_received_at?->format('M j, Y g:i A') }}</p>
                    @endforeach
                </div>
            @endif

            {{-- Event timeline --}}
            <div class="rounded-lg bg-white p-5 shadow">
                <h3 class="mb-3 text-sm font-semibold text-gray-700">Timeline</h3>
                <ul class="space-y-2">
                    @foreach($order->events as $event)
                        <li class="text-sm">
                            <span class="font-medium text-gray-800">● {{ $event->event->label() }}</span>
                            <span class="text-gray-500">— {{ $event->created_at->format('M j, Y g:i A') }} · by {{ $event->createdBy?->name }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

        </div>
    </div>

    @can('recordCashPayment', $order)
        @if($order->payment_status === \App\Enums\PaymentStatus::Unpaid)
            <div
                data-testid="record-payment-modal"
                x-data="{ payOpen: false }"
                @open-pay-modal.window="payOpen = true"
                x-show="payOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
            >
                <form method="POST" action="{{ route('orders.cash-payment', $order) }}" class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                    @csrf

                    <h3 class="text-lg font-semibold text-gray-800">Record Cash Payment</h3>
                    <p class="mt-1 text-xs text-gray-500">Order {{ $order->number }}</p>

                    @if($errors->any())
                        <div class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                            @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
                        </div>
                    @endif

                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-500">Amount due</span>
                            <span class="font-semibold">${{ number_format((float) $order->grand_total, 2) }}</span>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500">Amount received</label>
                            <input
                                type="number"
                                name="amount"
                                step="0.01"
                                min="0"
                                value="{{ old('amount', $order->grand_total) }}"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"
                            />
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Method</span><span>Cash</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Cashier</span><span>{{ auth()->user()->name }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Received at</span><span>{{ now()->format('M j, Y g:i A') }}</span>
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="payOpen = false" class="rounded-md border border-gray-300 px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="rounded-md bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700">Confirm payment</button>
                    </div>
                </form>
            </div>
        @endif
    @endcan
</x-app-layout>
