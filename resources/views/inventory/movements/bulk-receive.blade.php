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
