@props([
    'title' => null,
    'sidebarBg' => null,
    'sidebarFg' => null,
])
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
<body class="h-full bg-gray-50 text-gray-900 antialiased">
    {{--
        Application shell.

        Desktop  : fixed sidebar + top header + scrollable main content.
        Mobile   : compact header with a hamburger that opens a slide-in drawer
                   (closes on backdrop click, Escape, or the drawer's own close
                   button), capped at 85vw so a small phone never has it fill
                   the whole screen.

        Role-specific navigation and the school-identity sidebar header are
        intentionally NOT built here — this shell stays generic. They're
        injected via the $navigation and $brandHeader slots by
        <x-layouts.authenticated> (see docs/ui-ux-guidelines.md).
    --}}
    <div x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false" class="min-h-full">
        {{-- Mobile drawer backdrop --}}
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden"
            x-cloak
        ></div>

        {{-- Sidebar — a school's own primary colour (if configured) washes the
             whole panel, matching the product's "school owns the experience"
             branding; with no colour configured it stays the neutral white
             default. See docs/ui-ux-guidelines.md §"School branding / theme". --}}
        <aside
            @class([
                'fixed inset-y-0 left-0 z-50 flex w-72 max-w-[85vw] transform flex-col transition-transform duration-200 ease-in-out lg:w-64 lg:max-w-none lg:translate-x-0',
                'border-r border-gray-200 bg-white' => ! ($sidebarBg ?? null),
            ])
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            @if(! empty($sidebarBg)) style="background-color: {{ $sidebarBg }};" @endif
            x-cloak
        >
            <button
                type="button"
                @click="sidebarOpen = false"
                @class([
                    'absolute right-3 top-3 rounded-md p-2 lg:hidden',
                    'text-gray-400 hover:bg-gray-100 hover:text-gray-600' => empty($sidebarBg),
                ])
                @if(! empty($sidebarBg)) style="color: {{ $sidebarFg ?? '#ffffff' }};" @endif
                aria-label="{{ __('Close navigation') }}"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>

            {{ $brandHeader ?? '' }}

            <nav class="flex flex-1 flex-col gap-1 overflow-y-auto p-4" aria-label="{{ __('Primary') }}">
                {{ $navigation ?? '' }}
            </nav>
        </aside>

        {{-- Content column --}}
        <div class="lg:pl-64">
            {{-- Header --}}
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-gray-200 bg-white/95 px-3 backdrop-blur sm:gap-4 sm:px-6">
                <button
                    type="button"
                    @click="sidebarOpen = true"
                    class="-ml-1 shrink-0 rounded-md p-2 text-gray-500 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 lg:hidden"
                    aria-label="{{ __('Open navigation') }}"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <div class="min-w-0 flex-1">
                    {{ $header ?? '' }}
                </div>

                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    {{ $headerActions ?? '' }}
                </div>
            </header>

            {{-- Flash messages --}}
            @if (session('status') || session('success') || session('error'))
                <div class="px-4 pt-4 sm:px-6 lg:px-8">
                    @if (session('success'))
                        <x-alert variant="success">{{ session('success') }}</x-alert>
                    @endif
                    @if (session('error'))
                        <x-alert variant="danger">{{ session('error') }}</x-alert>
                    @endif
                    @if (session('status'))
                        <x-alert variant="info">{{ session('status') }}</x-alert>
                    @endif
                </div>
            @endif

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
