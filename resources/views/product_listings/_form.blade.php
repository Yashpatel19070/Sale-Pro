{{-- $listing = existing model on edit, null on create --}}
{{-- $products = dropdown collection (create only) --}}
{{-- $visibilities = ListingVisibility::options() --}}
{{-- $selectedProductId = pre-selected product_id from query param (create only) --}}
<div class="space-y-5">

    {{-- Product — select on create, read-only on edit --}}
    @if ($listing === null)
        <div>
            <x-input-label for="product_id" value="Product *" />
            <select id="product_id" name="product_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    required>
                <option value="">— Select a product —</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}"
                            data-regular="{{ $product->regular_price }}"
                            data-sale="{{ $product->sale_price }}"
                            @selected(old('product_id', $selectedProductId ?? null) == $product->id)>
                        {{ $product->sku }} — {{ $product->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('product_id')" class="mt-1" />

            {{-- Read-only price info shown after product selection --}}
            <div id="price-info" class="mt-2 hidden rounded-md bg-blue-50 p-3 text-sm text-blue-800">
                Prices from selected product —
                Regular: <span id="price-regular" class="font-medium"></span>
                <span id="price-sale-wrap" class="hidden"> / Sale: <span id="price-sale" class="font-medium text-green-700"></span></span>
            </div>
        </div>
    @else
        <div>
            <x-input-label value="Product" />
            <p class="mt-1 text-sm text-gray-900">
                <a href="{{ route('products.show', $listing->product) }}" class="text-indigo-600 hover:underline">
                    {{ $listing->product->name }}
                </a>
                <span class="ml-2 text-xs text-gray-400">(cannot be changed)</span>
            </p>
            {{-- Read-only product context panel --}}
            <div class="mt-2 rounded-md bg-blue-50 p-3 text-sm text-blue-800 space-y-1">
                <div>
                    <span class="font-medium">SKU:</span> {{ $listing->product->sku }}
                    @if ($listing->product->category)
                        &nbsp;·&nbsp; <span class="font-medium">Category:</span> {{ $listing->product->category->name }}
                    @endif
                </div>
                <div>
                    <span class="font-medium">Regular:</span> ${{ $listing->product->regular_price }}
                    @if ($listing->product->sale_price)
                        &nbsp;·&nbsp; <span class="font-medium text-green-700">Sale: ${{ $listing->product->sale_price }}</span>
                    @endif
                    <span class="text-xs text-blue-600">(prices managed on the product)</span>
                </div>
            </div>
        </div>
    @endif

    {{-- Title --}}
    <div>
        <x-input-label for="title" value="Title *" />
        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full"
                      maxlength="200"
                      value="{{ old('title', $listing->title ?? '') }}"
                      placeholder="e.g. Blue / XL" required autofocus />
        <p class="mt-1 text-xs text-gray-500">Human-readable label for this listing. Changing the title regenerates the slug.</p>
        <x-input-error :messages="$errors->get('title')" class="mt-1" />
    </div>

    {{-- Visibility --}}
    <div>
        <x-input-label for="visibility" value="Visibility *" />
        <select id="visibility" name="visibility"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                required>
            @foreach ($visibilities as $value => $label)
                <option value="{{ $value }}"
                    @selected(old('visibility', $listing->visibility->value ?? 'draft') === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('visibility')" class="mt-1" />
    </div>

    {{-- Active --}}
    <div class="flex items-center gap-2">
        <input type="hidden" name="is_active" value="0" />
        <input type="checkbox" id="is_active" name="is_active" value="1"
               class="rounded border-gray-300 text-indigo-600 shadow-sm"
               @checked(old('is_active', $listing->is_active ?? true)) />
        <x-input-label for="is_active" value="Active" class="mb-0" />
    </div>

</div>

@if ($listing === null)
{{-- JS: show price info when product is selected on create form --}}
<script>
    document.getElementById('product_id').addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        const info = document.getElementById('price-info');
        const regular = document.getElementById('price-regular');
        const saleWrap = document.getElementById('price-sale-wrap');
        const sale = document.getElementById('price-sale');

        if (opt.value) {
            regular.textContent = '$' + opt.dataset.regular;
            if (opt.dataset.sale) {
                sale.textContent = '$' + opt.dataset.sale;
                saleWrap.classList.remove('hidden');
            } else {
                saleWrap.classList.add('hidden');
            }
            info.classList.remove('hidden');
        } else {
            info.classList.add('hidden');
        }
    });
    // Trigger on load if a product is pre-selected
    document.getElementById('product_id').dispatchEvent(new Event('change'));
</script>
@endif
