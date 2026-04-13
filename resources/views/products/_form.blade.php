{{-- $product = existing model on edit, null on create --}}
<div class="space-y-5">

    {{-- SKU --}}
    <div>
        <x-input-label for="sku" value="SKU *" />
        <x-text-input id="sku" name="sku" type="text" class="mt-1 block w-full uppercase"
                      maxlength="64"
                      value="{{ old('sku', $product->sku ?? '') }}"
                      placeholder="e.g. WIDGET-001" required />
        <p class="mt-1 text-xs text-gray-500">Letters, numbers, hyphens and dots only. Changing SKU will regenerate listing slugs.</p>
        <x-input-error :messages="$errors->get('sku')" class="mt-1" />
    </div>

    {{-- Name --}}
    <div>
        <x-input-label for="name" value="Name *" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                      maxlength="200"
                      value="{{ old('name', $product->name ?? '') }}" required autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>

    {{-- Category --}}
    <div>
        <x-input-label for="category_id" value="Category" />
        <select id="category_id" name="category_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            <option value="">— Uncategorised —</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->id }}"
                    @selected(old('category_id', $product->category_id ?? null) == $cat->id)>
                    {{ $cat->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('category_id')" class="mt-1" />
    </div>

    {{-- Prices --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <x-input-label for="regular_price" value="Regular Price *" />
            <x-text-input id="regular_price" name="regular_price" type="number"
                          class="mt-1 block w-full"
                          step="0.01" min="0"
                          value="{{ old('regular_price', $product->regular_price ?? '') }}" required />
            <x-input-error :messages="$errors->get('regular_price')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="sale_price" value="Sale Price" />
            <x-text-input id="sale_price" name="sale_price" type="number"
                          class="mt-1 block w-full"
                          step="0.01" min="0"
                          value="{{ old('sale_price', $product->sale_price ?? '') }}" />
            <p class="mt-1 text-xs text-gray-500">Leave blank if not on sale.</p>
            <x-input-error :messages="$errors->get('sale_price')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="purchase_price" value="Purchase Price" />
            <x-text-input id="purchase_price" name="purchase_price" type="number"
                          class="mt-1 block w-full"
                          step="0.01" min="0"
                          value="{{ old('purchase_price', $product->purchase_price ?? '') }}" />
            <p class="mt-1 text-xs text-gray-500">Internal — not shown to customers.</p>
            <x-input-error :messages="$errors->get('purchase_price')" class="mt-1" />
        </div>
    </div>

    {{-- Description --}}
    <div>
        <x-input-label for="description" value="Description" />
        <textarea id="description" name="description" rows="4"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('description', $product->description ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-1" />
    </div>

    {{-- Notes --}}
    <div>
        <x-input-label for="notes" value="Internal Notes" />
        <textarea id="notes" name="notes" rows="2"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('notes', $product->notes ?? '') }}</textarea>
        <p class="mt-1 text-xs text-gray-500">Not shown to customers.</p>
        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
    </div>

    {{-- Active toggle --}}
    <div class="flex items-center gap-2">
        <input type="hidden" name="is_active" value="0" />
        <input type="checkbox" id="is_active" name="is_active" value="1"
               class="rounded border-gray-300 text-indigo-600 shadow-sm"
               @checked(old('is_active', $product->is_active ?? true)) />
        <x-input-label for="is_active" value="Active" class="mb-0" />
    </div>

</div>
