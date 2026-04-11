@extends('portal.layouts.guest')

@section('content')
    <h2 class="text-xl font-bold text-gray-800 mb-6">Login</h2>

    @if(session('status'))
        <div class="bg-blue-100 text-blue-800 px-4 py-3 rounded text-sm mb-4">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('portal.login.store') }}">
        @csrf

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full border rounded px-3 py-2 text-sm @error('email') border-red-500 @enderror">
            @error('email') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input type="password" name="password" required
                   class="w-full border rounded px-3 py-2 text-sm">
        </div>

        <div class="mb-6 flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="remember"> Remember me
            </label>
            <a href="{{ route('portal.password.request') }}" class="text-sm text-blue-600 hover:underline">
                Forgot password?
            </a>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded font-medium hover:bg-blue-700">
            Login
        </button>
    </form>

    <p class="text-sm text-center text-gray-500 mt-4">
        Don't have an account?
        <a href="{{ route('portal.register') }}" class="text-blue-600 hover:underline">Register</a>
    </p>
@endsection
