<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Edit: {{ $category->name }}
            </h2>
            <a href="{{ route('product-categories.show', $category) }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                Back
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="px-6 py-5">
                    <form method="POST" action="{{ route('product-categories.update', $category) }}">
                        @csrf
                        @method('PUT')
                        @include('product_categories._form')
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
