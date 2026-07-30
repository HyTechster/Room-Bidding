<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <title>{{ config('app.name', 'Room Bidding') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            <header class="bg-white shadow-sm">
                <div class="max-w-3xl mx-auto py-4 px-4 sm:px-6 lg:px-8 flex items-center gap-2">
                    <x-application-logo class="w-6 h-6 text-blue-600" />
                    <span class="font-semibold text-gray-800">{{ config('app.name', 'Room Bidding') }}</span>
                </div>
            </header>
            <main>{{ $slot }}</main>
        </div>
    </body>
</html>
