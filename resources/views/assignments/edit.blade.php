@php
    $fmt = fn ($n) => $n === null ? '' : rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp

<x-layouts.authenticated :title="__('Edit assignment')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('assessments.assignments.show', $assignment->id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← Back to assignment') }}</a>
        </p>

        <x-card :title="__('Assignment details')">
            <p class="mb-4 text-xs text-gray-500">
                {{ __('The academic context is fixed:') }}
                {{ $assignment->level?->name }} — {{ $assignment->arm?->name }} · {{ $assignment->subject?->name }}
            </p>

            <form method="POST" action="{{ route('assessments.assignments.update', $assignment->id) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <x-input name="title" :label="__('Title')" :value="old('title', $assignment->title)" required />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-input name="assigned_on" type="date" :label="__('Assigned on')"
                        :value="old('assigned_on', $assignment->assigned_on->toDateString())" required />
                    <x-input name="due_on" type="date" :label="__('Due on')"
                        :value="old('due_on', $assignment->due_on->toDateString())" required />
                    <x-input name="max_score" type="number" step="0.01" min="0.01" :label="__('Max marks (optional)')"
                        :value="old('max_score', $fmt($assignment->max_score))" />
                </div>

                <div class="space-y-1">
                    <label for="instructions" class="block text-sm font-medium text-gray-700">{{ __('Instructions (optional)') }}</label>
                    <textarea id="instructions" name="instructions" rows="4"
                        class="block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('instructions', $assignment->instructions) }}</textarea>
                    @error('instructions') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <x-button type="submit">{{ __('Save changes') }}</x-button>
                    <x-button :href="route('assessments.assignments.show', $assignment->id)" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
