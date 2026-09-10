@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $currentRelationship = old('relationship', $link->relationship?->value);
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="space-y-1">
        <label for="relationship" class="block text-sm font-medium text-gray-700">{{ __('Relationship') }} <span class="text-red-500">*</span></label>
        <select id="relationship" name="relationship" class="{{ $selectClass }}">
            <option value="">{{ __('Select…') }}</option>
            @foreach ($relationships as $relationship)
                <option value="{{ $relationship->value }}" @selected($currentRelationship === $relationship->value)>{{ $relationship->label() }}</option>
            @endforeach
        </select>
        @error('relationship') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="flex items-end pb-2">
        <x-checkbox name="is_primary" :label="__('Primary contact for this student')" :checked="old('is_primary', $link->is_primary)" />
    </div>
</div>
@error('is_primary') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
