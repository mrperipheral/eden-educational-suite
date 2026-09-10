@props([
    'size' => 'md',
    'label' => 'Loading',
])

@php
    $sizes = ['sm' => 'h-4 w-4', 'md' => 'h-6 w-6', 'lg' => 'h-8 w-8'];
    $dimension = $sizes[$size] ?? $sizes['md'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 text-gray-500']) }} role="status" aria-live="polite">
    <svg class="{{ $dimension }} animate-spin text-brand-600" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
    </svg>
    <span class="sr-only">{{ $label }}</span>
    @if ($slot->isNotEmpty())
        <span class="text-sm not-sr-only">{{ $slot }}</span>
    @endif
</span>
