@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<x-layouts.authenticated :title="__('Entry / Placement Assessment')">
    <x-slot:actions>
        <x-button :href="route('entry-assessments.export', $filters)" size="sm" variant="secondary">{{ __('Export CSV') }}</x-button>
        @can('placement.record')
            <x-button :href="route('entry-assessments.create')" size="sm">{{ __('Record assessment') }}</x-button>
        @endcan
    </x-slot:actions>

    <div class="space-y-6">
        <x-alert variant="info">
            {{ __('Records the assessment conducted for a prospective or newly admitted student. It does not make a placement decision or change a student\'s enrolment.') }}
        </x-alert>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        <form method="GET" action="{{ route('entry-assessments.index') }}" class="flex flex-wrap items-end gap-2" x-data="{ levelId: '{{ $filters['level'] ?? '' }}' }">
            <div class="space-y-1">
                <label for="q" class="block text-xs font-medium text-gray-700">{{ __('Search') }}</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Candidate name or admission reference…') }}" class="{{ $selectClass }}">
            </div>
            <div class="space-y-1">
                <label for="level" class="block text-xs font-medium text-gray-700">{{ __('Level') }}</label>
                <select id="level" name="level" x-model="levelId" class="{{ $selectClass }}">
                    <option value="">{{ __('All levels') }}</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level->id }}">{{ $level->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="arm" class="block text-xs font-medium text-gray-700">{{ __('Arm') }}</label>
                <select id="arm" name="arm" class="{{ $selectClass }}">
                    <option value="">{{ __('All arms') }}</option>
                    @foreach ($levels as $level)
                        @foreach ($level->arms as $arm)
                            <option value="{{ $arm->id }}" x-show="levelId === '{{ $level->id }}'" @selected(($filters['arm'] ?? null) == $arm->id)>{{ $level->name }} — {{ $arm->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="subject" class="block text-xs font-medium text-gray-700">{{ __('Subject') }}</label>
                <select id="subject" name="subject" class="{{ $selectClass }}">
                    <option value="">{{ __('All subjects') }}</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected(($filters['subject'] ?? null) == $subject->id)>{{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="status" class="block text-xs font-medium text-gray-700">{{ __('Status') }}</label>
                <select id="status" name="status" class="{{ $selectClass }}">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" size="sm" variant="secondary">{{ __('Filter') }}</x-button>
            @if (array_filter($filters))
                <x-button :href="route('entry-assessments.index')" size="sm" variant="ghost">{{ __('Clear') }}</x-button>
            @endif
        </form>

        @if ($assessments->isEmpty())
            <x-empty-state
                :title="__('No assessment records match')"
                :description="__('Record an assessment conducted for a prospective or newly admitted student.')"
            >
                @can('placement.record')
                    <x-slot:actions>
                        <x-button :href="route('entry-assessments.create')" size="sm">{{ __('Record assessment') }}</x-button>
                    </x-slot:actions>
                @endcan
            </x-empty-state>
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($assessments as $assessment)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <a href="{{ route('entry-assessments.show', $assessment->id) }}" class="hover:text-brand-700">{{ $assessment->candidate_name }}</a>
                                    <x-badge :variant="$assessment->status->badgeVariant()" class="ml-1">{{ $assessment->status->label() }}</x-badge>
                                    @if ($assessment->result)
                                        <x-badge variant="gray">{{ $assessment->result }}</x-badge>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $assessment->subject?->name }}
                                    · {{ $assessment->level?->name }}{{ $assessment->arm ? ' — '.$assessment->arm->name : '' }}
                                    · {{ $assessment->assessed_on->format('d M Y') }}
                                    @if ($assessment->score !== null)
                                        · {{ __(':score / :max (:pct%)', ['score' => $assessment->score, 'max' => $assessment->max_score, 'pct' => $assessment->percentage()]) }}
                                    @else
                                        · {{ __('No score entered yet') }}
                                    @endif
                                    @if ($assessment->admission_reference)
                                        · {{ $assessment->admission_reference }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <x-button :href="route('entry-assessments.show', $assessment->id)" size="sm" variant="ghost">{{ __('View') }}</x-button>
                                @can('placement.record')
                                    <x-button :href="route('entry-assessments.edit', $assessment->id)" size="sm" variant="secondary">{{ __('Edit') }}</x-button>
                                    @if ($assessment->status === \App\Enums\EntryAssessmentStatus::Active)
                                        <x-confirm
                                            :action="route('entry-assessments.archive', $assessment->id)"
                                            method="POST"
                                            size="sm"
                                            :title="__('Archive this record?')"
                                            :message="__('It moves out of the active working set. Nothing is deleted — it can be restored later.')"
                                            :confirm="__('Archive')"
                                        >
                                            {{ __('Archive') }}
                                        </x-confirm>
                                    @else
                                        <form method="POST" action="{{ route('entry-assessments.restore', $assessment->id) }}">
                                            @csrf
                                            <x-button type="submit" size="sm" variant="ghost">{{ __('Restore') }}</x-button>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $assessments->links() }}
        @endif
    </div>
</x-layouts.authenticated>
