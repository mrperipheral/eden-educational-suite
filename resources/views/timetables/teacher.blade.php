@php
    $selectClass = 'rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<x-layouts.authenticated :title="__('Teacher timetable')">
    <x-slot:actions>
        <x-button :href="route('timetables.index')" size="sm" variant="secondary">{{ __('All timetables') }}</x-button>
    </x-slot:actions>

    <div class="space-y-6">
        <p class="text-sm">
            <a href="{{ route('timetables.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Timetables') }}</a>
        </p>

        <form method="GET" action="{{ route('timetables.teacher') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Teacher') }}</label>
                <select name="teacher" class="{{ $selectClass }}">
                    <option value="">{{ __('Select a teacher') }}</option>
                    @foreach ($teachers as $t)
                        <option value="{{ $t->id }}" @selected($teacher?->id === $t->id)>{{ $t->fullName() }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" variant="secondary">{{ __('Show') }}</x-button>
        </form>

        @if ($teacher === null)
            <x-empty-state :title="__('Pick a teacher')" :description="__('Choose a teacher to see every lesson they are scheduled for.')" />
        @elseif ($entries->isEmpty())
            <x-empty-state
                :title="__(':name has no scheduled lessons', ['name' => $teacher->shortName()])"
                :description="__('This teacher does not appear on any timetable yet.')"
            />
        @else
            <x-card :title="__(':name — weekly lessons', ['name' => $teacher->fullName()])">
                @include('timetables._day-list', ['mode' => 'teacher'])
            </x-card>
        @endif
    </div>
</x-layouts.authenticated>
