@php
    $selectClass = 'rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $filtering = collect($filters)->filter(fn ($v) => $v !== null)->isNotEmpty();
@endphp

<x-layouts.authenticated :title="__('Assessments')">
    <x-slot:actions>
        <div class="flex flex-wrap gap-2">
            @can('assessment.view')
                <x-button :href="route('assessments.assignments.index')" size="sm" variant="secondary">{{ __('Assignments') }}</x-button>
            @endcan
            @can('assessment.manage')
                <x-button :href="route('assessments.categories.index')" size="sm" variant="secondary">{{ __('Categories') }}</x-button>
            @endcan
            @can('assessment.record')
                <x-button :href="route('assessments.create')" size="sm">{{ __('New assessment') }}</x-button>
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

        <form method="GET" action="{{ route('assessments.index') }}"
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
                <label class="block text-xs font-medium text-gray-500">{{ __('Category') }}</label>
                <select name="category" class="{{ $selectClass }}">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected($filters['category'] === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Status') }}</label>
                <select name="status" class="{{ $selectClass }}">
                    <option value="">{{ __('Any') }}</option>
                    @foreach (\App\Enums\AssessmentStatus::all() as $s)
                        <option value="{{ $s->value }}" @selected($filters['status'] === $s)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" variant="secondary">{{ __('Filter') }}</x-button>
            @if ($filtering)
                <x-button :href="route('assessments.index')" variant="ghost">{{ __('Clear') }}</x-button>
            @endif
        </form>

        @if ($assessments->isEmpty())
            <x-empty-state
                :title="$filtering ? __('No assessments match') : __('No assessments yet')"
                :description="$filtering ? __('Try a different filter.') : __('Create an assessment for a class and subject, then enter scores.')"
            >
                @can('assessment.record')
                    @unless ($filtering)
                        <x-slot:actions>
                            <x-button :href="route('assessments.create')" size="sm">{{ __('New assessment') }}</x-button>
                        </x-slot:actions>
                    @endunless
                @endcan
            </x-empty-state>
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($assessments as $assessment)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <a href="{{ route('assessments.show', $assessment->id) }}" class="hover:text-brand-700">{{ $assessment->title }}</a>
                                    <x-badge :variant="$assessment->status->badgeVariant()" class="ml-1">{{ $assessment->status->label() }}</x-badge>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $assessment->level?->name }} — {{ $assessment->arm?->name }} ·
                                    {{ $assessment->subject?->name }} · {{ $assessment->category?->name }} ·
                                    {{ $assessment->assessment_date->toFormattedDateString() }} ·
                                    {{ __(':entered / :total scored', ['entered' => $assessment->entered_count, 'total' => $assessment->scores_count]) }} ·
                                    {{ __('max :n', ['n' => rtrim(rtrim(number_format((float) $assessment->max_score, 2), '0'), '.')]) }}
                                </p>
                            </div>
                            <div class="shrink-0">
                                <x-button :href="route('assessments.show', $assessment->id)" size="sm" variant="secondary">{{ __('Open') }}</x-button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $assessments->links() }}
        @endif
    </div>
</x-layouts.authenticated>
