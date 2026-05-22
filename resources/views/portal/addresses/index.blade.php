@extends('portal.layouts.app')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">My Addresses</h1>
        <a href="{{ route('portal.addresses.create') }}"
           class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            + Add Address
        </a>
    </div>

    @if (session('error'))
        <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if ($addresses->isEmpty())
        <div class="rounded-lg bg-white p-12 shadow text-center text-gray-500">
            No addresses yet.
            <a href="{{ route('portal.addresses.create') }}" class="ml-1 text-indigo-600 hover:underline">Add one now.</a>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($addresses as $address)
                <div class="rounded-lg bg-white p-5 shadow flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-semibold text-gray-800 text-sm">{{ $address->label }}</span>
                            @if ($address->is_default)
                                <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Default</span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-600">{{ $address->first_name }} {{ $address->last_name }}</p>
                        <p class="text-sm text-gray-600">{{ $address->address_line1 }}@if($address->address_line2), {{ $address->address_line2 }}@endif</p>
                        <p class="text-sm text-gray-600">{{ $address->city }}, {{ $address->state }} {{ $address->postal_code }}, {{ $address->country }}</p>
                        @if ($address->phone)
                            <p class="text-sm text-gray-500 mt-1">{{ $address->phone }}</p>
                        @endif
                    </div>
                    <div class="flex flex-col items-end gap-2 shrink-0">
                        <a href="{{ route('portal.addresses.edit', $address) }}"
                           class="text-sm text-indigo-600 hover:text-indigo-900">Edit</a>

                        @unless ($address->is_default)
                            <form method="POST" action="{{ route('portal.addresses.setDefault', $address) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="text-sm text-gray-500 hover:text-gray-800">Set Default</button>
                            </form>

                            <form method="POST" action="{{ route('portal.addresses.destroy', $address) }}"
                                  onsubmit="return confirm('Delete this address?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-500 hover:text-red-700">Delete</button>
                            </form>
                        @endunless
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
