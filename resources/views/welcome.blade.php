<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Room Bidding') }}: fair rent split for shared houses</title>
        @include('partials.head-meta')
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900">
        <div class="min-h-screen flex flex-col">
            {{-- Top bar --}}
            <header class="w-full">
                <div class="max-w-5xl mx-auto px-6 py-5 flex items-center justify-between">
                    <span class="flex items-center gap-2 font-semibold text-lg">
                        <x-application-logo class="w-6 h-6 text-blue-600" />
                        {{ config('app.name', 'Room Bidding') }}
                    </span>
                    <nav class="flex items-center gap-4 text-sm">
                        @auth
                            <a href="{{ route('dashboard') }}" class="text-blue-600 hover:underline">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="text-gray-600 hover:text-gray-900">Log in</a>
                            <a href="{{ route('register') }}" class="px-3 py-1.5 rounded-md bg-gray-900 text-white hover:bg-gray-700">Sign up</a>
                        @endauth
                    </nav>
                </div>
            </header>

            {{-- Hero --}}
            <main class="flex-1">
                <section class="max-w-3xl mx-auto px-6 pt-12 pb-8 text-center">
                    <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight">Split shared-house rent, fairly.</h1>
                    <p class="mt-4 text-lg text-gray-600">
                        Rooms aren't equal, so why split rent equally? This tool prices each room by
                        how much it's wanted, and always adds up to exactly the total rent.
                    </p>
                    <div class="mt-8 flex items-center justify-center gap-3">
                        <a href="{{ route('tool') }}"
                           class="px-6 py-3 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
                            Launch the tool
                        </a>
                        <span class="text-xs text-gray-500">No account needed. Sign in only to save results.</span>
                    </div>
                </section>

                {{-- How it works --}}
                <section class="max-w-4xl mx-auto px-6 py-10">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 text-center mb-6">How it works</h2>
                    <ol class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <li class="bg-white rounded-lg border border-gray-100 p-5">
                            <div class="text-blue-600 font-semibold mb-1">1 · Set up the house</div>
                            <p class="text-sm text-gray-600">Enter the total rent, the rooms and their capacities, and everyone's name.</p>
                        </li>
                        <li class="bg-white rounded-lg border border-gray-100 p-5">
                            <div class="text-blue-600 font-semibold mb-1">2 · Place people</div>
                            <p class="text-sm text-gray-600">Drag each housemate into a room. Live prices show what each person would pay.</p>
                        </li>
                        <li class="bg-white rounded-lg border border-gray-100 p-5">
                            <div class="text-blue-600 font-semibold mb-1">3 · Let it settle</div>
                            <p class="text-sm text-gray-600">Over-subscribed rooms get pricier, quieter rooms cheaper. Continue rounds until no room is over capacity.</p>
                        </li>
                        <li class="bg-white rounded-lg border border-gray-100 p-5">
                            <div class="text-blue-600 font-semibold mb-1">4 · See who pays what</div>
                            <p class="text-sm text-gray-600">A clear per-person breakdown that always sums to exactly the rent, printable and shareable.</p>
                        </li>
                    </ol>
                </section>
            </main>

            <footer class="py-8 text-center text-xs text-gray-400">
                {{ config('app.name', 'Room Bidding') }}
            </footer>
        </div>
    </body>
</html>
