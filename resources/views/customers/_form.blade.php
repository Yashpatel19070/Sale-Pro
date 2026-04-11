@php
    $isEdit = $customer !== null;
    $action = $isEdit
        ? route('customers.update', $customer)
        : route('customers.store');
@endphp

<form method="POST" action="{{ $action }}" novalidate>
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="overflow-hidden rounded-xl bg-white shadow">
        <div class="border-b border-gray-100 px-6 py-4">
            <h3 class="text-sm font-semibold text-gray-700">
                {{ $isEdit ? 'Edit Customer' : 'New Customer' }}
            </h3>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

                {{-- First Name --}}
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700">
                        First Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="first_name" name="first_name"
                           value="{{ old('first_name', $customer?->first_name) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('first_name') border-red-300 @enderror"
                           required />
                    @error('first_name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Last Name --}}
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700">
                        Last Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="last_name" name="last_name"
                           value="{{ old('last_name', $customer?->last_name) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('last_name') border-red-300 @enderror"
                           required />
                    @error('last_name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" id="email" name="email"
                           value="{{ old('email', $customer?->email) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('email') border-red-300 @enderror" />
                    @error('email')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Phone --}}
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                    <input type="text" id="phone" name="phone"
                           value="{{ old('phone', $customer?->phone) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('phone') border-red-300 @enderror" />
                    @error('phone')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Company Name (full width) --}}
                <div class="md:col-span-2">
                    <label for="company_name" class="block text-sm font-medium text-gray-700">Company Name</label>
                    <input type="text" id="company_name" name="company_name"
                           value="{{ old('company_name', $customer?->company_name) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('company_name') border-red-300 @enderror" />
                    @error('company_name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Job Title --}}
                <div>
                    <label for="job_title" class="block text-sm font-medium text-gray-700">Job Title</label>
                    <input type="text" id="job_title" name="job_title"
                           value="{{ old('job_title', $customer?->job_title) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('job_title') border-red-300 @enderror" />
                    @error('job_title')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Status --}}
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <select id="status" name="status"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('status') border-red-300 @enderror">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}"
                                @selected(old('status', $customer?->status?->value ?? \App\Enums\CustomerStatus::Lead->value) === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                    @error('status')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Source --}}
                <div>
                    <label for="source" class="block text-sm font-medium text-gray-700">
                        Source <span class="text-red-500">*</span>
                    </label>
                    <select id="source" name="source"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('source') border-red-300 @enderror">
                        @foreach ($sources as $source)
                            <option value="{{ $source->value }}"
                                @selected(old('source', $customer?->source?->value ?? \App\Enums\CustomerSource::Other->value) === $source->value)>
                                {{ $source->label() }}
                            </option>
                        @endforeach
                    </select>
                    @error('source')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Assigned To --}}
                <div>
                    <label for="assigned_to" class="block text-sm font-medium text-gray-700">Assigned To</label>
                    <select id="assigned_to" name="assigned_to"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('assigned_to') border-red-300 @enderror">
                        <option value="">— Unassigned —</option>
                        @foreach ($salesUsers as $rep)
                            <option value="{{ $rep->id }}"
                                @selected(old('assigned_to', $customer?->assigned_to) == $rep->id)>
                                {{ $rep->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('assigned_to')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Department --}}
                <div>
                    <label for="department_id" class="block text-sm font-medium text-gray-700">Department</label>
                    <select id="department_id" name="department_id"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('department_id') border-red-300 @enderror">
                        <option value="">— None —</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept->id }}"
                                @selected(old('department_id', $customer?->department_id) == $dept->id)>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('department_id')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Address section --}}
                <div class="md:col-span-2 border-t border-gray-100 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-4">Address</p>
                </div>

                {{-- Address Line 1 (full width) --}}
                <div class="md:col-span-2">
                    <label for="address_line1" class="block text-sm font-medium text-gray-700">Address Line 1</label>
                    <input type="text" id="address_line1" name="address_line1"
                           value="{{ old('address_line1', $customer?->address_line1) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('address_line1') border-red-300 @enderror" />
                    @error('address_line1')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Address Line 2 (full width) --}}
                <div class="md:col-span-2">
                    <label for="address_line2" class="block text-sm font-medium text-gray-700">Address Line 2</label>
                    <input type="text" id="address_line2" name="address_line2"
                           value="{{ old('address_line2', $customer?->address_line2) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('address_line2') border-red-300 @enderror" />
                    @error('address_line2')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- City --}}
                <div>
                    <label for="city" class="block text-sm font-medium text-gray-700">City</label>
                    <input type="text" id="city" name="city"
                           value="{{ old('city', $customer?->city) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('city') border-red-300 @enderror" />
                    @error('city')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- State --}}
                <div>
                    <label for="state" class="block text-sm font-medium text-gray-700">State</label>
                    <input type="text" id="state" name="state"
                           value="{{ old('state', $customer?->state) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('state') border-red-300 @enderror" />
                    @error('state')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Postcode --}}
                <div>
                    <label for="postcode" class="block text-sm font-medium text-gray-700">Postcode</label>
                    <input type="text" id="postcode" name="postcode"
                           value="{{ old('postcode', $customer?->postcode) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('postcode') border-red-300 @enderror" />
                    @error('postcode')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Country --}}
                <div>
                    <label for="country" class="block text-sm font-medium text-gray-700">Country</label>
                    <input type="text" id="country" name="country"
                           value="{{ old('country', $customer?->country ?? 'Australia') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('country') border-red-300 @enderror" />
                    @error('country')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Notes (full width) --}}
                <div class="md:col-span-2">
                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea id="notes" name="notes" rows="4"
                              class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('notes') border-red-300 @enderror">{{ old('notes', $customer?->notes) }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

            </div>
        </div>

        <div class="border-t border-gray-100 bg-gray-50 px-6 py-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                <a href="{{ route('customers.index') }}"
                   class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:w-auto">
                    Cancel
                </a>
                <button type="submit"
                        class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 sm:w-auto">
                    {{ $isEdit ? 'Save Changes' : 'Create Customer' }}
                </button>
            </div>
        </div>
    </div>
</form>
