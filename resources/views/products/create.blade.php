<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('products.index') }}"
               class="text-sm text-indigo-600 hover:underline">← Products</a>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">New Product</h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <form method="POST" action="{{ route('products.store') }}" class="p-6">
                    @csrf

                    @include('products._form', ['product' => null, 'categories' => $categories])

                    <div class="mt-6 flex items-center gap-3">
                        <x-primary-button>Create Product</x-primary-button>
                        <a href="{{ route('products.index') }}"
                           class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
