<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Record Movement</h2>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

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
                        From Location <span class="text-red-500" id="from-required-marker">*</span>
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

                {{-- To Location (transfer only) --}}
                <div class="px-6 py-5" id="to-location-row">
                    <label for="to_location_id" class="block text-sm font-medium text-gray-700 mb-1">
                        To Location <span class="text-red-500">*</span>
                    </label>
                    <select id="to_location_id"
                            name="to_location_id"
                            class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                                   focus:border-indigo-500 focus:ring-indigo-500
                                   @error('to_location_id') border-red-300 @enderror">
                        <option value="">None (leaves warehouse)</option>
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

                {{-- Adjustment Status (adjustment only) --}}
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
                        <option value="damaged" {{ old('adjustment_status') === 'damaged' ? 'selected' : '' }}>
                            Damaged
                        </option>
                        <option value="missing" {{ old('adjustment_status') === 'missing' ? 'selected' : '' }}>
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
        var adjustmentRow = document.getElementById('adjustment-status-row');
        var toLocationRow = document.getElementById('to-location-row');
        var radios        = document.querySelectorAll('input[name="type"]');

        function applyType(type) {
            adjustmentRow.classList.toggle('hidden', type !== 'adjustment');
            toLocationRow.classList.toggle('hidden', type === 'sale');

            if (type === 'sale') {
                document.getElementById('to_location_id').value = '';
            }
        }

        radios.forEach(function (radio) {
            radio.addEventListener('change', function () { applyType(this.value); });
        });

        var checked = document.querySelector('input[name="type"]:checked');
        if (checked) { applyType(checked.value); }
    })();
    </script>

</x-app-layout>
