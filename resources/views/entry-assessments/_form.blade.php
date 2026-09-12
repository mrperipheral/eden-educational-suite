@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $textareaClass = $selectClass;
@endphp

<form method="POST" action="{{ $action }}"
    x-data="{
        @if ($unrestricted ?? true)
        levelId: '{{ old('academic_level_id', $assessment->academic_level_id ?? '') }}',
        @else
        selectedAssignment: '',
        subjectId: '', levelId: '', armId: '',
        applyAssignment() {
            const [s, l, a] = this.selectedAssignment.split('|');
            this.subjectId = s ?? ''; this.levelId = l ?? ''; this.armId = a ?? '';
        },
        @endif
    }"
>
    @csrf
    @if ($method ?? null)
        @method($method)
    @endif

    <div class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-1">
                <label for="candidate_name" class="block text-sm font-medium text-gray-700">{{ __('Candidate / student name') }}</label>
                <input type="text" id="candidate_name" name="candidate_name" value="{{ old('candidate_name', $assessment->candidate_name ?? '') }}" maxlength="150" required class="{{ $selectClass }}">
            </div>
            <div class="space-y-1">
                <label for="admission_reference" class="block text-sm font-medium text-gray-700">{{ __('Application / admission reference (optional)') }}</label>
                <input type="text" id="admission_reference" name="admission_reference" value="{{ old('admission_reference', $assessment->admission_reference ?? '') }}" maxlength="100" class="{{ $selectClass }}">
            </div>
        </div>

        <div class="space-y-1">
            <label for="student_id" class="block text-sm font-medium text-gray-700">{{ __('Link to an existing student (optional)') }}</label>
            <select id="student_id" name="student_id" class="{{ $selectClass }}">
                <option value="">{{ __('Not linked — a prospective candidate') }}</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}" @selected(old('student_id', $assessment->student_id ?? '') == $student->id)>
                        {{ trim($student->first_name.' '.$student->last_name) }} ({{ $student->admission_number }})
                    </option>
                @endforeach
            </select>
        </div>

        @if ($unrestricted ?? true)
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="space-y-1">
                    <label for="academic_level_id" class="block text-sm font-medium text-gray-700">{{ __('Intended / assessed level') }}</label>
                    <select id="academic_level_id" name="academic_level_id" x-model="levelId" class="{{ $selectClass }}" required>
                        <option value="">{{ __('Select a level') }}</option>
                        @foreach ($levels as $level)
                            <option value="{{ $level->id }}">{{ $level->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label for="level_arm_id" class="block text-sm font-medium text-gray-700">{{ __('Arm (optional)') }}</label>
                    <select id="level_arm_id" name="level_arm_id" class="{{ $selectClass }}">
                        <option value="">{{ __('Not yet decided') }}</option>
                        @foreach ($levels as $level)
                            @foreach ($level->arms as $arm)
                                <option value="{{ $arm->id }}" x-show="levelId === '{{ $level->id }}'" @selected(old('level_arm_id', $assessment->level_arm_id ?? '') == $arm->id)>{{ $level->name }} — {{ $arm->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label for="subject_id" class="block text-sm font-medium text-gray-700">{{ __('Subject') }}</label>
                    <select id="subject_id" name="subject_id" class="{{ $selectClass }}" required>
                        <option value="">{{ __('Select a subject') }}</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected(old('subject_id', $assessment->subject_id ?? '') == $subject->id)>{{ $subject->name }}</option>
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
                <input type="hidden" name="subject_id" x-model="subjectId">
                <input type="hidden" name="academic_level_id" x-model="levelId">
                <input type="hidden" name="level_arm_id" x-model="armId">
            </div>
        @endif

        <div class="space-y-1">
            <label for="assessed_on" class="block text-sm font-medium text-gray-700">{{ __('Assessment date') }}</label>
            <input type="date" id="assessed_on" name="assessed_on" value="{{ old('assessed_on', isset($assessment->assessed_on) ? $assessment->assessed_on->toDateString() : now()->toDateString()) }}" required class="{{ $selectClass }}">
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="space-y-1">
                <label for="score" class="block text-sm font-medium text-gray-700">{{ __('Score (optional — leave blank until conducted)') }}</label>
                <input type="number" id="score" name="score" step="0.01" min="0" value="{{ old('score', $assessment->score ?? '') }}" class="{{ $selectClass }}">
            </div>
            <div class="space-y-1">
                <label for="max_score" class="block text-sm font-medium text-gray-700">{{ __('Maximum score') }}</label>
                <input type="number" id="max_score" name="max_score" step="0.01" min="0.01" value="{{ old('max_score', $assessment->max_score ?? 100) }}" required class="{{ $selectClass }}">
            </div>
            <div class="space-y-1">
                <label for="result" class="block text-sm font-medium text-gray-700">{{ __('Result (optional)') }}</label>
                <input type="text" id="result" name="result" value="{{ old('result', $assessment->result ?? '') }}" maxlength="50" placeholder="{{ __('e.g. Pass, Fail') }}" class="{{ $selectClass }}">
            </div>
        </div>

        <div class="space-y-1">
            <label for="notes" class="block text-sm font-medium text-gray-700">{{ __('Notes / comments (optional)') }}</label>
            <textarea id="notes" name="notes" rows="3" class="{{ $textareaClass }}">{{ old('notes', $assessment->notes ?? '') }}</textarea>
        </div>
    </div>

    <div class="mt-6">
        <x-button type="submit">{{ $submitLabel }}</x-button>
    </div>
</form>
