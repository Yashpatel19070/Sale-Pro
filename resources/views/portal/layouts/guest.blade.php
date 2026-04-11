<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

    <div class="w-full max-w-md">

        <div class="text-center mb-8">
            <a href="/" class="text-2xl font-bold text-gray-800">
                {{ config('app.name') }}
            </a>
        </div>

        <div class="bg-white rounded-lg shadow px-8 py-8">
            @yield('content')
        </div>

    </div>

</body>
</html>
