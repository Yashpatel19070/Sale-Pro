@extends('portal.layouts.guest')

@section('content')
    <h2 class="text-xl font-bold text-gray-800 mb-2">Forgot Password</h2>
    <p class="text-sm text-gray-500 mb-6">Enter your email and we'll send a reset link.</p>

    @if(session('status'))
        <div class="bg-green-100 text-green-800 px-4 py-3 rounded text-sm mb-4">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('portal.password.email') }}">
        @csrf

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full border rounded px-3 py-2 text-sm @error('email') border-red-500 @enderror">
            @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded font-medium hover:bg-blue-700">
            Send Reset Link
        </button>
    </form>

    <p class="text-sm text-center text-gray-500 mt-4">
        <a href="{{ route('portal.login') }}" class="text-blue-600 hover:underline">Back to login</a>
    </p>
@endsection
