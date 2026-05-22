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
