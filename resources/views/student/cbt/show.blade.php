<x-layouts.authenticated :title="$examination->title">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('student.cbt.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← CBT') }}</a>
        </p>

        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        <x-card :title="$examination->title">
            @if ($examination->description)
                <p class="mb-4 text-sm text-gray-600">{{ $examination->description }}</p>
            @endif

            <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-xs text-gray-500">{{ __('Subject') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $examination->subject?->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('Questions') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $examination->questions_count }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('Duration') }}</dt>
                    <dd class="font-medium text-gray-900">{{ __(':count minutes', ['count' => $examination->duration_minutes]) }}</dd>
                </div>
                <div class="col-span-2 sm:col-span-3">
                    <dt class="text-xs text-gray-500">{{ __('Available') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $examination->starts_at->format('d M Y, H:i') }} – {{ $examination->ends_at->format('d M Y, H:i') }}</dd>
                </div>
            </dl>

            <div class="mt-6 border-t border-gray-100 pt-4">
                @if ($attempt?->isCompleted())
                    <p class="mb-3 text-sm text-gray-600">{{ __('You have already completed this examination.') }}</p>
                    <x-button :href="route('student.cbt.result', $examination->id)" size="sm">{{ __('View result') }}</x-button>
                @elseif ($attempt?->isInProgress())
                    <p class="mb-3 text-sm text-gray-600">{{ __('You have an attempt in progress.') }}</p>
                    <x-button :href="route('student.cbt.take', $examination->id)" size="sm">{{ __('Continue examination') }}</x-button>
                @elseif ($examination->isOpenForAttempts())
                    <form method="POST" action="{{ route('student.cbt.start', $examination->id) }}" onsubmit="return confirm('{{ __('Once started, the timer cannot be paused. Begin now?') }}')">
                        @csrf
                        <x-button type="submit">{{ __('Start examination') }}</x-button>
                    </form>
                @else
                    <x-badge variant="gray">{{ __('Not currently open') }}</x-badge>
                @endif
            </div>
        </x-card>
    </div>
</x-layouts.authenticated>
