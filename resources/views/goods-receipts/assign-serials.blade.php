<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Assign Serial Numbers — {{ $goodsReceipt->grn_number }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $purchaseOrder->po_number }} &mdash; {{ $purchaseOrder->supplier->name }}
                </p>
            </div>
            <a href="{{ route('purchase-orders.goods-receipts.show', [$purchaseOrder, $goodsReceipt]) }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                Back to GRN
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="rounded-md bg-green-100 px-4 py-3 text-green-800">
                    {{ session('success') }}
                    @if(session('bulk_receive_ids'))
                        &nbsp;
                        <a href="{{ route('inventory-movements.bulk-receive-print') }}"
                           class="font-medium underline hover:no-underline" target="_blank">
                            Print Labels
                        </a>
                    @endif
                </div>
            @endif

            @if($errors->has('error'))
                <div class="rounded-md bg-red-100 px-4 py-3 text-red-800">
                    {{ $errors->first('error') }}
                </div>
            @endif

            {{-- Context summary --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Receipt Summary</h3>
                </div>
                <dl class="divide-y divide-gray-200">
                    <div class="grid grid-cols-3 gap-4 px-6 py-3">
                        <dt class="text-sm font-medium text-gray-500">Received Date</dt>
                        <dd class="col-span-2 text-sm text-gray-900">{{ $goodsReceipt->received_date->format('M d, Y') }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-3">
                        <dt class="text-sm font-medium text-gray-500">Received By</dt>
                        <dd class="col-span-2 text-sm text-gray-900">{{ $goodsReceipt->receivedBy->name ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Serial assignment form --}}
            @php
                $passedLines = $goodsReceipt->lines->filter(fn($l) => $l->qcPassed() > 0);
                $totalPassed = $passedLines->sum(fn($l) => $l->qcPassed());
            @endphp

            @if($passedLines->isEmpty())
                <div class="rounded-lg bg-yellow-50 px-6 py-4 text-yellow-800">
                    No units passed QC on this goods receipt — no serials to assign.
                </div>
            @else
                <form method="POST" action="{{ route('purchase-orders.goods-receipts.storeSerials', [$purchaseOrder, $goodsReceipt]) }}">
                    @csrf

                    <div class="space-y-4">
                        @foreach($passedLines as $i => $line)
                            <div class="overflow-hidden rounded-lg bg-white shadow">
                                <div class="border-b border-gray-200 bg-gray-50 px-6 py-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="text-sm font-medium text-gray-900">
                                                {{ $line->purchaseOrderLine->product->name ?? '—' }}
                                            </span>
                                            <span class="ml-2 text-xs text-gray-500">
                                                {{ $line->purchaseOrderLine->product->sku ?? '' }}
                                            </span>
                                        </div>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">
                                            {{ $line->qcPassed() }} unit{{ $line->qcPassed() !== 1 ? 's' : '' }} to generate
                                        </span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 gap-4 px-6 py-4 sm:grid-cols-2">
                                    <input type="hidden" name="lines[{{ $i }}][goods_receipt_line_id]" value="{{ $line->id }}">

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Qty to Generate</label>
                                        <p class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900">
                                            {{ $line->qcPassed() }}
                                            <span class="text-xs text-gray-500">(from QC — cannot be changed)</span>
                                        </p>
                                    </div>

                                    <div>
                                        <label for="location_{{ $i }}" class="block text-sm font-medium text-gray-700">
                                            Location <span class="text-red-500">*</span>
                                        </label>
                                        <select id="location_{{ $i }}"
                                                name="lines[{{ $i }}][inventory_location_id]"
                                                required
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <option value="">— Select Location —</option>
                                            @foreach($locations as $location)
                                                <option value="{{ $location->id }}">{{ $location->code }} — {{ $location->name }}</option>
                                            @endforeach
                                        </select>
                                        @error("lines.{$i}.inventory_location_id")
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="price_{{ $i }}" class="block text-sm font-medium text-gray-700">
                                            Purchase Price (per unit) <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative mt-1">
                                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 text-sm">$</span>
                                            <input type="number"
                                                   id="price_{{ $i }}"
                                                   name="lines[{{ $i }}][purchase_price]"
                                                   min="0"
                                                   max="999999.99"
                                                   step="0.01"
                                                   required
                                                   value="{{ old("lines.{$i}.purchase_price", $line->purchaseOrderLine->unit_cost) }}"
                                                   class="block w-full rounded-md border-gray-300 pl-7 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        </div>
                                        @error("lines.{$i}.purchase_price")
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 flex items-center justify-between">
                        <a href="{{ route('purchase-orders.goods-receipts.show', [$purchaseOrder, $goodsReceipt]) }}"
                           class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>

                        <button type="submit"
                                class="rounded-md bg-indigo-600 px-6 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Generate {{ $totalPassed }} Serial{{ $totalPassed !== 1 ? 's' : '' }}
                        </button>
                    </div>
                </form>
            @endif

        </div>
    </div>
</x-app-layout>
