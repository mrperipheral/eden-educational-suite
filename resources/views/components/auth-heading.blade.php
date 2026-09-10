@props([
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'mb-6']) }}>
    <h1 class="text-lg font-semibold text-gray-900">{{ $title }}</h1>
    @if ($description)
        <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
    @endif
</div>
