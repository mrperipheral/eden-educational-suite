<x-layouts.authenticated :title="__('CBT')">
    <div class="space-y-6">
        @include('student._nav', ['active' => 'cbt'])

        @if (! $student)
            <x-empty-state :title="__('Your account is not linked to a student record yet')" :description="__('Please contact your school administrator.')" />
        @elseif (! $moduleOn)
            <x-empty-state :title="__('CBT is not currently available')" :description="__('This school has not enabled online examinations for this account yet.')" />
        @elseif (is_null($examinations))
            <x-empty-state :title="__('No current class placement')" :description="__('Examinations appear here once you are enrolled in a class.')" />
        @elseif ($examinations->isEmpty())
            <x-empty-state :title="__('No examinations yet')" :description="__('Examinations your teachers schedule for your class will appear here.')" />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($examinations as $examination)
                        @php($attempt = $attemptStatuses->get($examination->id))
                        <li class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    <a href="{{ route('student.cbt.show', $examination->id) }}" class="hover:text-brand-700">{{ $examination->title }}</a>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $examination->subject?->name }}
                                    · {{ __(':count questions', ['count' => $examination->questions_count]) }}
                                    · {{ __(':count minutes', ['count' => $examination->duration_minutes]) }}
                                    · {{ $examination->starts_at->format('d M Y, H:i') }} – {{ $examination->ends_at->format('d M Y, H:i') }}
                                </p>
                            </div>
                            <div class="shrink-0">
                                @if ($attempt?->isCompleted())
                                    <x-badge variant="success">{{ __('Completed') }}</x-badge>
                                @elseif ($attempt?->isInProgress())
                                    <x-badge variant="warning">{{ __('In progress') }}</x-badge>
                                @elseif ($examination->isOpenForAttempts())
                                    <x-badge variant="success">{{ __('Open') }}</x-badge>
                                @else
                                    <x-badge variant="gray">{{ $examination->status->label() }}</x-badge>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $examinations->links() }}
        @endif
    </div>
</x-layouts.authenticated>
