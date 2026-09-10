@props([
    'variant' => 'info',
    'title' => null,
    'dismissible' => false,
])

@php
    $variants = [
        'info' => 'bg-blue-50 text-blue-800 ring-blue-200',
        'success' => 'bg-green-50 text-green-800 ring-green-200',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'danger' => 'bg-red-50 text-red-800 ring-red-200',
    ];
    $classes = $variants[$variant] ?? $variants['info'];
@endphp

<div
    @if ($dismissible) x-data="{ show: true }" x-show="show" x-cloak @endif
    {{ $attributes->merge(['class' => "rounded-md p-4 text-sm ring-1 ring-inset $classes"]) }}
    role="alert"
>
    <div class="flex items-start gap-3">
        <div class="flex-1">
            @if ($title)
                <p class="font-semibold">{{ $title }}</p>
            @endif
            <div @class(['mt-0.5' => $title])>{{ $slot }}</div>
        </div>

        @if ($dismissible)
            <button type="button" @click="show = false" class="shrink-0 text-current/70 hover:text-current" aria-label="Dismiss">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        @endif
    </div>
</div>
