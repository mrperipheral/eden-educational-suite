<x-layouts.authenticated :title="__('Edit timetable')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('timetables.show', $timetable->id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← Back to timetable') }}</a>
        </p>

        <x-card :title="__('Edit :name', ['name' => $timetable->name])">
            <form method="POST" action="{{ route('timetables.update', $timetable->id) }}">
                @csrf
                @method('PATCH')
                @include('timetables._form')

                <div class="flex items-center gap-2 pt-6">
                    <x-button type="submit">{{ __('Save changes') }}</x-button>
                    <x-button :href="route('timetables.show', $timetable->id)" variant="ghost">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts.authenticated>
