@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
])

@php
    $id = $attributes->get('id', $name);
    $error = $errors->first($name);
@endphp

<div class="space-y-1">
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-gray-700">{{ $label }}</label>
    @endif

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
        {{ $attributes->merge([
            'class' => 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset '
                .($error ? 'ring-red-400 focus:ring-red-500' : 'ring-gray-300 focus:ring-brand-500')
                .' placeholder:text-gray-400 focus:ring-2 focus:ring-inset',
        ]) }}
    >

    @if ($hint && ! $error)
        <p class="text-xs text-gray-500">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" class="text-xs text-red-600">{{ $error }}</p>
    @endif
</div>
