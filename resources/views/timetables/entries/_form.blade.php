@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';

    $levelData = $levels->map(fn ($l) => [
        'id' => $l->id,
        'label' => $l->name,
        'arms' => $l->arms->map(fn ($a) => ['id' => $a->id, 'label' => $a->name])->values(),
    ])->values();

    $oldLevel = old('academic_level_id', $entry->academic_level_id);
    $oldArm = old('level_arm_id', $entry->level_arm_id);
    $oldWeekday = old('weekday', $entry->weekday?->value);
@endphp

<div
    class="space-y-4"
    x-data="{
        levels: {{ Illuminate\Support\Js::from($levelData) }},
        levelId: @js((string) ($oldLevel ?? '')),
        armId: @js((string) ($oldArm ?? '')),
        get arms() { return this.levels.find(l => String(l.id) === this.levelId)?.arms ?? []; },
    }"
    x-effect="if (!arms.some(a => String(a.id) === armId)) armId = ''"
>
    <p class="rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600">
        {{ __('Session') }}: <span class="font-medium">{{ $timetable->session?->name }}</span>
        · {{ $timetable->period?->name ?: __('whole session') }}
    </p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="space-y-1">
            <label for="academic_level_id" class="block text-sm font-medium text-gray-700">{{ __('Level') }} <span class="text-red-500">*</span></label>
            <select id="academic_level_id" name="academic_level_id" x-model="levelId" class="{{ $selectClass }}">
                <option value="">{{ __('Select a level') }}</option>
                <template x-for="l in levels" :key="l.id">
                    <option :value="l.id" x-text="l.label"></option>
                </template>
            </select>
            @error('academic_level_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-1">
            <label for="level_arm_id" class="block text-sm font-medium text-gray-700">{{ __('Class / arm') }} <span class="text-red-500">*</span></label>
            <select id="level_arm_id" name="level_arm_id" x-model="armId" class="{{ $selectClass }}" :disabled="!arms.length">
                <option value="">{{ __('Select an arm') }}</option>
                <template x-for="a in arms" :key="a.id">
                    <option :value="a.id" x-text="a.label"></option>
                </template>
            </select>
            @error('level_arm_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="space-y-1">
            <label for="subject_id" class="block text-sm font-medium text-gray-700">{{ __('Subject') }} <span class="text-red-500">*</span></label>
            <select id="subject_id" name="subject_id" class="{{ $selectClass }}">
                <option value="">{{ __('Select a subject') }}</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected((int) old('subject_id', $entry->subject_id) === $subject->id)>{{ $subject->name }}</option>
                @endforeach
            </select>
            @error('subject_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-1">
            <label for="teacher_id" class="block text-sm font-medium text-gray-700">{{ __('Teacher') }} <span class="text-red-500">*</span></label>
            <select id="teacher_id" name="teacher_id" class="{{ $selectClass }}">
                <option value="">{{ __('Select a teacher') }}</option>
                @foreach ($teachers as $teacher)
                    <option value="{{ $teacher->id }}" @selected((int) old('teacher_id', $entry->teacher_id) === $teacher->id)>{{ $teacher->fullName() }}</option>
                @endforeach
            </select>
            @error('teacher_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            <p class="text-xs text-gray-400">{{ __('The teacher must have an active assignment for this subject and class.') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
        <div class="space-y-1">
            <label for="weekday" class="block text-sm font-medium text-gray-700">{{ __('Day') }} <span class="text-red-500">*</span></label>
            <select id="weekday" name="weekday" class="{{ $selectClass }}">
                <option value="">{{ __('Day') }}</option>
                @foreach (\App\Enums\Weekday::all() as $d)
                    <option value="{{ $d->value }}" @selected((string) old('weekday', $oldWeekday) === (string) $d->value)>{{ $d->label() }}</option>
                @endforeach
            </select>
            @error('weekday') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <x-input name="start_time" type="time" :label="__('Start')" :value="old('start_time', $entry->start_time)" required />
        <x-input name="end_time" type="time" :label="__('End')" :value="old('end_time', $entry->end_time)" required />
        <x-input name="room" :label="__('Room (optional)')" :value="old('room', $entry->room)" placeholder="{{ __('e.g. Lab 2') }}" />
    </div>
</div>
