@props([
    'title' => 'Nothing here yet',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-dashed border-gray-300 bg-white px-6 py-12 text-center']) }}>
    <div class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-400">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
        </svg>
    </div>
    <h3 class="text-sm font-semibold text-gray-900">{{ $title }}</h3>
    @if ($description)
        <p class="mx-auto mt-1 max-w-sm text-sm text-gray-500">{{ $description }}</p>
    @endif
    @isset($actions)
        <div class="mt-4 flex justify-center gap-2">{{ $actions }}</div>
    @endisset
</div>
