<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Receive New Serial</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            <div class="mb-4">
                <a href="{{ route('inventory-serials.index') }}"
                   class="text-sm text-indigo-600 hover:underline">← Back to Serials</a>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="p-6">
                    <form method="POST" action="{{ route('inventory-serials.store') }}">
                        @csrf
                        @include('inventory.serials._form', ['serial' => null])
                        <div class="mt-6 flex justify-end gap-3">
                            <a href="{{ route('inventory-serials.index') }}"
                               class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </a>
                            <button type="submit"
                                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                Receive Serial
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
