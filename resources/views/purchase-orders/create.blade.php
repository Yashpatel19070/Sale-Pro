<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">New Purchase Order</h2>
            <a href="{{ route('purchase-orders.index') }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                Cancel
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

            @if($errors->any())
                <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-red-800">
                    <ul class="list-disc list-inside space-y-1 text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('purchase-orders.store') }}"
                  x-data="{
                      lines: [{ product_id: '', description: '', qty_ordered: 1, unit_cost: 0, tax_rate: 0, line_total: 0 }],
                      products: @json($products),
                      get subtotal() {
                          return this.lines.reduce((s, l) => s + parseFloat(l.qty_ordered || 0) * parseFloat(l.unit_cost || 0), 0);
                      },
                      get taxTotal() {
                          return this.lines.reduce((s, l) => s + parseFloat(l.qty_ordered || 0) * parseFloat(l.unit_cost || 0) * parseFloat(l.tax_rate || 0) / 100, 0);
                      },
                      get grandTotal() {
                          return this.subtotal + this.taxTotal;
                      },
                      updateLineTotal(line) {
                          line.line_total = (parseFloat(line.qty_ordered || 0) * parseFloat(line.unit_cost || 0)) * (1 + parseFloat(line.tax_rate || 0) / 100);
                      },
                      onProductChange(line) {
                          const p = this.products.find(p => p.id == line.product_id);
                          if (p) line.description = p.name;
                          this.updateLineTotal(line);
                      },
                      addLine() {
                          this.lines.push({ product_id: '', description: '', qty_ordered: 1, unit_cost: 0, tax_rate: 0, line_total: 0 });
                      },
                      removeLine(i) {
                          if (this.lines.length > 1) this.lines.splice(i, 1);
                      },
                      formatCurrency(val) {
                          return parseFloat(val || 0).toFixed(2);
                      }
                  }">
                @csrf

                {{-- Header fields --}}
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Order Details</h3>
                    </div>
                    <div class="grid grid-cols-1 gap-6 px-6 py-5 sm:grid-cols-2">
                        <div>
                            <label for="supplier_id" class="block text-sm font-medium text-gray-700">
                                Supplier <span class="text-red-500">*</span>
                            </label>
                            <select id="supplier_id"
                                    name="supplier_id"
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Select a supplier...</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>
                                        {{ $supplier->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('supplier_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="expected_delivery_date" class="block text-sm font-medium text-gray-700">
                                Expected Delivery Date
                            </label>
                            <input type="date"
                                   id="expected_delivery_date"
                                   name="expected_delivery_date"
                                   value="{{ old('expected_delivery_date') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('expected_delivery_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea id="notes"
                                      name="notes"
                                      rows="3"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Line Items --}}
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow">
                    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Line Items</h3>
                        <button type="button"
                                @click="addLine()"
                                class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                            + Add Line
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Description</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Qty</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Unit Cost</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Tax %</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Line Total</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <template x-for="(line, index) in lines" :key="index">
                                    <tr>
                                        <td class="px-4 py-3">
                                            <select :name="`lines[${index}][product_id]`"
                                                    x-model="line.product_id"
                                                    @change="onProductChange(line)"
                                                    required
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">Select product...</option>
                                                <template x-for="product in products" :key="product.id">
                                                    <option :value="product.id" x-text="`${product.sku} — ${product.name}`"
                                                            :selected="line.product_id == product.id"></option>
                                                </template>
                                            </select>
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="text"
                                                   :name="`lines[${index}][description]`"
                                                   x-model="line.description"
                                                   placeholder="Description"
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number"
                                                   :name="`lines[${index}][qty_ordered]`"
                                                   x-model="line.qty_ordered"
                                                   @input="updateLineTotal(line)"
                                                   min="0.01"
                                                   step="0.01"
                                                   required
                                                   class="block w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-right" />
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number"
                                                   :name="`lines[${index}][unit_cost]`"
                                                   x-model="line.unit_cost"
                                                   @input="updateLineTotal(line)"
                                                   min="0"
                                                   step="0.01"
                                                   required
                                                   class="block w-28 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-right" />
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number"
                                                   :name="`lines[${index}][tax_rate]`"
                                                   x-model="line.tax_rate"
                                                   @input="updateLineTotal(line)"
                                                   min="0"
                                                   max="100"
                                                   step="0.01"
                                                   class="block w-20 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-right" />
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <span class="text-sm font-medium text-gray-900"
                                                  x-text="formatCurrency(line.line_total)"></span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button type="button"
                                                    @click="removeLine(index)"
                                                    :disabled="lines.length === 1"
                                                    class="text-red-500 hover:text-red-700 disabled:opacity-30 disabled:cursor-not-allowed text-sm">
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="5" class="px-4 py-3 text-right text-sm font-medium text-gray-700">Subtotal</td>
                                    <td class="px-4 py-3 text-right text-sm font-medium text-gray-900" x-text="formatCurrency(subtotal)"></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="5" class="px-4 py-3 text-right text-sm font-medium text-gray-700">Tax Total</td>
                                    <td class="px-4 py-3 text-right text-sm font-medium text-gray-900" x-text="formatCurrency(taxTotal)"></td>
                                    <td></td>
                                </tr>
                                <tr class="border-t-2 border-gray-300">
                                    <td colspan="5" class="px-4 py-3 text-right text-sm font-bold text-gray-700">Grand Total</td>
                                    <td class="px-4 py-3 text-right text-sm font-bold text-gray-900" x-text="formatCurrency(grandTotal)"></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('purchase-orders.index') }}"
                       class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Create Purchase Order
                    </button>
                </div>

            </form>
        </div>
    </div>
</x-app-layout>
