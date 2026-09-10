@props([
    'name',
    'label' => null,
    'checked' => false,
])

@php
    $id = $attributes->get('id', $name);
@endphp

<label for="{{ $id }}" class="flex items-start gap-2 text-sm text-gray-700">
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="checkbox"
        value="1"
        @checked(old($name, $checked))
        {{ $attributes->merge(['class' => 'mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500']) }}
    >
    @if ($label)
        <span>{{ $label }}</span>
    @endif
    {{ $slot }}
</label>
