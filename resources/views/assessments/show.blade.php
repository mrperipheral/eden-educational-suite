@php
    $status = $assessment->status;
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp

<x-layouts.authenticated :title="$assessment->title">
    <x-slot:actions>
        <div class="flex flex-wrap gap-2">
            @if ($canRecord)
                @if ($status->acceptsScores())
                    <x-button :href="route('assessments.scores.edit', $assessment->id)" size="sm">{{ __('Enter scores') }}</x-button>
                @endif
                @if ($assessment->isDraft())
                    <x-button :href="route('assessments.edit', $assessment->id)" size="sm" variant="secondary">{{ __('Edit') }}</x-button>
                    <form method="POST" action="{{ route('assessments.publish', $assessment->id) }}">@csrf
                        <x-button type="submit" size="sm" variant="secondary">{{ __('Publish') }}</x-button>
                    </form>
                @elseif ($assessment->isPublished())
                    <form method="POST" action="{{ route('assessments.unpublish', $assessment->id) }}">@csrf
                        <x-button type="submit" size="sm" variant="ghost">{{ __('Return to draft') }}</x-button>
                    </form>
                @endif
                @if (! $status->isLocked())
                    <x-confirm :action="route('assessments.lock', $assessment->id)" method="POST" size="sm"
                        :confirm="__('Lock')" :title="__('Lock this assessment?')"
                        :message="__('Locked scores cannot be changed without an authorized unlock.')">
                        {{ __('Lock') }}
                    </x-confirm>
                @endif
            @endif
            @if ($status->isLocked() && $canManage)
                <x-confirm :action="route('assessments.unlock', $assessment->id)" method="POST" size="sm" variant="secondary"
                    :confirm="__('Unlock')" :title="__('Unlock for correction?')"
                    :message="__('This returns the assessment to published so scores can be corrected.')">
                    {{ __('Unlock for correction') }}
                </x-confirm>
            @endif
        </div>
    </x-slot:actions>

    <div class="max-w-3xl space-y-6">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        <p class="text-sm">
            <a href="{{ route('assessments.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Assessments') }}</a>
        </p>

        <x-card>
            <div class="flex flex-col gap-1">
                <p class="text-sm text-gray-900">
                    <span class="font-medium">{{ $assessment->title }}</span>
                    <x-badge :variant="$status->badgeVariant()" class="ml-1">{{ $status->label() }}</x-badge>
                </p>
                <p class="text-xs text-gray-500">
                    {{ $assessment->level?->name }} — {{ $assessment->arm?->name }} ·
                    {{ $assessment->subject?->name }} · {{ $assessment->category?->name }}
                </p>
                <p class="text-xs text-gray-500">
                    {{ $assessment->session?->name }} · {{ $assessment->period?->name }} ·
                    {{ $assessment->assessment_date->toFormattedDateString() }} ·
                    {{ __('out of :n', ['n' => $fmt($assessment->max_score)]) }}
                </p>
                @if ($assessment->assignment)
                    <p class="text-xs text-gray-500">{{ __('Grades assignment: :title', ['title' => $assessment->assignment->title]) }}</p>
                @endif
                @if ($status->isLocked() && $assessment->lockedBy)
                    <p class="text-xs text-gray-400">
                        {{ __('locked by :name', ['name' => $assessment->lockedBy->name]) }}
                        @if ($assessment->locked_at) {{ $assessment->locked_at->diffForHumans() }} @endif
                    </p>
                @elseif ($assessment->createdBy)
                    <p class="text-xs text-gray-400">{{ __('created by :name', ['name' => $assessment->createdBy->name]) }}</p>
                @endif
            </div>

            @if ($assessment->instructions)
                <p class="mt-3 whitespace-pre-line border-t border-gray-100 pt-3 text-sm text-gray-600">{{ $assessment->instructions }}</p>
            @endif

            <div class="mt-3 border-t border-gray-100 pt-3">
                @include('assessments._summary')
            </div>

            @if ($canRecord && $assessment->isDraft() && $summary['entered'] === 0)
                <div class="mt-3">
                    <x-confirm :action="route('assessments.destroy', $assessment->id)" method="DELETE" size="sm"
                        :confirm="__('Delete')" :title="__('Delete assessment?')"
                        :message="__('This deletes the assessment and its (empty) roster.')">
                        {{ __('Delete') }}
                    </x-confirm>
                </div>
            @endif
        </x-card>

        <x-card :title="__('Scores')">
            @if ($assessment->scores_count === 0)
                <x-empty-state
                    :title="__('No students on this assessment')"
                    :description="__('No students were enrolled in this class on the assessment date.')"
                />
            @else
                <p class="text-sm text-gray-600">
                    {{ __(':entered of :total scores entered.', ['entered' => $assessment->entered_count, 'total' => $assessment->scores_count]) }}
                </p>
                @if ($canRecord && $status->acceptsScores())
                    <div class="mt-3">
                        <x-button :href="route('assessments.scores.edit', $assessment->id)" size="sm">{{ __('Open score sheet') }}</x-button>
                    </div>
                @elseif ($status->isLocked())
                    <p class="mt-2 text-xs text-gray-400">{{ __('This assessment is locked. An authorized unlock is required to change any score.') }}</p>
                @endif
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
