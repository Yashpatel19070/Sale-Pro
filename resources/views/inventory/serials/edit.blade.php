<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Edit Serial — <span class="font-mono">{{ $serial->serial_number }}</span>
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            <div class="mb-4">
                <a href="{{ route('inventory-serials.show', $serial) }}"
                   class="text-sm text-indigo-600 hover:underline">← Back to Serial</a>
            </div>

            {{-- Read-only immutable fields --}}
            <div class="mb-4 rounded bg-gray-50 p-4">
                <p class="text-sm text-gray-500">Serial Number</p>
                <p class="font-mono font-semibold">{{ $serial->serial_number }}</p>
                @can('viewPurchasePrice', $serial)
                    <p class="mt-2 text-sm text-gray-500">Purchase Price</p>
                    <p class="font-semibold">${{ number_format($serial->purchase_price, 2) }}</p>
                @endcan
                <p class="mt-1 text-xs text-gray-400">These fields cannot be changed after receipt.</p>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="p-6">
                    <form method="POST" action="{{ route('inventory-serials.update', $serial) }}">
                        @csrf
                        @method('PUT')
                        @include('inventory.serials._form', ['serial' => $serial, 'editMode' => true])
                        <div class="mt-6 flex justify-end gap-3">
                            <a href="{{ route('inventory-serials.show', $serial) }}"
                               class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </a>
                            <button type="submit"
                                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
