@props([
    'align' => 'right',
    'width' => '48',
])

@php
    $alignmentClasses = match ($align) {
        'left' => 'origin-top-left left-0',
        'top' => 'origin-top',
        default => 'origin-top-right right-0',
    };
    $widthClass = 'w-'.$width;
@endphp

<div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    <div
        x-show="open"
        x-transition
        x-cloak
        class="absolute z-50 mt-2 {{ $widthClass }} {{ $alignmentClasses }} rounded-md border border-gray-200 bg-white py-1 shadow-lg"
        role="menu"
    >
        {{ $slot }}
    </div>
</div>
