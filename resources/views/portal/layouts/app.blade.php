<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — My Account</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">

    <nav class="bg-white border-b shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-3 flex justify-between items-center">
            <a href="{{ route('portal.dashboard') }}" class="text-lg font-bold text-gray-800">
                {{ config('app.name') }}
            </a>
            <div class="flex items-center gap-6 text-sm text-gray-600">
                <a href="{{ route('portal.dashboard') }}" class="hover:text-gray-900">Home</a>
                <a href="{{ route('portal.profile.show') }}" class="hover:text-gray-900">Profile</a>
                <form method="POST" action="{{ route('portal.logout') }}">
                    @csrf
                    <button type="submit" class="hover:text-gray-900">Logout</button>
                </form>
            </div>
        </div>
    </nav>

    @if(session('success'))
        <div class="max-w-5xl mx-auto px-4 mt-4">
            <div class="bg-green-100 text-green-800 px-4 py-3 rounded text-sm">
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if(session('status'))
        <div class="max-w-5xl mx-auto px-4 mt-4">
            <div class="bg-blue-100 text-blue-800 px-4 py-3 rounded text-sm">
                {{ session('status') }}
            </div>
        </div>
    @endif

    <main class="max-w-5xl mx-auto px-4 py-8">
        @yield('content')
    </main>

</body>
</html>
