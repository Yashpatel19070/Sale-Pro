{{--
    Shared form partial for create and edit.
    Variables:
        $serial    — InventorySerial|null        (null = create mode)
        $editMode  — bool                        (true = show only mutable fields)
        $products  — Collection<Product>         (required in create mode)
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
                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('product_id') border-red-300 @enderror">
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
                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('inventory_location_id') border-red-300 @enderror">
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
               class="mt-1 block w-full rounded-md border-gray-300 font-mono uppercase text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('serial_number') border-red-300 @enderror" />
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
                   class="block w-full rounded-md border-gray-300 pl-7 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('purchase_price') border-red-300 @enderror" />
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
               class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('received_at') border-red-300 @enderror" />
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
           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('supplier_name') border-red-300 @enderror" />
    @error('supplier_name')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

{{-- Notes (mutable) --}}
<div class="mb-4">
    <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
    <textarea id="notes" name="notes" rows="4" maxlength="5000"
              placeholder="Optional internal notes…"
              class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('notes') border-red-300 @enderror">{{ old('notes', $serial?->notes) }}</textarea>
    @error('notes')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
