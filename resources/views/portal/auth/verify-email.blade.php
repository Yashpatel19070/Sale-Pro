@extends('portal.layouts.guest')

@section('content')
    <h2 class="text-xl font-bold text-gray-800 mb-2">Verify Your Email</h2>
    <p class="text-sm text-gray-500 mb-6">
        We sent a verification link to your email. Click the link to activate your account.
    </p>

    @if(session('status') === 'verification-link-sent')
        <div class="bg-green-100 text-green-800 px-4 py-3 rounded text-sm mb-4">
            A new verification link has been sent.
        </div>
    @endif

    <form method="POST" action="{{ route('portal.verification.send') }}">
        @csrf
        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded font-medium hover:bg-blue-700">
            Resend Verification Email
        </button>
    </form>

    <form method="POST" action="{{ route('portal.logout') }}" class="mt-3">
        @csrf
        <button type="submit" class="w-full text-sm text-gray-500 hover:underline">
            Logout
        </button>
    </form>
@endsection
