<x-layouts.authenticated :title="__('New timetable')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('timetables.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Timetables') }}</a>
        </p>

        <x-card :title="__('New timetable')">
            @if ($sessions->isEmpty())
                <x-empty-state
                    :title="__('Academic session needed')"
                    :description="__('Create an academic session before building a timetable.')"
                >
                    <x-slot:actions>
                        <x-button :href="route('academic.sessions.index')" size="sm" variant="secondary">{{ __('Academic setup') }}</x-button>
                    </x-slot:actions>
                </x-empty-state>
            @else
                <form method="POST" action="{{ route('timetables.store') }}">
                    @csrf
                    @include('timetables._form')

                    <div class="flex items-center gap-2 pt-6">
                        <x-button type="submit">{{ __('Create timetable') }}</x-button>
                        <x-button :href="route('timetables.index')" variant="ghost">{{ __('Cancel') }}</x-button>
                    </div>
                </form>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
