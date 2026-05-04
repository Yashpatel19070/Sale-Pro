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

        </div>
    </div>
</x-app-layout>
