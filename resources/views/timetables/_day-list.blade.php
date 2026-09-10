@php
    /** @var \Illuminate\Support\Collection $entries */
    /** @var \Illuminate\Support\Collection $weekdaysInUse */
    $mode ??= 'timetable';          // 'timetable' | 'teacher'
    $conflictIds ??= collect();
    $byDay = $entries->groupBy(fn ($e) => $e->weekday->value);
@endphp

<div class="space-y-6">
    @foreach ($weekdaysInUse as $day)
        <div>
            <h3 class="text-sm font-semibold text-gray-900">{{ $day->label() }}</h3>
            <ul class="mt-2 divide-y divide-gray-100 rounded-md border border-gray-200">
                @foreach ($byDay->get($day->value, collect()) as $entry)
                    <li @class([
                        'flex flex-col gap-1 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between',
                        'bg-red-50' => $conflictIds->contains($entry->id),
                    ])>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">
                                <span class="font-mono text-xs text-gray-500">{{ $entry->start_time }}–{{ $entry->end_time }}</span>
                                {{ $entry->subject?->name }}
                                @if ($conflictIds->contains($entry->id))
                                    <x-badge variant="danger" class="ml-1">{{ __('Clash') }}</x-badge>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500">
                                @if ($mode === 'teacher')
                                    {{ $entry->level?->name }} — {{ $entry->arm?->name }}
                                    · {{ $entry->timetable?->name }}
                                    <x-badge :variant="$entry->timetable?->status->badgeVariant()" class="ml-1">{{ $entry->timetable?->status->label() }}</x-badge>
                                @else
                                    {{ $entry->level?->name }} — {{ $entry->arm?->name }}
                                    · {{ $entry->teacher?->shortName() }}
                                    @if ($entry->room) · {{ __('Room') }} {{ $entry->room }} @endif
                                @endif
                            </p>
                        </div>
                        @if ($mode === 'timetable')
                            @can('timetable.manage')
                                <div class="flex shrink-0 items-center gap-2">
                                    <x-button :href="route('timetables.entries.edit', $entry->id)" size="sm" variant="ghost">{{ __('Edit') }}</x-button>
                                    <x-confirm :action="route('timetables.entries.destroy', $entry->id)" method="DELETE" size="sm"
                                        :confirm="__('Remove')"
                                        :title="__('Remove lesson?')"
                                        :message="__('This removes the lesson from the timetable.')">
                                        {{ __('Remove') }}
                                    </x-confirm>
                                </div>
                            @endcan
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
