<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">New Order</h2>
            <a href="{{ route('orders.index') }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @if($errors->any())
                <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                    <ul class="list-inside list-disc space-y-1 text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <script>
                window.__orderCustomers = @json($customers);
                window.__orderAddresses = @json($addresses);
            </script>

            <form method="POST" action="{{ route('orders.store') }}"
                  x-data="{
                      customerId: '{{ old('customer_id') }}',
                      customers: window.__orderCustomers,
                      addressesAll: window.__orderAddresses,

                      lines: [{
                          listing_id: null, product_id: null,
                          location_id: null, serial_id: null,
                          sku: '', unit_price: 0, tax_rate: 0, tax_amount: 0,
                          availableLocations: [], availableSerials: [],
                      }],

                      fees: [],
                      shippingAmount: parseFloat('{{ old('shipping_amount', 0) }}'),

                      shippingType: '{{ old('shipping_type', 'saved') }}',
                      selectedShippingId: null,

                      billingType: '{{ old('billing_type', 'none') }}',
                      selectedBillingId: null,

                      get selectedCustomer() {
                          return this.customers.find(c => c.id == this.customerId) || null;
                      },

                      get customerAddresses() {
                          return this.addressesAll[this.customerId] || [];
                      },

                      listingTsConfig(line) {
                          return {
                              valueField: 'value',
                              labelField: 'label',
                              searchField: ['label'],
                              placeholder: 'Type 2+ chars to search...',
                              load(query, callback) {
                                  if (query.length < 2) return callback();
                                  fetch('/admin/product-listings/search?q=' + encodeURIComponent(query))
                                      .then(r => r.json())
                                      .then(data => callback(data.map(l => ({
                                          value: String(l.id),
                                          label: l.label,
                                          product_id: l.product_id,
                                          sku: l.sku,
                                          price: l.price,
                                      }))))
                                      .catch(() => callback());
                              },
                              shouldLoad(q) { return q.length >= 2; },
                              onItemAdd(value) {
                                  const opt = this.options[value];
                                  if (!opt) return;
                                  line.product_id         = opt.product_id;
                                  line.listing_id         = value;
                                  line.sku                = opt.sku || '';
                                  line.unit_price         = parseFloat(opt.price || 0);
                                  line.location_id        = null;
                                  line.serial_id          = null;
                                  line.availableLocations = [];
                                  line.availableSerials   = [];
                                  fetch('/admin/inventory-locations/search?product_id=' + opt.product_id)
                                      .then(r => r.json())
                                      .then(locs => { line.availableLocations = locs; })
                                      .catch(() => {});
                              },
                              onItemRemove() {
                                  line.product_id         = null;
                                  line.listing_id         = null;
                                  line.sku                = '';
                                  line.unit_price         = 0;
                                  line.location_id        = null;
                                  line.serial_id          = null;
                                  line.availableLocations = [];
                                  line.availableSerials   = [];
                              },
                          };
                      },

                      async onLocationChange(line) {
                          line.serial_id        = null;
                          line.availableSerials = [];
                          if (line.product_id && line.location_id) {
                              try {
                                  const res = await fetch(
                                      '/admin/inventory-serials/search?product_id=' + line.product_id +
                                      '&location_id=' + line.location_id
                                  );
                                  line.availableSerials = await res.json();
                              } catch (_) {}
                          }
                      },

                      lineTotal(line) {
                          return parseFloat(line.unit_price || 0) + parseFloat(line.tax_amount || 0);
                      },

                      get subtotal() {
                          return this.lines.reduce((s, l) => s + parseFloat(l.unit_price || 0), 0);
                      },

                      get taxTotal() {
                          return this.lines.reduce((s, l) => s + parseFloat(l.tax_amount || 0), 0);
                      },

                      async refreshTax() {
                          const lines = this.lines.map((l, i) => ({
                              index: i,
                              serial_id: l.serial_id ? parseInt(l.serial_id) : null,
                              unit_price: parseFloat(l.unit_price || 0),
                          }));
                          if (!lines.some(l => l.serial_id)) return;

                          const shipping = (this.shippingType === 'saved' && this.selectedShippingId)
                              ? { address_id: this.selectedShippingId }
                              : {};

                          try {
                              const res = await fetch('/admin/orders/tax-preview', {
                                  method: 'POST',
                                  headers: {
                                      'Content-Type': 'application/json',
                                      'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                  },
                                  body: JSON.stringify({ lines, shipping }),
                              });
                              if (!res.ok) return;
                              const taxData = await res.json();
                              Object.entries(taxData).forEach(([idx, tax]) => {
                                  const i = parseInt(idx);
                                  if (this.lines[i]) {
                                      this.lines[i].tax_rate   = tax.tax_rate;
                                      this.lines[i].tax_amount = tax.tax_amount;
                                  }
                              });
                          } catch (_) {}
                      },

                      get feesTotal() {
                          return this.fees.reduce((s, f) => s + parseFloat(f.amount || 0), 0);
                      },

                      get grandTotal() {
                          return this.subtotal + this.taxTotal + this.feesTotal + parseFloat(this.shippingAmount || 0);
                      },

                      addLine() {
                          this.lines.push({
                              listing_id: null, product_id: null,
                              location_id: null, serial_id: null,
                              sku: '', unit_price: 0, tax_rate: 0, tax_amount: 0,
                              availableLocations: [], availableSerials: [],
                          });
                      },

                      removeLine(i) {
                          if (this.lines.length > 1) this.lines.splice(i, 1);
                      },

                      addFee() { this.fees.push({ name: '', amount: 0 }); },
                      removeFee(i) { this.fees.splice(i, 1); },
                      fmt(val) { return parseFloat(val || 0).toFixed(2); },
                  }">
                @csrf

                {{-- Managed hidden input: drives billing_same_as_shipping server-side logic --}}
                <input type="hidden" name="billing_same_as_shipping" :value="billingType === 'same' ? '1' : ''">

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                    {{-- ══ LEFT COLUMN (2/3): Items + Fees ════════════════════════════ --}}
                    <div class="space-y-6 lg:col-span-2">

                        {{-- Line Items --}}
                        <div class="overflow-hidden rounded-lg bg-white shadow">
                            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                                <h3 class="text-base font-semibold text-gray-900">Line Items</h3>
                                <button type="button" @click="addLine()"
                                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                                    + Add Line
                                </button>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product / SKU</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Location</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Serial #</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Unit Price</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Tax</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Total</th>
                                            <th class="w-10 px-4 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        <template x-for="(line, index) in lines" :key="index">
                                            <tr>
                                                {{-- Product Listing AJAX (Tom Select) --}}
                                                <td class="min-w-[14rem] px-4 py-3">
                                                    <select :name="`lines[${index}][listing_id]`"
                                                            x-model="line.listing_id"
                                                            required
                                                            x-ts="listingTsConfig(line)"
                                                            class="block w-full">
                                                        <option value="">Type to search...</option>
                                                    </select>
                                                    <span x-show="line.sku" x-text="line.sku"
                                                          class="mt-1 block font-mono text-xs text-gray-400"></span>
                                                    {{-- Hidden inputs submitted to server --}}
                                                    <input type="hidden" :name="`lines[${index}][serial_id]`"  :value="line.serial_id">
                                                    <input type="hidden" :name="`lines[${index}][unit_price]`" :value="line.unit_price">
                                                    <input type="hidden" :name="`lines[${index}][tax_rate]`"   :value="line.tax_rate">
                                                </td>

                                                {{-- Location (populated after listing selected) --}}
                                                <td class="px-4 py-3">
                                                    <select x-model="line.location_id"
                                                            @change="onLocationChange(line)"
                                                            :disabled="!line.product_id"
                                                            class="block w-36 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400">
                                                        <option value="" x-text="line.product_id ? 'Select...' : '—'"></option>
                                                        <template x-for="loc in line.availableLocations" :key="loc.id">
                                                            <option :value="loc.id" x-text="loc.name"></option>
                                                        </template>
                                                    </select>
                                                </td>

                                                {{-- Serial (populated after location selected) --}}
                                                <td class="px-4 py-3">
                                                    <select x-model="line.serial_id"
                                                            @change="refreshTax()"
                                                            :disabled="!line.location_id"
                                                            class="block w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400">
                                                        <option value="" x-text="line.location_id ? 'Select...' : '—'"></option>
                                                        <template x-for="ser in line.availableSerials" :key="ser.id">
                                                            <option :value="ser.id" x-text="ser.serial_number"></option>
                                                        </template>
                                                    </select>
                                                </td>

                                                {{-- Unit Price (pre-filled from listing, editable) --}}
                                                <td class="px-4 py-3">
                                                    <input type="number" x-model="line.unit_price"
                                                           @change="refreshTax()"
                                                           min="0" step="0.01" required
                                                           class="block w-28 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                                </td>

                                                {{-- Tax (from Avalara, read-only) --}}
                                                <td class="px-4 py-3 text-right text-sm text-gray-700">
                                                    <span x-text="'$' + fmt(line.tax_amount)"></span>
                                                    <span x-show="line.tax_rate > 0"
                                                          x-text="' (' + (line.tax_rate * 100).toFixed(2) + '%)'"
                                                          class="block text-xs text-gray-400"></span>
                                                </td>

                                                {{-- Line Total (computed) --}}
                                                <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900"
                                                    x-text="'$' + fmt(lineTotal(line))"></td>

                                                {{-- Remove --}}
                                                <td class="px-4 py-3 text-center">
                                                    <button type="button" @click="removeLine(index)"
                                                            :disabled="lines.length === 1"
                                                            class="text-red-400 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30">
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot class="bg-gray-50 text-sm">
                                        <tr>
                                            <td colspan="5" class="px-4 py-2 text-right font-medium text-gray-500">Subtotal (excl. tax)</td>
                                            <td class="px-4 py-2 text-right font-semibold text-gray-900" x-text="'$' + fmt(subtotal)"></td>
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td colspan="5" class="px-4 py-2 text-right font-medium text-gray-500">Tax</td>
                                            <td class="px-4 py-2 text-right font-medium text-gray-700" x-text="'$' + fmt(taxTotal)"></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            @error('lines')
                                <p class="px-6 pb-4 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Additional Fees --}}
                        <div class="overflow-hidden rounded-lg bg-white shadow">
                            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                                <h3 class="text-base font-semibold text-gray-900">Additional Fees</h3>
                                <button type="button" @click="addFee()"
                                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">
                                    + Add Fee
                                </button>
                            </div>
                            <div class="px-6 py-5">
                                <div x-show="fees.length === 0" class="text-sm italic text-gray-400">No additional fees.</div>
                                <div class="space-y-3">
                                    <template x-for="(fee, fi) in fees" :key="fi">
                                        <div class="flex items-center gap-3">
                                            <input type="text" :name="`fees[${fi}][name]`" x-model="fee.name"
                                                   placeholder="Fee name (e.g. Handling, Insurance)"
                                                   class="block flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                            <div class="relative w-36 shrink-0">
                                                <span class="absolute inset-y-0 left-3 flex items-center text-sm text-gray-400">$</span>
                                                <input type="number" :name="`fees[${fi}][amount]`" x-model="fee.amount"
                                                       min="0" step="0.01" placeholder="0.00"
                                                       class="block w-full rounded-md border-gray-300 pl-7 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                            </div>
                                            <button type="button" @click="removeFee(fi)"
                                                    class="text-sm text-red-500 hover:text-red-700">Remove</button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                    </div>{{-- /left column --}}

                    {{-- ══ RIGHT SIDEBAR (1/3): Customer, Addresses, Total ════════════ --}}
                    <div class="space-y-6">

                        {{-- Customer + Source ──────────────────────────────────────── --}}
                        <div class="overflow-hidden rounded-lg bg-white shadow">
                            <div class="border-b border-gray-200 px-6 py-4">
                                <h3 class="text-base font-semibold text-gray-900">Customer</h3>
                            </div>
                            <div class="space-y-4 px-6 py-5">

                                <div>
                                    <label for="customer_id" class="block text-sm font-medium text-gray-700">
                                        Customer <span class="text-red-500">*</span>
                                    </label>
                                    <select id="customer_id" name="customer_id" required
                                            x-model="customerId"
                                            @change="selectedShippingId = null; selectedBillingId = null"
                                            x-ts
                                            class="mt-1 block w-full">
                                        <option value="">Select customer...</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}"
                                                    {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                                {{ $customer->name }} — {{ $customer->email }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('customer_id')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Customer info card --}}
                                <div x-show="selectedCustomer" x-cloak
                                     class="flex items-center gap-3 rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-600">
                                        <span class="text-sm font-bold text-white"
                                              x-text="selectedCustomer?.name?.charAt(0)?.toUpperCase()"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900"
                                           x-text="selectedCustomer?.name"></p>
                                        <p class="truncate text-xs text-indigo-600"
                                           x-text="selectedCustomer?.email"></p>
                                        <p x-show="selectedCustomer?.phone"
                                           class="text-xs text-gray-500"
                                           x-text="selectedCustomer?.phone"></p>
                                    </div>
                                </div>

                                <div>
                                    <label for="source" class="block text-sm font-medium text-gray-700">
                                        Source <span class="text-red-500">*</span>
                                    </label>
                                    <select id="source" name="source" required
                                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Select source...</option>
                                        @foreach($sources as $source)
                                            <option value="{{ $source->value }}"
                                                    {{ old('source') === $source->value ? 'selected' : '' }}>
                                                {{ $source->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('source')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                            </div>
                        </div>

                        {{-- Shipping Address ────────────────────────────────────────── --}}
                        <div class="overflow-hidden rounded-lg bg-white shadow">
                            <div class="border-b border-gray-200 px-6 py-4">
                                <h3 class="text-base font-semibold text-gray-900">Shipping Address</h3>
                            </div>
                            <div class="px-6 py-5">

                                {{-- Type tabs --}}
                                <div class="mb-4 flex flex-wrap gap-2">
                                    @foreach(['saved' => 'Saved', 'new' => 'New Address', 'none' => 'No Shipping'] as $val => $label)
                                        <label class="flex cursor-pointer items-center rounded-md border px-3 py-1.5 text-xs font-medium transition-colors"
                                               :class="shippingType === '{{ $val }}'
                                                   ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                                   : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                                            <input type="radio" name="shipping_type" value="{{ $val }}"
                                                   x-model="shippingType" class="sr-only" />
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>

                                {{-- Saved addresses --}}
                                <div x-show="shippingType === 'saved'">
                                    <p x-show="!customerId" class="text-sm italic text-gray-400">
                                        Select a customer to see saved addresses.
                                    </p>
                                    <p x-show="customerId && customerAddresses.length === 0" class="text-sm text-gray-500">
                                        No saved addresses.
                                        <button type="button" @click="shippingType = 'new'"
                                                class="ml-1 text-indigo-600 underline">Enter new</button>
                                    </p>
                                    <div x-show="customerAddresses.length > 0" class="space-y-2">
                                        <template x-for="addr in customerAddresses" :key="addr.id">
                                            <div @click="selectedShippingId = addr.id"
                                                 :class="selectedShippingId == addr.id
                                                     ? 'border-indigo-500 ring-2 ring-indigo-100 bg-indigo-50'
                                                     : 'border-gray-200 bg-white hover:border-indigo-300'"
                                                 class="relative cursor-pointer rounded-lg border p-3 transition-all">
                                                <div class="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-full"
                                                     :class="selectedShippingId == addr.id
                                                         ? 'bg-indigo-600'
                                                         : 'border-2 border-gray-300 bg-white'">
                                                    <svg x-show="selectedShippingId == addr.id"
                                                         class="h-3 w-3 text-white" fill="none"
                                                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </div>
                                                <template x-if="addr.label">
                                                    <span class="mb-1 inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600"
                                                          x-text="addr.label"></span>
                                                </template>
                                                <p class="pr-8 text-sm font-semibold text-gray-900"
                                                   x-text="addr.first_name + ' ' + addr.last_name"></p>
                                                <p class="text-xs text-gray-500" x-text="addr.address_line1"></p>
                                                <p class="text-xs text-gray-500"
                                                   x-text="addr.city + ', ' + addr.state + (addr.postal_code ? ' ' + addr.postal_code : '')"></p>
                                            </div>
                                        </template>
                                    </div>
                                    <input type="hidden" name="shipping[address_id]" :value="selectedShippingId">
                                </div>

                                {{-- New shipping address form --}}
                                <div x-show="shippingType === 'new'" x-cloak>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">First Name</label>
                                            <input type="text" name="shipping[first_name]"
                                                   value="{{ old('shipping.first_name') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Last Name</label>
                                            <input type="text" name="shipping[last_name]"
                                                   value="{{ old('shipping.last_name') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-700">Email</label>
                                            <input type="email" name="shipping[email]"
                                                   value="{{ old('shipping.email') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-700">Phone</label>
                                            <input type="text" name="shipping[phone]"
                                                   value="{{ old('shipping.phone') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-700">Address Line 1</label>
                                            <input type="text" name="shipping[line1]"
                                                   value="{{ old('shipping.line1') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-700">Address Line 2</label>
                                            <input type="text" name="shipping[line2]"
                                                   value="{{ old('shipping.line2') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">City</label>
                                            <input type="text" name="shipping[city]"
                                                   value="{{ old('shipping.city') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">State</label>
                                            <input type="text" name="shipping[state]"
                                                   value="{{ old('shipping.state') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Postal Code</label>
                                            <input type="text" name="shipping[postal_code]"
                                                   value="{{ old('shipping.postal_code') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Country</label>
                                            <input type="text" name="shipping[country]"
                                                   value="{{ old('shipping.country', 'US') }}" maxlength="2"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                    </div>
                                </div>

                                {{-- No shipping --}}
                                <div x-show="shippingType === 'none'" x-cloak>
                                    <p class="text-sm text-gray-500">Walk-in / digital order — no shipping needed.</p>
                                </div>

                            </div>
                        </div>

                        {{-- Billing Address ─────────────────────────────────────────── --}}
                        <div class="overflow-hidden rounded-lg bg-white shadow">
                            <div class="border-b border-gray-200 px-6 py-4">
                                <h3 class="text-base font-semibold text-gray-900">Billing Address</h3>
                            </div>
                            <div class="px-6 py-5">

                                {{-- Type tabs --}}
                                <div class="mb-4 flex flex-wrap gap-2">
                                    @foreach(['same' => 'Same as Shipping', 'saved' => 'Saved', 'new' => 'New Address', 'none' => 'None'] as $val => $label)
                                        <label class="flex cursor-pointer items-center rounded-md border px-3 py-1.5 text-xs font-medium transition-colors"
                                               :class="billingType === '{{ $val }}'
                                                   ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                                   : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                                            <input type="radio" name="billing_type" value="{{ $val }}"
                                                   x-model="billingType" class="sr-only" />
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>

                                {{-- Same as shipping --}}
                                <div x-show="billingType === 'same'">
                                    <p class="text-sm text-gray-500">Billing address will match the shipping address.</p>
                                </div>

                                {{-- Saved billing addresses --}}
                                <div x-show="billingType === 'saved'" x-cloak>
                                    <p x-show="!customerId" class="text-sm italic text-gray-400">
                                        Select a customer to see saved addresses.
                                    </p>
                                    <p x-show="customerId && customerAddresses.length === 0" class="text-sm text-gray-500">
                                        No saved addresses.
                                        <button type="button" @click="billingType = 'new'"
                                                class="ml-1 text-indigo-600 underline">Enter new</button>
                                    </p>
                                    <div x-show="customerAddresses.length > 0" class="space-y-2">
                                        <template x-for="addr in customerAddresses" :key="addr.id">
                                            <div @click="selectedBillingId = addr.id"
                                                 :class="selectedBillingId == addr.id
                                                     ? 'border-indigo-500 ring-2 ring-indigo-100 bg-indigo-50'
                                                     : 'border-gray-200 bg-white hover:border-indigo-300'"
                                                 class="relative cursor-pointer rounded-lg border p-3 transition-all">
                                                <div class="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-full"
                                                     :class="selectedBillingId == addr.id
                                                         ? 'bg-indigo-600'
                                                         : 'border-2 border-gray-300 bg-white'">
                                                    <svg x-show="selectedBillingId == addr.id"
                                                         class="h-3 w-3 text-white" fill="none"
                                                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </div>
                                                <template x-if="addr.label">
                                                    <span class="mb-1 inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600"
                                                          x-text="addr.label"></span>
                                                </template>
                                                <p class="pr-8 text-sm font-semibold text-gray-900"
                                                   x-text="addr.first_name + ' ' + addr.last_name"></p>
                                                <p class="text-xs text-gray-500" x-text="addr.address_line1"></p>
                                                <p class="text-xs text-gray-500"
                                                   x-text="addr.city + ', ' + addr.state + (addr.postal_code ? ' ' + addr.postal_code : '')"></p>
                                            </div>
                                        </template>
                                    </div>
                                    <input type="hidden" name="billing[address_id]" :value="selectedBillingId">
                                </div>

                                {{-- New billing address form --}}
                                <div x-show="billingType === 'new'" x-cloak>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">First Name</label>
                                            <input type="text" name="billing[first_name]"
                                                   value="{{ old('billing.first_name') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Last Name</label>
                                            <input type="text" name="billing[last_name]"
                                                   value="{{ old('billing.last_name') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-700">Email</label>
                                            <input type="email" name="billing[email]"
                                                   value="{{ old('billing.email') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-700">Phone</label>
                                            <input type="text" name="billing[phone]"
                                                   value="{{ old('billing.phone') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-700">Address Line 1</label>
                                            <input type="text" name="billing[line1]"
                                                   value="{{ old('billing.line1') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-700">Address Line 2</label>
                                            <input type="text" name="billing[line2]"
                                                   value="{{ old('billing.line2') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">City</label>
                                            <input type="text" name="billing[city]"
                                                   value="{{ old('billing.city') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">State</label>
                                            <input type="text" name="billing[state]"
                                                   value="{{ old('billing.state') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Postal Code</label>
                                            <input type="text" name="billing[postal_code]"
                                                   value="{{ old('billing.postal_code') }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Country</label>
                                            <input type="text" name="billing[country]"
                                                   value="{{ old('billing.country', 'US') }}" maxlength="2"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                    </div>
                                </div>

                                {{-- None --}}
                                <div x-show="billingType === 'none'" x-cloak>
                                    <p class="text-sm text-gray-500">No billing address for this order.</p>
                                </div>

                            </div>
                        </div>

                        {{-- Order Total + Submit ─────────────────────────────────────── --}}
                        <div class="overflow-hidden rounded-lg bg-white shadow">
                            <div class="border-b border-gray-200 px-6 py-4">
                                <h3 class="text-base font-semibold text-gray-900">Order Total</h3>
                            </div>
                            <div class="px-6 py-5">

                                <div class="mb-4">
                                    <label for="shipping_amount" class="block text-sm font-medium text-gray-700">
                                        Shipping Cost
                                    </label>
                                    <div class="relative mt-1">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-sm text-gray-400">$</span>
                                        <input type="number" id="shipping_amount" name="shipping_amount"
                                               x-model="shippingAmount"
                                               value="{{ old('shipping_amount', 0) }}"
                                               step="0.01" min="0"
                                               class="block w-full rounded-md border-gray-300 pl-7 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    </div>
                                    @error('shipping_amount')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="space-y-1.5 border-t border-gray-100 pt-4 text-sm">
                                    <div class="flex justify-between text-gray-500">
                                        <span>Subtotal</span>
                                        <span x-text="'$' + fmt(subtotal)"></span>
                                    </div>
                                    <div class="flex justify-between text-gray-500">
                                        <span>Tax</span>
                                        <span x-text="'$' + fmt(taxTotal)"></span>
                                    </div>
                                    <div class="flex justify-between text-gray-500">
                                        <span>Fees</span>
                                        <span x-text="'$' + fmt(feesTotal)"></span>
                                    </div>
                                    <div class="flex justify-between text-gray-500">
                                        <span>Shipping</span>
                                        <span x-text="'$' + fmt(shippingAmount)"></span>
                                    </div>
                                    <div class="flex justify-between border-t border-gray-200 pt-3 text-base font-bold text-gray-900">
                                        <span>Grand Total</span>
                                        <span x-text="'$' + fmt(grandTotal)"></span>
                                    </div>
                                </div>

                                <div class="mt-6 space-y-3">
                                    <button type="submit"
                                            class="w-full rounded-md bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                        Create Order
                                    </button>
                                    <a href="{{ route('orders.index') }}"
                                       class="block text-center text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                                </div>

                            </div>
                        </div>

                    </div>{{-- /sidebar --}}

                </div>{{-- /grid --}}

            </form>
        </div>
    </div>
</x-app-layout>
