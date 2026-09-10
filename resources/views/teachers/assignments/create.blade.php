<x-layouts.authenticated :title="__('Add assignment')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('teachers.show', $teacher->id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← Back to :name', ['name' => $teacher->shortName()]) }}</a>
        </p>

        <x-card :title="__('Assign :name', ['name' => $teacher->shortName()])">
            @if ($levels->isEmpty() || $sessions->isEmpty() || $subjects->isEmpty())
                <x-empty-state
                    :title="__('Academic structure needed')"
                    :description="__('Create at least one academic session, one level and one subject before assigning teachers.')"
                >
                    <x-slot:actions>
                        <x-button :href="route('academic.sessions.index')" size="sm" variant="secondary">{{ __('Academic setup') }}</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <form method="POST" action="{{ route('teachers.assignments.store', $teacher->id) }}">
                    @csrf

                    @include('teachers.assignments._form')

                    <div class="flex items-center gap-2 pt-4">
                        <x-button type="submit">{{ __('Add assignment') }}</x-button>
                        <x-button :href="route('teachers.show', $teacher->id)" variant="ghost">{{ __('Cancel') }}</x-button>
                    </div>
                </form>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
