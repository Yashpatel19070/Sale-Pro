@php
    $po = $purchaseOrder ?? null;
@endphp

{{-- Supplier --}}
<div class="mb-4">
    <label class="block text-sm font-medium text-gray-700">Supplier <span class="text-red-500">*</span></label>
    <select name="supplier_id" required
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
        <option value="">Select supplier…</option>
        @foreach ($suppliers as $supplier)
            <option value="{{ $supplier->id }}"
                @selected(old('supplier_id', $po?->supplier_id) == $supplier->id)>
                {{ $supplier->code }} — {{ $supplier->name }}
            </option>
        @endforeach
    </select>
    @error('supplier_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
</div>

{{-- Skip flags --}}
<div class="mb-4 flex gap-6">
    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="skip_tech" value="1"
               @checked(old('skip_tech', $po?->skip_tech))
               class="rounded border-gray-300 text-indigo-600">
        Skip Tech Inspection
    </label>
    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="skip_qa" value="1"
               @checked(old('skip_qa', $po?->skip_qa))
               class="rounded border-gray-300 text-indigo-600">
        Skip QA
    </label>
</div>

{{-- Notes --}}
<div class="mb-6">
    <label class="block text-sm font-medium text-gray-700">Notes</label>
    <textarea name="notes" rows="2"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              placeholder="Internal notes (optional)">{{ old('notes', $po?->notes) }}</textarea>
    @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
</div>

{{-- Lines --}}
<div class="mb-4">
    <div class="flex items-center justify-between mb-2">
        <label class="block text-sm font-medium text-gray-700">Order Lines <span class="text-red-500">*</span></label>
        <button type="button" onclick="addLine()"
                class="text-sm text-indigo-600 hover:text-indigo-800">+ Add Line</button>
    </div>
    @error('lines') <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @enderror

    <div id="lines-container" class="space-y-2">
        @php
            $existingLines = old('lines', $existingLines ?? [['product_id' => '', 'qty_ordered' => 1, 'unit_price' => '']]);
        @endphp
        @foreach ($existingLines as $i => $line)
            <div class="flex gap-2 items-start line-row">
                <select name="lines[{{ $i }}][product_id]" required
                        class="flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select product…</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}"
                            @selected(($line['product_id'] ?? '') == $product->id)>
                            {{ $product->sku }} — {{ $product->name }}
                        </option>
                    @endforeach
                </select>
                <input type="number" name="lines[{{ $i }}][qty_ordered]" min="1" max="10000"
                       value="{{ $line['qty_ordered'] ?? 1 }}" placeholder="Qty" required
                       class="w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <input type="number" name="lines[{{ $i }}][unit_price]" min="0.01" step="0.01"
                       value="{{ $line['unit_price'] ?? '' }}" placeholder="Unit price" required
                       class="w-32 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <button type="button" onclick="removeLine(this)"
                        class="mt-1 text-red-400 hover:text-red-600 text-sm">✕</button>
            </div>
            @error("lines.{$i}.product_id") <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            @error("lines.{$i}.qty_ordered") <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            @error("lines.{$i}.unit_price") <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        @endforeach
    </div>
</div>

<script>
    const products = @json($products->map(fn ($p) => ['id' => $p->id, 'sku' => $p->sku, 'name' => $p->name]));

    function buildProductSelect(name) {
        const select = document.createElement('select');
        select.name = name;
        select.required = true;
        select.className = 'flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
        const blank = document.createElement('option');
        blank.value = '';
        blank.textContent = 'Select product…';
        select.appendChild(blank);
        products.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.sku + ' — ' + p.name;
            select.appendChild(opt);
        });
        return select;
    }

    let lineCount = document.querySelectorAll('.line-row').length;

    function addLine() {
        const container = document.getElementById('lines-container');
        const idx = lineCount++;
        const row = document.createElement('div');
        row.className = 'flex gap-2 items-start line-row';

        row.appendChild(buildProductSelect(`lines[${idx}][product_id]`));

        const qty = document.createElement('input');
        qty.type = 'number'; qty.name = `lines[${idx}][qty_ordered]`;
        qty.min = '1'; qty.max = '10000'; qty.value = '1';
        qty.placeholder = 'Qty'; qty.required = true;
        qty.className = 'w-24 rounded-md border-gray-300 text-sm shadow-sm';
        row.appendChild(qty);

        const price = document.createElement('input');
        price.type = 'number'; price.name = `lines[${idx}][unit_price]`;
        price.min = '0.01'; price.step = '0.01';
        price.placeholder = 'Unit price'; price.required = true;
        price.className = 'w-32 rounded-md border-gray-300 text-sm shadow-sm';
        row.appendChild(price);

        const btn = document.createElement('button');
        btn.type = 'button'; btn.textContent = '✕';
        btn.className = 'mt-1 text-red-400 hover:text-red-600 text-sm';
        btn.onclick = () => removeLine(btn);
        row.appendChild(btn);

        container.appendChild(row);
    }

    function removeLine(btn) {
        const row = btn.closest('.line-row');
        if (document.querySelectorAll('.line-row').length > 1) {
            row.remove();
        }
    }
</script>
