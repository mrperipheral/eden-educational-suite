@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp

<x-layouts.authenticated :title="__('Edit assessment')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('assessments.show', $assessment->id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← Back to assessment') }}</a>
        </p>

        <x-card :title="__('Assessment details')">
            <p class="mb-4 text-xs text-gray-500">
                {{ __('The academic context is fixed:') }}
                {{ $assessment->level?->name }} — {{ $assessment->arm?->name }} · {{ $assessment->subject?->name }} ·
                {{ $assessment->assessment_date->toFormattedDateString() }}
            </p>

            <form method="POST" action="{{ route('assessments.update', $assessment->id) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div class="space-y-1">
                    <label for="assessment_category_id" class="block text-sm font-medium text-gray-700">{{ __('Category') }} <span class="text-red-500">*</span></label>
                    <select id="assessment_category_id" name="assessment_category_id"
                        class="block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                        @foreach ($categories->where('is_active', true)->merge($categories->where('id', $assessment->assessment_category_id)) as $category)
                            <option value="{{ $category->id }}" @selected(old('assessment_category_id', $assessment->assessment_category_id) == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('assessment_category_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <x-input name="title" :label="__('Title')" :value="old('title', $assessment->title)" required />

                <x-input name="max_score" type="number" step="0.01" min="0.01" :label="__('Maximum score')"
                    :value="old('max_score', $fmt($assessment->max_score))" required />

                <div class="space-y-1">
                    <label for="instructions" class="block text-sm font-medium text-gray-700">{{ __('Instructions (optional)') }}</label>
                    <textarea id="instructions" name="instructions" rows="3"
                        class="block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('instructions', $assessment->instructions) }}</textarea>
                    @error('instructions') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <x-button type="submit">{{ __('Save changes') }}</x-button>
                    <x-button :href="route('assessments.show', $assessment->id)" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
