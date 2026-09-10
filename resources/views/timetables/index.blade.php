<x-layouts.authenticated :title="__('Timetables')">
    <x-slot:actions>
        <div class="flex items-center gap-2">
            <x-button :href="route('timetables.teacher')" size="sm" variant="secondary">{{ __('Teacher view') }}</x-button>
            @can('timetable.manage')
                <x-button :href="route('timetables.create')" size="sm">{{ __('New timetable') }}</x-button>
            @endcan
        </div>
    </x-slot:actions>

    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        <form method="GET" action="{{ route('timetables.index') }}" class="flex flex-wrap items-center gap-2">
            <select name="session" class="rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                <option value="">{{ __('All sessions') }}</option>
                @foreach ($sessions as $s)
                    <option value="{{ $s->id }}" @selected($sessionId === $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                <option value="">{{ __('Any status') }}</option>
                @foreach (\App\Enums\TimetableStatus::all() as $s)
                    <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->label() }}</option>
                @endforeach
            </select>
            <x-button type="submit" variant="secondary">{{ __('Filter') }}</x-button>
        </form>

        @if ($timetables->isEmpty())
            <x-empty-state
                :title="__('No timetables yet')"
                :description="__('Create a timetable for a session, then schedule its lessons and publish it.')"
            >
                @can('timetable.manage')
                    <x-slot:actions>
                        <x-button :href="route('timetables.create')" size="sm">{{ __('New timetable') }}</x-button>
                    </x-slot:actions>
                @endcan
            </x-empty-state>
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($timetables as $timetable)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <a href="{{ route('timetables.show', $timetable->id) }}" class="hover:text-brand-700">{{ $timetable->name }}</a>
                                    <x-badge :variant="$timetable->status->badgeVariant()" class="ml-1">{{ $timetable->status->label() }}</x-badge>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $timetable->session?->name }}{{ $timetable->period ? ' · '.$timetable->period->name : ' · '.__('whole session') }}
                                    · {{ trans_choice('{0} no lessons|{1} :count lesson|[2,*] :count lessons', $timetable->entries_count, ['count' => $timetable->entries_count]) }}
                                </p>
                            </div>
                            <div class="shrink-0">
                                <x-button :href="route('timetables.show', $timetable->id)" size="sm" variant="secondary">{{ __('Open') }}</x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $timetables->links() }}
        @endif
    </div>
</x-layouts.authenticated>
