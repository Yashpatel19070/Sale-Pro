@extends('portal.layouts.app')

@section('content')
    <h1 class="mb-2 text-2xl font-bold text-gray-800">Welcome, {{ $customer->name }}</h1>
    <p class="mb-6 text-sm text-gray-500">You are logged in to your account.</p>

    <div class="rounded-lg bg-white p-6 shadow">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500">Email</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $customer->email }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Phone</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $customer->phone }}</dd>
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

    <div class="mt-6 flex gap-3">
        <a href="{{ route('portal.profile.show') }}"
           class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            View Profile
        </a>
        <a href="{{ route('portal.profile.edit') }}"
           class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
            Edit Profile
        </a>
        <a href="{{ route('portal.profile.password') }}"
           class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
            Change Password
        </a>
    </div>
@endsection
