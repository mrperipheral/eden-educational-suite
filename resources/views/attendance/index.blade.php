@php
    $selectClass = 'rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $filtering = $filters['date'] || $filters['session'] || $filters['level'] || $filters['arm'] || $filters['status'];
@endphp

<x-layouts.authenticated :title="__('Attendance')">
    @can('attendance.record')
        <x-slot:actions>
            <x-button :href="route('attendance.create')" size="sm">{{ __('New register') }}</x-button>
        </x-slot:actions>
    @endcan

    <div class="space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        <form method="GET" action="{{ route('attendance.index') }}"
            x-data="{ levelId: '{{ $filters['level'] }}' }" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Date') }}</label>
                <input type="date" name="date" value="{{ $filters['date'] }}" class="{{ $selectClass }}">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Session') }}</label>
                <select name="session" class="{{ $selectClass }}">
                    <option value="">{{ __('All sessions') }}</option>
                    @foreach ($sessions as $s)
                        <option value="{{ $s->id }}" @selected($filters['session'] === $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Level') }}</label>
                <select name="level" x-model="levelId" class="{{ $selectClass }}">
                    <option value="">{{ __('All levels') }}</option>
                    @foreach ($levels as $l)
                        <option value="{{ $l->id }}" @selected($filters['level'] === $l->id)>{{ $l->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Arm') }}</label>
                <select name="arm" class="{{ $selectClass }}">
                    <option value="">{{ __('All arms') }}</option>
                    @foreach ($levels as $l)
                        @foreach ($l->arms as $arm)
                            <option value="{{ $arm->id }}" x-show="!levelId || levelId === '{{ $l->id }}'" @selected($filters['arm'] === $arm->id)>{{ $l->name }} — {{ $arm->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Status') }}</label>
                <select name="status" class="{{ $selectClass }}">
                    <option value="">{{ __('Any') }}</option>
                    @foreach (\App\Enums\AttendanceRegisterStatus::all() as $s)
                        <option value="{{ $s->value }}" @selected($filters['status'] === $s)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" variant="secondary">{{ __('Filter') }}</x-button>
            @if ($filtering)
                <x-button :href="route('attendance.index')" variant="ghost">{{ __('Clear') }}</x-button>
            @endif
        </form>

        @if ($registers->isEmpty())
            <x-empty-state
                :title="$filtering ? __('No registers match') : __('No attendance registers yet')"
                :description="$filtering ? __('Try a different filter.') : __('Start a register for a class and date, mark the students, then submit to lock it.')"
            >
                @can('attendance.record')
                    @unless ($filtering)
                        <x-slot:actions>
                            <x-button :href="route('attendance.create')" size="sm">{{ __('New register') }}</x-button>
                        </x-slot:actions>
                    @endunless
                @endcan
            </x-empty-state>
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($registers as $register)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <a href="{{ route('attendance.show', $register->id) }}" class="hover:text-brand-700">
                                        {{ $register->level?->name }} — {{ $register->arm?->name }}
                                    </a>
                                    <span class="text-gray-400">·</span> {{ $register->attendance_date->toFormattedDateString() }}
                                    <x-badge :variant="$register->status->badgeVariant()" class="ml-1">{{ $register->status->label() }}</x-badge>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $register->session?->name }}{{ $register->period ? ' · '.$register->period->name : '' }}
                                    · {{ __(':present / :total present', ['present' => $register->present_count, 'total' => $register->records_count]) }}
                                </p>
                            </div>
                            <div class="shrink-0">
                                <x-button :href="route('attendance.show', $register->id)" size="sm" variant="secondary">{{ __('Open') }}</x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $registers->links() }}
        @endif
    </div>
</x-layouts.authenticated>
