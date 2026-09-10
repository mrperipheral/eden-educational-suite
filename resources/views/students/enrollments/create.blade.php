<x-layouts.authenticated :title="__('Add enrollment')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('students.show', $student->id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← Back to :name', ['name' => $student->shortName()]) }}</a>
        </p>

        <x-card :title="__('Place :name', ['name' => $student->shortName()])">
            @if ($levels->isEmpty() || $sessions->isEmpty())
                <x-empty-state
                    :title="__('Academic structure needed')"
                    :description="__('Create at least one academic session and one level before enrolling students.')"
                >
                    <x-slot:actions>
                        <x-button :href="route('academic.sessions.index')" size="sm" variant="secondary">{{ __('Academic setup') }}</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <form method="POST" action="{{ route('students.enrollments.store', $student->id) }}">
                    @csrf

                    @include('students.enrollments._form')

                    <div class="flex items-center gap-2 pt-4">
                        <x-button type="submit">{{ __('Add enrollment') }}</x-button>
                        <x-button :href="route('students.show', $student->id)" variant="ghost">{{ __('Cancel') }}</x-button>
                    </div>
                </form>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
