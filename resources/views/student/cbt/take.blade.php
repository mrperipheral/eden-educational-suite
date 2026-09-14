@php
    $questionsData = $questions->map(fn ($q) => [
        'id' => $q->id,
        'text' => $q->question_text,
        'options' => $q->options->map(fn ($o) => ['id' => $o->id, 'text' => $o->option_text])->values()->all(),
    ])->values()->all();

    $initialAnswers = [];
    foreach ($questions as $q) {
        $initialAnswers[$q->id] = optional($answers->get($q->id))->selected_option_id;
    }
@endphp

<x-layouts.authenticated :title="$examination->title">
    <div
        class="mx-auto max-w-3xl space-y-3 pb-4 sm:space-y-4"
        x-data="{
            questions: @js($questionsData),
            current: 0,
            selected: @js($initialAnswers),
            expiresAt: new Date('{{ $attempt->expires_at->toIso8601String() }}').getTime(),
            remaining: 0,
            timerHandle: null,
            submitting: false,

            init() {
                this.tick();
                this.timerHandle = setInterval(() => this.tick(), 1000);
            },
            tick() {
                this.remaining = Math.max(0, Math.floor((this.expiresAt - Date.now()) / 1000));
                if (this.remaining <= 0) {
                    clearInterval(this.timerHandle);
                    this.autoSubmit();
                }
            },
            formattedRemaining() {
                const m = Math.floor(this.remaining / 60);
                const s = this.remaining % 60;
                return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
            },
            isAnswered(qId) {
                return this.selected[qId] !== null && this.selected[qId] !== undefined;
            },
            answeredCount() {
                return Object.values(this.selected).filter((v) => v !== null && v !== undefined).length;
            },
            async selectOption(qId, optId) {
                this.selected[qId] = optId;
                try {
                    const res = await fetch('{{ route('student.cbt.answer', $examination->id) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ examination_question_id: qId, selected_option_id: optId }),
                    });
                    if (res.status === 409) {
                        window.location = '{{ route('student.cbt.result', $examination->id) }}';
                    }
                } catch (e) {
                    // A dropped request just means this answer will be
                    // out of sync with the server until the next
                    // successful save — the selection stays visible
                    // locally so the student can retry by reselecting.
                }
            },
            next() { if (this.current < this.questions.length - 1) this.current++ },
            prev() { if (this.current > 0) this.current-- },
            goTo(i) { this.current = i },
            autoSubmit() {
                if (this.submitting) return;
                this.submitting = true;
                document.getElementById('cbt-submit-form').submit();
            },
            confirmSubmit() {
                const unanswered = this.questions.length - this.answeredCount();
                const message = unanswered > 0
                    ? '{{ __('You have :count unanswered question(s). Submit anyway?') }}'.replace(':count', unanswered)
                    : '{{ __('Submit your examination now? This cannot be undone.') }}';
                if (confirm(message)) {
                    this.submitting = true;
                    document.getElementById('cbt-submit-form').submit();
                }
            },
        }"
        x-init="init()"
    >
        {{-- Title / progress / timer — sticky under the app header so the
             remaining time and progress stay visible while scrolling a long
             question on a small screen. --}}
        <div class="sticky top-16 z-20 -mx-4 flex items-center justify-between gap-3 border-b border-gray-200 bg-white/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-lg sm:border sm:shadow-sm">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-gray-900">{{ $examination->title }}</p>
                <p class="text-xs text-gray-500">
                    <span x-text="answeredCount()"></span> {{ __('of') }} {{ count($questionsData) }} {{ __('answered') }}
                </p>
                <div class="mt-1 h-1 w-28 overflow-hidden rounded-full bg-gray-100 sm:w-36" role="presentation">
                    <div
                        class="h-full rounded-full bg-brand-500 transition-all"
                        :style="'width: ' + (questions.length ? Math.round((answeredCount() / questions.length) * 100) : 0) + '%'"
                    ></div>
                </div>
            </div>
            <div class="shrink-0 text-right" role="status" aria-label="{{ __('Time remaining') }}">
                <p class="text-xs text-gray-500">{{ __('Time left') }}</p>
                <p
                    class="font-mono text-lg font-semibold tabular-nums sm:text-xl"
                    :class="remaining <= 60 ? 'text-red-600' : 'text-gray-900'"
                    x-text="formattedRemaining()"
                ></p>
            </div>
        </div>

        {{-- Question navigator --}}
        <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
            <p class="mb-2 text-xs font-medium text-gray-500">{{ __('Questions') }}</p>
            <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('Jump to question') }}">
                <template x-for="(q, index) in questions" :key="q.id">
                    <button
                        type="button"
                        @click="goTo(index)"
                        :class="{
                            'bg-brand-600 text-white': current === index,
                            'bg-green-100 text-green-800': current !== index && isAnswered(q.id),
                            'bg-gray-100 text-gray-600': current !== index && ! isAnswered(q.id),
                        }"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-1 sm:h-8 sm:w-8"
                        :aria-current="current === index ? 'true' : false"
                        :aria-label="'{{ __('Question') }} ' + (index + 1) + (isAnswered(q.id) ? ', {{ __('answered') }}' : ', {{ __('not answered') }}')"
                        x-text="index + 1"
                    ></button>
                </template>
            </div>
            <div class="mt-2 flex items-center gap-4 text-xs text-gray-500">
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-green-100 ring-1 ring-green-300" aria-hidden="true"></span>{{ __('Answered') }}</span>
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-gray-100 ring-1 ring-gray-300" aria-hidden="true"></span>{{ __('Not answered') }}</span>
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-brand-600" aria-hidden="true"></span>{{ __('Current') }}</span>
            </div>
        </div>

        {{-- Question / answer panel --}}
        <template x-for="(q, index) in questions" :key="q.id">
            <div x-show="current === index" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                <p class="mb-4 text-base font-medium leading-relaxed text-gray-900 sm:text-lg">
                    <span x-text="index + 1"></span>. <span x-text="q.text"></span>
                </p>
                <div class="space-y-2.5" role="radiogroup" :aria-label="'{{ __('Answer options for question') }} ' + (index + 1)">
                    <template x-for="option in q.options" :key="option.id">
                        <label
                            class="flex min-h-[3rem] cursor-pointer items-center gap-3 rounded-md border px-4 py-3 text-sm focus-within:ring-2 focus-within:ring-brand-500 focus-within:ring-offset-1 sm:text-base"
                            :class="selected[q.id] === option.id ? 'border-brand-500 bg-brand-50' : 'border-gray-200 hover:bg-gray-50'"
                        >
                            <input
                                type="radio"
                                :name="'question-' + q.id"
                                :checked="selected[q.id] === option.id"
                                @change="selectOption(q.id, option.id)"
                                class="h-4 w-4 shrink-0 text-brand-600 focus:ring-brand-500"
                            >
                            <span class="leading-snug" x-text="option.text"></span>
                        </label>
                    </template>
                </div>
            </div>
        </template>

        {{-- Previous / Next / Submit — sticky to the viewport bottom so it's
             always reachable without scrolling on a phone. --}}
        <div class="sticky bottom-0 z-20 -mx-4 flex items-center justify-between gap-2 border-t border-gray-200 bg-white px-4 pt-3 shadow-[0_-4px_12px_-6px_rgba(0,0,0,0.15)] sm:mx-0 sm:rounded-lg sm:border sm:shadow-sm" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
            <div class="flex gap-2">
                <x-button type="button" variant="secondary" size="sm" @click="prev()" x-bind:disabled="current === 0">
                    {{ __('Previous') }}
                </x-button>
                <x-button type="button" variant="secondary" size="sm" @click="next()" x-bind:disabled="current === questions.length - 1">
                    {{ __('Next') }}
                </x-button>
            </div>
            <x-button type="button" variant="danger" size="sm" @click="confirmSubmit()" x-bind:disabled="submitting">
                <span class="sm:hidden">{{ __('Submit') }}</span>
                <span class="hidden sm:inline">{{ __('Submit examination') }}</span>
            </x-button>
        </div>

        <form id="cbt-submit-form" method="POST" action="{{ route('student.cbt.submit', $examination->id) }}" class="hidden">
            @csrf
        </form>
    </div>
</x-layouts.authenticated>
