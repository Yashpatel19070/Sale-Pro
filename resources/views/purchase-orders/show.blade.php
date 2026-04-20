<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ $purchaseOrder->po_number }}
            </h2>
            <div class="flex items-center gap-2">
                @can('update', $purchaseOrder)
                    <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}"
                       class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Edit</a>
                @endcan
                @can('confirm', $purchaseOrder)
                    @if ($purchaseOrder->isEditable())
                        <form method="POST" action="{{ route('purchase-orders.confirm', $purchaseOrder) }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                Confirm Order
                            </button>
                        </form>
                    @endif
                @endcan
                @can('cancel', $purchaseOrder)
                    @if (in_array($purchaseOrder->status, [\App\Enums\PoStatus::Draft, \App\Enums\PoStatus::Open], true))
                        <button onclick="document.getElementById('cancel-modal').classList.remove('hidden')"
                                class="rounded-md border border-red-300 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            Cancel
                        </button>
                    @endif
                @endcan
                @can('reopen', $purchaseOrder)
                    @if ($purchaseOrder->isClosed())
                        <form method="POST" action="{{ route('purchase-orders.reopen', $purchaseOrder) }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-md border border-green-300 px-4 py-2 text-sm text-green-600 hover:bg-green-50">
                                Reopen
                            </button>
                        </form>
                    @endif
                @endcan
                <a href="{{ route('purchase-orders.index') }}"
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

            @if ($errors->has('po'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first('po') }}</div>
            @endif

            @if ($purchaseOrder->isCancelled())
                <div class="rounded-md bg-red-50 p-4">
                    <p class="text-sm font-medium text-red-700">Cancelled</p>
                    @if ($purchaseOrder->cancel_notes)
                        <p class="mt-1 text-sm text-red-600">{{ $purchaseOrder->cancel_notes }}</p>
                    @endif
                </div>
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
                        <dt class="text-xs font-medium uppercase text-gray-500">Type</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $purchaseOrder->type->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-gray-500">Supplier</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $purchaseOrder->supplier->name }} ({{ $purchaseOrder->supplier->code }})</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-gray-500">Created By</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $purchaseOrder->createdBy?->name ?? 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-gray-500">Created</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $purchaseOrder->created_at->format('d M Y H:i') }}</dd>
                    </div>
                    @if ($purchaseOrder->confirmed_at)
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Confirmed</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $purchaseOrder->confirmed_at->format('d M Y H:i') }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs font-medium uppercase text-gray-500">Skip Tech</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $purchaseOrder->skip_tech ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase text-gray-500">Skip QA</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $purchaseOrder->skip_qa ? 'Yes' : 'No' }}</dd>
                    </div>
                    @if ($purchaseOrder->reopen_count > 0)
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Reopen Count</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $purchaseOrder->reopen_count }}</dd>
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
                    <h3 class="text-sm font-semibold text-gray-900">Order Lines</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Ordered</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Received</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Unit Price</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Progress</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($purchaseOrder->lines as $line)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    {{ $line->product->sku }} — {{ $line->product->name }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900">{{ $line->qty_ordered }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900">{{ $line->qty_received }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900">${{ number_format($line->unit_price, 2) }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900">${{ $line->lineTotalFormatted() }}</td>
                                <td class="px-6 py-4">
                                    @php($progress = $line->progressPercent())
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-24 overflow-hidden rounded-full bg-gray-200">
                                            <div class="h-2 rounded-full {{ $line->isFulfilled() ? 'bg-green-500' : 'bg-blue-500' }}"
                                                 style="width: {{ $progress }}%">
                                            </div>
                                        </div>
                                        <span class="text-xs text-gray-500">{{ $progress }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-400">No lines.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($purchaseOrder->unitJobs->count() > 0)
                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-sm font-semibold text-gray-900">Unit Jobs ({{ $purchaseOrder->unitJobs->count() }})</h3>
                    <p class="mt-1 text-sm text-gray-500">Pipeline processing tracked separately.</p>
                </div>
            @endif

        </div>
    </div>

    {{-- Cancel modal --}}
    <div id="cancel-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-gray-900">Cancel Purchase Order</h3>
            <p class="mt-1 text-sm text-gray-500">Provide a reason for cancellation (required, min 10 characters).</p>
            <form method="POST" action="{{ route('purchase-orders.cancel', $purchaseOrder) }}" class="mt-4">
                @csrf
                <textarea name="cancel_notes" rows="3" required minlength="10"
                          class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="Reason for cancellation…"></textarea>
                @error('cancel_notes')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
                <div class="mt-4 flex justify-end gap-3">
                    <button type="button"
                            onclick="document.getElementById('cancel-modal').classList.add('hidden')"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                        Back
                    </button>
                    <button type="submit"
                            class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                        Confirm Cancellation
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
