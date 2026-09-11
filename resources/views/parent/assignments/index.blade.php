<x-layouts.authenticated :title="__('Assignments — :name', ['name' => $student->fullName()])">
    <div class="space-y-6">
        @include('parent._child-nav', [
            'student' => $student, 'siblings' => $siblings, 'active' => 'assignments',
            'modules' => $modules, 'sectionRoute' => 'parent.assignments.index',
        ])

        @if (! $moduleOn)
            <x-empty-state
                :title="__('Assignments are not currently available')"
                :description="__('This school has not enabled assignments for this account yet.')"
            />
        @elseif ($submissions->isEmpty())
            <x-empty-state
                :title="__('No assignments have been issued to this student yet')"
                :description="__('Assignments appear here once a teacher publishes one for this class.')"
            />
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
                            @if ($submission->remark)
                                <p class="text-xs text-gray-500">{{ __('Remark') }}: {{ $submission->remark }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $submissions->links() }}
        @endif
    </div>
</x-layouts.authenticated>
