<x-layouts.authenticated :title="__('Add lesson')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('timetables.show', $timetable->id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← Back to :name', ['name' => $timetable->name]) }}</a>
        </p>

        <x-card :title="__('Add a lesson')">
            @if ($levels->isEmpty() || $subjects->isEmpty() || $teachers->isEmpty())
                <x-empty-state
                    :title="__('Academic setup needed')"
                    :description="__('Add levels, subjects and teachers (with assignments) before scheduling lessons.')"
                >
                    <x-slot:actions>
                        <x-button :href="route('academic.levels.index')" size="sm" variant="secondary">{{ __('Academic setup') }}</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <form method="POST" action="{{ route('timetables.entries.store', $timetable->id) }}">
                    @csrf
                    @include('timetables.entries._form')

                    <div class="flex items-center gap-2 pt-4">
                        <x-button type="submit">{{ __('Add lesson') }}</x-button>
                        <x-button :href="route('timetables.show', $timetable->id)" variant="ghost">{{ __('Cancel') }}</x-button>
                    </div>
                </form>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
