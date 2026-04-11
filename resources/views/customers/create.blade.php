<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('customers.index') }}" class="text-sm text-gray-400 hover:text-gray-600">Customers</a>
            <span class="text-gray-300">/</span>
            <span class="text-sm font-medium text-gray-700">New Customer</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            @include('partials.flash')
            @include('customers._form', ['customer' => null])
        </div>
    </div>
</x-app-layout>
