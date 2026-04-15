<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('inventory.by-sku', $product) }}"
               class="text-sm text-gray-500 hover:text-gray-700">← {{ $product->sku }}</a>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Serials: <span class="font-mono">{{ $product->sku }}</span>
                at <span class="font-mono">{{ $location->code }}</span>
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            {{-- Summary card --}}
            <div class="mb-6 rounded-lg bg-white p-4 shadow">
                <p class="text-sm text-gray-600">
                    <span class="font-medium text-gray-800">Product:</span> {{ $product->name }}
                    &nbsp;|&nbsp;
                    <span class="font-medium text-gray-800">Location:</span> {{ $location->name }}
                    &nbsp;|&nbsp;
                    <span class="font-medium text-gray-800">Units:</span>
                    <span class="font-semibold text-indigo-700">{{ $serials->count() }}</span>
                </p>
            </div>

            @if ($serials->isEmpty())
                <div class="rounded-lg bg-white p-8 text-center shadow">
                    <p class="text-sm text-gray-500">
                        No in_stock serials for this SKU at this location.
                    </p>
                </div>
            @else
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Serial Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Received At</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($serials as $serial)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 font-mono text-sm text-gray-700">
                                        {{ $serial->serial_number }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                        {{ $serial->received_at->format('d M Y') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <a href="{{ route('inventory-serials.show', $serial) }}"
                                           class="text-indigo-600 hover:text-indigo-900">
                                            Detail
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
