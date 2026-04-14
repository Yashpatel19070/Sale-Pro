# InventoryLocation Module — Views

All views extend `<x-app-layout>`. Tailwind CSS v3 only — no inline styles, no JS frameworks.

---

## View Files

| File | Route |
|------|-------|
| `resources/views/inventory/locations/index.blade.php` | GET /admin/inventory-locations |
| `resources/views/inventory/locations/show.blade.php` | GET /admin/inventory-locations/{inventoryLocation} |
| `resources/views/inventory/locations/create.blade.php` | GET /admin/inventory-locations/create |
| `resources/views/inventory/locations/edit.blade.php` | GET /admin/inventory-locations/{inventoryLocation}/edit |
| `resources/views/inventory/locations/_form.blade.php` | Partial — included by create + edit |

---

## 1. index.blade.php

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Inventory Locations</h2>
            @can('create', App\Models\InventoryLocation::class)
                <a href="{{ route('inventory-locations.create') }}"
                   class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Add Location
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

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

            {{-- Filter bar --}}
            <form method="GET" action="{{ route('inventory-locations.index') }}" class="mb-6 flex flex-wrap gap-3">
                <input type="text"
                       name="search"
                       value="{{ $filters['search'] ?? '' }}"
                       placeholder="Search code or name..."
                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />

                <select name="status"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    <option value="active"   {{ ($filters['status'] ?? '') === 'active'   ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>

                <button type="submit"
                        class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white hover:bg-gray-700">
                    Filter
                </button>
                <a href="{{ route('inventory-locations.index') }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                    Clear
                </a>
            </form>

            {{-- Table --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                @if($locations->isEmpty())
                    <div class="py-16 text-center text-gray-500">No inventory locations found.</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($locations as $location)
                                <tr class="{{ $location->trashed() ? 'opacity-50' : '' }}">
                                    <td class="px-6 py-4 text-sm font-mono font-medium text-gray-900">
                                        {{ $location->code }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $location->name }}
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        @if($location->is_active && ! $location->trashed())
                                            <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">Active</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-800">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ $location->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex items-center gap-3">
                                            @if(! $location->trashed())
                                                <a href="{{ route('inventory-locations.show', $location) }}"
                                                   class="text-indigo-600 hover:text-indigo-900">View</a>

                                                @can('update', $location)
                                                    <a href="{{ route('inventory-locations.edit', $location) }}"
                                                       class="text-yellow-600 hover:text-yellow-900">Edit</a>
                                                @endcan

                                                @can('delete', $location)
                                                    <form method="POST"
                                                          action="{{ route('inventory-locations.destroy', $location) }}"
                                                          onsubmit="return confirm('Deactivate location {{ $location->code }}?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                                class="text-red-600 hover:text-red-900">
                                                            Deactivate
                                                        </button>
                                                    </form>
                                                @endcan
                                            @else
                                                @can('restore', $location)
                                                    <form method="POST"
                                                          action="{{ route('inventory-locations.restore', $location->id) }}">
                                                        @csrf
                                                        <button type="submit"
                                                                class="text-green-600 hover:text-green-900">
                                                            Restore
                                                        </button>
                                                    </form>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $locations->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
```

---

## 2. show.blade.php

```blade
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
                                  onsubmit="return confirm('Deactivate location {{ $location->code }}? This cannot be done if active serials are on it.')">
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
```

---

## 3. create.blade.php

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">New Inventory Location</h2>
            <a href="{{ route('inventory-locations.index') }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <form method="POST" action="{{ route('inventory-locations.store') }}" class="px-6 py-5">
                    @csrf
                    @include('inventory.locations._form', ['location' => null])

                    <div class="mt-6 flex items-center gap-4">
                        <button type="submit"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Create Location
                        </button>
                        <a href="{{ route('inventory-locations.index') }}"
                           class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
```

---

## 4. edit.blade.php

```blade
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
```

---

## 5. _form.blade.php

```blade
{{--
  Shared form partial — included by create.blade.php and edit.blade.php.
  $location is null on create, an InventoryLocation model on edit.
--}}

@if($errors->any())
    <div class="mb-4 rounded-md bg-red-50 px-4 py-3">
        <ul class="list-disc pl-5 text-sm text-red-700">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Code — shown on create only; read-only display on edit --}}
@if($location === null)
    <div class="mb-4">
        <label for="code" class="block text-sm font-medium text-gray-700">
            Location Code <span class="text-red-500">*</span>
        </label>
        <input type="text"
               id="code"
               name="code"
               value="{{ old('code') }}"
               maxlength="20"
               placeholder="e.g. L1, L99, ZONE-A"
               class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 uppercase
                      @error('code') border-red-500 @enderror" />
        @error('code')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
        <p class="mt-1 text-xs text-gray-500">Letters, numbers, hyphens, underscores only. Cannot be changed after creation.</p>
    </div>
@else
    <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">Location Code</label>
        <p class="mt-1 font-mono text-sm font-semibold text-gray-900">{{ $location->code }}</p>
        <p class="text-xs text-gray-500">Code cannot be changed after creation.</p>
    </div>
@endif

{{-- Name --}}
<div class="mb-4">
    <label for="name" class="block text-sm font-medium text-gray-700">
        Name <span class="text-red-500">*</span>
    </label>
    <input type="text"
           id="name"
           name="name"
           value="{{ old('name', $location?->name) }}"
           maxlength="100"
           placeholder="e.g. Shelf L1 Row A"
           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500
                  @error('name') border-red-500 @enderror" />
    @error('name')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

{{-- Description --}}
<div class="mb-4">
    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
    <textarea id="description"
              name="description"
              rows="3"
              maxlength="1000"
              placeholder="Optional notes about this location..."
              class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500
                     @error('description') border-red-500 @enderror">{{ old('description', $location?->description) }}</textarea>
    @error('description')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
```
