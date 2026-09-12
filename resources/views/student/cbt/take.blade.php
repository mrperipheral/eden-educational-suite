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
        class="mx-auto max-w-3xl space-y-4"
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
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
            <div>
                <p class="text-sm font-semibold text-gray-900">{{ $examination->title }}</p>
                <p class="text-xs text-gray-500"><span x-text="answeredCount()"></span> {{ __('of') }} {{ count($questionsData) }} {{ __('answered') }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-500">{{ __('Time remaining') }}</p>
                <p class="font-mono text-lg font-semibold" :class="remaining <= 60 ? 'text-red-600' : 'text-gray-900'" x-text="formattedRemaining()"></p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 rounded-lg border border-gray-200 bg-white p-3 shadow-sm">
            <template x-for="(q, index) in questions" :key="q.id">
                <button
                    type="button"
                    @click="goTo(index)"
                    :class="{
                        'bg-brand-600 text-white': current === index,
                        'bg-green-100 text-green-800': current !== index && isAnswered(q.id),
                        'bg-gray-100 text-gray-600': current !== index && ! isAnswered(q.id),
                    }"
                    class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-medium"
                    x-text="index + 1"
                ></button>
            </template>
        </div>

        <template x-for="(q, index) in questions" :key="q.id">
            <div x-show="current === index" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                <p class="mb-4 text-sm font-medium text-gray-900">
                    <span x-text="index + 1"></span>. <span x-text="q.text"></span>
                </p>
                <div class="space-y-2">
                    <template x-for="option in q.options" :key="option.id">
                        <label
                            class="flex cursor-pointer items-center gap-3 rounded-md border px-3 py-2 text-sm"
                            :class="selected[q.id] === option.id ? 'border-brand-500 bg-brand-50' : 'border-gray-200 hover:bg-gray-50'"
                        >
                            <input
                                type="radio"
                                :name="'question-' + q.id"
                                :checked="selected[q.id] === option.id"
                                @change="selectOption(q.id, option.id)"
                                class="text-brand-600 focus:ring-brand-500"
                            >
                            <span x-text="option.text"></span>
                        </label>
                    </template>
                </div>
            </div>
        </template>

        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
            <div class="flex gap-2">
                <x-button type="button" variant="secondary" size="sm" @click="prev()" x-bind:disabled="current === 0">{{ __('Previous') }}</x-button>
                <x-button type="button" variant="secondary" size="sm" @click="next()" x-bind:disabled="current === questions.length - 1">{{ __('Next') }}</x-button>
            </div>
            <x-button type="button" variant="danger" size="sm" @click="confirmSubmit()">{{ __('Submit examination') }}</x-button>
        </div>

        <form id="cbt-submit-form" method="POST" action="{{ route('student.cbt.submit', $examination->id) }}" class="hidden">
            @csrf
        </form>
    </div>
</x-layouts.authenticated>
