# InventoryMovement Module — Views

## index.blade.php — Movement History Log

```blade
{{-- resources/views/inventory/movements/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Inventory Movement History</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Movement History</h1>
                <p class="mt-1 text-sm text-gray-500">Immutable ledger of every serial number movement.</p>
            </div>
            @can('create', App\Models\InventoryMovement::class)
                <a href="{{ route('inventory-movements.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm
                          font-medium rounded-lg hover:bg-indigo-700 transition">
                    Record Movement
                </a>
            @endcan
        </div>

        {{-- Flash message --}}
        @if (session('success'))
            <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800 border border-green-200">
                {{ session('success') }}
            </div>
        @endif

        {{-- Validation / domain error --}}
        @if ($errors->has('error'))
            <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 border border-red-200">
                {{ $errors->first('error') }}
            </div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('inventory-movements.index') }}"
              class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">

            <div>
                <label for="serial_number" class="block text-xs font-medium text-gray-700 mb-1">
                    Serial Number
                </label>
                <input type="text"
                       id="serial_number"
                       name="serial_number"
                       value="{{ $filters['serial_number'] ?? '' }}"
                       placeholder="SN-00123"
                       class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                              focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="location_id" class="block text-xs font-medium text-gray-700 mb-1">
                    Location
                </label>
                <select id="location_id" name="location_id"
                        class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                               focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All locations</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}"
                                @selected(($filters['location_id'] ?? '') == $location->id)>
                            {{ $location->code }} — {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="type" class="block text-xs font-medium text-gray-700 mb-1">
                    Type
                </label>
                <select id="type" name="type"
                        class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                               focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}"
                                @selected(($filters['type'] ?? '') === $type->value)>
                            {{ $type->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="date_from" class="block text-xs font-medium text-gray-700 mb-1">
                    From date
                </label>
                <input type="date"
                       id="date_from"
                       name="date_from"
                       value="{{ $filters['date_from'] ?? '' }}"
                       class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                              focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="date_to" class="block text-xs font-medium text-gray-700 mb-1">
                    To date
                </label>
                <input type="date"
                       id="date_to"
                       name="date_to"
                       value="{{ $filters['date_to'] ?? '' }}"
                       class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                              focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div class="lg:col-span-5 flex gap-3">
                <button type="submit"
                        class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-lg
                               hover:bg-gray-700 transition">
                    Filter
                </button>
                <a href="{{ route('inventory-movements.index') }}"
                   class="px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-lg border
                          border-gray-300 hover:bg-gray-50 transition">
                    Reset
                </a>
            </div>
        </form>

        {{-- Table --}}
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">
                            Date / Time
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">
                            Type
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">
                            Serial Number
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">
                            Product
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">
                            Direction
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">
                            Reference
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">
                            Recorded by
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($movements as $movement)
                        @php
                            $badgeColors = [
                                'green'  => 'bg-green-100 text-green-800',
                                'blue'   => 'bg-blue-100 text-blue-800',
                                'purple' => 'bg-purple-100 text-purple-800',
                                'yellow' => 'bg-yellow-100 text-yellow-800',
                            ];
                            $badge = $badgeColors[$movement->type->badgeColor()] ?? 'bg-gray-100 text-gray-800';
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                {{ $movement->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5
                                             text-xs font-medium {{ $badge }}">
                                    {{ $movement->type->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-gray-900">
                                {{ $movement->serial->serial_number }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $movement->serial->product->name }}
                                <span class="text-gray-400 text-xs ml-1">
                                    {{ $movement->serial->product->sku }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 font-mono text-xs">
                                {{ $movement->directionLabel() }}
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $movement->reference ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $movement->user->name }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                                No movements found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($movements->hasPages())
            <div class="mt-4">
                {{ $movements->links() }}
            </div>
        @endif

    </div>

</x-app-layout>
```

---

## create.blade.php — Movement Form

```blade
{{-- resources/views/inventory/movements/create.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Record Movement</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Record Movement</h1>
            <p class="mt-1 text-sm text-gray-500">
                Transfer, sell, or adjust a serial number. All movements are permanent and cannot be edited.
            </p>
        </div>

        {{-- Domain error --}}
        @if ($errors->has('error'))
            <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 border border-red-200">
                {{ $errors->first('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('inventory-movements.store') }}" id="movement-form">
            @csrf

            <div class="bg-white shadow-sm rounded-lg divide-y divide-gray-100">

                {{-- Movement Type --}}
                <div class="px-6 py-5">
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        Movement Type <span class="text-red-500">*</span>
                    </label>
                    <div class="flex flex-wrap gap-3" id="type-selector">

                        @can('inventory-movements.transfer')
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="transfer"
                                   class="sr-only peer"
                                   {{ old('type', $selectedType) === 'transfer' ? 'checked' : '' }}>
                            <span class="inline-flex items-center px-4 py-2 rounded-lg border-2
                                         border-gray-200 text-sm font-medium text-gray-700
                                         peer-checked:border-indigo-600 peer-checked:bg-indigo-50
                                         peer-checked:text-indigo-700 hover:border-gray-300 transition">
                                Transfer
                            </span>
                        </label>
                        @endcan

                        @can('inventory-movements.sell')
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="sale"
                                   class="sr-only peer"
                                   {{ old('type', $selectedType) === 'sale' ? 'checked' : '' }}>
                            <span class="inline-flex items-center px-4 py-2 rounded-lg border-2
                                         border-gray-200 text-sm font-medium text-gray-700
                                         peer-checked:border-purple-600 peer-checked:bg-purple-50
                                         peer-checked:text-purple-700 hover:border-gray-300 transition">
                                Sale
                            </span>
                        </label>
                        @endcan

                        @can('inventory-movements.adjust')
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="adjustment"
                                   class="sr-only peer"
                                   {{ old('type', $selectedType) === 'adjustment' ? 'checked' : '' }}>
                            <span class="inline-flex items-center px-4 py-2 rounded-lg border-2
                                         border-gray-200 text-sm font-medium text-gray-700
                                         peer-checked:border-yellow-500 peer-checked:bg-yellow-50
                                         peer-checked:text-yellow-700 hover:border-gray-300 transition">
                                Adjustment
                            </span>
                        </label>
                        @endcan

                    </div>
                    @error('type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Serial Number --}}
                <div class="px-6 py-5">
                    <label for="inventory_serial_id" class="block text-sm font-medium text-gray-700 mb-1">
                        Serial Number <span class="text-red-500">*</span>
                    </label>
                    <select id="inventory_serial_id"
                            name="inventory_serial_id"
                            class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                                   focus:border-indigo-500 focus:ring-indigo-500
                                   @error('inventory_serial_id') border-red-300 @enderror">
                        <option value="">Select a serial…</option>
                        @foreach ($serials as $serial)
                            <option value="{{ $serial->id }}"
                                    data-location="{{ $serial->inventory_location_id }}"
                                    {{ old('inventory_serial_id', $selectedSerial?->id) == $serial->id ? 'selected' : '' }}>
                                {{ $serial->serial_number }} — {{ $serial->product->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('inventory_serial_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- From Location --}}
                <div class="px-6 py-5" id="from-location-row">
                    <label for="from_location_id" class="block text-sm font-medium text-gray-700 mb-1">
                        From Location
                        <span class="text-red-500 transfer-required sale-required">*</span>
                        <span class="text-gray-400 text-xs adjustment-note hidden">(optional)</span>
                    </label>
                    <select id="from_location_id"
                            name="from_location_id"
                            class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                                   focus:border-indigo-500 focus:ring-indigo-500
                                   @error('from_location_id') border-red-300 @enderror">
                        <option value="">None (external / outside warehouse)</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}"
                                    {{ old('from_location_id') == $location->id ? 'selected' : '' }}>
                                {{ $location->code }} — {{ $location->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('from_location_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- To Location --}}
                <div class="px-6 py-5" id="to-location-row">
                    <label for="to_location_id" class="block text-sm font-medium text-gray-700 mb-1">
                        To Location
                        <span class="text-red-500 transfer-required">*</span>
                        <span class="text-gray-400 text-xs sale-note hidden">(leave empty — serial leaves warehouse)</span>
                        <span class="text-gray-400 text-xs adjustment-note hidden">(optional)</span>
                    </label>
                    <select id="to_location_id"
                            name="to_location_id"
                            class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                                   focus:border-indigo-500 focus:ring-indigo-500
                                   @error('to_location_id') border-red-300 @enderror">
                        <option value="">None (external / leaves warehouse)</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}"
                                    {{ old('to_location_id') == $location->id ? 'selected' : '' }}>
                                {{ $location->code }} — {{ $location->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('to_location_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Adjustment Status (only shown for adjustment type) --}}
                <div class="px-6 py-5 hidden" id="adjustment-status-row">
                    <label for="adjustment_status" class="block text-sm font-medium text-gray-700 mb-1">
                        New Status <span class="text-red-500">*</span>
                    </label>
                    <select id="adjustment_status"
                            name="adjustment_status"
                            class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                                   focus:border-indigo-500 focus:ring-indigo-500
                                   @error('adjustment_status') border-red-300 @enderror">
                        <option value="">Select…</option>
                        <option value="damaged"  {{ old('adjustment_status') === 'damaged'  ? 'selected' : '' }}>
                            Damaged
                        </option>
                        <option value="missing"  {{ old('adjustment_status') === 'missing'  ? 'selected' : '' }}>
                            Missing
                        </option>
                    </select>
                    @error('adjustment_status')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Reference --}}
                <div class="px-6 py-5">
                    <label for="reference" class="block text-sm font-medium text-gray-700 mb-1">
                        Reference <span class="text-gray-400 font-normal">(order no., PO number, reason code)</span>
                    </label>
                    <input type="text"
                           id="reference"
                           name="reference"
                           value="{{ old('reference') }}"
                           maxlength="150"
                           placeholder="e.g. ORD-2024-00123"
                           class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                                  focus:border-indigo-500 focus:ring-indigo-500
                                  @error('reference') border-red-300 @enderror">
                    @error('reference')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Notes --}}
                <div class="px-6 py-5">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                        Notes
                    </label>
                    <textarea id="notes"
                              name="notes"
                              rows="3"
                              maxlength="1000"
                              placeholder="Optional details about this movement…"
                              class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                                     focus:border-indigo-500 focus:ring-indigo-500
                                     @error('notes') border-red-300 @enderror">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            {{-- Actions --}}
            <div class="mt-6 flex items-center gap-4">
                <button type="submit"
                        class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg
                               hover:bg-indigo-700 transition">
                    Record Movement
                </button>
                <a href="{{ route('inventory-movements.index') }}"
                   class="px-6 py-2.5 bg-white text-gray-700 text-sm font-medium rounded-lg border
                          border-gray-300 hover:bg-gray-50 transition">
                    Cancel
                </a>
                <p class="text-xs text-gray-400 ml-auto">
                    Movements are permanent and cannot be edited.
                </p>
            </div>

        </form>
    </div>

    {{-- Type-aware form toggling --}}
    <script>
    (function () {
        const adjustmentRow = document.getElementById('adjustment-status-row');
        const toLocationRow  = document.getElementById('to-location-row');
        const radios         = document.querySelectorAll('input[name="type"]');

        function applyType(type) {
            // adjustment status row
            adjustmentRow.classList.toggle('hidden', type !== 'adjustment');

            // to-location: hidden for sale (serial leaves warehouse, no destination)
            toLocationRow.classList.toggle('hidden', type === 'sale');

            // If sale, force clear to_location_id
            if (type === 'sale') {
                document.getElementById('to_location_id').value = '';
            }

            // from_location asterisk (*): required for transfer + sale, hidden for adjustment
            document.querySelectorAll('.transfer-required.sale-required').forEach(function (el) {
                el.classList.toggle('hidden', type === 'adjustment');
            });

            // to_location asterisk (*): required for transfer only
            document.querySelectorAll('.transfer-required:not(.sale-required)').forEach(function (el) {
                el.classList.toggle('hidden', type !== 'transfer');
            });

            // "(optional)" hint on from/to location labels: visible for adjustment only
            document.querySelectorAll('.adjustment-note').forEach(function (el) {
                el.classList.toggle('hidden', type !== 'adjustment');
            });

            // "(leave empty — serial leaves warehouse)" hint: visible for sale only
            document.querySelectorAll('.sale-note').forEach(function (el) {
                el.classList.toggle('hidden', type !== 'sale');
            });
        }

        radios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                applyType(this.value);
            });
        });

        // Apply on page load (handles old() repopulation after validation failure)
        const checked = document.querySelector('input[name="type"]:checked');
        if (checked) { applyType(checked.value); }
    })();
    </script>

</x-app-layout>
```

---

## serial-timeline.blade.php — Serial Movement Timeline (partial embed)

```blade
{{-- resources/views/inventory/movements/serial-timeline.blade.php --}}
{{-- Used on InventorySerial show page via: GET /admin/inventory-serials/{serial}/movements --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Movement Timeline — {{ $inventorySerial->serial_number }}
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <div class="mb-6">
            <a href="{{ route('inventory-serials.show', $inventorySerial) }}"
               class="text-sm text-indigo-600 hover:underline">&larr; Back to serial</a>
            <h1 class="mt-2 text-2xl font-bold text-gray-900">
                Movement Timeline
            </h1>
            <p class="text-sm text-gray-500">
                Serial: <span class="font-mono font-medium">{{ $inventorySerial->serial_number }}</span>
                &mdash; {{ $inventorySerial->product->name }}
                ({{ $inventorySerial->product->sku }})
            </p>
        </div>

        {{-- Timeline --}}
        <ol class="relative border-l border-gray-200 ml-4">
            @forelse ($movements as $movement)
                @php
                    $dotColor = match($movement->type->badgeColor()) {
                        'green'  => 'bg-green-500',
                        'blue'   => 'bg-blue-500',
                        'purple' => 'bg-purple-500',
                        'yellow' => 'bg-yellow-500',
                        default  => 'bg-gray-400',
                    };
                @endphp
                <li class="mb-8 ml-6">
                    <span class="absolute -left-3 flex h-6 w-6 items-center justify-center
                                 rounded-full {{ $dotColor }} ring-4 ring-white"></span>
                    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
                        <div class="flex items-start justify-between mb-1">
                            <span class="text-sm font-semibold text-gray-900">
                                {{ $movement->type->label() }}
                            </span>
                            <time class="text-xs text-gray-400">
                                {{ $movement->created_at->format('Y-m-d H:i') }}
                            </time>
                        </div>
                        <p class="text-sm text-gray-600 font-mono">
                            {{ $movement->directionLabel() }}
                        </p>
                        @if ($movement->reference)
                            <p class="mt-1 text-xs text-gray-500">
                                Reference: {{ $movement->reference }}
                            </p>
                        @endif
                        @if ($movement->notes)
                            <p class="mt-1 text-xs text-gray-500 italic">
                                {{ $movement->notes }}
                            </p>
                        @endif
                        <p class="mt-1 text-xs text-gray-400">
                            Recorded by {{ $movement->user->name }}
                        </p>
                    </div>
                </li>
            @empty
                <li class="ml-6 text-sm text-gray-400">No movements recorded yet.</li>
            @endforelse
        </ol>

    </div>

</x-app-layout>
```

---

## bulk-receive.blade.php — Bulk Serial Generation Form

```blade
{{-- resources/views/inventory/movements/bulk-receive.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Bulk Receive</h2>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Bulk Receive</h1>
            <p class="mt-1 text-sm text-gray-500">
                Auto-generate serial numbers for a batch of units. Serial labels will be ready to print immediately.
            </p>
        </div>

        @if ($errors->has('error'))
            <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 border border-red-200">
                {{ $errors->first('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('inventory-movements.bulk-receive.store') }}"
              x-data="{ qty: {{ old('qty', 1) }} }">
            @csrf

            <div class="bg-white shadow-sm rounded-lg divide-y divide-gray-100">

                {{-- Product --}}
                <div class="px-6 py-5">
                    <label for="product_id" class="block text-sm font-medium text-gray-700 mb-1">
                        Product <span class="text-red-500">*</span>
                    </label>
                    <select id="product_id" name="product_id"
                            class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                                   focus:border-indigo-500 focus:ring-indigo-500
                                   @error('product_id') border-red-300 @enderror">
                        <option value="">Select a product…</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}"
                                    {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                {{ $product->sku }} — {{ $product->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('product_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Quantity --}}
                <div class="px-6 py-5">
                    <label for="qty" class="block text-sm font-medium text-gray-700 mb-1">
                        Quantity <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="qty" name="qty"
                           x-model.number="qty"
                           min="1" max="500"
                           value="{{ old('qty', 1) }}"
                           class="block w-32 rounded-md border-gray-300 shadow-sm text-sm
                                  focus:border-indigo-500 focus:ring-indigo-500
                                  @error('qty') border-red-300 @enderror">
                    <p class="mt-1 text-xs text-gray-400">
                        Will generate <span x-text="qty" class="font-medium text-gray-700"></span>
                        serial numbers. Max 500 per batch.
                    </p>
                    @error('qty')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Location --}}
                <div class="px-6 py-5">
                    <label for="inventory_location_id" class="block text-sm font-medium text-gray-700 mb-1">
                        Receiving Location <span class="text-red-500">*</span>
                    </label>
                    <select id="inventory_location_id" name="inventory_location_id"
                            class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                                   focus:border-indigo-500 focus:ring-indigo-500
                                   @error('inventory_location_id') border-red-300 @enderror">
                        <option value="">Select a location…</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}"
                                    {{ old('inventory_location_id') == $location->id ? 'selected' : '' }}>
                                {{ $location->code }} — {{ $location->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('inventory_location_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Purchase Price --}}
                <div class="px-6 py-5">
                    <label for="purchase_price" class="block text-sm font-medium text-gray-700 mb-1">
                        Purchase Price (per unit) <span class="text-red-500">*</span>
                    </label>
                    <div class="relative w-48">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 text-sm">$</span>
                        <input type="number" id="purchase_price" name="purchase_price"
                               step="0.01" min="0" max="999999.99"
                               value="{{ old('purchase_price') }}"
                               placeholder="0.00"
                               class="block w-full pl-7 rounded-md border-gray-300 shadow-sm text-sm
                                      focus:border-indigo-500 focus:ring-indigo-500
                                      @error('purchase_price') border-red-300 @enderror">
                    </div>
                    @error('purchase_price')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Source Ref --}}
                <div class="px-6 py-5">
                    <label for="source_ref" class="block text-sm font-medium text-gray-700 mb-1">
                        Source Reference
                        <span class="text-gray-400 font-normal">(GRN number, PO number, etc.)</span>
                    </label>
                    <input type="text" id="source_ref" name="source_ref"
                           value="{{ old('source_ref') }}"
                           maxlength="100"
                           placeholder="e.g. GRN-2026-0012"
                           class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                                  focus:border-indigo-500 focus:ring-indigo-500
                                  @error('source_ref') border-red-300 @enderror">
                    @error('source_ref')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            <div class="mt-6 flex items-center gap-4">
                <button type="submit"
                        class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg
                               hover:bg-indigo-700 transition">
                    Generate &amp; Print Labels
                </button>
                <a href="{{ route('inventory-movements.index') }}"
                   class="px-6 py-2.5 bg-white text-gray-700 text-sm font-medium rounded-lg border
                          border-gray-300 hover:bg-gray-50 transition">
                    Cancel
                </a>
            </div>

        </form>
    </div>
</x-app-layout>
```

---

## bulk-receive-print.blade.php — Printable Label Sheet

Standalone full HTML document — does NOT use `<x-app-layout>`.
Renders a grid of barcode labels. JsBarcode generates Code128 SVG inline.

> **Setup:** Download `JsBarcode.all.min.js` from jsDelivr and save to `public/js/jsbarcode.min.js`.
> Loads locally — CDN silently fails without internet in warehouse environments.

```blade
{{-- resources/views/inventory/movements/bulk-receive-print.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serial Labels — {{ $serials->first()?->product->name ?? 'Batch' }}</title>

    <style>
        /* ── Screen ─────────────────────────────── */
        body { font-family: monospace; background: #f3f4f6; padding: 16px; }
        .no-print { text-align: center; margin-bottom: 16px; }
        .no-print button {
            padding: 10px 24px; background: #4f46e5; color: #fff;
            border: none; border-radius: 8px; font-size: 14px; cursor: pointer;
        }
        .no-print a { margin-left: 12px; font-size: 14px; color: #6b7280; text-decoration: none; }
        .label-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .label {
            background: #fff; border: 1px solid #374151;
            padding: 6px 8px; font-size: 9pt;
        }
        .label .sku  { font-weight: bold; font-size: 8pt; color: #374151; }
        .label .name { font-size: 8pt; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .label .sn   { font-weight: bold; font-size: 11pt; margin: 2px 0; letter-spacing: 0.5px; }
        .label .meta { font-size: 7pt; color: #9ca3af; margin-top: 2px; }
        .label svg   { width: 100%; display: block; }

        /* ── Print ──────────────────────────────── */
        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none; }
            .label-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4mm; }
            .label { page-break-inside: avoid; border: 1px solid #000; padding: 3mm; }
            @page { margin: 10mm; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <p style="color:#374151;font-family:sans-serif;margin-bottom:12px">
            <strong>{{ $serials->count() }}</strong> labels —
            {{ $serials->first()?->product->name }}
            &nbsp;·&nbsp; {{ now()->format('Y-m-d') }}
        </p>
        <button onclick="window.print()">Print Labels</button>
        <a href="{{ route('inventory-movements.index') }}">← Back to movements</a>
    </div>

    <div class="label-grid">
        @foreach ($serials as $serial)
        <div class="label">
            <div class="sku">{{ $serial->product->sku }}</div>
            <div class="name">{{ $serial->product->name }}</div>
            <div class="sn">{{ $serial->serial_number }}</div>
            <svg class="barcode" data-serial="{{ $serial->serial_number }}"></svg>
            <div class="meta">
                Rcvd: {{ $serial->received_at->format('Y-m-d') }}
                &nbsp;·&nbsp; {{ $serial->location->code }}
            </div>
        </div>
        @endforeach
    </div>

    <script src="{{ asset('js/jsbarcode.min.js') }}"></script>
    <script>
        document.querySelectorAll('.barcode').forEach(function (el) {
            JsBarcode(el, el.dataset.serial, {
                format:       'CODE128',
                width:        1.5,
                height:       35,
                displayValue: false,
                margin:       2,
            });
        });
    </script>

</body>
</html>
```
