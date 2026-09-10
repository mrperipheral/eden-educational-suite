@php
    /** @var \Illuminate\Support\Collection $entries */
    /** @var \Illuminate\Support\Collection $weekdaysInUse */
    $conflictIds ??= collect();
    $slots = $entries->map(fn ($e) => $e->start_time.'|'.$e->end_time)->unique()
        ->sort()->values();
    $lookup = $entries->groupBy(fn ($e) => $e->weekday->value.'|'.$e->start_time.'|'.$e->end_time);
@endphp

<div class="overflow-x-auto">
    <table class="min-w-full border-separate border-spacing-1 text-sm">
        <thead>
            <tr>
                <th class="w-24 px-2 py-1 text-left text-xs font-semibold text-gray-500">{{ __('Time') }}</th>
                @foreach ($weekdaysInUse as $day)
                    <th class="min-w-40 px-2 py-1 text-left text-xs font-semibold text-gray-700">{{ $day->short() }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($slots as $slot)
                @php([$slotStart, $slotEnd] = explode('|', $slot))
                <tr>
                    <td class="whitespace-nowrap px-2 py-1 align-top font-mono text-xs text-gray-500">{{ $slotStart }}<br>{{ $slotEnd }}</td>
                    @foreach ($weekdaysInUse as $day)
                        <td class="align-top">
                            @foreach ($lookup->get($day->value.'|'.$slotStart.'|'.$slotEnd, collect()) as $entry)
                                <div @class([
                                    'mb-1 rounded-md border p-2',
                                    'border-red-300 bg-red-50' => $conflictIds->contains($entry->id),
                                    'border-gray-200 bg-white' => ! $conflictIds->contains($entry->id),
                                ])>
                                    <p class="text-xs font-medium text-gray-900">{{ $entry->subject?->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $entry->arm?->name ? $entry->level?->name.' — '.$entry->arm?->name : $entry->level?->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $entry->teacher?->shortName() }}@if ($entry->room) · {{ $entry->room }}@endif</p>
                                    @can('timetable.manage')
                                        <a href="{{ route('timetables.entries.edit', $entry->id) }}" class="mt-1 inline-block text-xs text-brand-600 hover:text-brand-700">{{ __('Edit') }}</a>
                                    @endcan
                                </div>
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
