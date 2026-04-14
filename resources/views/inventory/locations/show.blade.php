<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Location: <span class="font-mono">{{ $location->code }}</span>
            </h2>
            <div class="flex items-center gap-3">
                @can('update', $location)
                    @if(! $location->trashed())
                        <a href="{{ route('inventory-locations.edit', $location) }}"
                           class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Edit
                        </a>
                    @endif
                @endcan
                <a href="{{ route('inventory-locations.index') }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                    Back to List
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 rounded-md bg-green-100 px-4 py-3 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 rounded-md bg-red-100 px-4 py-3 text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Location detail card --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="px-6 py-5">
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Code</dt>
                            <dd class="mt-1 font-mono text-sm font-semibold text-gray-900">{{ $location->code }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $location->name }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Description</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $location->description ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1 text-sm">
                                @if($location->is_active && ! $location->trashed())
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">Active</span>
                                @else
                                    <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-800">Inactive</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Active Serials on this Location</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $activeSerialCount }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $location->created_at->format('M d, Y H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $location->updated_at->format('M d, Y H:i') }}</dd>
                        </div>
                        @if($location->trashed())
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Deactivated At</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $location->deleted_at->format('M d, Y H:i') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Deactivate / Restore actions --}}
                @if(! $location->trashed())
                    @can('delete', $location)
                        <div class="border-t border-gray-200 bg-gray-50 px-6 py-4">
                            <form method="POST"
                                  action="{{ route('inventory-locations.destroy', $location) }}"
                                  onsubmit="return confirm('Deactivate this location? This cannot be done if active serials are on it.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                                    Deactivate Location
                                </button>
                            </form>
                        </div>
                    @endcan
                @else
                    @can('restore', $location)
                        <div class="border-t border-gray-200 bg-gray-50 px-6 py-4">
                            <form method="POST"
                                  action="{{ route('inventory-locations.restore', $location->id) }}">
                                @csrf
                                <button type="submit"
                                        class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                                    Restore Location
                                </button>
                            </form>
                        </div>
                    @endcan
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
