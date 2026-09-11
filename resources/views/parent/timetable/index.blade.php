<x-layouts.authenticated :title="__('Timetable — :name', ['name' => $student->fullName()])">
    <div class="space-y-6">
        @include('parent._child-nav', [
            'student' => $student, 'siblings' => $siblings, 'active' => 'timetable',
            'modules' => $modules, 'sectionRoute' => 'parent.timetable.index',
        ])

        @if (! $moduleOn)
            <x-empty-state
                :title="__('Timetable is not currently available')"
                :description="__('This school has not enabled the timetable for this account yet.')"
            />
        @elseif (! $timetable)
            <x-empty-state
                :title="__('No published timetable is currently available')"
                :description="__('A timetable appears here once the school publishes one for this class.')"
            />
        @else
            <div class="space-y-4">
                @foreach ($weekdays as $day)
                    @php($dayEntries = $entriesByDay->get($day->value, collect()))
                    @if ($dayEntries->isNotEmpty())
                        <x-card :title="$day->label()">
                            <ul class="divide-y divide-gray-100">
                                @foreach ($dayEntries as $entry)
                                    <li class="flex items-center justify-between gap-2 py-2 text-sm first:pt-0 last:pb-0">
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-gray-900">{{ $entry->subject?->name }}</p>
                                            <p class="truncate text-xs text-gray-500">
                                                {{ $entry->teacher?->shortName() }}
                                                @if ($entry->room) · {{ $entry->room }} @endif
                                            </p>
                                        </div>
                                        <p class="shrink-0 text-xs text-gray-500">{{ $entry->start_time }}–{{ $entry->end_time }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        </x-card>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.authenticated>
