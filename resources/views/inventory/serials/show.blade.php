<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="font-mono text-xl font-semibold leading-tight text-gray-800">
                    {{ $serial->serial_number }}
                </h2>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $serial->status->badgeClasses() }}">
                    {{ $serial->status->label() }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                @can('update', $serial)
                    <a href="{{ route('inventory-serials.edit', $serial) }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Edit Notes
                    </a>
                @endcan
                {{-- Status changes go through the movement module as an adjustment --}}
                @can('create', App\Models\InventoryMovement::class)
                    @if ($serial->isInStock())
                        <a href="{{ route('inventory-movements.create', ['serial_id' => $serial->id, 'type' => 'adjustment']) }}"
                           class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Record Adjustment
                        </a>
                    @endif
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            <div class="mb-2">
                <a href="{{ route('inventory-serials.index') }}"
                   class="text-sm text-indigo-600 hover:underline">← Back to Serials</a>
            </div>

            {{-- Detail card --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="p-6">
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Serial Number</dt>
                            <dd class="mt-1 font-mono text-sm font-medium text-gray-900">{{ $serial->serial_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Product</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                    {{ $serial->product->sku }}
                                </span>
                                {{ $serial->product->name }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Current Location</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if ($serial->location)
                                    <span class="font-mono">{{ $serial->location->code }}</span>
                                    — {{ $serial->location->name }}
                                @else
                                    <span class="text-gray-400">Not on shelf</span>
                                @endif
                            </dd>
                        </div>
                        @can('viewPurchasePrice', $serial)
                            <div>
                                <dt class="text-xs font-medium uppercase text-gray-500">
                                    Purchase Price <span class="normal-case text-gray-400">(internal)</span>
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900">${{ number_format($serial->purchase_price, 2) }}</dd>
                            </div>
                        @endcan
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Received Date</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $serial->received_at->format('M d, Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Received By</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $serial->receivedBy->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Supplier</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $serial->supplier_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Status</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $serial->status->badgeClasses() }}">
                                    {{ $serial->status->label() }}
                                </span>
                            </dd>
                        </div>
                    </dl>

                    @if ($serial->notes)
                        <div class="mt-4 border-t border-gray-100 pt-4">
                            <dt class="text-xs font-medium uppercase text-gray-500">Notes</dt>
                            <dd class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $serial->notes }}</dd>
                        </div>
                    @endif

                    <div class="mt-4 flex gap-6 border-t border-gray-100 pt-4 text-xs text-gray-400">
                        <span>Created {{ $serial->created_at->format('M d, Y H:i') }}</span>
                        <span>Updated {{ $serial->updated_at->format('M d, Y H:i') }}</span>
                    </div>
                </div>
            </div>

            {{-- Movement history --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-sm font-semibold text-gray-900">Movement History</h3>
                </div>
                @if ($movements->isEmpty())
                    <p class="px-6 py-8 text-center text-sm text-gray-400">No movements recorded.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">From</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">To</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($movements as $movement)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-sm text-gray-600">
                                        {{ $movement->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium capitalize text-gray-700">
                                            {{ $movement->type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 font-mono text-sm text-gray-600">
                                        {{ $movement->fromLocation?->code ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2 font-mono text-sm text-gray-600">
                                        {{ $movement->toLocation?->code ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600">
                                        {{ $movement->notes ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="px-6 py-4">
                        {{ $movements->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
