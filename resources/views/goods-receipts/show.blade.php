<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $goodsReceipt->grn_number }}</h2>
                <span class="px-2 py-1 text-xs font-medium rounded-full bg-{{ $goodsReceipt->status->color() }}-100 text-{{ $goodsReceipt->status->color() }}-700">
                    {{ $goodsReceipt->status->label() }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                @if($goodsReceipt->status === \App\Enums\GoodsReceiptStatus::Draft)
                    @can('update', $goodsReceipt)
                        <a href="{{ route('purchase-orders.goods-receipts.edit', [$purchaseOrder, $goodsReceipt]) }}"
                           class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Edit</a>
                    @endcan
                    @can('update', $goodsReceipt)
                        <form method="POST" action="{{ route('purchase-orders.goods-receipts.complete', [$purchaseOrder, $goodsReceipt]) }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                                Complete
                            </button>
                        </form>
                    @endcan
                    @can('delete', $goodsReceipt)
                        <form method="POST" action="{{ route('purchase-orders.goods-receipts.destroy', [$purchaseOrder, $goodsReceipt]) }}"
                              onsubmit="return confirm('Delete this goods receipt?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                                Delete
                            </button>
                        </form>
                    @endcan
                @endif

                <a href="{{ route('purchase-orders.show', $goodsReceipt->purchaseOrder) }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                    Back to PO
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

            @if(session('error'))
                <div class="rounded-md bg-red-100 px-4 py-3 text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->has('error'))
                <div class="rounded-md bg-red-100 px-4 py-3 text-red-800">
                    {{ $errors->first('error') }}
                </div>
            @endif

            {{-- Summary card --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Receipt Details</h3>
                </div>
                <dl class="divide-y divide-gray-200">
                    <div class="grid grid-cols-3 gap-4 px-6 py-3">
                        <dt class="text-sm font-medium text-gray-500">GRN Number</dt>
                        <dd class="col-span-2 text-sm text-gray-900">{{ $goodsReceipt->grn_number }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-3">
                        <dt class="text-sm font-medium text-gray-500">Purchase Order</dt>
                        <dd class="col-span-2 text-sm text-gray-900">
                            <a href="{{ route('purchase-orders.show', $goodsReceipt->purchaseOrder) }}"
                               class="text-indigo-600 hover:text-indigo-900">
                                {{ $goodsReceipt->purchaseOrder->po_number }}
                            </a>
                            &mdash; {{ $goodsReceipt->purchaseOrder->supplier->name }}
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-3">
                        <dt class="text-sm font-medium text-gray-500">Received Date</dt>
                        <dd class="col-span-2 text-sm text-gray-900">{{ $goodsReceipt->received_date->format('M d, Y') }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-3">
                        <dt class="text-sm font-medium text-gray-500">Received By</dt>
                        <dd class="col-span-2 text-sm text-gray-900">{{ $goodsReceipt->receivedBy->name ?? '—' }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-3">
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="col-span-2 text-sm">
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-{{ $goodsReceipt->status->color() }}-100 text-{{ $goodsReceipt->status->color() }}-700">
                                {{ $goodsReceipt->status->label() }}
                            </span>
                        </dd>
                    </div>
                    @if($goodsReceipt->notes)
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Notes</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $goodsReceipt->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Lines --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Received Items</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Qty Ordered</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Qty Received</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach($goodsReceipt->lines as $line)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    {{ $line->purchaseOrderLine->product->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900">
                                    {{ number_format($line->purchaseOrderLine->qty_ordered, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-medium text-gray-900">
                                    {{ number_format($line->qty_received, 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $line->notes ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- QC Section --}}
            @php
                $qcDone = $goodsReceipt->lines->every(fn($l) => $l->qcDone());
                $showQcForm = $goodsReceipt->status === \App\Enums\GoodsReceiptStatus::Complete
                    && $purchaseOrder->status === \App\Enums\PurchaseOrderStatus::QualityCheck
                    && ! $qcDone;
                $showQcResults = $goodsReceipt->status === \App\Enums\GoodsReceiptStatus::Complete && $qcDone;
            @endphp

            @if($showQcForm)
                @can('update', $goodsReceipt)
                @php
                    $qcLineData = $goodsReceipt->lines->map(fn ($l) => [
                        'id'           => $l->id,
                        'qty_received' => (int) $l->qty_received,
                        'qty_passed'   => 0,
                        'qty_failed'   => 0,
                    ])->values()->all();
                @endphp
                <script>
                window.__qcLines = @json($qcLineData);
                </script>
                <div class="overflow-hidden rounded-lg bg-white shadow"
                     x-data="{
                         lines: window.__qcLines,
                         get allValid() {
                             return this.lines.every(function (l) {
                                 return (l.qty_passed + l.qty_failed) === l.qty_received;
                             });
                         }
                     }">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Quality Check Inspection</h3>
                        <p class="mt-1 text-sm text-gray-500">Enter pass/fail counts for each received line. Pass + Fail must equal Received for every line.</p>
                    </div>

                    <form method="POST" action="{{ route('purchase-orders.goods-receipts.submitQc', [$purchaseOrder, $goodsReceipt]) }}">
                        @csrf
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Received</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Passed</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Failed</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach($goodsReceipt->lines as $i => $line)
                                    <tr>
                                        <input type="hidden" name="lines[{{ $i }}][goods_receipt_line_id]" value="{{ $line->id }}">
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            {{ $line->purchaseOrderLine->product->name ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-900">
                                            {{ (int) $line->qty_received }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <input type="number"
                                                   name="lines[{{ $i }}][qty_passed]"
                                                   min="0"
                                                   max="{{ (int) $line->qty_received }}"
                                                   x-model.number="lines[{{ $i }}].qty_passed"
                                                   class="w-20 rounded-md border-gray-300 text-center text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <input type="number"
                                                   name="lines[{{ $i }}][qty_failed]"
                                                   min="0"
                                                   max="{{ (int) $line->qty_received }}"
                                                   x-model.number="lines[{{ $i }}].qty_failed"
                                                   class="w-20 rounded-md border-gray-300 text-center text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </td>
                                        <td class="px-4 py-3 text-center text-sm"
                                            x-text="(lines[{{ $i }}].qty_passed + lines[{{ $i }}].qty_failed) === lines[{{ $i }}].qty_received
                                                ? '✓'
                                                : (lines[{{ $i }}].qty_passed + lines[{{ $i }}].qty_failed) + ' / ' + lines[{{ $i }}].qty_received">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="border-t border-gray-200 px-6 py-4 flex justify-end">
                            <button type="submit"
                                    :disabled="! allValid"
                                    :class="allValid
                                        ? 'bg-indigo-600 hover:bg-indigo-700 cursor-pointer'
                                        : 'bg-gray-300 cursor-not-allowed'"
                                    class="rounded-md px-6 py-2 text-sm font-medium text-white transition-colors">
                                Submit QC
                            </button>
                        </div>
                    </form>
                </div>
                @endcan
            @endif

            @if($showQcResults)
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900">QC Results</h3>
                        @can('bulkReceive', App\Models\InventoryMovement::class)
                            @php
                                $allowedForSerials = in_array($purchaseOrder->status, [
                                    \App\Enums\PurchaseOrderStatus::PartiallyReceived,
                                    \App\Enums\PurchaseOrderStatus::Received,
                                ]);
                            @endphp
                            @if($allowedForSerials && ! $serialsAssigned)
                                <a href="{{ route('purchase-orders.goods-receipts.assignSerials', [$purchaseOrder, $goodsReceipt]) }}"
                                   class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                    Assign Serial Numbers →
                                </a>
                            @elseif($serialsAssigned)
                                <span class="px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">
                                    Serials Assigned ✓
                                </span>
                            @endif
                        @endcan
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Received</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Passed</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Failed</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Inspected By</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Inspected At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($goodsReceipt->lines as $line)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ $line->purchaseOrderLine->product->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-900">{{ (int) $line->qty_received }}</td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">
                                            {{ $line->qcPassed() }} passed
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        @if($line->qcFailed() > 0)
                                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-700">
                                                {{ $line->qcFailed() }} failed
                                            </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $line->qcInspectedBy?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        {{ $line->qc_inspected_at?->format('M d, Y H:i') ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900" colspan="2">Total</td>
                                <td class="px-4 py-3 text-right text-sm font-medium text-green-700">
                                    {{ $goodsReceipt->lines->sum(fn($l) => $l->qcPassed()) }} passed
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-medium text-red-700">
                                    {{ $goodsReceipt->lines->sum(fn($l) => $l->qcFailed()) }} failed
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
