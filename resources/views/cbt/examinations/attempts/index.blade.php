<x-layouts.authenticated :title="__('Attempts: :title', ['title' => $examination->title])">
    <div class="space-y-6">
        <p class="text-sm">
            <a href="{{ route('cbt.examinations.show', $examination->id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← :title', ['title' => $examination->title]) }}</a>
        </p>

        @if ($attempts->isEmpty())
            <x-empty-state :title="__('No attempts yet')" :description="__('Attempts appear here once a student starts the examination.')" />
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($attempts as $attempt)
                        <li class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    {{ $attempt->student?->displayName() }}
                                    <x-badge :variant="$attempt->status->badgeVariant()" class="ml-1">{{ $attempt->status->label() }}</x-badge>
                                    @if ($attempt->auto_submitted)
                                        <x-badge variant="gray">{{ __('Auto-submitted') }}</x-badge>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ __('Started') }} {{ $attempt->started_at->format('d M Y, H:i') }}
                                    @if ($attempt->submitted_at)
                                        · {{ __('Submitted') }} {{ $attempt->submitted_at->format('d M Y, H:i') }}
                                    @endif
                                </p>
                            </div>
                            <div class="text-right text-sm">
                                @if ($attempt->isCompleted())
                                    <p class="font-medium text-gray-900">{{ $attempt->score }} / {{ $attempt->max_score }} ({{ $attempt->percentage }}%)</p>
                                    <x-badge :variant="$attempt->passed ? 'success' : 'danger'">{{ $attempt->passed ? __('Pass') : __('Fail') }}</x-badge>
                                @else
                                    <span class="text-xs text-gray-400">{{ __('In progress') }}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $attempts->links() }}
        @endif
    </div>
</x-layouts.authenticated>
