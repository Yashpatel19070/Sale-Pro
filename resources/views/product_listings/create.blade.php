<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('product-listings.index') }}"
               class="text-sm text-indigo-600 hover:underline">← Listings</a>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">New Listing</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <form method="POST" action="{{ route('product-listings.store') }}" class="p-6">
                    @csrf

                    @include('product_listings._form', [
                        'listing'           => null,
                        'selectedProductId' => $selectedProductId,
                    ])

                    <div class="mt-6 flex items-center gap-3">
                        <button type="submit"
                                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Create Listing
                        </button>
                        <a href="{{ route('product-listings.index') }}"
                           class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
