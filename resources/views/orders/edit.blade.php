<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Edit Order {{ $order->number }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">

            @if($errors->any())
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
                    @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
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
                        'summary'       => trim($a->first_name.' '.$a->last_name.', '.$a->address_line1.', '.$a->city),
                        'is_default'    => $a->is_default,
                        'address_line1' => $a->address_line1,
                        'city'          => $a->city,
                        'state'         => $a->state,
                        'postal_code'   => $a->postal_code,
                        'country'       => $a->country,
                    ]),
                ]);
            @endphp
            <script>
                window.__orderListings  = @json($listingsData);
                window.__orderCustomers = @json($customersData);
                window.__existingOrder  = @json($existingOrder);
            </script>

            <form method="POST" action="{{ route('orders.update', $order) }}"
                  x-data="{
                      listings: window.__orderListings,
                      customers: window.__orderCustomers,
                      existing: window.__existingOrder,
                      customerId: String(window.__existingOrder.customer_id || ''),
                      addresses: [],
                      taxExempt: false,
                      billingAddressId: String(window.__existingOrder.billing_address_id || ''),
                      shippingSelection: String(window.__existingOrder.shipping_address_id || ''),
                      lines: window.__existingOrder.lines.map(l => ({...l, fees: l.fees.map(f => ({...f}))})),
                      shipping: window.__existingOrder.shipping,
                      taxTimer: null,
                      newAddressOpen: false,
                      newAddressTarget: 'billing',
                      newAddressSaving: false,
                      newAddressErrors: [],
                      newAddress: { label: '', first_name: '', last_name: '', address_line1: '', address_line2: '', city: '', state: '', postal_code: '', country: 'US', phone: '' },

                      get shippingAddressId() {
                          if (this.shippingSelection === 'same')   return this.billingAddressId;
                          if (this.shippingSelection === 'manage') return '';
                          return this.shippingSelection;
                      },
                      lineTotal(line) { return parseFloat(line.unit_price || 0) + parseFloat(line.tax_amount || 0); },
                      feeTotal(fee)   { return parseFloat(fee.amount || 0) + parseFloat(fee.tax_amount || 0); },
                      lineSubtotal(line) {
                          return this.lineTotal(line) + line.fees.reduce((s, f) => s + this.feeTotal(f), 0);
                      },
                      get subtotal()  { return this.lines.reduce((s, l) => s + this.lineTotal(l), 0); },
                      get feesTotal() { return this.lines.reduce((s, l) => s + l.fees.reduce((ss, f) => ss + this.feeTotal(f), 0), 0); },
                      get grandTotal(){ return this.subtotal + this.feesTotal + parseFloat(this.shipping || 0); },

                      hydrateCustomer() {
                          const cx = this.customers.find(c => c.id == this.customerId);
                          this.addresses = cx ? cx.addresses : [];
                          this.taxExempt = cx ? cx.tax_exempt : false;
                      },
                      onCustomerChange() {
                          this.hydrateCustomer();
                          this.billingAddressId = '';
                          this.shippingSelection = '';
                          this.debounceTax();
                      },
                      onProductChange(line) {
                          const listing = this.listings.find(l => l.id == line.product_listing_id);
                          if (listing) {
                              line.unit_price = listing.price;
                              line.sku = listing.sku;
                          } else {
                              line.unit_price = 0;
                              line.sku = '';
                          }
                          line.stock = '';
                          this.loadStock(line);
                          this.debounceTax();
                      },
                      async loadStock(line) {
                          if (!line.product_listing_id) return;
                          try {
                              const r = await fetch('/admin/orders/listing-stock/' + line.product_listing_id);
                              const data = await r.json();
                              line.stock = data.stock.length ? data.stock.map(s => s.location + ': ' + s.qty).join(' · ') : 'Out of stock';
                          } catch (e) { console.error(e); }
                      },
                      debounceTax() {
                          clearTimeout(this.taxTimer);
                          this.taxTimer = setTimeout(() => this.fetchAllLineTax(), 400);
                      },
                      async fetchAllLineTax() {
                          if (this.taxExempt) return;
                          const addr = this.addresses.find(a => a.id == this.shippingAddressId) || null;
                          const payload = {
                              customer_id: this.customerId,
                              shipping_address: addr ? {
                                  address_line1: addr.address_line1, city: addr.city, state: addr.state,
                                  postal_code: addr.postal_code, country: addr.country,
                              } : null,
                              lines: this.lines.map(l => ({
                                  unit_price: parseFloat(l.unit_price) || 0,
                                  sku: l.sku || '',
                                  fees: l.fees.map(f => ({ name: f.name, amount: parseFloat(f.amount) || 0 })),
                              })),
                          };
                          try {
                              const resp = await fetch('/admin/orders/calculate-tax', {
                                  method: 'POST',
                                  headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                                  body: JSON.stringify(payload),
                              });
                              const result = await resp.json();
                              (result.lines || []).forEach((r, i) => {
                                  if (this.lines[i]) {
                                      this.lines[i].tax_amount = r.tax_amount || 0;
                                      (r.fees || []).forEach((f, j) => {
                                          if (this.lines[i].fees[j]) this.lines[i].fees[j].tax_amount = f.tax_amount || 0;
                                      });
                                  }
                              });
                          } catch (e) { console.error('calculate-tax failed', e); }
                      },
                      openNewAddress(target) {
                          this.newAddressTarget = target;
                          this.newAddressErrors = [];
                          this.newAddress = { label: '', first_name: '', last_name: '', address_line1: '', address_line2: '', city: '', state: '', postal_code: '', country: 'US', phone: '' };
                          this.newAddressOpen = true;
                      },
                      async saveNewAddress() {
                          if (!this.customerId) return;
                          this.newAddressSaving = true;
                          this.newAddressErrors = [];
                          try {
                              const resp = await fetch('{{ route('orders.customer-addresses.store') }}', {
                                  method: 'POST',
                                  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                                  body: JSON.stringify({ ...this.newAddress, customer_id: this.customerId }),
                              });
                              if (resp.status === 422) {
                                  const data = await resp.json();
                                  this.newAddressErrors = Object.values(data.errors || {}).flat();
                                  return;
                              }
                              if (!resp.ok) { this.newAddressErrors = ['Could not save address']; return; }
                              const addr = await resp.json();
                              this.addresses.push(addr);
                              if (this.newAddressTarget === 'billing') this.billingAddressId = String(addr.id);
                              else this.shippingSelection = String(addr.id);
                              this.newAddressOpen = false;
                              this.debounceTax();
                          } catch (e) {
                              this.newAddressErrors = ['Network error'];
                          } finally {
                              this.newAddressSaving = false;
                          }
                      },
                      addLine() { this.lines.push({ product_listing_id: '', unit_price: 0, tax_amount: 0, sku: '', stock: '', fees: [] }); },
                      removeLine(i) { if (this.lines.length > 1) this.lines.splice(i, 1); },
                      addFee(line) { line.fees.push({ name: '', amount: 0, tax_amount: 0 }); },
                      removeFee(line, j) { line.fees.splice(j, 1); },
                      fmt(v) { return parseFloat(v || 0).toFixed(2); }
                  }"
                  x-init="hydrateCustomer(); lines.forEach(l => { if (l.product_listing_id) loadStock(l); });">
                @csrf
                @method('PUT')

                {{-- Customer & Addresses --}}
                <div class="mb-4 rounded-lg bg-white p-5 shadow">
                    <h3 class="mb-3 text-sm font-semibold text-gray-700">Customer & Addresses</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Customer <span class="text-red-500">*</span></label>
                            <select name="customer_id" required x-model="customerId" @change="onCustomerChange()" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                <option value="">Select…</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Billing Address</label>
                            <div class="mt-1 flex gap-1">
                                <select x-model="billingAddressId" @change="debounceTax()" :disabled="!customerId" class="block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    <option value="">— Shop billing for cash —</option>
                                    <template x-for="a in addresses" :key="a.id"><option :value="a.id" x-text="a.summary"></option></template>
                                </select>
                                <button type="button" data-testid="new-address-button" @click="openNewAddress('billing')" :disabled="!customerId" class="rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-40">+ New</button>
                            </div>
                            <input type="hidden" name="billing_address_id" :value="billingAddressId">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Shipping Address</label>
                            <div class="mt-1 flex gap-1">
                                <select x-model="shippingSelection" @change="debounceTax()" :disabled="!customerId" class="block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    <option value="">— In-store pickup —</option>
                                    <option value="same">Same as billing</option>
                                    <template x-for="a in addresses" :key="a.id"><option :value="a.id" x-text="a.summary"></option></template>
                                </select>
                                <button type="button" data-testid="new-address-button" @click="openNewAddress('shipping')" :disabled="!customerId" class="rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-40">+ New</button>
                            </div>
                            <input type="hidden" name="shipping_address_id" :value="shippingAddressId">
                        </div>
                    </div>
                </div>

                {{-- New Address Modal --}}
                <div data-testid="new-address-modal" x-show="newAddressOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                    <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">Add new address</h3>
                        <template x-if="newAddressErrors.length">
                            <ul class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                                <template x-for="err in newAddressErrors" :key="err"><li x-text="err"></li></template>
                            </ul>
                        </template>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="col-span-2"><label class="block text-xs">Label *</label><input type="text" x-model="newAddress.label" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" /></div>
                            <div><label class="block text-xs">First name *</label><input type="text" x-model="newAddress.first_name" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" /></div>
                            <div><label class="block text-xs">Last name *</label><input type="text" x-model="newAddress.last_name" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" /></div>
                            <div class="col-span-2"><label class="block text-xs">Address line 1 *</label><input type="text" x-model="newAddress.address_line1" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" /></div>
                            <div class="col-span-2"><label class="block text-xs">Address line 2</label><input type="text" x-model="newAddress.address_line2" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" /></div>
                            <div><label class="block text-xs">City *</label><input type="text" x-model="newAddress.city" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" /></div>
                            <div><label class="block text-xs">State *</label><input type="text" x-model="newAddress.state" maxlength="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" /></div>
                            <div><label class="block text-xs">Postal code *</label><input type="text" x-model="newAddress.postal_code" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" /></div>
                            <div><label class="block text-xs">Country *</label><input type="text" x-model="newAddress.country" maxlength="2" placeholder="US" pattern="[A-Z]{2}" title="2-letter ISO country code (e.g. US, CA, MX)" style="text-transform:uppercase" @input="newAddress.country = newAddress.country.toUpperCase()" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" /></div>
                            <div class="col-span-2"><label class="block text-xs">Phone</label><input type="text" x-model="newAddress.phone" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" /></div>
                        </div>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" @click="newAddressOpen = false" class="rounded-md border border-gray-300 px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="button" @click="saveNewAddress()" :disabled="newAddressSaving" class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">Save address</button>
                        </div>
                    </div>
                </div>

                {{-- Order Details --}}
                <div class="mb-4 rounded-lg bg-white p-5 shadow">
                    <h3 class="mb-3 text-sm font-semibold text-gray-700">Order Details</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Source <span class="text-red-500">*</span></label>
                            <select name="source" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                @foreach($sources as $s)<option value="{{ $s->value }}" @selected($order->source->value === $s->value)>{{ $s->label() }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Payment Method <span class="text-red-500">*</span></label>
                            <select name="payment_method" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                @foreach($paymentMethods as $m)<option value="{{ $m->value }}" @selected($existingOrder['payment_method'] === $m->value)>{{ $m->label() }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Shipping Cost</label>
                            <input type="number" name="shipping" step="0.01" min="0" x-model="shipping" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                        </div>
                    </div>
                </div>

                {{-- Line Items --}}
                <div class="mb-4 overflow-hidden rounded-lg bg-white shadow">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                        <h3 class="text-sm font-semibold text-gray-700">Items</h3>
                        <button type="button" @click="addLine()" class="rounded-md bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100">+ Add item</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table data-testid="items-table" class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-2 font-medium">Product</th>
                                    <th class="px-3 py-2 font-medium">Qty</th>
                                    <th class="px-3 py-2 font-medium">Unit Price</th>
                                    <th class="px-3 py-2 font-medium">Tax</th>
                                    <th class="px-3 py-2 font-medium">Stock</th>
                                    <th class="px-3 py-2 text-right font-medium">Subtotal</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <template x-for="(line, i) in lines" :key="i">
                                <tbody class="divide-y divide-gray-100 border-b border-gray-200">
                                    <tr class="align-top">
                                        <td class="px-4 py-3">
                                            <select :name="'lines[' + i + '][product_listing_id]'" x-model="line.product_listing_id" @change="onProductChange(line)" required class="block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                                <option value="">Select product…</option>
                                                <template x-for="l in listings" :key="l.id"><option :value="l.id" x-text="l.name"></option></template>
                                            </select>
                                            <p class="mt-1 text-xs text-gray-500" x-text="line.sku || ''"></p>
                                        </td>
                                        <td class="px-3 py-3 text-gray-600">1</td>
                                        <td class="px-3 py-3">
                                            <input type="number" :name="'lines[' + i + '][unit_price]'" x-model="line.unit_price" @input="debounceTax()" step="0.01" min="0" required class="block w-24 rounded-md border-gray-300 text-sm shadow-sm" />
                                        </td>
                                        <td class="px-3 py-3 text-gray-700">
                                            <span x-text="'$' + fmt(line.tax_amount)"></span>
                                            <input type="hidden" :name="'lines[' + i + '][tax_amount]'" x-model="line.tax_amount" />
                                        </td>
                                        <td class="px-3 py-3 text-xs text-gray-500" x-text="line.stock || '—'"></td>
                                        <td class="px-3 py-3 text-right font-semibold text-gray-800" x-text="'$' + fmt(lineTotal(line))"></td>
                                        <td class="px-3 py-3 text-center">
                                            <button type="button" @click="removeLine(i)" :disabled="lines.length === 1" class="text-red-500 hover:text-red-700 disabled:opacity-30">×</button>
                                        </td>
                                    </tr>
                                    <template x-for="(fee, j) in line.fees" :key="i + '-' + j">
                                        <tr data-testid="fee-row" class="bg-gray-50/40 text-gray-600">
                                            <td class="px-4 py-2 pl-8">
                                                <span class="mr-1 text-gray-400">└</span>
                                                <input type="text" :name="'lines[' + i + '][fees][' + j + '][name]'" x-model="fee.name" required placeholder="Fee name" class="inline-block w-44 rounded-md border-gray-300 text-xs shadow-sm" />
                                            </td>
                                            <td class="px-3 py-2 text-gray-500">1</td>
                                            <td class="px-3 py-2">
                                                <input type="number" :name="'lines[' + i + '][fees][' + j + '][amount]'" x-model="fee.amount" @input="debounceTax()" step="0.01" min="0" required class="block w-24 rounded-md border-gray-300 text-xs shadow-sm" />
                                            </td>
                                            <td class="px-3 py-2 text-gray-700">
                                                <span x-text="'$' + fmt(fee.tax_amount)"></span>
                                                <input type="hidden" :name="'lines[' + i + '][fees][' + j + '][tax_amount]'" x-model="fee.tax_amount" />
                                            </td>
                                            <td class="px-3 py-2 text-xs text-gray-400">—</td>
                                            <td class="px-3 py-2 text-right font-medium text-gray-700" x-text="'$' + fmt(feeTotal(fee))"></td>
                                            <td class="px-3 py-2 text-center">
                                                <button type="button" @click="removeFee(line, j)" class="text-red-500 hover:text-red-700">×</button>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr class="bg-gray-50/40">
                                        <td colspan="7" class="px-4 py-1 pl-8">
                                            <button type="button" @click="addFee(line)" class="text-xs font-medium text-indigo-600 hover:text-indigo-700">+ Add fee</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </template>
                        </table>
                    </div>
                </div>

                {{-- Totals + submit --}}
                <div class="rounded-lg bg-gray-50 px-5 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex gap-6 text-sm">
                            <span>Subtotal: <span class="font-semibold" x-text="'$' + fmt(subtotal)"></span></span>
                            <span>Fees: <span class="font-semibold" x-text="'$' + fmt(feesTotal)"></span></span>
                            <span>Shipping: <span class="font-semibold" x-text="'$' + fmt(shipping)"></span></span>
                            <span class="text-base">Total: <span class="font-bold" x-text="'$' + fmt(grandTotal)"></span></span>
                        </div>
                        <button type="submit" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
