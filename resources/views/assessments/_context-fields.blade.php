{{--
  The five academic-context selects, driven by an Alpine cascade. The parent
  <form> must provide x-data with: sessions, levels (JS arrays), and the
  reactive keys sessionId / periodId / levelId / armId / subjectId, plus the
  computed getters `periods`, `arms`, `subjects`. See create.blade.php.
--}}
@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="space-y-1">
        <label for="academic_session_id" class="block text-sm font-medium text-gray-700">{{ __('Academic session') }} <span class="text-red-500">*</span></label>
        <select id="academic_session_id" name="academic_session_id" x-model="sessionId" class="{{ $selectClass }}">
            <option value="">{{ __('Select a session') }}</option>
            <template x-for="s in sessions" :key="s.id"><option :value="s.id" x-text="s.label"></option></template>
        </select>
        @error('academic_session_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
    <div class="space-y-1">
        <label for="academic_period_id" class="block text-sm font-medium text-gray-700">{{ __('Term') }} <span class="text-red-500">*</span></label>
        <select id="academic_period_id" name="academic_period_id" x-model="periodId" class="{{ $selectClass }}" :disabled="!periods.length">
            <option value="">{{ __('Select a term') }}</option>
            <template x-for="p in periods" :key="p.id"><option :value="p.id" x-text="p.label"></option></template>
        </select>
        @error('academic_period_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="space-y-1">
        <label for="academic_level_id" class="block text-sm font-medium text-gray-700">{{ __('Level') }} <span class="text-red-500">*</span></label>
        <select id="academic_level_id" name="academic_level_id" x-model="levelId" class="{{ $selectClass }}">
            <option value="">{{ __('Select a level') }}</option>
            <template x-for="l in levels" :key="l.id"><option :value="l.id" x-text="l.label"></option></template>
        </select>
        @error('academic_level_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
    <div class="space-y-1">
        <label for="level_arm_id" class="block text-sm font-medium text-gray-700">{{ __('Class / arm') }} <span class="text-red-500">*</span></label>
        <select id="level_arm_id" name="level_arm_id" x-model="armId" class="{{ $selectClass }}" :disabled="!arms.length">
            <option value="">{{ __('Select a class') }}</option>
            <template x-for="a in arms" :key="a.id"><option :value="a.id" x-text="a.label"></option></template>
        </select>
        @error('level_arm_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
    <div class="space-y-1">
        <label for="subject_id" class="block text-sm font-medium text-gray-700">{{ __('Subject') }} <span class="text-red-500">*</span></label>
        <select id="subject_id" name="subject_id" x-model="subjectId" class="{{ $selectClass }}" :disabled="!subjects.length">
            <option value="">{{ __('Select a subject') }}</option>
            <template x-for="s in subjects" :key="s.id"><option :value="s.id" x-text="s.label"></option></template>
        </select>
        @error('subject_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        <p x-show="levelId && !subjects.length" class="text-xs text-amber-600">{{ __('That level offers no subjects yet — add some in the Academic area.') }}</p>
    </div>
</div>
