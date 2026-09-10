@php
    $status = $assignment->status;
    $fmt = fn ($n) => $n === null ? null : rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp

<x-layouts.authenticated :title="$assignment->title">
    <x-slot:actions>
        <div class="flex flex-wrap gap-2">
            @if ($canRecord)
                @if ($status->tracksCompletion())
                    <x-button :href="route('assessments.assignments.submissions.edit', $assignment->id)" size="sm">{{ __('Track completion') }}</x-button>
                @endif
                @if ($assignment->isDraft())
                    <x-button :href="route('assessments.assignments.edit', $assignment->id)" size="sm" variant="secondary">{{ __('Edit') }}</x-button>
                    <form method="POST" action="{{ route('assessments.assignments.publish', $assignment->id) }}">@csrf
                        <x-button type="submit" size="sm" variant="secondary">{{ __('Publish') }}</x-button>
                    </form>
                @elseif ($assignment->isPublished())
                    <form method="POST" action="{{ route('assessments.assignments.unpublish', $assignment->id) }}">@csrf
                        <x-button type="submit" size="sm" variant="ghost">{{ __('Return to draft') }}</x-button>
                    </form>
                    <form method="POST" action="{{ route('assessments.assignments.close', $assignment->id) }}">@csrf
                        <x-button type="submit" size="sm" variant="secondary">{{ __('Close') }}</x-button>
                    </form>
                @elseif ($assignment->isClosed())
                    <form method="POST" action="{{ route('assessments.assignments.reopen', $assignment->id) }}">@csrf
                        <x-button type="submit" size="sm" variant="secondary">{{ __('Reopen') }}</x-button>
                    </form>
                @endif
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
            <a href="{{ route('assessments.assignments.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Assignments') }}</a>
        </p>

        <x-card>
            <div class="flex flex-col gap-1">
                <p class="text-sm text-gray-900">
                    <span class="font-medium">{{ $assignment->title }}</span>
                    <x-badge :variant="$status->badgeVariant()" class="ml-1">{{ $status->label() }}</x-badge>
                </p>
                <p class="text-xs text-gray-500">
                    {{ $assignment->level?->name }} — {{ $assignment->arm?->name }} · {{ $assignment->subject?->name }} ·
                    {{ $assignment->session?->name }} · {{ $assignment->period?->name }}
                </p>
                <p class="text-xs text-gray-500">
                    {{ __('assigned :a · due :d', ['a' => $assignment->assigned_on->toFormattedDateString(), 'd' => $assignment->due_on->toFormattedDateString()]) }}
                    @if ($assignment->max_score) · {{ __('max :n', ['n' => $fmt($assignment->max_score)]) }} @endif
                </p>
                @if ($assignment->teacher)
                    <p class="text-xs text-gray-400">{{ __('owned by :name', ['name' => $assignment->teacher->shortName()]) }}</p>
                @elseif ($assignment->createdBy)
                    <p class="text-xs text-gray-400">{{ __('created by :name', ['name' => $assignment->createdBy->name]) }}</p>
                @endif
            </div>

            @if ($assignment->instructions)
                <p class="mt-3 whitespace-pre-line border-t border-gray-100 pt-3 text-sm text-gray-600">{{ $assignment->instructions }}</p>
            @endif

            <div class="mt-3 border-t border-gray-100 pt-3">
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="rounded-md bg-gray-100 px-2 py-1 font-medium text-gray-700">{{ trans_choice('{0}No students|{1}:count student|[2,*]:count students', $summary['total'], ['count' => $summary['total']]) }}</span>
                    <span class="rounded-md bg-green-100 px-2 py-1 font-medium text-green-700">{{ __('Submitted') }} {{ $summary['submitted'] }}</span>
                    <span class="rounded-md bg-brand-100 px-2 py-1 font-medium text-brand-700">{{ __('Late') }} {{ $summary['late'] }}</span>
                    <span class="rounded-md bg-gray-100 px-2 py-1 font-medium text-gray-600">{{ __('Exempt') }} {{ $summary['exempt'] }}</span>
                    @if ($summary['pending'] > 0)
                        <span class="rounded-md bg-yellow-100 px-2 py-1 font-medium text-yellow-800">{{ __('Pending') }} {{ $summary['pending'] }}</span>
                    @endif
                </div>
            </div>

            @if ($canRecord && $assignment->isDraft() && $assignment->submissions_count > 0 && $summary['pending'] === $summary['total'])
                <div class="mt-3">
                    <x-confirm :action="route('assessments.assignments.destroy', $assignment->id)" method="DELETE" size="sm"
                        :confirm="__('Delete')" :title="__('Delete assignment?')"
                        :message="__('This deletes the assignment and its (untouched) completion roster.')">
                        {{ __('Delete') }}
                    </x-confirm>
                </div>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
