@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $textareaClass = $selectClass;
    $initialOptions = old('options') ?? ($question->options ?? collect())->map(fn ($o) => ['option_text' => $o->option_text, 'is_correct' => $o->is_correct])->values()->all();
    if (empty($initialOptions)) {
        $initialOptions = [['option_text' => '', 'is_correct' => true], ['option_text' => '', 'is_correct' => false]];
    }
@endphp

<form method="POST" action="{{ $action }}"
    x-data="{
        type: '{{ old('type', $question->type->value ?? 'multiple_choice') }}',
        options: @js($initialOptions),
        addOption() { this.options.push({ option_text: '', is_correct: false }) },
        removeOption(i) { if (this.options.length > 2) this.options.splice(i, 1) },
        setType(t) {
            this.type = t;
            if (t === 'true_false') {
                this.options = [{ option_text: 'True', is_correct: true }, { option_text: 'False', is_correct: false }];
            }
        },
        setCorrect(i) {
            this.options.forEach((o, idx) => { o.is_correct = (idx === i) });
        },
    }"
>
    @csrf
    @if ($method ?? null)
        @method($method)
    @endif

    <div class="space-y-4">
        <div class="space-y-1">
            <label for="subject_id" class="block text-sm font-medium text-gray-700">{{ __('Subject') }}</label>
            <select id="subject_id" name="subject_id" class="{{ $selectClass }}" required>
                <option value="">{{ __('Select a subject') }}</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected(old('subject_id', $question->subject_id ?? '') == $subject->id)>{{ $subject->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="space-y-1">
            <label for="type" class="block text-sm font-medium text-gray-700">{{ __('Question type') }}</label>
            <select id="type" name="type" x-model="type" @change="setType($event.target.value)" class="{{ $selectClass }}" required>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="space-y-1">
            <label for="question_text" class="block text-sm font-medium text-gray-700">{{ __('Question text') }}</label>
            <textarea id="question_text" name="question_text" rows="3" required class="{{ $textareaClass }}">{{ old('question_text', $question->question_text ?? '') }}</textarea>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-1">
                <label for="marks" class="block text-sm font-medium text-gray-700">{{ __('Marks') }}</label>
                <input type="number" id="marks" name="marks" step="0.01" min="0.01" value="{{ old('marks', $question->marks ?? 1) }}" required class="{{ $selectClass }}">
            </div>
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $question->is_active ?? true)) class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Active (selectable when attaching to an exam)') }}
                </label>
            </div>
        </div>

        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <label class="block text-sm font-medium text-gray-700">{{ __('Options — select the correct one') }}</label>
                <button type="button" x-show="type === 'multiple_choice'" @click="addOption()" class="text-xs font-medium text-brand-600 hover:text-brand-700">{{ __('+ Add option') }}</button>
            </div>

            <template x-for="(option, index) in options" :key="index">
                <div class="flex items-center gap-2">
                    <input type="radio" name="correct_option" :checked="option.is_correct" @change="setCorrect(index)" class="text-brand-600 focus:ring-brand-500">
                    <input type="text" :name="'options[' + index + '][option_text]'" x-model="option.option_text" required class="{{ $selectClass }}" :placeholder="'{{ __('Option') }} ' + (index + 1)">
                    <input type="hidden" :name="'options[' + index + '][is_correct]'" :value="option.is_correct ? 1 : 0">
                    <button type="button" x-show="type === 'multiple_choice' && options.length > 2" @click="removeOption(index)" class="text-xs text-gray-400 hover:text-red-600">{{ __('Remove') }}</button>
                </div>
            </template>
        </div>
    </div>

    <div class="mt-6">
        <x-button type="submit">{{ $submitLabel }}</x-button>
    </div>
</form>
