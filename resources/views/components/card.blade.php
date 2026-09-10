@props([
    'title' => null,
    'padding' => true,
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-gray-200 bg-white shadow-sm']) }}>
    @if ($title || isset($actions))
        <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-4 py-3 sm:px-6">
            <h3 class="text-sm font-semibold text-gray-900">{{ $title }}</h3>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div @class(['px-4 py-4 sm:px-6' => $padding])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-gray-200 px-4 py-3 sm:px-6">{{ $footer }}</div>
    @endisset
</div>
