# InventorySerial — Views

All views live under `resources/views/inventory/serials/`.

---

## index.blade.php

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Inventory Serials</h2>
            @can('create', App\Models\InventorySerial::class)
                <a href="{{ route('inventory-serials.create') }}"
                   class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    + Receive Serial
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            {{-- Filters --}}
            <form method="GET" class="mb-4 flex flex-wrap gap-3">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Serial number or SKU…"
                       class="w-64 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />

                <select name="status"
                        class="w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="product_id"
                        class="w-52 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All products</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>
                            [{{ $product->sku }}] {{ $product->name }}
                        </option>
                    @endforeach
                </select>

                <select name="location_id"
                        class="w-44 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All locations</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" @selected(request('location_id') == $loc->id)>
                            {{ $loc->code }} — {{ $loc->name }}
                        </option>
                    @endforeach
                </select>

                <button type="submit"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Filter
                </button>
                <a href="{{ route('inventory-serials.index') }}"
                   class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</a>
            </form>

            {{-- Table --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Serial #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Location</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Received</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($serials as $serial)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('inventory-serials.show', $serial) }}"
                                       class="font-mono text-sm font-medium text-indigo-600 hover:underline">
                                        {{ $serial->serial_number }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                        {{ $serial->product->sku }}
                                    </span>
                                    <span class="ml-1 text-sm text-gray-700">{{ $serial->product->name }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $serial->location?->code ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $serial->status->badgeClasses() }}">
                                        {{ $serial->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $serial->received_at->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        @can('view', $serial)
                                            <a href="{{ route('inventory-serials.show', $serial) }}"
                                               class="text-xs text-indigo-600 hover:underline">View</a>
                                        @endcan
                                        @can('update', $serial)
                                            <a href="{{ route('inventory-serials.edit', $serial) }}"
                                               class="text-xs text-gray-600 hover:text-gray-900">Edit</a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">
                                    No serials found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $serials->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
```

**File path:** `resources/views/inventory/serials/index.blade.php`

---

## show.blade.php

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="font-mono text-xl font-semibold leading-tight text-gray-800">
                    {{ $serial->serial_number }}
                </h2>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $serial->status->badgeClasses() }}">
                    {{ $serial->status->label() }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                @can('update', $serial)
                    <a href="{{ route('inventory-serials.edit', $serial) }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Edit Notes
                    </a>
                @endcan
                {{-- Status changes (damaged/missing) go through the movement module as an adjustment. --}}
                {{-- This ensures every status change creates a movement row in the ledger. --}}
                @can('create', App\Models\InventoryMovement::class)
                    <a href="{{ route('inventory-movements.create', ['serial_id' => $serial->id, 'type' => 'adjustment']) }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Record Adjustment
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

            @include('partials.flash')

            <div class="mb-2">
                <a href="{{ route('inventory-serials.index') }}"
                   class="text-sm text-indigo-600 hover:underline">← Back to Serials</a>
            </div>

            {{-- Detail card --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="p-6">
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Serial Number</dt>
                            <dd class="mt-1 font-mono text-sm font-medium text-gray-900">{{ $serial->serial_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Product</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                    {{ $serial->product->sku }}
                                </span>
                                {{ $serial->product->name }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Current Location</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if ($serial->location)
                                    <span class="font-mono">{{ $serial->location->code }}</span>
                                    — {{ $serial->location->name }}
                                @else
                                    <span class="text-gray-400">Not on shelf</span>
                                @endif
                            </dd>
                        </div>
                        {{-- purchase_price is hidden from sales role (internal cost data) --}}
                        {{-- viewPurchasePrice policy: returns true for admin and manager only --}}
                        @can('viewPurchasePrice', $serial)
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Purchase Price <span class="normal-case text-gray-400">(internal)</span></dt>
                            <dd class="mt-1 text-sm text-gray-900">${{ number_format($serial->purchase_price, 2) }}</dd>
                        </div>
                        @endcan
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Received Date</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $serial->received_at->format('M d, Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Received By</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $serial->receivedBy->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Supplier</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $serial->supplier_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Status</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $serial->status->badgeClasses() }}">
                                    {{ $serial->status->label() }}
                                </span>
                            </dd>
                        </div>
                    </dl>

                    @if ($serial->notes)
                        <div class="mt-4 border-t border-gray-100 pt-4">
                            <dt class="text-xs font-medium uppercase text-gray-500">Notes</dt>
                            <dd class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $serial->notes }}</dd>
                        </div>
                    @endif

                    <div class="mt-4 flex gap-6 border-t border-gray-100 pt-4 text-xs text-gray-400">
                        <span>Created {{ $serial->created_at->format('M d, Y H:i') }}</span>
                        <span>Updated {{ $serial->updated_at->format('M d, Y H:i') }}</span>
                    </div>
                </div>
            </div>

            {{-- Movement history --}}
            {{--
                NOTE: $movements is a separate paginated query loaded by the controller, NOT eager-loaded.
                Controller loads it as:
                    $movements = $serial->movements()->with(['fromLocation', 'toLocation', 'user'])
                        ->latest()
                        ->paginate(15);
                Pass $movements to the view alongside $serial.
            --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-sm font-semibold text-gray-900">Movement History</h3>
                </div>
                @if ($movements->isEmpty())
                    <p class="px-6 py-8 text-center text-sm text-gray-400">No movements recorded.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">From</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">To</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($movements as $movement)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-sm text-gray-600">
                                        {{ $movement->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 capitalize">
                                            {{ $movement->type }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 font-mono text-sm text-gray-600">
                                        {{ $movement->fromLocation?->code ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2 font-mono text-sm text-gray-600">
                                        {{ $movement->toLocation?->code ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600">
                                        {{ $movement->notes ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="px-6 py-4">
                        {{ $movements->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>

</x-app-layout>
```

**File path:** `resources/views/inventory/serials/show.blade.php`

---

## create.blade.php

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Receive New Serial</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            <div class="mb-4">
                <a href="{{ route('inventory-serials.index') }}"
                   class="text-sm text-indigo-600 hover:underline">← Back to Serials</a>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="p-6">
                    <form method="POST" action="{{ route('inventory-serials.store') }}">
                        @csrf
                        @include('inventory.serials._form', ['serial' => null])
                        <div class="mt-6 flex justify-end gap-3">
                            <a href="{{ route('inventory-serials.index') }}"
                               class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </a>
                            <button type="submit"
                                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                Receive Serial
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
```

**File path:** `resources/views/inventory/serials/create.blade.php`

---

## edit.blade.php

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Edit Serial — <span class="font-mono">{{ $serial->serial_number }}</span>
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            <div class="mb-4">
                <a href="{{ route('inventory-serials.show', $serial) }}"
                   class="text-sm text-indigo-600 hover:underline">← Back to Serial</a>
            </div>

            {{-- Read-only fields display --}}
            <div class="bg-gray-50 rounded p-4 mb-4">
                <p class="text-sm text-gray-500">Serial Number</p>
                <p class="font-mono font-semibold">{{ $serial->serial_number }}</p>
                <p class="text-sm text-gray-500 mt-2">Purchase Price</p>
                <p class="font-semibold">{{ number_format($serial->purchase_price, 2) }}</p>
                <p class="text-xs text-gray-400 mt-1">These fields cannot be changed after receipt.</p>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="p-6">
                    <form method="POST" action="{{ route('inventory-serials.update', $serial) }}">
                        @csrf
                        @method('PUT')
                        @include('inventory.serials._form', ['serial' => $serial, 'editMode' => true])
                        <div class="mt-6 flex justify-end gap-3">
                            <a href="{{ route('inventory-serials.show', $serial) }}"
                               class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </a>
                            <button type="submit"
                                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
```

**File path:** `resources/views/inventory/serials/edit.blade.php`

---

## _form.blade.php

```blade
{{--
    Shared form partial for create and edit.
    Variables:
        $serial    — InventorySerial|null   (null = create mode)
        $editMode  — bool                   (true = show only mutable fields)
        $products  — Collection<Product>    (required in create mode)
        $locations — Collection<InventoryLocation> (required in create mode)
--}}

@php $editMode = $editMode ?? false; @endphp

@if (! $editMode)
    {{-- Product --}}
    <div class="mb-4">
        <label for="product_id" class="block text-sm font-medium text-gray-700">
            Product <span class="text-red-500">*</span>
        </label>
        <select id="product_id" name="product_id" required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm @error('product_id') border-red-300 @enderror">
            <option value="">— Select product —</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>
                    [{{ $product->sku }}] {{ $product->name }}
                </option>
            @endforeach
        </select>
        @error('product_id')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Location --}}
    <div class="mb-4">
        <label for="inventory_location_id" class="block text-sm font-medium text-gray-700">
            Shelf Location <span class="text-red-500">*</span>
        </label>
        <select id="inventory_location_id" name="inventory_location_id" required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm @error('inventory_location_id') border-red-300 @enderror">
            <option value="">— Select location —</option>
            @foreach ($locations as $loc)
                <option value="{{ $loc->id }}" @selected(old('inventory_location_id') == $loc->id)>
                    {{ $loc->code }} — {{ $loc->name }}
                </option>
            @endforeach
        </select>
        @error('inventory_location_id')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Serial Number --}}
    <div class="mb-4">
        <label for="serial_number" class="block text-sm font-medium text-gray-700">
            Serial Number <span class="text-red-500">*</span>
        </label>
        <input type="text" id="serial_number" name="serial_number"
               value="{{ old('serial_number') }}"
               required maxlength="100"
               placeholder="e.g. SN-00001"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono uppercase @error('serial_number') border-red-300 @enderror" />
        @error('serial_number')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Purchase Price --}}
    <div class="mb-4">
        <label for="purchase_price" class="block text-sm font-medium text-gray-700">
            Purchase Price <span class="text-red-500">*</span>
            <span class="text-xs font-normal text-gray-400">(internal — not shown to customers)</span>
        </label>
        <div class="relative mt-1">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">$</span>
            <input type="number" id="purchase_price" name="purchase_price"
                   value="{{ old('purchase_price') }}"
                   required min="0" max="9999999.99" step="0.01"
                   class="block w-full rounded-md border-gray-300 pl-7 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm @error('purchase_price') border-red-300 @enderror" />
        </div>
        @error('purchase_price')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Received At --}}
    <div class="mb-4">
        <label for="received_at" class="block text-sm font-medium text-gray-700">
            Received Date <span class="text-red-500">*</span>
        </label>
        <input type="date" id="received_at" name="received_at"
               value="{{ old('received_at', now()->format('Y-m-d')) }}"
               required max="{{ now()->format('Y-m-d') }}"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm @error('received_at') border-red-300 @enderror" />
        @error('received_at')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>
@endif

{{-- Supplier Name (mutable) --}}
<div class="mb-4">
    <label for="supplier_name" class="block text-sm font-medium text-gray-700">Supplier Name</label>
    <input type="text" id="supplier_name" name="supplier_name"
           value="{{ old('supplier_name', $serial?->supplier_name) }}"
           maxlength="150"
           placeholder="Optional"
           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm @error('supplier_name') border-red-300 @enderror" />
    @error('supplier_name')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

{{-- Notes (mutable) --}}
<div class="mb-4">
    <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
    <textarea id="notes" name="notes" rows="4" maxlength="5000"
              placeholder="Optional internal notes…"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm @error('notes') border-red-300 @enderror">{{ old('notes', $serial?->notes) }}</textarea>
    @error('notes')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
```

**File path:** `resources/views/inventory/serials/_form.blade.php`
