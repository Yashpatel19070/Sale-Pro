<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('orders.show', $order) }}" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Edit Order {{ $order->number }}</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">

            @if($errors->any())
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @php
                $listingsData = $productListings->map(fn($l) => [
                    'id'    => $l->id,
                    'name'  => $l->product->name,
                    'sku'   => $l->product->sku,
                    'price' => (float) $l->currentPrice(),
                ]);
                $customersData = $customers->map(fn($c) => [
                    'id'         => $c->id,
                    'name'       => $c->name,
                    'tax_exempt' => $c->tax_exempt,
                    'addresses'  => $c->addresses->map(fn($a) => [
                        'id'            => $a->id,
                        'label'         => $a->label,
                        'summary'       => trim(implode(', ', array_filter([
                            trim($a->first_name . ' ' . $a->last_name),
                            $a->address_line1,
                            $a->city,
                        ]))),
                        'is_default'    => $a->is_default,
                        'address_line1' => $a->address_line1,
                        'city'          => $a->city,
                        'state'         => $a->state,
                        'postal_code'   => $a->postal_code,
                        'country'       => $a->country,
                    ]),
                ]);
                $linesData = $order->lines->map(fn($l) => [
                    'product_listing_id' => $l->product_listing_id,
                    'unit_price'         => (float) $l->unit_price,
                    'tax_rate'           => (float) $l->tax_rate,
                    'tax_amount'         => (float) $l->tax_amount,
                    'sku'                => $l->productListing?->product?->sku ?? '',
                    'serial_number'      => $l->inventorySerial?->serial_number,
                    'stock'              => '',
                    'stockLoading'       => false,
                ]);
                $feesData = $order->orderFees->map(fn($f) => ['name' => $f->name, 'amount' => (float) $f->amount]);
            @endphp
            <script>
                window.__orderListings      = @json($listingsData);
                window.__orderCustomers     = @json($customersData);
                window.__orderExistingLines = @json($linesData);
                window.__orderExistingFees  = @json($feesData);
            </script>

            <form method="POST" action="{{ route('orders.update', $order) }}"
                  x-data="{
                      listings:         window.__orderListings,
                      customers:        window.__orderCustomers,
                      customerId:       '{{ old('customer_id', $order->customer_id) }}',
                      addresses:        [],
                      taxExempt:        false,
                      taxTimer:         null,
                      billingAddressId: '{{ old('billing_address_id', $order->billing_address_id ?? '') }}',
                      shippingSelection:'{{ old('shipping_address_id', $order->shipping_address_id ?? '') }}',
                      lines:            window.__orderExistingLines.map(l => ({ ...l })),
                      fees:             window.__orderExistingFees.map(f => ({ ...f })),
                      shipping:         '{{ old('shipping', $order->shipping) }}',

                      get shippingAddressId() {
                          if (this.shippingSelection === 'same')   return this.billingAddressId;
                          if (this.shippingSelection === 'manage') return '';
                          return this.shippingSelection;
                      },

                      get subtotal() {
                          return this.lines.reduce((s, l) =>
                              s + parseFloat(l.unit_price || 0) + parseFloat(l.tax_amount || 0), 0);
                      },
                      get feesTotal() { return this.fees.reduce((s, f) => s + parseFloat(f.amount || 0), 0); },
                      get grandTotal() {
                          return this.subtotal + this.feesTotal + parseFloat(this.shipping || 0);
                      },

                      lineSubtotal(l) { return parseFloat(l.unit_price || 0) + parseFloat(l.tax_amount || 0); },

                      onCustomerChange() {
                          const cx = this.customers.find(c => c.id == this.customerId);
                          this.addresses        = cx ? cx.addresses : [];
                          this.taxExempt        = cx ? cx.tax_exempt : false;
                          this.billingAddressId  = '';
                          this.shippingSelection = '';
                          this.debounceTax();
                      },

                      onBillingChange() {
                          if (this.billingAddressId === 'manage') {
                              window.open('/admin/customers/' + this.customerId + '/addresses', '_blank');
                              this.billingAddressId = '';
                          }
                          this.debounceTax();
                      },
                      onShippingChange() {
                          if (this.shippingSelection === 'manage') {
                              window.open('/admin/customers/' + this.customerId + '/addresses', '_blank');
                              this.shippingSelection = '';
                          }
                          this.debounceTax();
                      },

                      onProductChange(line) {
                          const listing = this.listings.find(l => l.id == line.product_listing_id);
                          if (listing) {
                              line.unit_price = listing.price;
                              line.sku        = listing.sku;
                          } else {
                              line.unit_price = 0;
                              line.sku        = '';
                          }
                          line.stock = '';
                          this.loadStock(line);
                          this.debounceTax();
                      },

                      async loadStock(line) {
                          if (!line.product_listing_id) return;
                          line.stockLoading = true;
                          try {
                              const r    = await fetch('/admin/orders/listing-stock/' + line.product_listing_id);
                              const data = await r.json();
                              line.stock = data.stock.length
                                  ? data.stock.map(s => s.location + ': ' + s.qty).join(' · ')
                                  : 'Out of stock';
                          } finally {
                              line.stockLoading = false;
                          }
                      },

                      debounceTax() {
                          clearTimeout(this.taxTimer);
                          this.taxTimer = setTimeout(() => this.fetchAllLineTax(), 400);
                      },

                      async fetchAllLineTax() {
                          if (this.taxExempt) return;
                          const addrId = this.shippingAddressId;
                          const addr   = addrId ? this.addresses.find(a => a.id == addrId) || null : null;
                          if (!addr) return;
                          const payload = {
                              customer_id:      this.customerId,
                              shipping_address: {
                                  address_line1: addr.address_line1,
                                  city:          addr.city,
                                  state:         addr.state,
                                  postal_code:   addr.postal_code,
                                  country:       addr.country,
                              },
                              lines: this.lines.map(l => ({
                                  unit_price: parseFloat(l.unit_price) || 0,
                                  sku:        l.sku || '',
                              })),
                          };
                          try {
                              const resp = await fetch('/admin/orders/calculate-tax', {
                                  method:  'POST',
                                  headers: {
                                      'Content-Type': 'application/json',
                                      'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                  },
                                  body: JSON.stringify(payload),
                              });
                              const result = await resp.json();
                              result.forEach((t, idx) => {
                                  if (this.lines[idx]) {
                                      this.lines[idx].tax_rate   = t.tax_rate;
                                      this.lines[idx].tax_amount = t.tax_amount;
                                  }
                              });
                          } catch (e) { console.error('[order/edit] calculate-tax failed', e); }
                      },

                      addLine()     { this.lines.push({ product_listing_id: '', unit_price: 0, tax_rate: 0, tax_amount: 0, sku: '', stock: '', stockLoading: false, serial_number: null }); },
                      removeLine(i) { if (this.lines.length > 1) this.lines.splice(i, 1); },
                      addFee()      { this.fees.push({ name: '', amount: '' }); },
                      removeFee(i)  { this.fees.splice(i, 1); },
                      fmt(v)        { return parseFloat(v || 0).toFixed(2); }
                  }"
                  x-init="
                      const cx = customers.find(c => c.id == customerId);
                      addresses = cx ? cx.addresses : [];
                      taxExempt = cx ? cx.tax_exempt : false;
                      lines.forEach(l => loadStock(l));
                  ">
                @csrf
                @method('PUT')

                {{-- Customer & Addresses --}}
                <div class="mb-4 overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="border-b border-gray-100 px-5 py-3">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Customer & Addresses</span>
                    </div>
                    <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">

                        <div>
                            <label for="customer_id" class="block text-sm font-medium text-gray-700">Customer <span class="text-red-500">*</span></label>
                            <select id="customer_id" name="customer_id" required
                                    x-model="customerId" @change="onCustomerChange()"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select customer…</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Billing Address</label>
                            <select x-model="billingAddressId" @change="onBillingChange()" :disabled="!customerId"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-400">
                                <option value="" x-text="customerId ? '— No billing address —' : 'Select a customer first…'"></option>
                                <template x-for="addr in addresses" :key="addr.id">
                                    <option :value="addr.id"
                                            x-text="(addr.label ? addr.label + ': ' : '') + addr.summary + (addr.is_default ? ' ★' : '')"></option>
                                </template>
                                <option value="manage" x-show="customerId">+ Manage addresses →</option>
                            </select>
                            <input type="hidden" name="billing_address_id" :value="billingAddressId !== 'manage' ? billingAddressId : ''">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Shipping Address</label>
                            <select x-model="shippingSelection" @change="onShippingChange()" :disabled="!customerId"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-400">
                                <option value="" x-text="customerId ? '— In-store pickup —' : 'Select a customer first…'"></option>
                                <option value="same" x-show="customerId">Same as billing</option>
                                <template x-for="addr in addresses" :key="addr.id">
                                    <option :value="addr.id"
                                            x-text="(addr.label ? addr.label + ': ' : '') + addr.summary + (addr.is_default ? ' ★' : '')"></option>
                                </template>
                                <option value="manage" x-show="customerId">+ Manage addresses →</option>
                            </select>
                            <input type="hidden" name="shipping_address_id" :value="shippingAddressId">
                        </div>

                    </div>
                </div>

                {{-- Order Details --}}
                <div class="mb-4 overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="border-b border-gray-100 px-5 py-3">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Order Details</span>
                    </div>
                    <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-3">

                        <div>
                            <label for="source" class="block text-sm font-medium text-gray-700">Source <span class="text-red-500">*</span></label>
                            <select id="source" name="source" required
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select…</option>
                                @foreach($sources as $source)
                                    <option value="{{ $source->value }}" @selected(old('source', $order->source?->value) === $source->value)>{{ $source->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="payment_method" class="block text-sm font-medium text-gray-700">Payment Method <span class="text-red-500">*</span></label>
                            <select id="payment_method" name="payment_method" required
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Select…</option>
                                @foreach($paymentMethods as $method)
                                    <option value="{{ $method->value }}" @selected(old('payment_method', $order->payment_method?->value) === $method->value)>{{ $method->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="shipping" class="block text-sm font-medium text-gray-700">Shipping Cost</label>
                            <input type="number" id="shipping" name="shipping" step="0.01" min="0"
                                   x-model="shipping" placeholder="0.00"
                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>

                    </div>
                </div>

                {{-- Line Items --}}
                <div class="mb-4 overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Items</span>
                        <button type="button" @click="addLine()"
                                class="text-xs font-medium text-indigo-600 hover:text-indigo-700">+ Add item</button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead>
                                <tr class="bg-gray-50/60">
                                    <th class="w-56 px-4 py-2 text-left text-xs font-medium text-gray-400">Product</th>
                                    <th class="w-24 px-3 py-2 text-left text-xs font-medium text-gray-400">SKU</th>
                                    <th class="w-40 px-3 py-2 text-left text-xs font-medium text-gray-400">Stock</th>
                                    <th class="w-28 px-3 py-2 text-left text-xs font-medium text-gray-400">Unit Price</th>
                                    <th class="w-24 px-3 py-2 text-left text-xs font-medium text-gray-400">Tax $</th>
                                    <th class="w-28 px-3 py-2 text-right text-xs font-medium text-gray-400">Subtotal</th>
                                    <th class="w-10 px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <template x-for="(line, i) in lines" :key="i">
                                    <tr>
                                        <td class="px-4 py-2">
                                            <select :name="'lines[' + i + '][product_listing_id]'"
                                                    x-model="line.product_listing_id"
                                                    @change="onProductChange(line)"
                                                    required
                                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="">Select product…</option>
                                                <template x-for="listing in listings" :key="listing.id">
                                                    <option :value="listing.id" x-text="listing.name"></option>
                                                </template>
                                            </select>
                                            <span x-show="line.serial_number" class="mt-0.5 block text-xs text-gray-400">
                                                Serial: <span x-text="line.serial_number"></span>
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="block truncate text-xs text-gray-500"
                                                  x-text="line.sku || '—'"></span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="block text-xs"
                                                  :class="line.stock === 'Out of stock' ? 'font-medium text-red-500' : 'text-gray-500'"
                                                  x-text="line.stockLoading ? 'Loading…' : (line.stock || '—')"></span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" :name="'lines[' + i + '][unit_price]'"
                                                   x-model="line.unit_price"
                                                   @input="debounceTax()"
                                                   step="0.01" min="0" required placeholder="0.00"
                                                   class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </td>
                                        <input type="hidden" :name="'lines[' + i + '][tax_rate]'" x-model="line.tax_rate" />
                                        <td class="px-3 py-2">
                                            <input type="number" :name="'lines[' + i + '][tax_amount]'"
                                                   x-model="line.tax_amount"
                                                   step="0.01" min="0" placeholder="0.00"
                                                   class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <span class="text-sm font-semibold text-gray-800"
                                                  x-text="'$' + fmt(lineSubtotal(line))"></span>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <button type="button" @click="removeLine(i)"
                                                    :disabled="lines.length === 1"
                                                    class="text-gray-300 hover:text-red-400 disabled:cursor-not-allowed disabled:opacity-30 transition-colors">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Fees --}}
                <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Fees</span>
                        <button type="button" @click="addFee()"
                                class="text-xs font-medium text-indigo-600 hover:text-indigo-700">+ Add fee</button>
                    </div>

                    <div x-show="fees.length === 0" class="px-5 py-4 text-sm text-gray-400">No additional fees.</div>

                    <template x-for="(fee, i) in fees" :key="i">
                        <div class="flex items-center gap-3 border-t border-gray-50 px-5 py-2.5">
                            <input type="text" :name="'fees[' + i + '][name]'"
                                   x-model="fee.name" required placeholder="Description"
                                   class="flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <input type="number" :name="'fees[' + i + '][amount]'"
                                   x-model="fee.amount" step="0.01" min="0" required placeholder="0.00"
                                   class="w-32 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <button type="button" @click="removeFee(i)"
                                    class="flex-shrink-0 text-gray-300 hover:text-red-400 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>

                {{-- Totals strip + submit --}}
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-4">
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                        <div class="flex items-baseline gap-1.5 text-sm">
                            <span class="text-gray-500">Subtotal</span>
                            <span class="font-semibold text-gray-800" x-text="'$' + fmt(subtotal)"></span>
                        </div>
                        <div class="flex items-baseline gap-1.5 text-sm">
                            <span class="text-gray-500">Fees</span>
                            <span class="font-semibold text-gray-800" x-text="'$' + fmt(feesTotal)"></span>
                        </div>
                        <div class="flex items-baseline gap-1.5 text-sm">
                            <span class="text-gray-500">Shipping</span>
                            <span class="font-semibold text-gray-800" x-text="'$' + fmt(shipping)"></span>
                        </div>
                        <div class="ml-auto flex items-center gap-4">
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-sm font-medium text-gray-600">Total</span>
                                <span class="text-lg font-bold text-gray-900" x-text="'$' + fmt(grandTotal)"></span>
                            </div>
                            <a href="{{ route('orders.show', $order) }}"
                               class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Cancel</a>
                            <button type="submit"
                                    class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>
</x-app-layout>
