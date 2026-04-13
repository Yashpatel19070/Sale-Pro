<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('products.show', $product) }}"
               class="text-sm text-indigo-600 hover:underline">← {{ $product->name }}</a>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Edit Product</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <form method="POST" action="{{ route('products.update', $product) }}" class="p-6">
                    @csrf
                    @method('PATCH')

                    @include('products._form', ['product' => $product, 'categories' => $categories])

                    <div class="mt-6 flex items-center gap-3">
                        <x-primary-button>Save Changes</x-primary-button>
                        <a href="{{ route('products.show', $product) }}"
                           class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
