<x-layouts.authenticated :title="__('Assignments')">
    <div class="space-y-6">
        @include('student._nav', ['active' => 'assignments'])

        @if (! $student)
            <x-empty-state :title="__('Your account is not linked to a student record yet')" :description="__('Please contact your school administrator.')" />
        @elseif (! $moduleOn)
            <x-empty-state :title="__('Assignments are not currently available')" :description="__('This school has not enabled assignments for this account yet.')" />
        @elseif ($submissions->isEmpty())
            <x-empty-state :title="__('No assignments have been issued yet')" :description="__('Assignments appear here once a teacher publishes one for your class.')" />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($submissions as $submission)
                        <li class="flex flex-col gap-1 px-4 py-3 sm:px-6">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-medium text-gray-900">{{ $submission->assignment->title }}</p>
                                <div class="flex items-center gap-1">
                                    <x-badge :variant="$submission->assignment->status->badgeVariant()">{{ $submission->assignment->status->label() }}</x-badge>
                                    <x-badge :variant="$submission->status->badgeVariant()">{{ $submission->status->label() }}</x-badge>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500">
                                {{ $submission->assignment->subject?->name }}
                                @if ($submission->assignment->teacher) · {{ $submission->assignment->teacher->shortName() }} @endif
                                · {{ __('Assigned') }} {{ $submission->assignment->assigned_on?->format('d M Y') }}
                                · {{ __('Due') }} {{ $submission->assignment->due_on?->format('d M Y') }}
                            </p>
                            @if ($submission->assignment->instructions)
                                <p class="text-xs text-gray-500">{{ $submission->assignment->instructions }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $submissions->links() }}
        @endif
    </div>
</x-layouts.authenticated>
