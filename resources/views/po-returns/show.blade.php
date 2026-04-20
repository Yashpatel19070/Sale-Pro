<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ $purchaseOrder->po_number }} — Return
            </h2>
            <div class="flex items-center gap-2">
                @can('close', $purchaseOrder)
                    @if ($purchaseOrder->status === \App\Enums\PoStatus::Open)
                        <form method="POST" action="{{ route('po-returns.close', $purchaseOrder) }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                                Mark Closed
                            </button>
                        </form>
                    @endif
                @endcan
                <a href="{{ route('po-returns.index') }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                    Back to List
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>
            @endif

            @if ($errors->has('return'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first('return') }}</div>
            @endif

            {{-- Header --}}
            <div class="rounded-lg bg-white p-6 shadow">
                <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium uppercase text-gray-500">Status</dt>
                        <dd class="mt-1">
                            @include('purchase-orders._status_badge', ['status' => $purchaseOrder->status])
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-gray-500">Supplier</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $purchaseOrder->supplier->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-gray-500">Parent PO</dt>
                        <dd class="mt-1 text-sm">
                            @if ($purchaseOrder->parentPo)
                                <a href="{{ route('purchase-orders.show', $purchaseOrder->parentPo) }}"
                                   class="text-indigo-600 hover:text-indigo-900">
                                    {{ $purchaseOrder->parentPo->po_number }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-gray-500">Created By</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $purchaseOrder->createdBy?->name ?? 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-gray-500">Created</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $purchaseOrder->created_at->format('d M Y H:i') }}</dd>
                    </div>
                    @if ($purchaseOrder->closed_at)
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Closed</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $purchaseOrder->closed_at->format('d M Y H:i') }}</dd>
                        </div>
                    @endif
                </dl>
                @if ($purchaseOrder->notes)
                    <div class="mt-4 border-t pt-4">
                        <dt class="text-xs font-medium uppercase text-gray-500">Notes</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $purchaseOrder->notes }}</dd>
                    </div>
                @endif
            </div>

            {{-- Lines --}}
            <div class="rounded-lg bg-white shadow">
                <div class="border-b px-6 py-4">
                    <h3 class="text-sm font-semibold text-gray-900">Return Lines</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Qty</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Unit Price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($purchaseOrder->lines as $line)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $line->product->sku }} — {{ $line->product->name }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900">{{ $line->qty_ordered }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900">${{ number_format($line->unit_price, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-400">No lines.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>
