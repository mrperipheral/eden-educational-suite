@php
    $selectClass = 'rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $filtering = collect($filters)->filter(fn ($v) => $v !== null)->isNotEmpty();
@endphp

<x-layouts.authenticated :title="__('Assignments')">
    <x-slot:actions>
        <div class="flex flex-wrap gap-2">
            <x-button :href="route('assessments.index')" size="sm" variant="secondary">{{ __('Assessments') }}</x-button>
            @can('assessment.record')
                <x-button :href="route('assessments.assignments.create')" size="sm">{{ __('New assignment') }}</x-button>
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

        <form method="GET" action="{{ route('assessments.assignments.index') }}"
            x-data="{ levelId: '{{ $filters['level'] }}' }" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Session') }}</label>
                <select name="session" class="{{ $selectClass }}">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($sessions as $s)
                        <option value="{{ $s->id }}" @selected($filters['session'] === $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Level') }}</label>
                <select name="level" x-model="levelId" class="{{ $selectClass }}">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($levels as $l)
                        <option value="{{ $l->id }}" @selected($filters['level'] === $l->id)>{{ $l->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Class') }}</label>
                <select name="arm" class="{{ $selectClass }}">
                    <option value="">{{ __('All') }}</option>
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
                    @foreach (\App\Enums\AssignmentStatus::all() as $s)
                        <option value="{{ $s->value }}" @selected($filters['status'] === $s)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" variant="secondary">{{ __('Filter') }}</x-button>
            @if ($filtering)
                <x-button :href="route('assessments.assignments.index')" variant="ghost">{{ __('Clear') }}</x-button>
            @endif
        </form>

        @if ($assignments->isEmpty())
            <x-empty-state
                :title="$filtering ? __('No assignments match') : __('No assignments yet')"
                :description="$filtering ? __('Try a different filter.') : __('Set work for a class and track who turns it in.')"
            >
                @can('assessment.record')
                    @unless ($filtering)
                        <x-slot:actions>
                            <x-button :href="route('assessments.assignments.create')" size="sm">{{ __('New assignment') }}</x-button>
                        </x-slot:actions>
                    @endunless
                @endcan
            </x-empty-state>
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($assignments as $assignment)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <a href="{{ route('assessments.assignments.show', $assignment->id) }}" class="hover:text-brand-700">{{ $assignment->title }}</a>
                                    <x-badge :variant="$assignment->status->badgeVariant()" class="ml-1">{{ $assignment->status->label() }}</x-badge>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $assignment->level?->name }} — {{ $assignment->arm?->name }} · {{ $assignment->subject?->name }} ·
                                    {{ __('due :date', ['date' => $assignment->due_on->toFormattedDateString()]) }} ·
                                    {{ __(':in / :total in', ['in' => $assignment->turned_in_count, 'total' => $assignment->submissions_count]) }}
                                </p>
                            </div>
                            <div class="shrink-0">
                                <x-button :href="route('assessments.assignments.show', $assignment->id)" size="sm" variant="secondary">{{ __('Open') }}</x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $assignments->links() }}
        @endif
    </div>
</x-layouts.authenticated>
