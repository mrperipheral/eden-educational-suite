@php
    $selectClass = 'rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $filtering = $filters['arm'] || $filters['teacher'] || $filters['weekday'] !== null;
@endphp

<x-layouts.authenticated :title="$timetable->name">
    <x-slot:actions>
        <div class="flex flex-wrap items-center gap-2">
            @can('timetable.manage')
                <x-button :href="route('timetables.entries.create', $timetable->id)" size="sm">{{ __('Add lesson') }}</x-button>
                <x-button :href="route('timetables.edit', $timetable->id)" size="sm" variant="secondary">{{ __('Edit') }}</x-button>
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

        <p class="text-sm">
            <a href="{{ route('timetables.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Timetables') }}</a>
        </p>

        {{-- Summary + lifecycle --}}
        <x-card>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-900">
                        <span class="font-medium">{{ $timetable->session?->name }}</span>
                        · {{ $timetable->period?->name ?: __('whole session') }}
                        <x-badge :variant="$timetable->status->badgeVariant()" class="ml-1">{{ $timetable->status->label() }}</x-badge>
                    </p>
                    @if ($timetable->isPublished() && $timetable->published_at)
                        <p class="text-xs text-gray-500">{{ __('Published :when', ['when' => $timetable->published_at->toFormattedDateString()]) }}</p>
                    @endif
                </div>
                @can('timetable.manage')
                    <div class="flex items-center gap-2">
                        @if ($timetable->isPublished())
                            <form method="POST" action="{{ route('timetables.status', $timetable->id) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="draft">
                                <x-button type="submit" size="sm" variant="secondary">{{ __('Move to draft') }}</x-button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('timetables.status', $timetable->id) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="published">
                                <x-button type="submit" size="sm">{{ __('Publish') }}</x-button>
                            </form>
                            <x-confirm :action="route('timetables.destroy', $timetable->id)" method="DELETE" size="sm"
                                :confirm="__('Delete')" :title="__('Delete timetable?')"
                                :message="__('This deletes the timetable and all its lessons. Academic records are untouched.')">
                                {{ __('Delete') }}
                            </x-confirm>
                        @endif
                    </div>
                @endcan
            </div>
            @error('status')
                <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </x-card>

        {{-- Conflict banner --}}
        @if ($conflicts->isNotEmpty())
            <x-alert variant="danger" :title="__('Scheduling clashes')">
                <p>{{ trans_choice('{1} :count clash must be resolved before publishing.|[2,*] :count clashes must be resolved before publishing.', $conflicts->count(), ['count' => $conflicts->count()]) }}</p>
                <ul class="mt-1 list-disc pl-5 text-xs">
                    @foreach ($conflicts as $pair)
                        <li>{{ __(':reason double-booking', ['reason' => ucfirst($pair->reason)]) }} — {{ __('lessons') }} #{{ $pair->a_id }} &amp; #{{ $pair->b_id }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('timetables.show', $timetable->id) }}"
            x-data="{ levelId: '' }" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Class / level') }}</label>
                <select x-model="levelId" class="{{ $selectClass }}">
                    <option value="">{{ __('All levels') }}</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level->id }}">{{ $level->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Arm') }}</label>
                <select name="arm" class="{{ $selectClass }}">
                    <option value="">{{ __('All arms') }}</option>
                    @foreach ($levels as $level)
                        @foreach ($level->arms as $arm)
                            <option value="{{ $arm->id }}" x-show="!levelId || levelId === '{{ $level->id }}'" @selected($filters['arm'] === $arm->id)>{{ $level->name }} — {{ $arm->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Teacher') }}</label>
                <select name="teacher" class="{{ $selectClass }}">
                    <option value="">{{ __('All teachers') }}</option>
                    @foreach ($teachers as $t)
                        <option value="{{ $t->id }}" @selected($filters['teacher'] === $t->id)>{{ $t->shortName() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Day') }}</label>
                <select name="weekday" class="{{ $selectClass }}">
                    <option value="">{{ __('All days') }}</option>
                    @foreach (\App\Enums\Weekday::all() as $d)
                        <option value="{{ $d->value }}" @selected($filters['weekday'] === $d->value)>{{ $d->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" variant="secondary">{{ __('Apply') }}</x-button>
            @if ($filtering)
                <x-button :href="route('timetables.show', $timetable->id)" variant="ghost">{{ __('Clear') }}</x-button>
            @endif
        </form>

        {{-- The timetable itself --}}
        @if ($entries->isEmpty())
            <x-empty-state
                :title="$filtering ? __('No lessons match') : __('No lessons scheduled')"
                :description="$filtering ? __('Try a different filter.') : __('Add lessons day by day, then publish when the week is complete.')"
            >
                @can('timetable.manage')
                    @unless ($filtering)
                        <x-slot:actions>
                            <x-button :href="route('timetables.entries.create', $timetable->id)" size="sm">{{ __('Add lesson') }}</x-button>
                        </x-slot:actions>
                    @endunless
                @endcan
            </x-empty-state>
        @else
            <x-card :title="__('Weekly schedule')">
                <div class="hidden md:block">
                    @include('timetables._grid')
                </div>
                <div class="md:hidden">
                    @include('timetables._day-list', ['mode' => 'timetable'])
                </div>
            </x-card>
        @endif
    </div>
</x-layouts.authenticated>
