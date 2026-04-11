<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 capitalize">
            Edit Permissions — {{ $role->name }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            <form method="POST" action="{{ route('roles.update', $role) }}">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    @foreach ($allPermissions as $resource => $perms)
                        <div class="overflow-hidden rounded-lg bg-white shadow">
                            <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $resource }}</p>
                            </div>
                            <div class="grid grid-cols-1 gap-2 px-4 py-3 sm:grid-cols-2">
                                @foreach ($perms->sortBy('name') as $permission)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox"
                                               name="permissions[]"
                                               value="{{ $permission->name }}"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                               @checked($role->permissions->contains('name', $permission->name))>
                                        <span class="text-sm text-gray-700">{{ $permission->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 flex items-center gap-4">
                    <button type="submit"
                            class="rounded-md bg-indigo-600 px-6 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Save Permissions
                    </button>
                    <a href="{{ route('roles.show', $role) }}"
                       class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
