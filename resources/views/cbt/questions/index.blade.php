@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<x-layouts.authenticated :title="__('Question Bank')">
    @can('cbt.author')
        <x-slot:actions>
            <x-button :href="route('cbt.questions.create')" size="sm">{{ __('Add question') }}</x-button>
        </x-slot:actions>
    @endcan

    <div class="space-y-6">
        <p class="text-sm">
            <a href="{{ route('cbt.examinations.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Examinations') }}</a>
        </p>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <form method="GET" action="{{ route('cbt.questions.index') }}" class="flex flex-wrap items-end gap-2">
            <div class="space-y-1">
                <label for="subject" class="block text-xs font-medium text-gray-700">{{ __('Subject') }}</label>
                <select id="subject" name="subject" class="{{ $selectClass }}" onchange="this.form.submit()">
                    <option value="">{{ __('All subjects') }}</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected(($filters['subject'] ?? null) == $subject->id)>{{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        @if ($questions->isEmpty())
            <x-empty-state
                :title="__('No questions yet')"
                :description="__('Build a reusable question bank — attach questions to any draft examination for a matching subject.')"
            >
                @can('cbt.author')
                    <x-slot:actions>
                        <x-button :href="route('cbt.questions.create')" size="sm">{{ __('Add question') }}</x-button>
                    </x-slot:actions>
                @endcan
            </x-empty-state>
        @else
            <x-card :padding="false">
                <ul class="divide-y divide-gray-100">
                    @foreach ($questions as $question)
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">
                                    {{ $question->question_text }}
                                    <x-badge variant="gray" class="ml-1">{{ $question->type->label() }}</x-badge>
                                    @unless ($question->is_active)
                                        <x-badge variant="gray">{{ __('Inactive') }}</x-badge>
                                    @endunless
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $question->subject?->name }}
                                    · {{ __(':count marks', ['count' => $question->marks]) }}
                                    · {{ __(':count options', ['count' => $question->options->count()]) }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                @can('cbt.author')
                                    <x-button :href="route('cbt.questions.edit', $question->id)" size="sm" variant="secondary">{{ __('Edit') }}</x-button>
                                    <form method="POST" action="{{ route('cbt.questions.toggle-active', $question->id) }}">
                                        @csrf
                                        <x-button type="submit" size="sm" variant="ghost">
                                            {{ $question->is_active ? __('Deactivate') : __('Activate') }}
                                        </x-button>
                                    </form>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            {{ $questions->links() }}
        @endif
    </div>
</x-layouts.authenticated>
