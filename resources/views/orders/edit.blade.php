<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Edit Order {{ $order->number }}
            </h2>
            <a href="{{ route('orders.show', $order) }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                Back to Order
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
                window.__orderAddresses = @json($addresses);
                window.__orderFees      = @json($order->orderFees->map(fn($f) => ['name' => $f->name, 'amount' => (float) $f->amount]));
                window.__orderSubtotal  = {{ (float) $order->subtotal }};
                window.__orderShipping  = {{ (float) old('shipping_amount', $order->shipping) }};
            </script>

            <form method="POST" action="{{ route('orders.update', $order) }}"
                  x-data="{
                      customerId: '{{ $order->customer_id }}',
                      addressesAll: window.__orderAddresses,

                      fees: window.__orderFees,
                      subtotal: window.__orderSubtotal,
                      shippingAmount: window.__orderShipping,

                      shippingType: '{{ old('shipping_type', $order->shipping_address_line1 ? 'new' : 'none') }}',
                      selectedShippingId: null,

                      billingType: '{{ old('billing_type', $order->billing_address_line1 ? 'new' : 'none') }}',
                      selectedBillingId: null,

                      get customerAddresses() {
                          return this.addressesAll[this.customerId] || [];
                      },

                      get feesTotal() {
                          return this.fees.reduce((s, f) => s + parseFloat(f.amount || 0), 0);
                      },

                      get grandTotal() {
                          return this.subtotal + this.feesTotal + parseFloat(this.shippingAmount || 0);
                      },

                      addFee() { this.fees.push({ name: '', amount: 0 }); },
                      removeFee(i) { this.fees.splice(i, 1); },
                      fmt(val) { return parseFloat(val || 0).toFixed(2); },
                  }">
                @csrf
                @method('PUT')

                {{-- billing_same_as_shipping — always in DOM, value driven by billingType --}}
                <input type="hidden" name="billing_same_as_shipping" :value="billingType === 'same' ? '1' : ''">

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                    {{-- ══ LEFT COLUMN (2/3): Line Items (read-only) + Fees ══════════════ --}}
                    <div class="space-y-6 lg:col-span-2">

                        {{-- Line Items — read-only --}}
                        <div class="overflow-hidden rounded-lg bg-white shadow">
                            <div class="border-b border-gray-200 px-6 py-4">
                                <h3 class="text-base font-semibold text-gray-900">Line Items</h3>
                                <p class="mt-0.5 text-xs text-gray-400">Line items cannot be changed after order creation.</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">SKU</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Serial #</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Unit Price</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Tax</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        @forelse($order->lines as $line)
                                            <tr class="bg-gray-50/40">
                                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-500">
                                                    {{ $line->sku ?: '—' }}
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
                                                    {{ $line->product_name ?: '—' }}
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                                    {{ $line->serial->serial_number ?? '—' }}
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900">
                                                    ${{ number_format($line->unit_price, 2) }}
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-500">
                                                    ${{ number_format($line->tax_amount, 2) }}
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-gray-900">
                                                    ${{ number_format($line->line_total, 2) }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-400">No line items.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
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

                    {{-- ══ RIGHT SIDEBAR (1/3): Customer (read-only), Addresses, Total ══ --}}
                    <div class="space-y-6">

                        {{-- Customer (read-only) + Source ─────────────────────────────── --}}
                        <div class="overflow-hidden rounded-lg bg-white shadow">
                            <div class="border-b border-gray-200 px-6 py-4">
                                <h3 class="text-base font-semibold text-gray-900">Customer</h3>
                            </div>
                            <div class="space-y-4 px-6 py-5">

                                {{-- Read-only customer card --}}
                                <div class="flex items-center gap-3 rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-600">
                                        <span class="text-sm font-bold text-white">
                                            {{ strtoupper(substr($order->customer->name, 0, 1)) }}
                                        </span>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $order->customer->name }}</p>
                                        <p class="truncate text-xs text-indigo-600">{{ $order->customer->email }}</p>
                                        @if($order->customer->phone)
                                            <p class="text-xs text-gray-500">{{ $order->customer->phone }}</p>
                                        @endif
                                    </div>
                                </div>

                                <div>
                                    <label for="source" class="block text-sm font-medium text-gray-700">
                                        Source <span class="text-red-500">*</span>
                                    </label>
                                    <select id="source" name="source" required
                                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @foreach($sources as $source)
                                            <option value="{{ $source->value }}"
                                                    {{ old('source', $order->source->value) === $source->value ? 'selected' : '' }}>
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

                        {{-- Shipping Address ────────────────────────────────────────────── --}}
                        <div class="overflow-hidden rounded-lg bg-white shadow">
                            <div class="border-b border-gray-200 px-6 py-4">
                                <h3 class="text-base font-semibold text-gray-900">Shipping Address</h3>
                            </div>
                            <div class="px-6 py-5">

                                {{-- Type tabs --}}
                                <div class="mb-4 flex flex-wrap gap-2">
                                    @foreach(['saved' => 'Saved', 'new' => 'Edit Address', 'none' => 'No Shipping'] as $val => $label)
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
                                    <p x-show="customerAddresses.length === 0" class="text-sm text-gray-500">
                                        No saved addresses.
                                        <button type="button" @click="shippingType = 'new'"
                                                class="ml-1 text-indigo-600 underline">Edit current</button>
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

                                {{-- Edit / new shipping address form --}}
                                <div x-show="shippingType === 'new'" x-cloak>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">First Name</label>
                                            <input type="text" name="shipping[first_name]"
                                                   value="{{ old('shipping.first_name', $order->shipping_first_name) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Last Name</label>
                                            <input type="text" name="shipping[last_name]"
                                                   value="{{ old('shipping.last_name', $order->shipping_last_name) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-700">Email</label>
                                            <input type="email" name="shipping[email]"
                                                   value="{{ old('shipping.email', $order->shipping_email) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-700">Phone</label>
                                            <input type="text" name="shipping[phone]"
                                                   value="{{ old('shipping.phone', $order->shipping_phone) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-700">Address Line 1</label>
                                            <input type="text" name="shipping[line1]"
                                                   value="{{ old('shipping.line1', $order->shipping_address_line1) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-700">Address Line 2</label>
                                            <input type="text" name="shipping[line2]"
                                                   value="{{ old('shipping.line2', $order->shipping_address_line2) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">City</label>
                                            <input type="text" name="shipping[city]"
                                                   value="{{ old('shipping.city', $order->shipping_city) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">State</label>
                                            <input type="text" name="shipping[state]"
                                                   value="{{ old('shipping.state', $order->shipping_state) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Postal Code</label>
                                            <input type="text" name="shipping[postal_code]"
                                                   value="{{ old('shipping.postal_code', $order->shipping_postal_code) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Country</label>
                                            <input type="text" name="shipping[country]"
                                                   value="{{ old('shipping.country', $order->shipping_country ?? 'US') }}"
                                                   maxlength="2"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                    </div>
                                </div>

                                {{-- No shipping --}}
                                <div x-show="shippingType === 'none'" x-cloak>
                                    <p class="text-sm text-gray-500">No shipping — walk-in or digital delivery.</p>
                                </div>

                            </div>
                        </div>

                        {{-- Billing Address ──────────────────────────────────────────────── --}}
                        <div class="overflow-hidden rounded-lg bg-white shadow">
                            <div class="border-b border-gray-200 px-6 py-4">
                                <h3 class="text-base font-semibold text-gray-900">Billing Address</h3>
                            </div>
                            <div class="px-6 py-5">

                                {{-- Type tabs --}}
                                <div class="mb-4 flex flex-wrap gap-2">
                                    @foreach(['same' => 'Same as Shipping', 'saved' => 'Saved', 'new' => 'Edit Address', 'none' => 'None'] as $val => $label)
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
                                    <p x-show="customerAddresses.length === 0" class="text-sm text-gray-500">
                                        No saved addresses.
                                        <button type="button" @click="billingType = 'new'"
                                                class="ml-1 text-indigo-600 underline">Edit current</button>
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

                                {{-- Edit / new billing address form --}}
                                <div x-show="billingType === 'new'" x-cloak>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">First Name</label>
                                            <input type="text" name="billing[first_name]"
                                                   value="{{ old('billing.first_name', $order->billing_first_name) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Last Name</label>
                                            <input type="text" name="billing[last_name]"
                                                   value="{{ old('billing.last_name', $order->billing_last_name) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-700">Email</label>
                                            <input type="email" name="billing[email]"
                                                   value="{{ old('billing.email', $order->billing_email) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-700">Phone</label>
                                            <input type="text" name="billing[phone]"
                                                   value="{{ old('billing.phone', $order->billing_phone) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-700">Address Line 1</label>
                                            <input type="text" name="billing[line1]"
                                                   value="{{ old('billing.line1', $order->billing_address_line1) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div class="col-span-2">
                                            <label class="block text-xs font-medium text-gray-700">Address Line 2</label>
                                            <input type="text" name="billing[line2]"
                                                   value="{{ old('billing.line2', $order->billing_address_line2) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">City</label>
                                            <input type="text" name="billing[city]"
                                                   value="{{ old('billing.city', $order->billing_city) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">State</label>
                                            <input type="text" name="billing[state]"
                                                   value="{{ old('billing.state', $order->billing_state) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Postal Code</label>
                                            <input type="text" name="billing[postal_code]"
                                                   value="{{ old('billing.postal_code', $order->billing_postal_code) }}"
                                                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700">Country</label>
                                            <input type="text" name="billing[country]"
                                                   value="{{ old('billing.country', $order->billing_country ?? 'US') }}"
                                                   maxlength="2"
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

                        {{-- Order Total + Submit ─────────────────────────────────────────── --}}
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
                                               step="0.01" min="0"
                                               class="block w-full rounded-md border-gray-300 pl-7 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                    </div>
                                    @error('shipping_amount')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="space-y-1.5 border-t border-gray-100 pt-4 text-sm">
                                    <div class="flex justify-between text-gray-500">
                                        <span>Subtotal (incl. tax)</span>
                                        <span x-text="'$' + fmt(subtotal)"></span>
                                    </div>
                                    <div class="flex justify-between text-gray-500">
                                        <span>Fees</span>
                                        <span x-text="'$' + fmt(feesTotal)"></span>
                                    </div>
                                    <div class="flex justify-between text-gray-500">
                                        <span>Shipping</span>
                                        <span x-text="'$' + fmt(shippingAmount)"></span>
                                    </div>
                                    <div class="flex justify-between border-t border-gray-200 pt-2 font-semibold text-gray-900">
                                        <span>Grand Total</span>
                                        <span x-text="'$' + fmt(grandTotal)"></span>
                                    </div>
                                </div>

                                @error('error')
                                    <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
                                @enderror

                                <div class="mt-6 flex gap-3">
                                    <button type="submit"
                                            class="flex-1 rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                        Save Changes
                                    </button>
                                    <a href="{{ route('orders.show', $order) }}"
                                       class="rounded-md border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50">
                                        Cancel
                                    </a>
                                </div>

                            </div>
                        </div>

                    </div>{{-- /right sidebar --}}

                </div>
            </form>

        </div>
    </div>
</x-app-layout>
