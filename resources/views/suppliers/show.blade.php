<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $supplier->name }}</h2>
            <div class="flex items-center gap-3">
                @can('update', $supplier)
                    <a href="{{ route('suppliers.edit', $supplier) }}"
                       class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Edit
                    </a>
                @endcan
                <a href="{{ route('suppliers.index') }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                    Back to Suppliers
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 rounded-md bg-green-100 px-4 py-3 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 rounded-md bg-red-100 px-4 py-3 text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="px-6 py-5 border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-900">Supplier Details</h3>
                </div>
                <dl class="divide-y divide-gray-200">
                    <div class="px-6 py-4 grid grid-cols-3 gap-4">
                        <dt class="text-sm font-medium text-gray-500">Name</dt>
                        <dd class="col-span-2 text-sm text-gray-900">{{ $supplier->name }}</dd>
                    </div>
                    <div class="px-6 py-4 grid grid-cols-3 gap-4">
                        <dt class="text-sm font-medium text-gray-500">Contact Name</dt>
                        <dd class="col-span-2 text-sm text-gray-900">{{ $supplier->contact_name ?? '—' }}</dd>
                    </div>
                    <div class="px-6 py-4 grid grid-cols-3 gap-4">
                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                        <dd class="col-span-2 text-sm text-gray-900">{{ $supplier->email }}</dd>
                    </div>
                    <div class="px-6 py-4 grid grid-cols-3 gap-4">
                        <dt class="text-sm font-medium text-gray-500">Phone</dt>
                        <dd class="col-span-2 text-sm text-gray-900">{{ $supplier->phone }}</dd>
                    </div>
                    <div class="px-6 py-4 grid grid-cols-3 gap-4">
                        <dt class="text-sm font-medium text-gray-500">Address</dt>
                        <dd class="col-span-2 text-sm text-gray-900">
                            @if($supplier->address)
                                {{ $supplier->address }}<br>
                                {{ implode(', ', array_filter([$supplier->city, $supplier->state, $supplier->postal_code])) }}
                                @if($supplier->country)
                                    <br>{{ $supplier->country }}
                                @endif
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="px-6 py-4 grid grid-cols-3 gap-4">
                        <dt class="text-sm font-medium text-gray-500">Payment Terms</dt>
                        <dd class="col-span-2 text-sm text-gray-900">{{ $supplier->payment_terms ?? '—' }}</dd>
                    </div>
                    <div class="px-6 py-4 grid grid-cols-3 gap-4">
                        <dt class="text-sm font-medium text-gray-500">Notes</dt>
                        <dd class="col-span-2 text-sm text-gray-900">{{ $supplier->notes ?? '—' }}</dd>
                    </div>
                    <div class="px-6 py-4 grid grid-cols-3 gap-4">
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="col-span-2 text-sm">
                            @php $color = $supplier->status->color(); @endphp
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold
                                {{ $color === 'green' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $color === 'yellow' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                {{ $supplier->status->label() }}
                            </span>
                        </dd>
                    </div>
                    <div class="px-6 py-4 grid grid-cols-3 gap-4">
                        <dt class="text-sm font-medium text-gray-500">Created</dt>
                        <dd class="col-span-2 text-sm text-gray-900">{{ $supplier->created_at->format('M d, Y') }}</dd>
                    </div>
                </dl>
            </div>

            @can('changeStatus', $supplier)
                <div class="mt-6 rounded-lg bg-white shadow p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Change Status</h3>
                    <form method="POST" action="{{ route('suppliers.changeStatus', $supplier) }}" class="flex items-center gap-3">
                        @csrf
                        @method('PATCH')
                        <select name="status"
                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($statuses as $status)
                                <option value="{{ $status->value }}" @selected($supplier->status === $status)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit"
                                class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white hover:bg-gray-700">
                            Update Status
                        </button>
                    </form>
                </div>
            @endcan

            @can('delete', $supplier)
                <div class="mt-6">
                    <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}"
                          onsubmit="return confirm('Are you sure you want to delete this supplier?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                            Delete Supplier
                        </button>
                    </form>
                </div>
            @endcan

        </div>
    </div>
</x-app-layout>
