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
        @if ($unrestricted ?? true)
        levelId: '{{ old('academic_level_id', $question->academic_level_id ?? '') }}',
        @else
        selectedAssignment: '',
        subjectId: '', levelId: '', armId: '',
        applyAssignment() {
            const [s, l, a] = this.selectedAssignment.split('|');
            this.subjectId = s ?? ''; this.levelId = l ?? ''; this.armId = a ?? '';
        },
        @endif
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
        @if ($unrestricted ?? true)
            <div class="space-y-1">
                <label for="subject_id" class="block text-sm font-medium text-gray-700">{{ __('Subject') }}</label>
                <select id="subject_id" name="subject_id" class="{{ $selectClass }}" required>
                    <option value="">{{ __('Select a subject') }}</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected(old('subject_id', $question->subject_id ?? '') == $subject->id)>{{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1">
                    <label for="academic_level_id" class="block text-sm font-medium text-gray-700">{{ __('Level (optional — leave blank to reuse at any level)') }}</label>
                    <select id="academic_level_id" name="academic_level_id" x-model="levelId" class="{{ $selectClass }}">
                        <option value="">{{ __('Any level') }}</option>
                        @foreach ($levels as $level)
                            <option value="{{ $level->id }}">{{ $level->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label for="level_arm_id" class="block text-sm font-medium text-gray-700">{{ __('Arm (optional)') }}</label>
                    <select id="level_arm_id" name="level_arm_id" class="{{ $selectClass }}">
                        <option value="">{{ __('Any arm') }}</option>
                        @foreach ($levels as $level)
                            @foreach ($level->arms as $arm)
                                <option value="{{ $arm->id }}" x-show="levelId === '{{ $level->id }}'" @selected(old('level_arm_id', $question->level_arm_id ?? '') == $arm->id)>{{ $level->name }} — {{ $arm->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
            </div>
        @else
            <div class="space-y-1">
                <label for="assignment" class="block text-sm font-medium text-gray-700">{{ __('Subject & class') }}</label>
                <select id="assignment" x-model="selectedAssignment" @change="applyAssignment()" class="{{ $selectClass }}" required>
                    <option value="">{{ __('Select one of your assigned subjects/classes') }}</option>
                    @foreach ($assignments as $assignment)
                        <option value="{{ $assignment->subject_id }}|{{ $assignment->academic_level_id }}|{{ $assignment->level_arm_id }}">
                            {{ $assignment->subject?->name }} — {{ $assignment->level?->name }}{{ $assignment->arm ? ' — '.$assignment->arm->name : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500">{{ __('The question will be reusable across any arm/level you pick above that matches your teaching assignment.') }}</p>
                <input type="hidden" name="subject_id" x-model="subjectId">
                <input type="hidden" name="academic_level_id" x-model="levelId">
                <input type="hidden" name="level_arm_id" x-model="armId">
            </div>
        @endif

        <div class="space-y-1">
            <label for="topic" class="block text-sm font-medium text-gray-700">{{ __('Topic (optional)') }}</label>
            <input type="text" id="topic" name="topic" value="{{ old('topic', $question->topic ?? '') }}" maxlength="150" class="{{ $selectClass }}">
        </div>

        <div class="space-y-1">
            <label for="type" class="block text-sm font-medium text-gray-700">{{ __('Question type') }}</label>
            <select id="type" name="type" x-model="type" @change="setType($event.target.value)" class="{{ $selectClass }}" required>
                @foreach ($types as $t)
                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
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
            <div class="space-y-1">
                <label for="difficulty" class="block text-sm font-medium text-gray-700">{{ __('Difficulty') }}</label>
                <select id="difficulty" name="difficulty" class="{{ $selectClass }}" required>
                    @foreach ($difficulties as $difficulty)
                        <option value="{{ $difficulty->value }}" @selected(old('difficulty', $question->difficulty->value ?? 'medium') === $difficulty->value)>{{ $difficulty->label() }}</option>
                    @endforeach
                </select>
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
