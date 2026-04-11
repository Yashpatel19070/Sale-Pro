@extends('portal.layouts.guest')

@section('content')
    <h2 class="text-xl font-bold text-gray-800 mb-6">Create Account</h2>

    <form method="POST" action="{{ route('portal.register.store') }}">
        @csrf

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full border rounded px-3 py-2 text-sm @error('name') border-red-500 @enderror">
            @error('name') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   class="w-full border rounded px-3 py-2 text-sm @error('email') border-red-500 @enderror">
            @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input type="password" name="password" required
                   class="w-full border rounded px-3 py-2 text-sm @error('password') border-red-500 @enderror">
            @error('password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input type="password" name="password_confirmation" required
                   class="w-full border rounded px-3 py-2 text-sm">
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
            <input type="text" name="phone" value="{{ old('phone') }}" required
                   class="w-full border rounded px-3 py-2 text-sm @error('phone') border-red-500 @enderror">
            @error('phone') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Company Name <span class="text-gray-400">(optional)</span>
            </label>
            <input type="text" name="company_name" value="{{ old('company_name') }}"
                   class="w-full border rounded px-3 py-2 text-sm">
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
            <input type="text" name="address" value="{{ old('address') }}" required
                   class="w-full border rounded px-3 py-2 text-sm @error('address') border-red-500 @enderror">
            @error('address') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                <input type="text" name="city" value="{{ old('city') }}" required
                       class="w-full border rounded px-3 py-2 text-sm @error('city') border-red-500 @enderror">
                @error('city') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                <input type="text" name="state" value="{{ old('state') }}" required
                       class="w-full border rounded px-3 py-2 text-sm @error('state') border-red-500 @enderror">
                @error('state') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mb-6 grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code</label>
                <input type="text" name="postal_code" value="{{ old('postal_code') }}" required
                       class="w-full border rounded px-3 py-2 text-sm @error('postal_code') border-red-500 @enderror">
                @error('postal_code') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                <input type="text" name="country" value="{{ old('country') }}" required
                       class="w-full border rounded px-3 py-2 text-sm @error('country') border-red-500 @enderror">
                @error('country') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded font-medium hover:bg-blue-700">
            Create Account
        </button>
    </form>

    <p class="text-sm text-center text-gray-500 mt-4">
        Already have an account?
        <a href="{{ route('portal.login') }}" class="text-blue-600 hover:underline">Login</a>
    </p>
@endsection
