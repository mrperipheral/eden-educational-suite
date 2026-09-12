<x-layouts.authenticated :title="$examination->title">
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

        <x-card>
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-gray-900">{{ $examination->title }}</h2>
                        <x-badge :variant="$examination->status->badgeVariant()">{{ $examination->status->label() }}</x-badge>
                    </div>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ $examination->subject?->name }} ·
                        {{ $examination->level?->name }}{{ $examination->arm ? ' — '.$examination->arm->name : '' }}
                        ({{ $examination->session?->name }}, {{ $examination->period?->name }})
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-button :href="route('cbt.examinations.preview', $examination->id)" size="sm" variant="secondary">{{ __('Preview') }}</x-button>
                    <x-button :href="route('cbt.examinations.attempts.index', $examination->id)" size="sm" variant="secondary">{{ __('Attempts & results') }}</x-button>
                    @if ($canManage)
                        @if ($examination->status->metadataEditable())
                            <x-button :href="route('cbt.examinations.edit', $examination->id)" size="sm" variant="secondary">{{ __('Edit') }}</x-button>
                        @endif
                        @if ($examination->status === \App\Enums\ExaminationStatus::Draft)
                            <x-confirm
                                :action="route('cbt.examinations.schedule', $examination->id)"
                                method="POST"
                                size="sm"
                                variant="primary"
                                :title="__('Schedule this examination?')"
                                :message="__('It becomes visible to its class and its questions can no longer be changed.')"
                                :confirm="__('Schedule')"
                            >
                                {{ __('Schedule') }}
                            </x-confirm>
                        @elseif ($examination->status === \App\Enums\ExaminationStatus::Scheduled)
                            <x-confirm
                                :action="route('cbt.examinations.close', $examination->id)"
                                method="POST"
                                size="sm"
                                :title="__('Close this examination?')"
                                :message="__('No further attempts will be able to start. This cannot be undone.')"
                                :confirm="__('Close')"
                            >
                                {{ __('Close') }}
                            </x-confirm>
                        @endif
                    @endif
                </div>
            </div>

            @if ($examination->description)
                <p class="mt-3 text-sm text-gray-600">{{ $examination->description }}</p>
            @endif

            <dl class="mt-4 grid grid-cols-2 gap-4 border-t border-gray-100 pt-4 text-sm sm:grid-cols-4">
                <div>
                    <dt class="text-xs text-gray-500">{{ __('Duration') }}</dt>
                    <dd class="font-medium text-gray-900">{{ __(':count minutes', ['count' => $examination->duration_minutes]) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('Window') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $examination->starts_at->format('d M Y, H:i') }} – {{ $examination->ends_at->format('d M Y, H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('Pass mark') }}</dt>
                    <dd class="font-medium text-gray-900">{{ $examination->pass_mark_percentage }}%</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('Result release') }}</dt>
                    <dd class="font-medium text-gray-900">
                        {{ $examination->result_release->label() }}
                        @if ($examination->result_release === \App\Enums\ResultReleaseMode::Scheduled && $examination->result_release_at)
                            ({{ $examination->result_release_at->format('d M Y, H:i') }})
                        @endif
                    </dd>
                </div>
            </dl>
        </x-card>

        <x-card :title="__('Questions')">
            @can('cbt.author')
                @if ($examination->status->structureEditable() && $canManage)
                    <x-slot:actions>
                        <x-button :href="route('cbt.examinations.questions.create', $examination->id)" size="sm">{{ __('Add question') }}</x-button>
                    </x-slot:actions>
                @endif
            @endcan

            @if ($questions->isEmpty())
                <p class="text-sm text-gray-500">{{ __('No questions attached yet.') }}</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($questions as $question)
                        <li class="flex items-center justify-between gap-2 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm text-gray-900">{{ $loop->iteration }}. {{ $question->question_text }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $question->type->label() }} · {{ __(':count marks', ['count' => $question->marks]) }} · {{ __(':count options', ['count' => $question->options_count]) }}
                                </p>
                            </div>
                            @if ($examination->status->structureEditable() && $canManage)
                                <form method="POST" action="{{ route('cbt.examinations.questions.destroy', [$examination->id, $question->id]) }}" onsubmit="return confirm('{{ __('Remove this question?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-gray-400 hover:text-red-600">{{ __('Remove') }}</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-gray-500">{{ __('Total: :count marks', ['count' => $examination->totalMarks()]) }}</p>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
