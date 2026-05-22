@extends('portal.layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">My Profile</h1>
        <div class="flex gap-3">
            <a href="{{ route('portal.profile.edit') }}"
               class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Edit Profile
            </a>
            <a href="{{ route('portal.profile.password') }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                Change Password
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    {{-- Default Address Card --}}
    <div class="mb-6 overflow-hidden rounded-lg bg-white shadow">
        <div class="flex items-center justify-between px-6 py-4 border-b">
            <h2 class="text-sm font-semibold text-gray-700">Default Shipping Address</h2>
            <a href="{{ route('portal.addresses.index') }}"
               class="text-sm text-indigo-600 hover:text-indigo-900">Manage Addresses</a>
        </div>
        <div class="px-6 py-4 text-sm text-gray-600">
            @if ($defaultAddress)
                <p class="font-medium text-gray-800">{{ $defaultAddress->first_name }} {{ $defaultAddress->last_name }}</p>
                <p>{{ $defaultAddress->address_line1 }}@if($defaultAddress->address_line2), {{ $defaultAddress->address_line2 }}@endif</p>
                <p>{{ $defaultAddress->city }}, {{ $defaultAddress->state }} {{ $defaultAddress->postal_code }}, {{ $defaultAddress->country }}</p>
                @if ($defaultAddress->phone)<p class="mt-1 text-gray-500">{{ $defaultAddress->phone }}</p>@endif
            @else
                <span class="text-gray-400">No default address set. </span>
                <a href="{{ route('portal.addresses.create') }}" class="text-indigo-600 hover:underline">Add one now.</a>
            @endif
        </div>
    </div>

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
                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                    <dd class="mt-1 text-sm">
                        @php $color = $customer->status->color(); @endphp
                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold
                            {{ $color === 'green'  ? 'bg-green-100 text-green-800'   : '' }}
                            {{ $color === 'yellow' ? 'bg-yellow-100 text-yellow-800' : '' }}
                            {{ $color === 'red'    ? 'bg-red-100 text-red-800'       : '' }}">
                            {{ $customer->status->label() }}
                        </span>
                    </dd>
                </div>
            </dl>
        </div>
    </div>
@endsection
