<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Edit: {{ $department->name }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <form method="POST" action="{{ route('departments.update', $department) }}" class="p-6">
                    @csrf
                    @method('PUT')

                    @include('departments._form')

                    <div class="mt-6 flex items-center gap-3">
                        <x-primary-button>Save Changes</x-primary-button>
                        <a href="{{ route('departments.show', $department) }}"
                           class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
