<x-layouts.authenticated :title="__('Result: :title', ['title' => $examination->title])">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('student.cbt.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← CBT') }}</a>
        </p>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-card :title="$examination->title">
            @if ($attempt === null)
                <x-empty-state :title="__('You have not attempted this examination')" />
            @elseif ($attempt->isInProgress())
                <p class="mb-3 text-sm text-gray-600">{{ __('Your attempt is still in progress.') }}</p>
                <x-button :href="route('student.cbt.take', $examination->id)" size="sm">{{ __('Continue examination') }}</x-button>
            @elseif (! $resultVisible)
                <div class="text-center py-6">
                    <p class="text-sm font-medium text-gray-900">{{ __('Examination submitted successfully.') }}</p>
                    @if ($examination->result_release_at)
                        <p class="mt-1 text-sm text-gray-600">
                            {{ __('Your result will be available on :date.', ['date' => $examination->result_release_at->format('d M Y, H:i')]) }}
                        </p>
                    @else
                        <p class="mt-1 text-sm text-gray-600">{{ __('Your result has not been released yet.') }}</p>
                    @endif
                </div>
            @else
                <div class="text-center py-6">
                    <p class="text-3xl font-semibold text-gray-900">{{ $attempt->percentage }}%</p>
                    <p class="mt-1 text-sm text-gray-600">{{ $attempt->score }} / {{ $attempt->max_score }} {{ __('marks') }}</p>
                    <x-badge :variant="$attempt->passed ? 'success' : 'danger'" class="mt-3">
                        {{ $attempt->passed ? __('Pass') : __('Fail') }}
                    </x-badge>
                    <p class="mt-4 text-xs text-gray-500">{{ __('Pass mark: :mark%', ['mark' => $examination->pass_mark_percentage]) }}</p>
                </div>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
