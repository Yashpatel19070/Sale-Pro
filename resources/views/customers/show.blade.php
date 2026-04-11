<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Customer: {{ $customer->name }}
            </h2>
            <div class="flex items-center gap-3">
                @can('update', $customer)
                    <a href="{{ route('customers.edit', $customer) }}"
                       class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Edit
                    </a>
                @endcan
                <a href="{{ route('customers.index') }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                    Back to List
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

            {{-- Customer detail card --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="px-6 py-5">
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $customer->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $customer->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Phone</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $customer->phone }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Company</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $customer->company_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Address</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $customer->address }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">City</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $customer->city }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">State</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $customer->state }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Postal Code</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $customer->postal_code }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Country</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $customer->country }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1 text-sm">
                                @php $color = $customer->status->color(); @endphp
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold
                                    {{ $color === 'green' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $color === 'yellow' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $color === 'red' ? 'bg-red-100 text-red-800' : '' }}">
                                    {{ $customer->status->label() }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $customer->created_at->format('M d, Y') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Portal Account / Email Verification --}}
            @can('update', $customer)
                @if($customer->user)
                    <div class="mt-6 rounded-lg bg-white p-6 shadow">
                        <h3 class="mb-4 text-sm font-medium text-gray-700">Portal Account</h3>
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-600">
                                Email verification:
                                @if($customer->user->hasVerifiedEmail())
                                    <span class="font-medium text-green-700">Verified</span>
                                    <span class="text-gray-400">({{ $customer->user->email_verified_at->format('M d, Y') }})</span>
                                @else
                                    <span class="font-medium text-yellow-700">Pending</span>
                                @endif
                            </div>
                            @unless($customer->user->hasVerifiedEmail())
                                <form method="POST" action="{{ route('customers.verifyEmail', $customer) }}">
                                    @csrf
                                    <button type="submit"
                                            class="rounded-md bg-green-600 px-4 py-2 text-sm text-white hover:bg-green-700">
                                        Force Verify Email
                                    </button>
                                </form>
                            @endunless
                        </div>
                    </div>
                @endif
            @endcan

            {{-- Change Status --}}
            @can('changeStatus', $customer)
                <div class="mt-6 rounded-lg bg-white p-6 shadow">
                    <h3 class="mb-4 text-sm font-medium text-gray-700">Change Status</h3>
                    <form method="POST"
                          action="{{ route('customers.changeStatus', $customer) }}"
                          class="flex items-center gap-3">
                        @csrf
                        @method('PATCH')
                        <select name="status"
                                class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach($statuses as $status)
                                <option value="{{ $status->value }}"
                                        {{ $customer->status === $status ? 'selected' : '' }}>
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

        </div>
    </div>
</x-app-layout>
