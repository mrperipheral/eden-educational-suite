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
        Mobile   : compact header with a hamburger that opens a slide-in drawer.

        Role-specific navigation is intentionally NOT built here yet. Later
        milestones inject navigation via the $navigation slot / a view composer.
    --}}
    <div x-data="{ sidebarOpen: false }" class="min-h-full">
        {{-- Mobile drawer backdrop --}}
        <div
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden"
            x-cloak
        ></div>

        {{-- Sidebar --}}
        <aside
            class="fixed inset-y-0 left-0 z-50 w-64 transform border-r border-gray-200 bg-white transition-transform duration-200 ease-in-out lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            x-cloak
        >
            <div class="flex h-16 items-center gap-2 border-b border-gray-200 px-6">
                <span class="text-lg font-semibold text-brand-700">{{ config('app.name') }}</span>
            </div>
            <nav class="flex flex-col gap-1 p-4" aria-label="Primary">
                {{ $navigation ?? '' }}
            </nav>
        </aside>

        {{-- Content column --}}
        <div class="lg:pl-64">
            {{-- Header --}}
            <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-gray-200 bg-white/95 px-4 backdrop-blur sm:px-6">
                <button
                    type="button"
                    @click="sidebarOpen = true"
                    class="-ml-1 rounded-md p-2 text-gray-500 hover:bg-gray-100 lg:hidden"
                    aria-label="Open navigation"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <div class="flex-1">
                    {{ $header ?? '' }}
                </div>

                <div class="flex items-center gap-3">
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
