@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $textareaClass = $selectClass;
@endphp

<form method="POST" action="{{ $action }}"
    x-data="{
        @if ($unrestricted ?? true)
        sessionId: '{{ old('academic_session_id', $examination->academic_session_id ?? '') }}',
        levelId: '{{ old('academic_level_id', $examination->academic_level_id ?? '') }}',
        @else
        selectedAssignment: '',
        levelId: '', armId: '', subjectId: '',
        applyAssignment() {
            const [l, a, s] = this.selectedAssignment.split('|');
            this.levelId = l ?? ''; this.armId = a ?? ''; this.subjectId = s ?? '';
        },
        @endif
        resultRelease: '{{ old('result_release', $examination->result_release->value ?? 'immediate') }}',
    }"
>
    @csrf
    @if ($method ?? null)
        @method($method)
    @endif

    <div class="space-y-4">
        @if ($unrestricted ?? true)
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1">
                    <label for="academic_session_id" class="block text-sm font-medium text-gray-700">{{ __('Session') }}</label>
                    <select id="academic_session_id" name="academic_session_id" x-model="sessionId" class="{{ $selectClass }}" required>
                        <option value="">{{ __('Select a session') }}</option>
                        @foreach ($sessions as $session)
                            <option value="{{ $session->id }}">{{ $session->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label for="academic_period_id" class="block text-sm font-medium text-gray-700">{{ __('Term') }}</label>
                    <select id="academic_period_id" name="academic_period_id" class="{{ $selectClass }}" required>
                        <option value="">{{ __('Select a term') }}</option>
                        @foreach ($sessions as $session)
                            @foreach ($session->periods as $period)
                                <option value="{{ $period->id }}" x-show="sessionId === '{{ $session->id }}'" @selected(old('academic_period_id', $examination->academic_period_id ?? '') == $period->id)>{{ $period->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="space-y-1">
                    <label for="academic_level_id" class="block text-sm font-medium text-gray-700">{{ __('Level') }}</label>
                    <select id="academic_level_id" name="academic_level_id" x-model="levelId" class="{{ $selectClass }}" required>
                        <option value="">{{ __('Select a level') }}</option>
                        @foreach ($levels as $level)
                            <option value="{{ $level->id }}">{{ $level->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label for="level_arm_id" class="block text-sm font-medium text-gray-700">{{ __('Arm') }}</label>
                    <select id="level_arm_id" name="level_arm_id" class="{{ $selectClass }}" required>
                        <option value="">{{ __('Select an arm') }}</option>
                        @foreach ($levels as $level)
                            @foreach ($level->arms as $arm)
                                <option value="{{ $arm->id }}" x-show="levelId === '{{ $level->id }}'" @selected(old('level_arm_id', $examination->level_arm_id ?? '') == $arm->id)>{{ $level->name }} — {{ $arm->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label for="subject_id" class="block text-sm font-medium text-gray-700">{{ __('Subject') }}</label>
                    <select id="subject_id" name="subject_id" class="{{ $selectClass }}" required>
                        <option value="">{{ __('Select a subject') }}</option>
                        @foreach ($levels as $level)
                            @foreach ($level->subjects as $subject)
                                <option value="{{ $subject->id }}" x-show="levelId === '{{ $level->id }}'" @selected(old('subject_id', $examination->subject_id ?? '') == $subject->id)>{{ $subject->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1">
                    <label for="academic_session_id" class="block text-sm font-medium text-gray-700">{{ __('Session') }}</label>
                    <select id="academic_session_id" name="academic_session_id" class="{{ $selectClass }}" required>
                        <option value="">{{ __('Select a session') }}</option>
                        @foreach ($sessions as $session)
                            <option value="{{ $session->id }}">{{ $session->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label for="academic_period_id" class="block text-sm font-medium text-gray-700">{{ __('Term') }}</label>
                    <select id="academic_period_id" name="academic_period_id" class="{{ $selectClass }}" required>
                        <option value="">{{ __('Select a term') }}</option>
                        @foreach ($sessions as $session)
                            @foreach ($session->periods as $period)
                                <option value="{{ $period->id }}">{{ $session->name }} — {{ $period->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="space-y-1">
                <label for="assignment" class="block text-sm font-medium text-gray-700">{{ __('Class & subject') }}</label>
                <select id="assignment" x-model="selectedAssignment" @change="applyAssignment()" class="{{ $selectClass }}" required>
                    <option value="">{{ __('Select one of your assigned classes') }}</option>
                    @foreach ($assignments as $assignment)
                        <option value="{{ $assignment->academic_level_id }}|{{ $assignment->level_arm_id }}|{{ $assignment->subject_id }}">
                            {{ $assignment->level?->name }}{{ $assignment->arm ? ' — '.$assignment->arm->name : '' }} — {{ $assignment->subject?->name }}
                        </option>
                    @endforeach
                </select>
                <input type="hidden" name="academic_level_id" x-model="levelId">
                <input type="hidden" name="level_arm_id" x-model="armId">
                <input type="hidden" name="subject_id" x-model="subjectId">
            </div>
        @endif

        <div class="space-y-1">
            <label for="title" class="block text-sm font-medium text-gray-700">{{ __('Title') }}</label>
            <input type="text" id="title" name="title" value="{{ old('title', $examination->title ?? '') }}" maxlength="150" required class="{{ $selectClass }}">
        </div>

        <div class="space-y-1">
            <label for="description" class="block text-sm font-medium text-gray-700">{{ __('Instructions (optional)') }}</label>
            <textarea id="description" name="description" rows="3" class="{{ $textareaClass }}">{{ old('description', $examination->description ?? '') }}</textarea>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="space-y-1">
                <label for="duration_minutes" class="block text-sm font-medium text-gray-700">{{ __('Duration (minutes)') }}</label>
                <input type="number" id="duration_minutes" name="duration_minutes" min="1" max="600" value="{{ old('duration_minutes', $examination->duration_minutes ?? 30) }}" required class="{{ $selectClass }}">
            </div>
            <div class="space-y-1">
                <label for="pass_mark_percentage" class="block text-sm font-medium text-gray-700">{{ __('Pass mark (%)') }}</label>
                <input type="number" id="pass_mark_percentage" name="pass_mark_percentage" step="0.01" min="0" max="100" value="{{ old('pass_mark_percentage', $examination->pass_mark_percentage ?? 50) }}" required class="{{ $selectClass }}">
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-1">
                <label for="starts_at" class="block text-sm font-medium text-gray-700">{{ __('Opens at') }}</label>
                <input type="datetime-local" id="starts_at" name="starts_at" value="{{ old('starts_at', isset($examination) ? $examination->starts_at?->format('Y-m-d\TH:i') : '') }}" required class="{{ $selectClass }}">
            </div>
            <div class="space-y-1">
                <label for="ends_at" class="block text-sm font-medium text-gray-700">{{ __('Closes at') }}</label>
                <input type="datetime-local" id="ends_at" name="ends_at" value="{{ old('ends_at', isset($examination) ? $examination->ends_at?->format('Y-m-d\TH:i') : '') }}" required class="{{ $selectClass }}">
            </div>
        </div>

        <div class="space-y-1">
            <label for="result_release" class="block text-sm font-medium text-gray-700">{{ __('Result release') }}</label>
            <select id="result_release" name="result_release" x-model="resultRelease" class="{{ $selectClass }}" required>
                <option value="immediate">{{ __('Immediately after submission') }}</option>
                <option value="scheduled">{{ __('Scheduled release') }}</option>
            </select>
        </div>

        <div class="space-y-1" x-show="resultRelease === 'scheduled'">
            <label for="result_release_at" class="block text-sm font-medium text-gray-700">{{ __('Release date/time') }}</label>
            <input type="datetime-local" id="result_release_at" name="result_release_at" value="{{ old('result_release_at', isset($examination) ? $examination->result_release_at?->format('Y-m-d\TH:i') : '') }}" class="{{ $selectClass }}">
            <p class="text-xs text-gray-500">{{ __('Results stay hidden from students until the server clock reaches this time — never the student\'s own device clock.') }}</p>
        </div>
    </div>

    <div class="mt-6">
        <x-button type="submit">{{ $submitLabel }}</x-button>
    </div>
</form>
