@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $textareaClass = $selectClass;
@endphp

<div class="space-y-6">
    <x-input name="title" :label="__('Title')" :value="old('title', $announcement->title)" required />

    <div class="space-y-1">
        <label for="audience" class="block text-sm font-medium text-gray-700">{{ __('Audience') }}</label>
        <select id="audience" name="audience" class="{{ $selectClass }}">
            @foreach ($audiences as $a)
                <option value="{{ $a->value }}" @selected(old('audience', $announcement->audience?->value) === $a->value)>{{ $a->label() }}</option>
            @endforeach
        </select>
        @error('audience') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="space-y-1">
        <label for="body" class="block text-sm font-medium text-gray-700">{{ __('Body') }}</label>
        <textarea id="body" name="body" rows="8" class="{{ $textareaClass }}" required>{{ old('body', $announcement->body) }}</textarea>
        @error('body') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
