@props([
    'context' => null,
])

@php
    $hour = (int) now()->format('G');
    $greeting = match (true) {
        $hour >= 5 && $hour < 12 => __('Good morning'),
        $hour >= 12 && $hour < 17 => __('Good afternoon'),
        default => __('Good evening'),
    };
    $firstName = str(auth()->user()->name)->trim()->before(' ')->toString() ?: auth()->user()->name;
@endphp

<div {{ $attributes->class(['mb-1']) }}>
    <p class="text-lg font-semibold text-gray-900 sm:text-xl">
        {{ $greeting }}, {{ $firstName }} <span aria-hidden="true">👋</span>
    </p>
    @if ($context)
        <p class="mt-0.5 text-sm text-gray-500">{{ $context }}</p>
    @endif
</div>
