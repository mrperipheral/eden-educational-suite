@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';

    $sessionData = $sessions->map(fn ($s) => [
        'id' => $s->id,
        'label' => $s->name.($s->is_current ? ' ('.__('current').')' : ''),
        'periods' => $s->periods->map(fn ($p) => ['id' => $p->id, 'label' => $p->name])->values(),
    ])->values();

    $levelData = $levels->map(fn ($l) => [
        'id' => $l->id,
        'label' => $l->name,
        'arms' => $l->arms->map(fn ($a) => ['id' => $a->id, 'label' => $a->name])->values(),
    ])->values();

    $oldSession = old('academic_session_id', $enrollment->academic_session_id);
    $oldPeriod = old('academic_period_id', $enrollment->academic_period_id);
    $oldLevel = old('academic_level_id', $enrollment->academic_level_id);
    $oldArm = old('level_arm_id', $enrollment->level_arm_id);
@endphp

<div
    class="space-y-4"
    x-data="{
        sessions: {{ Illuminate\Support\Js::from($sessionData) }},
        levels: {{ Illuminate\Support\Js::from($levelData) }},
        sessionId: @js((string) ($oldSession ?? '')),
        periodId: @js((string) ($oldPeriod ?? '')),
        levelId: @js((string) ($oldLevel ?? '')),
        armId: @js((string) ($oldArm ?? '')),
        get periods() { return this.sessions.find(s => String(s.id) === this.sessionId)?.periods ?? []; },
        get arms() { return this.levels.find(l => String(l.id) === this.levelId)?.arms ?? []; },
    }"
    x-effect="if (!periods.some(p => String(p.id) === periodId)) periodId = ''"
>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="space-y-1">
            <label for="academic_session_id" class="block text-sm font-medium text-gray-700">{{ __('Session') }} <span class="text-red-500">*</span></label>
            <select id="academic_session_id" name="academic_session_id" x-model="sessionId" class="{{ $selectClass }}">
                <option value="">{{ __('Select a session') }}</option>
                <template x-for="s in sessions" :key="s.id">
                    <option :value="s.id" x-text="s.label"></option>
                </template>
            </select>
            @error('academic_session_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-1">
            <label for="academic_period_id" class="block text-sm font-medium text-gray-700">{{ __('Term / period') }}</label>
            <select id="academic_period_id" name="academic_period_id" x-model="periodId" class="{{ $selectClass }}" :disabled="!periods.length">
                <option value="">{{ __('— None —') }}</option>
                <template x-for="p in periods" :key="p.id">
                    <option :value="p.id" x-text="p.label"></option>
                </template>
            </select>
            @error('academic_period_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="space-y-1">
            <label for="academic_level_id" class="block text-sm font-medium text-gray-700">{{ __('Level / class') }} <span class="text-red-500">*</span></label>
            <select id="academic_level_id" name="academic_level_id" x-model="levelId"
                @change="if (!arms.some(a => String(a.id) === armId)) armId = ''" class="{{ $selectClass }}">
                <option value="">{{ __('Select a level') }}</option>
                <template x-for="l in levels" :key="l.id">
                    <option :value="l.id" x-text="l.label"></option>
                </template>
            </select>
            @error('academic_level_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="space-y-1">
            <label for="level_arm_id" class="block text-sm font-medium text-gray-700">{{ __('Arm / stream') }}</label>
            <select id="level_arm_id" name="level_arm_id" x-model="armId" class="{{ $selectClass }}" :disabled="!arms.length">
                <option value="">{{ __('— None —') }}</option>
                <template x-for="a in arms" :key="a.id">
                    <option :value="a.id" x-text="a.label"></option>
                </template>
            </select>
            @error('level_arm_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="space-y-1">
            <label for="status" class="block text-sm font-medium text-gray-700">{{ __('Status') }}</label>
            <select id="status" name="status" class="{{ $selectClass }}">
                @foreach (\App\Enums\EnrollmentStatus::all() as $s)
                    <option value="{{ $s->value }}" @selected(old('status', $enrollment->status?->value ?? 'active') === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
            @error('status') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <x-input name="started_on" type="date" :label="__('Start date')"
            :value="old('started_on', $enrollment->started_on?->toDateString())" required />
        <x-input name="ended_on" type="date" :label="__('End date')"
            :value="old('ended_on', $enrollment->ended_on?->toDateString())" :hint="__('Required unless active.')" />
    </div>

    @unless ($enrollment->exists)
        <x-checkbox name="make_active" :label="__('Make this the student\'s current placement')" :checked="old('make_active', true)" />
    @endunless
</div>
