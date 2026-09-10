<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col items-center justify-center bg-gray-50 px-4 py-12 text-gray-900 antialiased">
    <div class="w-full max-w-lg text-center">
        <p class="text-sm font-medium uppercase tracking-wide text-brand-600">School Management Platform</p>
        <h1 class="mt-2 text-2xl font-semibold text-gray-900">Platform foundation is ready</h1>
        <p class="mt-3 text-sm text-gray-500">
            Multi-school SaaS for nursery, primary and secondary schools. Domain modules
            (users, schools, students, portals) are delivered in later milestones.
        </p>

        <div class="mt-6 flex items-center justify-center gap-3">
            <x-button href="{{ url('/health') }}" variant="secondary" size="sm">Health check</x-button>
            <x-button href="{{ url('/up') }}" variant="ghost" size="sm">Framework status</x-button>
        </div>
    </div>
</body>
</html>
