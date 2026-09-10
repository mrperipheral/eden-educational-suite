<li class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
    <div class="min-w-0">
        <p class="text-sm font-medium text-gray-900">
            {{ $assignment->subject?->name }}
            <span class="text-gray-400">·</span>
            {{ $assignment->level?->name }}{{ $assignment->arm ? ' — '.$assignment->arm->name : '' }}
            <x-badge :variant="$assignment->status->badgeVariant()" class="ml-1">{{ $assignment->status->label() }}</x-badge>
        </p>
        <p class="text-xs text-gray-500">
            {{ $assignment->session?->name }}{{ $assignment->period ? ' · '.$assignment->period->name : '' }}
            · {{ $assignment->started_on->toFormattedDateString() }}@if ($assignment->ended_on) – {{ $assignment->ended_on->toFormattedDateString() }} @endif
        </p>
    </div>
    @can('staff.manage')
        <div class="flex shrink-0 items-center gap-2">
            <x-button :href="route('teachers.assignments.edit', $assignment->id)" size="sm" variant="ghost">{{ __('Edit') }}</x-button>
            <x-confirm :action="route('teachers.assignments.destroy', $assignment->id)" method="DELETE" size="sm"
                :confirm="__('Remove')"
                :title="__('Remove assignment?')"
                :message="__('This deletes the assignment row. To keep it as history instead, edit it and set the status to Ended.')">
                {{ __('Remove') }}
            </x-confirm>
        </div>
    @endcan
</li>
