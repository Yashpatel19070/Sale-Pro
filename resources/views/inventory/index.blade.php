<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Stock Overview
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            @if ($orphanedSerialCount > 0)
                <div class="mb-4 rounded-lg border border-yellow-300 bg-yellow-50 p-4">
                    <p class="text-sm text-yellow-800">
                        <strong>{{ $orphanedSerialCount }}</strong>
                        {{ Str::plural('serial', $orphanedSerialCount) }} not shown — their product has been archived.
                        Contact an admin to reassign or write them off.
                    </p>
                </div>
            @endif

            @if ($stockOverview->isEmpty())
                <div class="rounded-lg bg-white p-8 text-center shadow">
                    <p class="text-sm text-gray-500">No stock on hand. No serials with status <em>in_stock</em> found.</p>
                </div>
            @else
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">SKU</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product Name</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Qty On Hand</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($stockOverview as $productId => $serials)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 font-mono text-sm text-gray-700">
                                        {{ $serials->first()->product->sku }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $serials->first()->product->name }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-semibold text-gray-900">
                                        {{ $serials->count() }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <a href="{{ route('inventory.by-sku', $serials->first()->product) }}"
                                           class="text-indigo-600 hover:text-indigo-900">
                                            View
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
