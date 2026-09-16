@php
    $settings = $school->settings;
    $logoUrl = $settings?->hasLogo() ? route('schools.logo.show', $school) : null;
    $brandColor = $settings?->brand_color;
    $heroText = $brandColor ? \App\Models\SchoolSetting::readableTextColor($brandColor) : '#ffffff';
    $hasProfile = $settings && ($settings->address_line1 || $settings->contact_email || $settings->contact_phone || $settings->website_url);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $school->name }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col bg-gray-50 text-gray-900 antialiased">
    <header class="flex items-center justify-between px-6 py-4">
        <span class="text-sm font-semibold text-gray-500">{{ __('School Portal') }}</span>
        <nav class="flex items-center gap-2">
            <x-button :href="route('login', ['school' => $school->slug])" variant="ghost" size="sm">{{ __('Sign in') }}</x-button>
            <x-button :href="route('register', ['school' => $school->slug])" size="sm">{{ __('Create account') }}</x-button>
        </nav>
    </header>

    <main class="flex flex-1 flex-col items-center px-4 py-10">
        <div
            class="flex w-full max-w-lg flex-col items-center rounded-2xl px-8 py-10 text-center"
            style="background-color: {{ $brandColor ?? '#0b3d66' }}; color: {{ $heroText }};"
        >
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="" class="mb-4 h-20 w-20 rounded-full bg-white object-contain p-2 ring-1 ring-black/5">
            @endif
            <h1 class="text-2xl font-semibold">{{ $school->name }}</h1>
            @if ($settings?->motto)
                <p class="mt-2 text-sm italic opacity-90">&ldquo;{{ $settings->motto }}&rdquo;</p>
            @endif
        </div>

        @if ($hasProfile)
            <div class="mt-6 w-full max-w-lg rounded-xl border border-gray-200 bg-white px-6 py-5 text-sm text-gray-600">
                @if ($settings->address_line1)
                    <p>
                        {{ $settings->address_line1 }}
                        @if ($settings->city), {{ $settings->city }}@endif
                        @if ($settings->state), {{ $settings->state }}@endif
                    </p>
                @endif
                @if ($settings->contact_phone || $settings->contact_email)
                    <p class="mt-1">
                        {{ $settings->contact_phone }}
                        @if ($settings->contact_phone && $settings->contact_email) &middot; @endif
                        {{ $settings->contact_email }}
                    </p>
                @endif
                @if ($settings->website_url)
                    <p class="mt-1"><a href="{{ $settings->website_url }}" class="text-brand-600 hover:text-brand-700" rel="noopener noreferrer" target="_blank">{{ $settings->website_url }}</a></p>
                @endif
            </div>
        @endif

        <div class="mt-8 flex items-center gap-3">
            <x-button :href="route('login', ['school' => $school->slug])">{{ __('Sign in to :school', ['school' => $school->name]) }}</x-button>
            <x-button :href="route('register', ['school' => $school->slug])" variant="secondary">{{ __('Create account') }}</x-button>
        </div>
    </main>

    <footer class="px-6 py-4 text-center text-[11px] tracking-wide text-gray-400">
        {{ __('Powered by Eden Education Suite') }}
    </footer>
</body>
</html>
