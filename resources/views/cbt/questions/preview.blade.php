<x-layouts.authenticated :title="__('Preview question')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('cbt.questions.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Question Bank') }}</a>
        </p>

        <x-alert variant="info">
            {{ __('Staff preview — the correct option is highlighted here for your own review. Students never see this.') }}
        </x-alert>

        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <x-badge :variant="$question->status->badgeVariant()">{{ $question->status->label() }}</x-badge>
                <x-badge :variant="$question->difficulty->badgeVariant()">{{ $question->difficulty->label() }}</x-badge>
                <x-badge variant="gray">{{ $question->type->label() }}</x-badge>
            </div>

            <p class="mt-3 text-sm font-medium text-gray-900">{{ $question->question_text }}</p>
            <p class="mt-1 text-xs text-gray-500">
                {{ $question->subject?->name }}
                @if ($question->level)
                    · {{ $question->level->name }}{{ $question->arm ? ' — '.$question->arm->name : '' }}
                @else
                    · {{ __('Any level') }}
                @endif
                @if ($question->topic)
                    · {{ $question->topic }}
                @endif
                · {{ __(':count marks', ['count' => $question->marks]) }}
            </p>

            <ul class="mt-4 space-y-1">
                @foreach ($question->options as $option)
                    <li @class([
                        'rounded-md px-3 py-2 text-sm',
                        'bg-green-50 text-green-800 ring-1 ring-inset ring-green-200' => $option->is_correct,
                        'bg-gray-50 text-gray-700' => ! $option->is_correct,
                    ])>
                        {{ $option->option_text }}
                        @if ($option->is_correct)
                            <span class="font-medium">({{ __('correct') }})</span>
                        @endif
                    </li>
                @endforeach
            </ul>

            @can('cbt.author')
                <div class="mt-6">
                    <x-button :href="route('cbt.questions.edit', $question->id)" size="sm" variant="secondary">{{ __('Edit') }}</x-button>
                </div>
            @endcan
        </x-card>
    </div>
</x-layouts.authenticated>
