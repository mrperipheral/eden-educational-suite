<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $title ?? config('app.name'))</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-full flex-col items-center justify-center bg-gray-50 px-4 py-12 text-gray-900 antialiased">
    {{--
        Minimal centred layout for pre-authentication screens (login, password
        reset, school selection). No sidebar / header chrome.
    --}}
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <span class="text-xl font-semibold text-brand-700">{{ config('app.name') }}</span>
        </div>

        <x-card>
            {{ $slot }}
        </x-card>
    </div>

    @stack('scripts')
</body>
</html>
