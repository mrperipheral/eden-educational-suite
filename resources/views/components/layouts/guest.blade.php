@props([
    // Optional School — when given (a branding hint only, e.g. arriving via
    // `?school=slug` from a school-specific homepage), this pre-auth page
    // shows THAT school's identity instead of the generic Eden Education
    // Suite one. See School::resolveActiveBySlug(). Never establishes a
    // tenant context; purely cosmetic.
    'school' => null,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $title ?? ($school?->name ?? config('app.name')))</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-full flex-col items-center justify-center bg-gray-50 px-4 py-12 text-gray-900 antialiased">
    {{--
        Minimal centred layout for pre-authentication screens (login, password
        reset, school selection). No sidebar / header chrome.
    --}}
    @php
        $brandLogoUrl = $school?->settings?->hasLogo() ? route('schools.logo.show', $school) : null;
    @endphp
    <div class="w-full max-w-md">
        <div class="mb-6 flex flex-col items-center text-center">
            @if ($school)
                @if ($brandLogoUrl)
                    <img src="{{ $brandLogoUrl }}" alt="" class="mb-2 h-14 w-14 rounded-full object-contain ring-1 ring-gray-200">
                @endif
                <span class="text-xl font-semibold text-gray-900">{{ $school->name }}</span>
                <span class="text-xs text-gray-500">{{ __('School Portal') }}</span>
            @else
                <span class="text-xl font-semibold text-brand-700">{{ config('app.name') }}</span>
            @endif
        </div>

        <x-card>
            {{ $slot }}
        </x-card>

        @if ($school)
            <p class="mt-6 text-center text-[11px] tracking-wide text-gray-400">
                {{ __('Powered by Eden Education Suite') }}
            </p>
        @endif
    </div>

    @stack('scripts')
</body>
</html>
