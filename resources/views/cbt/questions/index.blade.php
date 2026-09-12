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
        @if (session('error'))
            <x-alert variant="danger">{{ session('error') }}</x-alert>
        @endif

        <form method="GET" action="{{ route('cbt.questions.index') }}" class="flex flex-wrap items-end gap-2" x-data="{ levelId: '{{ $filters['level'] ?? '' }}' }">
            <div class="space-y-1">
                <label for="q" class="block text-xs font-medium text-gray-700">{{ __('Search') }}</label>
                <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Question text or topic…') }}" class="{{ $selectClass }}">
            </div>
            <div class="space-y-1">
                <label for="subject" class="block text-xs font-medium text-gray-700">{{ __('Subject') }}</label>
                <select id="subject" name="subject" class="{{ $selectClass }}">
                    <option value="">{{ __('All subjects') }}</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected(($filters['subject'] ?? null) == $subject->id)>{{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="level" class="block text-xs font-medium text-gray-700">{{ __('Level') }}</label>
                <select id="level" name="level" x-model="levelId" class="{{ $selectClass }}">
                    <option value="">{{ __('All levels') }}</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level->id }}">{{ $level->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="arm" class="block text-xs font-medium text-gray-700">{{ __('Arm') }}</label>
                <select id="arm" name="arm" class="{{ $selectClass }}">
                    <option value="">{{ __('All arms') }}</option>
                    @foreach ($levels as $level)
                        @foreach ($level->arms as $arm)
                            <option value="{{ $arm->id }}" x-show="levelId === '{{ $level->id }}'" @selected(($filters['arm'] ?? null) == $arm->id)>{{ $level->name }} — {{ $arm->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="type" class="block text-xs font-medium text-gray-700">{{ __('Type') }}</label>
                <select id="type" name="type" class="{{ $selectClass }}">
                    <option value="">{{ __('All types') }}</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(($filters['type'] ?? null) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="difficulty" class="block text-xs font-medium text-gray-700">{{ __('Difficulty') }}</label>
                <select id="difficulty" name="difficulty" class="{{ $selectClass }}">
                    <option value="">{{ __('All difficulties') }}</option>
                    @foreach ($difficulties as $difficulty)
                        <option value="{{ $difficulty->value }}" @selected(($filters['difficulty'] ?? null) === $difficulty->value)>{{ $difficulty->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="status" class="block text-xs font-medium text-gray-700">{{ __('Status') }}</label>
                <select id="status" name="status" class="{{ $selectClass }}">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" size="sm" variant="secondary">{{ __('Filter') }}</x-button>
            @if (array_filter($filters))
                <x-button :href="route('cbt.questions.index')" size="sm" variant="ghost">{{ __('Clear') }}</x-button>
            @endif
        </form>

        @if ($questions->isEmpty())
            <x-empty-state
                :title="__('No questions match')"
                :description="__('Build a reusable question bank — attach active questions to any draft examination for a matching subject/class.')"
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
                                    <a href="{{ route('cbt.questions.preview', $question->id) }}" class="hover:text-brand-700">{{ $question->question_text }}</a>
                                    <x-badge :variant="$question->status->badgeVariant()" class="ml-1">{{ $question->status->label() }}</x-badge>
                                    <x-badge :variant="$question->difficulty->badgeVariant()">{{ $question->difficulty->label() }}</x-badge>
                                    <x-badge variant="gray">{{ $question->type->label() }}</x-badge>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $question->subject?->name }}
                                    · {{ $question->level ? $question->level->name.($question->arm ? ' — '.$question->arm->name : '') : __('Any level') }}
                                    @if ($question->topic)
                                        · {{ $question->topic }}
                                    @endif
                                    · {{ __(':count marks', ['count' => $question->marks]) }}
                                    · {{ __(':count options', ['count' => $question->options->count()]) }}
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <x-button :href="route('cbt.questions.preview', $question->id)" size="sm" variant="ghost">{{ __('Preview') }}</x-button>
                                @can('cbt.author')
                                    <x-button :href="route('cbt.questions.edit', $question->id)" size="sm" variant="secondary">{{ __('Edit') }}</x-button>
                                    @if ($question->status === \App\Enums\QuestionStatus::Active)
                                        <form method="POST" action="{{ route('cbt.questions.deactivate', $question->id) }}">
                                            @csrf
                                            <x-button type="submit" size="sm" variant="ghost">{{ __('Deactivate') }}</x-button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('cbt.questions.activate', $question->id) }}">
                                            @csrf
                                            <x-button type="submit" size="sm" variant="ghost">{{ __('Activate') }}</x-button>
                                        </form>
                                    @endif
                                    @unless ($question->status === \App\Enums\QuestionStatus::Archived)
                                        <x-confirm
                                            :action="route('cbt.questions.archive', $question->id)"
                                            method="POST"
                                            size="sm"
                                            :title="__('Archive this question?')"
                                            :message="__('It can no longer be selected for a new examination. Exams that already use it keep their own copy, unaffected.')"
                                            :confirm="__('Archive')"
                                        >
                                            {{ __('Archive') }}
                                        </x-confirm>
                                    @endunless
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
