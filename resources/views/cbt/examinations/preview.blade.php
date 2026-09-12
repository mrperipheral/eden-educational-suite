<x-layouts.authenticated :title="__('Preview: :title', ['title' => $examination->title])">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('cbt.examinations.show', $examination->id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← :title', ['title' => $examination->title]) }}</a>
        </p>

        <x-alert variant="info">
            {{ __('Staff preview — the correct option is highlighted here for your own review. Students never see this.') }}
        </x-alert>

        @if ($questions->isEmpty())
            <x-empty-state :title="__('No questions attached yet')" />
        @else
            <div class="space-y-4">
                @foreach ($questions as $question)
                    <x-card>
                        <p class="text-sm font-medium text-gray-900">{{ $loop->iteration }}. {{ $question->question_text }}</p>
                        <p class="mb-3 text-xs text-gray-500">{{ $question->type->label() }} · {{ __(':count marks', ['count' => $question->marks]) }}</p>
                        <ul class="space-y-1">
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
                    </x-card>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.authenticated>
