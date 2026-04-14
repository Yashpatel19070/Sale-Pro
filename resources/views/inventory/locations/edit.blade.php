<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Edit Location: <span class="font-mono">{{ $location->code }}</span>
            </h2>
            <a href="{{ route('inventory-locations.show', $location) }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                Back to Location
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <form method="POST"
                      action="{{ route('inventory-locations.update', $location) }}"
                      class="px-6 py-5">
                    @csrf
                    @method('PUT')
                    @include('inventory.locations._form', ['location' => $location])

                    <div class="mt-6 flex items-center gap-4">
                        <button type="submit"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Save Changes
                        </button>
                        <a href="{{ route('inventory-locations.show', $location) }}"
                           class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
