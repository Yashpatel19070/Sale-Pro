<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Edit {{ $purchaseOrder->po_number }}
            </h2>
            <a href="{{ route('purchase-orders.show', $purchaseOrder) }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                Back to Order
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow">
                @if ($errors->has('po'))
                    <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first('po') }}</div>
                @endif

                <form method="POST" action="{{ route('purchase-orders.update', $purchaseOrder) }}">
                    @csrf
                    @method('PATCH')
                    @include('purchase-orders._form')
                    <div class="flex justify-end gap-3">
                        <a href="{{ route('purchase-orders.show', $purchaseOrder) }}"
                           class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Update Purchase Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
