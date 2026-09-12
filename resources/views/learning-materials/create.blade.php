@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $textareaClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<x-layouts.authenticated :title="__('Upload learning material')">
    <div class="max-w-2xl space-y-6">
        <p class="text-sm">
            <a href="{{ route('learning-materials.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Learning Materials') }}</a>
        </p>

        @if ($errors->any())
            <x-alert variant="danger" :title="__('Please fix the following')">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        @if (! $unrestricted && $assignments->isEmpty())
            <x-empty-state
                :title="__('No teaching assignments found')"
                :description="__('You are not currently assigned to teach any class/subject, so there is nothing to upload material for.')"
            />
        @else
            <x-card>
                <form method="POST" action="{{ route('learning-materials.store') }}" enctype="multipart/form-data"
                    x-data="{
                        @if ($unrestricted)
                        levelId: '{{ old('academic_level_id') }}',
                        @else
                        selectedAssignment: '',
                        levelId: '', armId: '', subjectId: '',
                        applyAssignment() {
                            const [l, a, s] = this.selectedAssignment.split('|');
                            this.levelId = l ?? '';
                            this.armId = a ?? '';
                            this.subjectId = s ?? '';
                        },
                        @endif
                    }"
                >
                    @csrf

                    <div class="space-y-4">
                        <div class="space-y-1">
                            <label for="academic_session_id" class="block text-sm font-medium text-gray-700">{{ __('Session') }}</label>
                            <select id="academic_session_id" name="academic_session_id" class="{{ $selectClass }}" required>
                                <option value="">{{ __('Select a session') }}</option>
                                @foreach ($sessions as $session)
                                    <option value="{{ $session->id }}" @selected(old('academic_session_id') == $session->id)>{{ $session->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label for="academic_period_id" class="block text-sm font-medium text-gray-700">{{ __('Term (optional — leave blank for the whole session)') }}</label>
                            <select id="academic_period_id" name="academic_period_id" class="{{ $selectClass }}">
                                <option value="">{{ __('Whole session') }}</option>
                                @foreach ($sessions as $session)
                                    @foreach ($session->periods as $period)
                                        <option value="{{ $period->id }}" @selected(old('academic_period_id') == $period->id)>{{ $session->name }} — {{ $period->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>

                        @if ($unrestricted)
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
                                    <label for="level_arm_id" class="block text-sm font-medium text-gray-700">{{ __('Arm (optional)') }}</label>
                                    <select id="level_arm_id" name="level_arm_id" class="{{ $selectClass }}">
                                        <option value="">{{ __('Whole level') }}</option>
                                        @foreach ($levels as $level)
                                            @foreach ($level->arms as $arm)
                                                <option value="{{ $arm->id }}" x-show="levelId === '{{ $level->id }}'" @selected(old('level_arm_id') == $arm->id)>{{ $level->name }} — {{ $arm->name }}</option>
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
                                                <option value="{{ $subject->id }}" x-show="levelId === '{{ $level->id }}'" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @else
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
                            <input type="text" id="title" name="title" value="{{ old('title') }}" maxlength="150" required class="{{ $selectClass }}">
                        </div>

                        <div class="space-y-1">
                            <label for="description" class="block text-sm font-medium text-gray-700">{{ __('Description (optional)') }}</label>
                            <textarea id="description" name="description" rows="3" class="{{ $textareaClass }}">{{ old('description') }}</textarea>
                        </div>

                        <div class="space-y-1">
                            <label for="file" class="block text-sm font-medium text-gray-700">{{ __('File') }}</label>
                            <input type="file" id="file" name="file" required class="{{ $selectClass }}">
                            <p class="text-xs text-gray-500">
                                {{ __('Accepted: PDF, JPG/JPEG, PNG, WEBP, MP3, M4A, WAV. Max 20MB.') }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <x-button type="submit">{{ __('Upload & save') }}</x-button>
                    </div>
                </form>
            </x-card>
        @endif
    </div>
</x-layouts.authenticated>
