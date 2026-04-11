@extends('portal.layouts.guest')

@section('content')
    <h2 class="text-xl font-bold text-gray-800 mb-6">Reset Password</h2>

    <form method="POST" action="{{ route('portal.password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email', $email) }}" required
                   class="w-full border rounded px-3 py-2 text-sm @error('email') border-red-500 @enderror">
            @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
            <input type="password" name="password" required
                   class="w-full border rounded px-3 py-2 text-sm @error('password') border-red-500 @enderror">
            @error('password') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input type="password" name="password_confirmation" required
                   class="w-full border rounded px-3 py-2 text-sm">
        </div>

        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded font-medium hover:bg-blue-700">
            Reset Password
        </button>
    </form>
@endsection
