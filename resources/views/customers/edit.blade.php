<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Edit Customer: {{ $customer->name }}
            </h2>
            <a href="{{ route('customers.show', $customer) }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                Back
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

            <div class="rounded-lg bg-white p-6 shadow">
                <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $customer->name) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $customer->email) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Phone <span class="text-red-500">*</span></label>
                        <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Company Name</label>
                        <input type="text" name="company_name" value="{{ old('company_name', $customer->company_name) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        @error('company_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status <span class="text-red-500">*</span></label>
                        <select name="status"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @foreach($statuses as $status)
                                <option value="{{ $status->value }}"
                                        {{ old('status', $customer->status->value) === $status->value ? 'selected' : '' }}>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    {{-- Tax & Exemption (Phase 5) --}}
                    <div x-data="{ taxExempt: @json((bool) old('tax_exempt', $customer->tax_exempt)) }" class="mt-6 border-t pt-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Tax & Exemption</h3>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs">Federal Tax ID / Taxpayer ID</label>
                                <input type="text" name="tax_identification_number"
                                       value="{{ old('tax_identification_number', $customer->tax_identification_number) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                                @error('tax_identification_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div class="flex items-center mt-6">
                                <input type="hidden" name="tax_exempt" value="0">
                                <input type="checkbox" name="tax_exempt" value="1" x-model="taxExempt"
                                       class="mr-2 rounded border-gray-300" />
                                <label class="text-sm font-medium">Tax-Exempt Customer</label>
                            </div>
                        </div>

                        <div x-show="taxExempt" x-transition class="mt-4 grid grid-cols-2 gap-4 bg-amber-50 p-3 rounded">
                            <div>
                                <label class="block text-xs">Exemption Reason *</label>
                                <select name="entity_use_code" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    <option value="">— select —</option>
                                    @php $euc = old('entity_use_code', $customer->entity_use_code); @endphp
                                    <option value="G" @selected($euc === 'G')>G — Resale</option>
                                    <option value="E" @selected($euc === 'E')>E — Charitable</option>
                                    <option value="A" @selected($euc === 'A')>A — Federal Government</option>
                                    <option value="B" @selected($euc === 'B')>B — State/Local Government</option>
                                    <option value="F" @selected($euc === 'F')>F — Religious</option>
                                    <option value="H" @selected($euc === 'H')>H — Agriculture</option>
                                    <option value="I" @selected($euc === 'I')>I — Industrial / Manufacturer</option>
                                    <option value="J" @selected($euc === 'J')>J — Direct Pay Permit</option>
                                    <option value="K" @selected($euc === 'K')>K — Direct Mail</option>
                                    <option value="M" @selected($euc === 'M')>M — Educational</option>
                                    <option value="N" @selected($euc === 'N')>N — Local Government</option>
                                    <option value="C" @selected($euc === 'C')>C — Tribal Government</option>
                                    <option value="D" @selected($euc === 'D')>D — Foreign Diplomat</option>
                                    <option value="L" @selected($euc === 'L')>L — Other</option>
                                </select>
                                @error('entity_use_code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block text-xs">Certificate Number *</label>
                                <input type="text" name="exemption_certificate_number"
                                       value="{{ old('exemption_certificate_number', $customer->exemption_certificate_number) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                                @error('exemption_certificate_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block text-xs">Exposure Zone (state) *</label>
                                <input type="text" name="exemption_exposure_zone" placeholder="California"
                                       value="{{ old('exemption_exposure_zone', $customer->exemption_exposure_zone) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                                @error('exemption_exposure_zone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div></div>

                            <div>
                                <label class="block text-xs">Signed Date *</label>
                                <input type="date" name="exemption_signed_date"
                                       value="{{ old('exemption_signed_date', optional($customer->exemption_signed_date)->toDateString()) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                                @error('exemption_signed_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label class="block text-xs">Expires At *</label>
                                <input type="date" name="exemption_expires_at"
                                       value="{{ old('exemption_expires_at', optional($customer->exemption_expires_at)->toDateString()) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                                @error('exemption_expires_at')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Save Changes
                        </button>
                        <a href="{{ route('customers.show', $customer) }}"
                           class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
