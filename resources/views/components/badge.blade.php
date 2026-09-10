@props([
    'variant' => 'gray',
])

@php
    $variants = [
        'gray' => 'bg-gray-100 text-gray-700',
        'brand' => 'bg-brand-100 text-brand-700',
        'success' => 'bg-green-100 text-green-700',
        'warning' => 'bg-amber-100 text-amber-700',
        'danger' => 'bg-red-100 text-red-700',
    ];
    $classes = $variants[$variant] ?? $variants['gray'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium $classes"]) }}>
    {{ $slot }}
</span>
