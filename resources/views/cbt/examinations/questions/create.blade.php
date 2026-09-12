<x-layouts.authenticated :title="__('Add a question')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('cbt.examinations.show', $examination->id) }}" class="text-brand-600 hover:text-brand-700">{{ __('← :title', ['title' => $examination->title]) }}</a>
        </p>

        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        @if ($availableQuestions->isEmpty())
            <x-empty-state
                :title="__('No matching questions available')"
                :description="__('Every active question bank entry for :subject is already attached, or none exist yet.', ['subject' => $examination->subject?->name])"
            >
                <x-slot:actions>
                    <x-button :href="route('cbt.questions.create')" size="sm">{{ __('Add a new question') }}</x-button>
                </x-slot:actions>
            </x-empty-state>
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($availableQuestions as $question)
                        <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm text-gray-900">{{ $question->question_text }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $question->type->label() }} · {{ $question->difficulty->label() }}
                                    · {{ $question->level ? $question->level->name.($question->arm ? ' — '.$question->arm->name : '') : __('Any level') }}
                                    · {{ __(':count marks', ['count' => $question->marks]) }} · {{ __(':count options', ['count' => $question->options->count()]) }}
                                </p>
                            </div>
                            <form method="POST" action="{{ route('cbt.examinations.questions.store', $examination->id) }}">
                                @csrf
                                <input type="hidden" name="question_id" value="{{ $question->id }}">
                                <x-button type="submit" size="sm">{{ __('Add') }}</x-button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>
</x-layouts.authenticated>
