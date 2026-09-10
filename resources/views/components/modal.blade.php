@props([
    'name',
    'title' => null,
])

{{--
    Generic Alpine modal. Open from anywhere with:
        <button @click="$dispatch('open-modal', 'my-modal')">Open</button>
    Close with $dispatch('close-modal', 'my-modal') or the built-in controls.
--}}
<div
    x-data="{ open: false }"
    x-on:open-modal.window="$event.detail === '{{ $name }}' && (open = true)"
    x-on:close-modal.window="$event.detail === '{{ $name }}' && (open = false)"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
>
    <div x-show="open" x-transition.opacity @click="open = false" class="absolute inset-0 bg-gray-900/50"></div>

    <div
        x-show="open"
        x-transition
        class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl"
    >
        @if ($title)
            <h3 class="text-base font-semibold text-gray-900">{{ $title }}</h3>
        @endif
        <div @class(['mt-2 text-sm text-gray-600' => $title])>
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="mt-6 flex justify-end gap-2">{{ $footer }}</div>
        @endisset
    </div>
</div>
