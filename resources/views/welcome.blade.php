<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col bg-gray-50 text-gray-900 antialiased">
    <header class="flex items-center justify-between px-6 py-4">
        <span class="text-sm font-semibold text-brand-700">{{ config('app.name') }}</span>
        <nav class="flex items-center gap-2">
            @auth
                <x-button :href="url('/dashboard')" size="sm">{{ __('Go to dashboard') }}</x-button>
            @else
                <x-button :href="route('login')" variant="ghost" size="sm">{{ __('Sign in') }}</x-button>
                <x-button :href="route('register')" size="sm">{{ __('Create account') }}</x-button>
            @endauth
        </nav>
    </header>

    <main class="flex flex-1 items-center justify-center px-4 py-16">
        <div class="w-full max-w-lg text-center">
            <p class="text-sm font-medium uppercase tracking-wide text-brand-600">{{ __('Eden Education Suite') }}</p>
            <h1 class="mt-2 text-2xl font-semibold text-gray-900">{{ __('One platform for running your school') }}</h1>
            <p class="mt-3 text-sm text-gray-500">
                {{ __('A multi-school management platform for nursery, primary and secondary schools — your school\'s own portal, powered by Eden Education Suite. Sign in to your account, or create one to get started.') }}
            </p>
        </div>
    </main>

    <footer class="px-6 py-4 text-center text-xs text-gray-400">
        &copy; {{ date('Y') }} {{ config('app.name') }}
    </footer>
</body>
</html>
