# Customer Address Module — Views

Three Blade views. All use `<x-app-layout>`. No separate show page.

---

## View List

| View | Path | Purpose |
|------|------|---------|
| `index` | `resources/views/customer-addresses/index.blade.php` | List all addresses for a customer |
| `create` | `resources/views/customer-addresses/create.blade.php` | Form to add a new address |
| `edit` | `resources/views/customer-addresses/edit.blade.php` | Form to edit an existing address |

---

## 1. `index.blade.php`

**File:** `resources/views/customer-addresses/index.blade.php`

**Variables:** `$customer` (Customer), `$addresses` (Collection<CustomerAddress> — default-first, then by label)

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Addresses for {{ $customer->name }}
            </h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('customers.show', $customer) }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                    Back to Customer
                </a>
                @can('create', [App\Models\CustomerAddress::class, $customer])
                    <a href="{{ route('customer-addresses.create', $customer) }}"
                       class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Add Address
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 rounded-md bg-green-100 px-4 py-3 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-lg bg-white shadow">
                @if($addresses->isEmpty())
                    <div class="py-16 text-center text-gray-500">
                        No addresses on file.
                        @can('create', [App\Models\CustomerAddress::class, $customer])
                            <a href="{{ route('customer-addresses.create', $customer) }}"
                               class="ml-1 text-indigo-600 hover:underline">Add an address to get started.</a>
                        @endcan
                    </div>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Label</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Phone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Default</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($addresses as $address)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                        {{ $address->label }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                        {{ $address->first_name }} {{ $address->last_name }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        {{ $address->address_line1 }}
                                        @if($address->address_line2)
                                            <br>{{ $address->address_line2 }}
                                        @endif
                                        <br>{{ $address->city }}, {{ $address->state }} {{ $address->postal_code }}, {{ $address->country }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                        {{ $address->phone ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        @if($address->is_default)
                                            <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">
                                                Default
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        <div class="flex items-center gap-3">
                                            @can('update', [$address, $customer])
                                                <a href="{{ route('customer-addresses.edit', [$customer, $address]) }}"
                                                   class="text-gray-600 hover:text-gray-900">Edit</a>
                                            @endcan

                                            @can('setDefault', [$address, $customer])
                                                @if(!$address->is_default)
                                                    <form method="POST"
                                                          action="{{ route('customer-addresses.setDefault', [$customer, $address]) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit"
                                                                class="text-indigo-600 hover:text-indigo-900">Set Default</button>
                                                    </form>
                                                @endif
                                            @endcan

                                            @can('delete', [$address, $customer])
                                                <form method="POST"
                                                      action="{{ route('customer-addresses.destroy', [$customer, $address]) }}"
                                                      onsubmit="return confirm('Delete this address?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="text-red-600 hover:text-red-900">Delete</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
```

---

## 2. `create.blade.php`

**File:** `resources/views/customer-addresses/create.blade.php`

**Variables:** `$customer` (Customer)

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Add Address for {{ $customer->name }}
            </h2>
            <a href="{{ route('customer-addresses.index', $customer) }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                Back to Addresses
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

            <div class="rounded-lg bg-white p-6 shadow">
                <form method="POST" action="{{ route('customer-addresses.store', $customer) }}" class="space-y-5">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Label <span class="text-red-500">*</span></label>
                        <input type="text" name="label" value="{{ old('label') }}"
                               placeholder="e.g. Home, Work, Billing"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        @error('label')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" value="{{ old('first_name') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('first_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" value="{{ old('last_name') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('last_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Address Line 1 <span class="text-red-500">*</span></label>
                        <input type="text" name="address_line1" value="{{ old('address_line1') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        @error('address_line1')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Address Line 2</label>
                        <input type="text" name="address_line2" value="{{ old('address_line2') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        @error('address_line2')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">City <span class="text-red-500">*</span></label>
                            <input type="text" name="city" value="{{ old('city') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('city')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">State <span class="text-red-500">*</span></label>
                            <input type="text" name="state" value="{{ old('state') }}"
                                   placeholder="e.g. TX"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('state')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Postal Code <span class="text-red-500">*</span></label>
                            <input type="text" name="postal_code" value="{{ old('postal_code') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('postal_code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Country <span class="text-red-500">*</span></label>
                            <input type="text" name="country" value="{{ old('country', 'US') }}"
                                   maxlength="2"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('country')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="is_default" id="is_default" value="1"
                               {{ old('is_default') ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <label for="is_default" class="text-sm font-medium text-gray-700">Set as default address</label>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Add Address
                        </button>
                        <a href="{{ route('customer-addresses.index', $customer) }}"
                           class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
```

---

## 3. `edit.blade.php`

**File:** `resources/views/customer-addresses/edit.blade.php`

**Variables:** `$customer` (Customer), `$address` (CustomerAddress)

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Edit Address — {{ $address->label }}
            </h2>
            <a href="{{ route('customer-addresses.index', $customer) }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                Back to Addresses
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

            <div class="rounded-lg bg-white p-6 shadow">
                <form method="POST" action="{{ route('customer-addresses.update', [$customer, $address]) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Label <span class="text-red-500">*</span></label>
                        <input type="text" name="label" value="{{ old('label', $address->label) }}"
                               placeholder="e.g. Home, Work, Billing"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        @error('label')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" value="{{ old('first_name', $address->first_name) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('first_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" value="{{ old('last_name', $address->last_name) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('last_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" value="{{ old('email', $address->email) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone', $address->phone) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Address Line 1 <span class="text-red-500">*</span></label>
                        <input type="text" name="address_line1" value="{{ old('address_line1', $address->address_line1) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        @error('address_line1')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Address Line 2</label>
                        <input type="text" name="address_line2" value="{{ old('address_line2', $address->address_line2) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        @error('address_line2')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">City <span class="text-red-500">*</span></label>
                            <input type="text" name="city" value="{{ old('city', $address->city) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('city')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">State <span class="text-red-500">*</span></label>
                            <input type="text" name="state" value="{{ old('state', $address->state) }}"
                                   placeholder="e.g. TX"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('state')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Postal Code <span class="text-red-500">*</span></label>
                            <input type="text" name="postal_code" value="{{ old('postal_code', $address->postal_code) }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('postal_code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Country <span class="text-red-500">*</span></label>
                            <input type="text" name="country" value="{{ old('country', $address->country) }}"
                                   maxlength="2"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('country')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="is_default" id="is_default" value="1"
                               {{ old('is_default', $address->is_default) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <label for="is_default" class="text-sm font-medium text-gray-700">Set as default address</label>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Save Changes
                        </button>
                        <a href="{{ route('customer-addresses.index', $customer) }}"
                           class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
```

---

## Navigation Integration

Add "Addresses" link on the customer show page (`customers/show.blade.php`):

```blade
<a href="{{ route('customer-addresses.index', $customer) }}"
   class="text-indigo-600 hover:text-indigo-900">
    Addresses
</a>
```

---

## Notes

- Layout: `<x-app-layout>` + `<x-slot name="header">` — matches all other admin views
- No custom components — plain Tailwind HTML throughout
- Input class: `mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm`
- Validation: `@error('field')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror` inline, no wrapper div
- Delete confirm: `onsubmit="return confirm('...')"` — no JS framework
- `@can` args: `[App\Models\CustomerAddress::class, $customer]` for create; `[$address, $customer]` for update/delete/setDefault
- `old('field', $address->field)` — second arg is model value, applied only in edit view
- Checkbox `is_default`: `value="1"` — `prepareForValidation` normalizes via `$this->boolean('is_default')`
- Flash: `session('success')` green banner — same pattern as customers/index
