<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Record Goods Receipt</h2>
                <p class="mt-1 text-sm text-gray-500">
                    PO: <a href="{{ route('purchase-orders.show', $purchaseOrder) }}"
                           class="text-indigo-600 hover:text-indigo-900">{{ $purchaseOrder->po_number }}</a>
                    &mdash; {{ $purchaseOrder->supplier->name }}
                </p>
            </div>
            <a href="{{ route('purchase-orders.show', $purchaseOrder) }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                Cancel
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

            {{-- PO summary --}}
            <div class="mb-6 rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-800">
                <strong>{{ $purchaseOrder->po_number }}</strong>
                &mdash; {{ $purchaseOrder->supplier->name }}
                &mdash;
                <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $purchaseOrder->status->color() }}-100 text-{{ $purchaseOrder->status->color() }}-700">
                    {{ $purchaseOrder->status->label() }}
                </span>
            </div>

            @if($errors->any())
                <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-red-800">
                    <ul class="list-disc list-inside space-y-1 text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('purchase-orders.goods-receipts.store', $purchaseOrder) }}">
                @csrf

                <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->id }}" />

                {{-- Header fields --}}
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Receipt Details</h3>
                    </div>
                    <div class="grid grid-cols-1 gap-6 px-6 py-5 sm:grid-cols-2">
                        <div>
                            <label for="received_date" class="block text-sm font-medium text-gray-700">
                                Received Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date"
                                   id="received_date"
                                   name="received_date"
                                   value="{{ old('received_date', now()->format('Y-m-d')) }}"
                                   required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('received_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea id="notes"
                                      name="notes"
                                      rows="3"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Lines --}}
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Items to Receive</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Qty Ordered</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Already Received</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Remaining</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Qty to Receive</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach($purchaseOrder->lines as $i => $line)
                                    @php $remaining = $line->qty_ordered - $line->qty_received; @endphp
                                    <tr class="{{ $remaining <= 0 ? 'opacity-50 bg-gray-50' : '' }}">
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            <input type="hidden" name="lines[{{ $i }}][purchase_order_line_id]" value="{{ $line->id }}" />
                                            {{ $line->product->name ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-900">
                                            {{ number_format($line->qty_ordered, 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-900">
                                            {{ number_format($line->qty_received, 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm {{ $remaining <= 0 ? 'text-gray-400' : 'text-gray-900' }}">
                                            {{ number_format($remaining, 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <input type="number"
                                                   name="lines[{{ $i }}][qty_received]"
                                                   value="{{ old("lines.{$i}.qty_received", 0) }}"
                                                   min="0"
                                                   max="{{ $remaining }}"
                                                   step="0.01"
                                                   @disabled($remaining <= 0)
                                                   class="block w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-right disabled:bg-gray-100 disabled:cursor-not-allowed" />
                                            @error("lines.{$i}.qty_received")
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="text"
                                                   name="lines[{{ $i }}][notes]"
                                                   value="{{ old("lines.{$i}.notes") }}"
                                                   placeholder="Optional notes"
                                                   @disabled($remaining <= 0)
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:cursor-not-allowed" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('purchase-orders.show', $purchaseOrder) }}"
                       class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Record Receipt
                    </button>
                </div>

            </form>
        </div>
    </div>
</x-app-layout>
